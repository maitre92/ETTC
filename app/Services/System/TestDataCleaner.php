<?php

namespace App\Services\System;

use App\Models\Article;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Demande;
use App\Models\DemandeNote;
use App\Models\Groupe;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\Seance;
use App\Models\Support;
use App\Models\Temoignage;
use App\Models\TestNiveau;
use App\Services\Audit\ActivityLogger;
use Database\Seeders\DonneesTestSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Données FICTIVES de DonneesTestSeeder, à retirer avant la mise en ligne : tout ce qui porte une adresse
 * « @test.ettc.ml » ou la mention « (test) », avec ce qui en dépend (paiements, séances, notes, pièces jointes).
 * Ne touche jamais une donnée réelle : seules ces marques sont prises en compte.
 */
class TestDataCleaner
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /** @return array<string, int> nombre d'éléments fictifs par nature */
    public function count(): array
    {
        return array_filter([
            'Inscriptions' => $this->inscriptions()->count(),
            'Paiements' => Paiement::withTrashed()->whereIn('inscription_id', $this->inscriptions()->select('id'))->count(),
            'Tests de niveau' => $this->email(TestNiveau::withTrashed())->count(),
            'Demandes' => $this->email(Demande::withTrashed())->count(),
            'Comptes apprenants' => $this->email(Client::withTrashed())->count(),
            'Groupes' => $this->groupes()->count(),
            'Actualités' => Article::withTrashed()->where('title', 'like', '%(test)%')->count(),
            'Témoignages' => Temoignage::withTrashed()->where('organization', 'like', '%(test)%')->count(),
        ]);
    }

    /** @return array<string, int> ce qui a été supprimé */
    public function purge(): array
    {
        $counts = $this->count();
        if ($counts === []) {
            return [];
        }
        $files = [];

        DB::transaction(function () use (&$files) {
            $inscriptionIds = $this->inscriptions()->pluck('id');
            $groupIds = $this->groupes()->pluck('id');
            $demandes = $this->email(Demande::withTrashed())->get(['id', 'attachment_path']);
            $clients = $this->email(Client::withTrashed())->pluck('id');

            Paiement::withTrashed()->whereIn('inscription_id', $inscriptionIds)->forceDelete();
            Inscription::withTrashed()->whereIn('groupe_id', $groupIds)->whereNotIn('id', $inscriptionIds)->update(['groupe_id' => null, 'session' => null]);
            Inscription::withTrashed()->whereIn('id', $inscriptionIds)->forceDelete();
            $files = array_merge($files, Support::withTrashed()->whereIn('groupe_id', $groupIds)->whereNotNull('path')->pluck('path')->all());
            Support::withTrashed()->whereIn('groupe_id', $groupIds)->forceDelete();
            Seance::withTrashed()->whereIn('groupe_id', $groupIds)->forceDelete();
            Groupe::withTrashed()->whereIn('id', $groupIds)->forceDelete();
            $this->email(TestNiveau::withTrashed())->forceDelete();
            DemandeNote::whereIn('demande_id', $demandes->pluck('id'))->delete();
            $files = array_merge($files, $demandes->pluck('attachment_path')->filter()->all());
            Demande::withTrashed()->whereIn('id', $demandes->pluck('id'))->forceDelete();
            $files = array_merge($files, ClientDocument::withTrashed()->whereIn('client_id', $clients)->pluck('path')->filter()->all());
            ClientDocument::withTrashed()->whereIn('client_id', $clients)->forceDelete();
            Client::withTrashed()->whereIn('id', $clients)->forceDelete();
            Article::withTrashed()->where('title', 'like', '%(test)%')->forceDelete();
            Temoignage::withTrashed()->where('organization', 'like', '%(test)%')->forceDelete();
        });

        Storage::disk('local')->delete($files);
        $this->logger->log('system.test_data_purged', 'system', 'Données de test supprimées : '.collect($counts)->map(fn ($n, $label) => "{$label} {$n}")->implode(', '), new: $counts);

        return $counts;
    }

    private function inscriptions(): Builder
    {
        return $this->email(Inscription::withTrashed());
    }

    private function groupes(): Builder
    {
        return Groupe::withTrashed()->where(fn (Builder $q) => $q->where('name', 'like', '%test%')->where('teacher', 'like', '%(test)%'));
    }

    private function email(Builder $query): Builder
    {
        return $query->where('email', 'like', '%@'.DonneesTestSeeder::EMAIL_DOMAIN);
    }
}
