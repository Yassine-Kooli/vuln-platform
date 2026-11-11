<?php

namespace App\Jobs;

use App\Models\Target;
use App\Services\Snapshotter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CaptureSnapshot implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Target $target)
    {
    }

    public function handle(Snapshotter $snapshotter): void
    {
        $snapshotter->capture($this->target);
    }
}
