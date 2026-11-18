<?php

namespace App\Console\Commands;

use App\Services\System\LaunchChecklist;
use Illuminate\Console\Command;

/** « Prêt pour la mise en ligne ? » : liste de contrôle (aussi sur l'écran Système). Code de sortie 1 si un point bloque. */
class CheckLaunch extends Command
{
    protected $signature = 'site:verifier';

    protected $description = 'Vérifie que le site est prêt pour la mise en ligne (production, HTTPS, e-mails, paiements, données de test…)';

    public function handle(LaunchChecklist $checklist): int
    {
        $icons = [LaunchChecklist::OK => '<fg=green>✔ OK</>', LaunchChecklist::BLOCKING => '<fg=red>✘ À REVOIR</>', LaunchChecklist::ADVICE => '<fg=yellow>• CONSEIL</>'];
        foreach ($checklist->items() as $item) {
            $this->line($icons[$item['state']].'  '.$item['label']);
            if ($item['state'] !== LaunchChecklist::OK) {
                $this->line('           '.$item['help']);
            }
        }
        $summary = $checklist->summary();
        $this->newLine();
        $this->line("{$summary['ok']} OK, {$summary['blocking']} à revoir, {$summary['advice']} conseil(s).");

        return $summary['blocking'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
