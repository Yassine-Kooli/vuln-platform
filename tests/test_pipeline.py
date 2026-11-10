"""Tests unitaires (stdlib) : normalisation, déduplication, comparaison, métriques.

Lancement : python3 -m unittest discover -s tests -v
"""
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
for sub in ("scanners", "normalizer", "reporting", "metrics"):
    sys.path.insert(0, str(ROOT / sub))

from normalize import dedupe, from_npm_audit, from_semgrep, from_trivy, from_zap  # noqa: E402
from compare import compare  # noqa: E402
from evaluate import evaluate  # noqa: E402


class SemgrepParser(unittest.TestCase):
    def test_extrait_fichier_regle_et_cwe(self):
        data = {"results": [{
            "check_id": "rules.custom-sequelize-query-concat",
            "path": "routes/login.ts",
            "start": {"line": 34},
            "extra": {"message": "SQL concat", "severity": "ERROR",
                      "metadata": {"cwe": ["CWE-89: Improper Neutralization"]}},
        }]}
        f = from_semgrep(data)
        self.assertEqual(len(f), 1)
        self.assertEqual(f[0]["approach"], "SAST")
        self.assertEqual(f[0]["cwe"], "CWE-89")
        self.assertEqual(f[0]["location"], "routes/login.ts")  # pas de numéro de ligne : empreinte stable
        self.assertEqual(f[0]["severity"], "high")


class ZapParser(unittest.TestCase):
    def test_une_finding_par_instance(self):
        data = {"site": [{"@name": "http://localhost:3000", "alerts": [{
            "pluginid": "40018", "alert": "SQL Injection", "riskcode": "3", "cweid": "89",
            "instances": [{"uri": "http://localhost:3000/rest/user/login", "param": "email"},
                          {"uri": "http://localhost:3000/rest/products/search"}],
        }]}]}
        f = from_zap(data)
        self.assertEqual(len(f), 2)
        self.assertEqual({x["severity"] for x in f}, {"high"})
        self.assertEqual(f[0]["cwe"], "CWE-89")
        self.assertIn("[param=email]", f[0]["location"])


class TrivyAndNpmParsers(unittest.TestCase):
    def test_trivy(self):
        data = {"Results": [{"Target": "package-lock.json", "Vulnerabilities": [{
            "VulnerabilityID": "CVE-2024-0001", "PkgName": "lodash", "InstalledVersion": "4.17.10",
            "Severity": "HIGH", "Title": "Prototype pollution", "CweIDs": ["CWE-1321"]}]}]}
        f = from_trivy(data, "trivy-fs")
        self.assertEqual(f[0]["location"], "lodash@4.17.10")
        self.assertEqual(f[0]["cwe"], "CWE-1321")
        self.assertEqual(f[0]["tool"], "trivy-fs")

    def test_npm_audit_ignore_les_references_textuelles(self):
        data = {"vulnerabilities": {
            "lodash": {"name": "lodash", "severity": "high", "range": "<4.17.21", "via": [
                {"source": 1094, "title": "Prototype Pollution", "severity": "high", "cwe": ["CWE-1321"]}]},
            "express-x": {"name": "express-x", "severity": "high", "range": "*", "via": ["lodash"]},
        }}
        f = from_npm_audit(data)
        self.assertEqual(len(f), 1)  # 'via: lodash' (chaîne) ignoré
        self.assertEqual(f[0]["cwe"], "CWE-1321")


class Dedupe(unittest.TestCase):
    def test_garde_la_severite_la_plus_haute(self):
        a = {"id": "x", "severity": "low"}
        b = {"id": "x", "severity": "high"}
        out = dedupe([a, b])
        self.assertEqual(len(out), 1)
        self.assertEqual(out[0]["severity"], "high")


class Compare(unittest.TestCase):
    def test_nouvelles_corrigees_inchangees(self):
        before = [{"id": "a", "tool": "semgrep"}, {"id": "b", "tool": "zap"}]
        after = [{"id": "b", "tool": "zap"}, {"id": "c", "tool": "trivy-fs"}]
        r = compare(before, after)
        self.assertEqual([f["id"] for f in r["new"]], ["c"])
        self.assertEqual([f["id"] for f in r["fixed"]], ["a"])
        self.assertEqual([f["id"] for f in r["unchanged"]], ["b"])


class Metrics(unittest.TestCase):
    TRUTH = [
        {"id": "SQLI", "cwe": "CWE-89", "location_pattern": "login"},
        {"id": "XSS", "cwe": "CWE-79", "location_pattern": None},
    ]

    def test_vp_fn_fp_precision_rappel(self):
        findings = [
            {"tool": "semgrep", "approach": "SAST", "cwe": "CWE-89", "location": "routes/login.ts"},
            {"tool": "semgrep", "approach": "SAST", "cwe": "CWE-22", "location": "routes/x.ts"},  # FP
        ]
        m = evaluate(findings, self.TRUTH, "tool")["semgrep"]
        self.assertEqual((m["tp"], m["fp"], m["fn"]), (1, 1, 1))
        self.assertEqual(m["precision"], 0.5)
        self.assertEqual(m["recall"], 0.5)

    def test_motif_d_emplacement_respecte(self):
        findings = [{"tool": "zap", "approach": "DAST", "cwe": "CWE-89", "location": "http://x/rest/products"}]
        m = evaluate(findings, self.TRUTH, "tool")["zap"]
        self.assertEqual(m["tp"], 0)  # le motif 'login' n'est pas présent


if __name__ == "__main__":
    unittest.main()
