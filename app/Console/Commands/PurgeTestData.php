<?php

namespace App\Console\Commands;

use App\Services\System\TestDataCleaner;
use Illuminate\Console\Command;

/** Retire les données fictives de DonneesTestSeeder (« @test.ettc.ml », « (test) ») avant la mise en ligne. */
class PurgeTestData extends Command
{
    protected $signature = 'donnees-test:supprimer {--force : Ne pas demander de confirmation}';

    protected $description = 'Supprime définitivement les données de test (adresses @test.ettc.ml, mentions « (test) »), jamais les données réelles';

    public function handle(TestDataCleaner $cleaner): int
    {
        $counts = $cleaner->count();
        if ($counts === []) {
            $this->info('Aucune donnée de test à supprimer.');

            return self::SUCCESS;
        }
        $this->table(['Nature', 'Nombre'], collect($counts)->map(fn ($n, $label) => [$label, $n])->values()->all());
        if (! $this->option('force') && ! $this->confirm('Supprimer définitivement ces données de test ?')) {
            $this->line('Rien n\'a été supprimé.');

            return self::SUCCESS;
        }
        $cleaner->purge();
        $this->info('Données de test supprimées.');

        return self::SUCCESS;
    }
}
