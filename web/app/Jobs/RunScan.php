<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\ScanRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunScan implements ShouldQueue
{
    use Queueable;

    public int $timeout = 0;   // une analyse complète dure plusieurs minutes
    public int $tries = 1;

    public function __construct(public Scan $scan)
    {
    }

    public function handle(ScanRunner $runner): void
    {
        $runner->run($this->scan);
    }

    /** Une erreur inattendue ne doit jamais laisser l'analyse bloquée en « en cours ». */
    public function failed(Throwable $exception): void
    {
        $this->scan->update([
            'status' => 'failed',
            'error' => mb_substr($exception->getMessage(), 0, 250),
            'finished_at' => now(),
        ]);
    }
}
