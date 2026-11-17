<?php

namespace App\Console\Commands;

use App\Services\Paiements\PaiementService;
use Illuminate\Console\Command;

/** Paiements en ligne restés « en attente » (notification perdue, apprenant parti) : relus chez le prestataire. */
class SyncOnlinePayments extends Command
{
    protected $signature = 'paiements:sync';

    protected $description = 'Relit chez le prestataire les paiements en ligne encore en attente (abandonnés après 24 h)';

    public function handle(PaiementService $service): int
    {
        $this->info($service->synchronizePending().' paiement(s) en ligne vérifié(s).');

        return self::SUCCESS;
    }
}
