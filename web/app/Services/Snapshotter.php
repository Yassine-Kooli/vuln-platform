<?php

namespace App\Services;

use App\Models\Target;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/** Capture une image de la page cible (navigateur headless) pour l'afficher dans l'interface. */
class Snapshotter
{
    public function capture(Target $target): void
    {
        $relative = "snapshots/{$target->name}.png";
        $absolute = Storage::disk('public')->path($relative);
        Storage::disk('public')->makeDirectory('snapshots');

        $result = Process::timeout(120)->run([
            config('vp.chrome'), '--headless=new', '--no-sandbox', '--disable-gpu', '--hide-scrollbars',
            '--window-size=1280,800', '--virtual-time-budget=8000', '--screenshot='.$absolute, $target->url,
        ]);

        if ($result->successful() && is_file($absolute)) {
            $target->update(['snapshot_path' => $relative, 'snapshot_at' => now(), 'snapshot_error' => null]);
        } else {
            $error = trim($result->errorOutput()) ?: 'capture impossible (URL injoignable ?)';
            $target->update(['snapshot_error' => mb_substr($error, -200)]);
        }
    }
}
