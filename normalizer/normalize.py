"""Normalisation : chaque outil produit son propre format JSON ; on les ramène à un schéma commun.

Schéma d'une finding :
  id        empreinte stable (outil + règle + emplacement sans numéro de ligne) -> sert à comparer
  tool      semgrep | zap | trivy | npm-audit
  approach  SAST | DAST | SCA
  rule      identifiant de la règle / alerte / vulnérabilité
  title     libellé lisible
  severity  critical | high | medium | low | info
  location  fichier:ligne (SAST), URL [+ paramètre] (DAST), paquet@version (SCA)
  cwe       "CWE-89" ou None
"""
from __future__ import annotations

import hashlib
import re
from typing import Any

SEVERITY_ORDER = ["critical", "high", "medium", "low", "info"]


def _fingerprint(*parts: str) -> str:
    return hashlib.sha1("|".join(parts).encode("utf-8")).hexdigest()[:16]


def _finding(tool: str, approach: str, rule: str, title: str, severity: str,
             location: str, cwe: str | None) -> dict[str, Any]:
    severity = severity.lower()
    if severity not in SEVERITY_ORDER:
        severity = "info"
    return {
        "id": _fingerprint(tool, rule, location),
        "tool": tool,
        "approach": approach,
        "rule": rule,
        "title": title,
        "severity": severity,
        "location": location,
        "cwe": cwe,
    }


def _cwe_number(value: Any) -> str | None:
    """Accepte '89', 89, 'CWE-89', 'CWE-89: Improper ...' -> 'CWE-89'."""
    if value is None or value == "":
        return None
    m = re.search(r"(\d+)", str(value))
    return f"CWE-{m.group(1)}" if m else None


# ---------------------------------------------------------------- SAST : Semgrep
_SEMGREP_SEV = {"ERROR": "high", "WARNING": "medium", "INFO": "low"}


def from_semgrep(data: dict) -> list[dict]:
    out = []
    for r in data.get("results", []):
        extra = r.get("extra", {})
        meta = extra.get("metadata", {}) or {}
        cwes = meta.get("cwe") or []
        cwe = _cwe_number(cwes[0]) if isinstance(cwes, list) and cwes else _cwe_number(cwes)
        sev = meta.get("severity") or _SEMGREP_SEV.get(extra.get("severity", ""), "info")
        out.append(_finding(
            "semgrep", "SAST", r.get("check_id", "unknown"),
            extra.get("message", r.get("check_id", ""))[:200],
            sev, f"{r.get('path')}", cwe,
        ))
    return out


# ---------------------------------------------------------------- SAST : SonarQube
_SONAR_SEV = {"BLOCKER": "critical", "CRITICAL": "high", "MAJOR": "medium", "MINOR": "low", "INFO": "info"}


def from_sonarqube(data: dict) -> list[dict]:
    out = []
    for i in data.get("issues", []):
        component = str(i.get("component", ""))
        path = component.split(":", 1)[-1]  # 'juice-shop:routes/login.ts' -> 'routes/login.ts'
        loc = f"{path}"
        out.append(_finding(
            "sonarqube", "SAST", str(i.get("rule", "unknown")),
            str(i.get("message", ""))[:200],
            _SONAR_SEV.get(str(i.get("severity", "")).upper(), "info"),
            loc, i.get("_cwe"),
        ))
    return out


# ---------------------------------------------------------------- DAST : ZAP
_ZAP_RISK = {"3": "high", "2": "medium", "1": "low", "0": "info"}


def from_zap(data: dict) -> list[dict]:
    out = []
    for site in data.get("site", []):
        for alert in site.get("alerts", []):
            riskcode = str(alert.get("riskcode", "0"))
            rule = str(alert.get("pluginid", alert.get("alert", "unknown")))
            # Une même alerte a plusieurs instances (URL/paramètre) : on crée une finding par instance.
            for inst in alert.get("instances", []) or [{"uri": site.get("@name", "")}]:
                loc = inst.get("uri", site.get("@name", ""))
                if inst.get("param"):
                    loc = f"{loc} [param={inst['param']}]"
                out.append(_finding(
                    "zap", "DAST", rule, alert.get("alert", rule),
                    _ZAP_RISK.get(riskcode, "info"), loc, _cwe_number(alert.get("cweid")),
                ))
    return out


# ---------------------------------------------------------------- SCA : Trivy
_TRIVY_SEV = {"CRITICAL": "critical", "HIGH": "high", "MEDIUM": "medium", "LOW": "low"}


def from_trivy(data: dict, tool_label: str = "trivy") -> list[dict]:
    out = []
    for res in data.get("Results", []) or []:
        for v in res.get("Vulnerabilities", []) or []:
            cwes = v.get("CweIDs") or []
            loc = f"{v.get('PkgName')}@{v.get('InstalledVersion')}"
            out.append(_finding(
                tool_label, "SCA", v.get("VulnerabilityID", "unknown"),
                (v.get("Title") or v.get("VulnerabilityID", ""))[:200],
                _TRIVY_SEV.get(str(v.get("Severity", "")).upper(), "info"),
                loc, _cwe_number(cwes[0]) if cwes else None,
            ))
    return out


# ---------------------------------------------------------------- SCA : npm audit
def from_npm_audit(data: dict) -> list[dict]:
    out = []
    for pkg_name, pkg in (data.get("vulnerabilities") or {}).items():
        # 'via' contient soit des avis (dict), soit le nom d'un autre paquet (str) : on ne garde que les avis.
        for via in pkg.get("via", []):
            if not isinstance(via, dict):
                continue
            cwes = via.get("cwe") or []
            advisory = str(via.get("source", via.get("url", via.get("title", "unknown"))))
            out.append(_finding(
                "npm-audit", "SCA", advisory,
                str(via.get("title", advisory))[:200],
                via.get("severity", pkg.get("severity", "info")),
                f"{pkg_name}@{pkg.get('range', '?')}",
                _cwe_number(cwes[0]) if cwes else None,
            ))
    return out


# ---------------------------------------------------------------- Point d'entrée
PARSERS = {
    "semgrep": from_semgrep,
    "zap": from_zap,
    "trivy-fs": lambda d: from_trivy(d, "trivy-fs"),
    "trivy-image": lambda d: from_trivy(d, "trivy-image"),
    "npm-audit": from_npm_audit,
    "sonarqube": from_sonarqube,
}


def normalize(tool_key: str, data: dict) -> list[dict]:
    if tool_key not in PARSERS:
        raise KeyError(f"Pas de parseur pour {tool_key}")
    return PARSERS[tool_key](data)


def dedupe(findings: list[dict]) -> list[dict]:
    """Supprime les doublons exacts (même id) en gardant la sévérité la plus haute."""
    best: dict[str, dict] = {}
    for f in findings:
        cur = best.get(f["id"])
        if cur is None or SEVERITY_ORDER.index(f["severity"]) < SEVERITY_ORDER.index(cur["severity"]):
            best[f["id"]] = f
    return list(best.values())
