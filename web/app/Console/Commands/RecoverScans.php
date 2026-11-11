<?php

namespace App\Console\Commands;

use App\Models\Scan;
use Illuminate\Console\Command;

/**
 * Au démarrage de la plateforme, aucun travailleur ne tourne : une analyse encore « en cours »
 * a été interrompue (arrêt brutal, redémarrage). On la marque en échec pour qu'elle ne reste pas bloquée.
 */
class RecoverScans extends Command
{
    protected $signature = 'vp:recover';

    protected $description = 'Marque en échec les analyses restées en cours après un arrêt';

    public function handle(): int
    {
        $stuck = Scan::where('status', 'running')->get();
        foreach ($stuck as $scan) {
            $scan->update(['status' => 'failed', 'error' => 'interrompue (arrêt de la plateforme pendant l\'analyse)', 'finished_at' => now()]);
        }
        $this->info($stuck->count().' analyse(s) interrompue(s) remise(s) en échec.');

        return self::SUCCESS;
    }
}
