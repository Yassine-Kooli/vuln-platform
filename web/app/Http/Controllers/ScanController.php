<?php

namespace App\Http\Controllers;

use App\Jobs\RunScan;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\Target;
use App\Services\ScanRunner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ScanController extends Controller
{
    private const FILES = ['report.html', 'comparison.md'];

    public function store(Request $request, Target $target): RedirectResponse
    {
        $data = $request->validate([
            'stages' => ['required', 'array', 'min:1'],
            'stages.*' => ['in:sast,sca,dast'],
        ], ['stages.required' => 'Choisissez au moins une étape.']);

        $scan = $target->scans()->create([
            'stages' => array_values(array_unique($data['stages'])),
            'label' => 'web',
            'status' => 'queued',
        ]);
        RunScan::dispatch($scan);

        return redirect()->route('scans.show', $scan);
    }

    public function show(Request $request, Scan $scan, ScanRunner $runner): View
    {
        $scan->load('target');
        $severityOrder = "CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END";

        $query = $scan->findings()->orderByRaw($severityOrder)->orderBy('tool')->orderBy('id');
        if ($approach = $request->query('approach')) {
            $query->where('approach', $approach);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }
        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('title', 'like', "%$term%")
                ->orWhere('location', 'like', "%$term%")->orWhere('rule', 'like', "%$term%")->orWhere('cwe', 'like', "%$term%"));
        }

        $overview = $scan->isFinished() ? $this->overview($scan) : null;

        return view('scans.show', [
            'scan' => $scan,
            'findings' => $query->paginate(50)->withQueryString(),
            'overview' => $overview,
            'live' => $runner->liveState($scan),
            'reportFiles' => $scan->run_dir ? array_values(array_filter(self::FILES, fn ($f) => is_file($scan->run_dir.'/'.$f))) : [],
        ]);
    }

    public function status(Scan $scan, ScanRunner $runner): JsonResponse
    {
        $live = $runner->liveState($scan);

        return response()->json([
            'status' => $scan->status,
            'finished' => $scan->isFinished(),
            'error' => $scan->error,
            'stage_states' => $live['stage_states'],
            'current_cmd' => $live['current_cmd'],
            'log_tail' => $live['log_tail'],
        ])->header('Cache-Control', 'no-store');
    }

    public function file(Scan $scan, string $file): Response
    {
        abort_unless(in_array($file, self::FILES, true) && $scan->run_dir, 404);
        $path = $scan->run_dir.'/'.$file;
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => $file === 'report.html' ? 'text/html; charset=UTF-8' : 'text/markdown; charset=UTF-8']);
    }

    /** Vue d'ensemble calculée depuis la base : par outil, par sévérité, règles et CWE. */
    private function overview(Scan $scan): array
    {
        $bySeverity = Finding::where('scan_id', $scan->id)->select('severity', DB::raw('count(*) as n'))
            ->groupBy('severity')->pluck('n', 'severity')->all();
        $byTool = Finding::where('scan_id', $scan->id)
            ->select('tool', 'approach', 'severity', DB::raw('count(*) as n'))
            ->groupBy('tool', 'approach', 'severity')->get()
            ->groupBy('tool')->map(fn ($rows) => [
                'approach' => $rows->first()->approach,
                'total' => $rows->sum('n'),
                'severity' => $rows->pluck('n', 'severity')->all(),
            ])->sortByDesc('total');
        $topRules = Finding::where('scan_id', $scan->id)->whereNotNull('rule')
            ->select('rule', DB::raw('count(*) as n'))->groupBy('rule')->orderByDesc('n')->limit(8)->pluck('n', 'rule');
        $topCwe = Finding::where('scan_id', $scan->id)->whereNotNull('cwe')
            ->select('cwe', DB::raw('count(*) as n'))->groupBy('cwe')->orderByDesc('n')->limit(8)->pluck('n', 'cwe');

        return compact('bySeverity', 'byTool', 'topRules', 'topCwe');
    }
}
