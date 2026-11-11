<?php

namespace App\Http\Controllers;

use App\Jobs\CaptureSnapshot;
use App\Models\Target;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class TargetController extends Controller
{
    public function create(): View
    {
        return view('targets.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9-]{0,39}$/', 'unique:targets,name'],
            'label' => ['nullable', 'string', 'max:80'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'source_path' => ['nullable', 'string', 'max:500', function (string $attr, mixed $value, \Closure $fail) {
                if ($value && ! is_dir($value)) {
                    $fail("Le dossier « {$value} » est introuvable sur cette machine.");
                }
            }],
            'image' => ['nullable', 'string', 'max:200', 'regex:/^[\w.\/:@-]+$/'],
        ], [
            'name.regex' => 'Lettres minuscules, chiffres et tirets uniquement (40 caractères max).',
            'name.unique' => 'Une cible porte déjà ce nom.',
        ]);

        $target = Target::create($data);
        CaptureSnapshot::dispatch($target)->onQueue('snapshots');

        return redirect()->route('targets.show', $target)->with('status', 'Cible ajoutée. Capture de la page en cours.');
    }

    public function show(Target $target): View
    {
        return view('targets.show', [
            'target' => $target,
            'scans' => $target->scans()->withCount('findings')->limit(50)->get(),
        ]);
    }

    public function snapshot(Target $target): RedirectResponse
    {
        CaptureSnapshot::dispatch($target)->onQueue('snapshots');

        return back()->with('status', 'Nouvelle capture demandée.');
    }

    public function destroy(Target $target): RedirectResponse
    {
        if ($target->scans()->whereIn('status', ['queued', 'running'])->exists()) {
            return back()->with('status', 'Impossible de supprimer : une analyse est en cours sur cette cible.');
        }
        if ($target->snapshot_path) {
            Storage::disk('public')->delete($target->snapshot_path);
        }
        $target->delete();

        return redirect()->route('dashboard')->with('status', 'Cible supprimée (les résultats déjà archivés restent dans results/).');
    }
}
