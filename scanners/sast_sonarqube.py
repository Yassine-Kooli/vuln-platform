"""SAST : SonarQube (serveur Docker profil 'sonar') + sonar-scanner-cli.

Pré-requis : `docker compose --profile sonar up -d sonarqube` et un jeton dans .sonar-token (ou SONAR_TOKEN).
Sortie : out_dir/sonarqube.json (liste des issues + CWE retrouvés dans la description de la règle).
"""
from __future__ import annotations

import base64
import json
import os
import re
import time
import urllib.parse
import urllib.request
from pathlib import Path

from common import ROOT, run

SONAR_URL = os.environ.get("SONAR_URL", "http://localhost:9000")
PROJECT_KEY = "juice-shop"
SCANNER_IMAGE = "sonarsource/sonar-scanner-cli:latest"


def _token() -> str:
    if os.environ.get("SONAR_TOKEN"):
        return os.environ["SONAR_TOKEN"]
    p = ROOT / ".sonar-token"
    if not p.exists():
        raise RuntimeError("jeton SonarQube absent : définissez SONAR_TOKEN ou créez .sonar-token")
    return p.read_text(encoding="utf-8").strip()


def _get(path: str, params: dict | None = None) -> dict:
    url = SONAR_URL + path + ("?" + urllib.parse.urlencode(params, doseq=True) if params else "")
    auth = base64.b64encode(f"{_token()}:".encode()).decode()
    req = urllib.request.Request(url, headers={"Authorization": f"Basic {auth}"})
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.loads(r.read().decode("utf-8"))


def _wait_for_analysis(report_task: Path, timeout_s: int = 900) -> None:
    """Attend la fin de la tâche de calcul côté serveur (ceTaskId du report-task.txt)."""
    props = dict(line.split("=", 1) for line in report_task.read_text(encoding="utf-8").splitlines() if "=" in line)
    task_id = props["ceTaskId"]
    deadline = time.time() + timeout_s
    while time.time() < deadline:
        status = _get("/api/ce/task", {"id": task_id})["task"]["status"]
        if status == "SUCCESS":
            return
        if status in ("FAILED", "CANCELED"):
            raise RuntimeError(f"analyse SonarQube {status}")
        time.sleep(5)
    raise RuntimeError("délai dépassé pendant l'analyse SonarQube")


def _issues() -> list[dict]:
    out, page = [], 1
    while True:
        data = _get("/api/issues/search", {"componentKeys": PROJECT_KEY, "ps": 500, "p": page, "resolved": "false"})
        out += data.get("issues", [])
        if page * 500 >= data.get("paging", {}).get("total", 0):
            return out
        page += 1


def _rule_cwe(rule_key: str, cache: dict) -> str | None:
    """Retrouve un CWE mentionné dans la description de la règle (ex. 'CWE-89')."""
    if rule_key not in cache:
        rule = _get("/api/rules/show", {"key": rule_key}).get("rule", {})
        text = json.dumps(rule)
        # SonarQube publie le CWE sous forme de lien vers cwe.mitre.org dans les sections de description.
        m = re.search(r'CWE[^<"]{0,30}<a[^>]*?definitions/(\d+)', text) or re.search(r"CWE-(\d+)", text)
        cache[rule_key] = f"CWE-{m.group(1)}" if m else None
    return cache[rule_key]


def scan(source_dir: Path, out_dir: Path) -> Path:
    out_dir = out_dir.resolve()
    out_dir.mkdir(parents=True, exist_ok=True)
    # Le conteneur sonar-scanner tourne sous un autre utilisateur (uid 1000) : le dossier doit être accessible en écriture.
    out_dir.chmod(0o777)
    out_file = out_dir / "sonarqube.json"

    cmd = [
        "docker", "run", "--rm", "--network", "host",
        "-e", f"SONAR_HOST_URL={SONAR_URL}",
        "-e", f"SONAR_TOKEN={_token()}",
        "-v", f"{source_dir.resolve()}:/usr/src:ro",
        "-v", f"{out_dir}:/out:rw",
        SCANNER_IMAGE,
        f"-Dsonar.projectKey={PROJECT_KEY}",
        "-Dsonar.sources=.",
        "-Dsonar.exclusions=**/node_modules/**,**/build/**,**/dist/**,data/static/codefixes/**",
        "-Dsonar.working.directory=/out/.scannerwork",
        "-Dsonar.scm.disabled=true",
    ]
    proc = run(cmd)
    if proc.returncode != 0:
        raise RuntimeError(f"sonar-scanner a échoué : {proc.stdout[-1500:]} {proc.stderr[-1500:]}")

    _wait_for_analysis(out_dir / ".scannerwork" / "report-task.txt")
    issues = _issues()
    cache: dict = {}
    for i in issues:
        i["_cwe"] = _rule_cwe(i["rule"], cache)
    out_file.write_text(json.dumps({"issues": issues}, indent=2, ensure_ascii=False), encoding="utf-8")
    return out_file
