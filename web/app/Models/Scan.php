<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_id', 'stages', 'label', 'status', 'stage_states', 'run_dir', 'exit_code',
        'error', 'summary', 'metrics', 'comparison', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'stages' => 'array', 'stage_states' => 'array', 'summary' => 'array',
            'metrics' => 'array', 'comparison' => 'array',
            'started_at' => 'datetime', 'finished_at' => 'datetime',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'done_with_errors', 'failed'], true);
    }
}
