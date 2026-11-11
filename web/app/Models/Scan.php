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

    /**
     * Avancement en pourcentage : chaque étape demandée (SAST, SCA, DAST) compte pour une part égale.
     * Une étape est terminée quand toutes ses sous-étapes sont finies, à moitié quand une est en cours.
     */
    public function progressPercent(array $stageStates): int
    {
        if ($this->status === 'done' || $this->status === 'done_with_errors' || $this->status === 'failed') {
            return 100;
        }
        $planned = $this->stages ?: [];
        if (! $planned) {
            return 0;
        }
        $total = 0;
        foreach ($planned as $group) {
            $subs = array_filter($stageStates, fn ($st) => explode('-', $st['name'])[0] === $group);
            if (! $subs) {
                continue;
            }
            $states = array_column($subs, 'state');
            if (in_array('running', $states, true)) {
                $total += 50;
            } elseif (count(array_diff($states, ['ok', 'error'])) === 0) {
                $total += 100;
            } else {
                $total += 50;
            }
        }

        return (int) round($total / count($planned));
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'done_with_errors', 'failed'], true);
    }
}
