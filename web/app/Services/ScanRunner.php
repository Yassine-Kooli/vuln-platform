<?php

namespace App\Services;

use App\Models\Finding;
use App\Models\Scan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Lance pipeline.py pour une analyse, puis importe les résultats dans la base.
 * Le journal complet est conservé dans storage/app/scans/<id>/journal.log.
 */
class ScanRunner
{
    public function run(Scan $scan): void
    {
        $target = $scan->target;
        $root = config('vp.root');
        $dir = $this->workDir($scan);
        File::ensureDirectoryExists($dir);
        $log = $this->logPath($scan);
        $pathFile = $dir.'/run.txt';
        @unlink($pathFile);
        file_put_contents($log, '');

        $scan->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        if (! $target->source_path || ! is_dir($target->source_path)) {
            $this->fail($scan, 'dossier de code source introuvable : '.$target->source_path);
            return;
        }

        $args = [
            config('vp.python'), $root.'/pipeline.py',
            '--url', $target->url,
            '--source-dir', $target->source_path,
            '--name', $target->name,
            '--stages', implode(',', $scan->stages),
            '--label', $scan->label,
        ];
        if ($target->image) {
            array_push($args, '--image', $target->image);
        }

        $code = $this->shell($args, $root, $log, ['VP_RUN_PATH_FILE' => $pathFile]);
        $runDir = is_file($pathFile) ? trim((string) file_get_contents($pathFile)) : null;

        if (! $runDir || ! is_dir($runDir)) {
            $scan->update(['stage_states' => $this->stageStates($log), 'exit_code' => $code]);
            $this->fail($scan, 'aucune exécution produite : voir le journal');
            return;
        }

        // Métriques si la cible a une vérité terrain, puis rapport HTML et tableau de bord.
        $groundTruth = $this->groundTruth();
        if (isset($groundTruth[$target->name])) {
            $this->shell([config('vp.python'), $root.'/metrics/evaluate.py', $runDir, '--target', $target->name], $root, $log);
        }
        $this->shell([config('vp.python'), $root.'/reporting/report.py', $runDir], $root, $log);
        $this->shell([config('vp.python'), $root.'/reporting/dashboard.py'], $root, $log);

        $this->import($scan, $runDir);
        $scan->update([
            'run_dir' => $runDir,
            'exit_code' => $code,
            'stage_states' => $this->stageStates($log),
            'status' => $code === 0 ? 'done' : 'done_with_errors',
            'finished_at' => now(),
        ]);
        @unlink($pathFile);
    }

    public function workDir(Scan $scan): string
    {
        return storage_path("app/scans/{$scan->id}");
    }

    public function logPath(Scan $scan): string
    {
        return $this->workDir($scan).'/journal.log';
    }

    /**
     * Lit le journal : étapes (état + commandes exécutées pendant chaque étape),
     * commande en cours et dernières lignes. Sert à l'affichage live et à la page du scan.
     */
    public function liveState(Scan $scan): array
    {
        $log = $this->logPath($scan);
        $lines = is_file($log) ? preg_split('/\R/', (string) file_get_contents($log)) : [];
        $stages = [];
        $order = [];
        $current = null;
        $cmd = '';
        foreach ($lines as $line) {
            if (preg_match('/== Étape : (\S+) \| (début|ok|erreur)/u', $line, $m)) {
                $name = $m[1];
                if (! isset($stages[$name])) {
                    $stages[$name] = ['name' => $name, 'state' => 'running', 'commands' => []];
                    $order[] = $name;
                }
                $stages[$name]['state'] = ['début' => 'running', 'ok' => 'ok', 'erreur' => 'error'][$m[2]];
                $current = $m[2] === 'début' ? $name : null;
            } elseif (str_starts_with($line, '  $ ')) {
                $cmd = mb_substr(substr($line, 4), 0, 400);
                if ($current !== null) {
                    $stages[$current]['commands'][] = $cmd;
                }
            }
        }

        return [
            'stage_states' => array_map(fn (string $n) => $stages[$n], $order),
            'current_cmd' => $cmd,
            'log_tail' => array_slice($lines, -500),
        ];
    }

    /** Étapes lues dans le journal : marqueurs « == Étape : nom | début/ok/erreur ». */
    public function stageStates(string $logPath): array
    {
        if (! is_file($logPath)) {
            return [];
        }
        $states = [];
        preg_match_all('/== Étape : (\S+) \| (début|ok|erreur)/u', (string) file_get_contents($logPath), $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            $states[$hit[1]] = ['name' => $hit[1], 'state' => ['début' => 'running', 'ok' => 'ok', 'erreur' => 'error'][$hit[2]]];
        }

        return array_values($states);
    }

    private function import(Scan $scan, string $runDir): void
    {
        $read = fn (string $file) => is_file("$runDir/$file") ? json_decode((string) file_get_contents("$runDir/$file"), true) : null;
        $summary = $read('summary.json');
        $scan->update([
            'summary' => $summary,
            'metrics' => $read('metrics.json'),
            'comparison' => $read('comparison.json'),
        ]);

        $findings = $read('findings.json') ?? [];
        Finding::where('scan_id', $scan->id)->delete();
        $now = now();
        foreach (array_chunk($findings, 500) as $chunk) {
            Finding::insert(array_map(fn (array $f) => [
                'scan_id' => $scan->id,
                'fingerprint' => $f['id'],
                'tool' => $f['tool'],
                'approach' => $f['approach'],
                'rule' => $f['rule'] ?? null,
                'title' => $f['title'] ?? '',
                'severity' => $f['severity'],
                'location' => $f['location'] ?? '',
                'cwe' => $f['cwe'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }
    }

    private function fail(Scan $scan, string $message): void
    {
        $scan->update(['status' => 'failed', 'error' => $message, 'finished_at' => now()]);
    }

    private function groundTruth(): array
    {
        $file = config('vp.root').'/config/ground_truth.json';

        return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?? []) : [];
    }

    /** Exécute une commande (sans shell) en ajoutant sa sortie au journal. Renvoie le code de sortie. */
    private function shell(array $args, string $cwd, string $log, array $env = []): int
    {
        $command = implode(' ', array_map('escapeshellarg', $args));
        file_put_contents($log, '  $ '.$command."\n", FILE_APPEND);
        $envPrefix = collect($env)->map(fn ($v, $k) => $k.'='.escapeshellarg((string) $v))->implode(' ');
        $result = Process::timeout(0)->run(
            ['bash', '-c', trim($envPrefix.' '.$command).' >> '.escapeshellarg($log).' 2>&1'],
        );
        return $result->exitCode() ?? 1;
    }
}
