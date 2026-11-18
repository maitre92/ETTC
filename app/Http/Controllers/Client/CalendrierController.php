<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Groupe;
use App\Models\Inscription;
use App\Models\Seance;
use App\Models\Support;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Mon calendrier » de l'apprenant (cahier §4 : calendrier, liens des séances à distance, supports autorisés) : ses
 * groupes (inscriptions non annulées), séances à venir avec « Rejoindre » 15 min avant, supports téléchargeables.
 */
class CalendrierController extends Controller
{
    public function index(Request $request): View
    {
        $groupes = $this->groupes($this->client($request));
        $ids = $groupes->pluck('id');

        return view('client.calendrier', [
            'groupes' => $groupes,
            'upcoming' => Seance::with('groupe')->whereIn('groupe_id', $ids)->where('starts_at', '>=', now()->subHours(3))->orderBy('starts_at')->limit(40)->get(),
            'past' => Seance::with('groupe')->whereIn('groupe_id', $ids)->where('starts_at', '<', now()->subHours(3))->where('is_cancelled', false)->latest('starts_at')->limit(10)->get(),
            'supports' => Support::with('groupe')->whereIn('groupe_id', $ids)->latest('id')->get(),
            'seo' => Seo::make('Mon calendrier')->noIndex(),
        ]);
    }

    public function download(Request $request, Support $support): StreamedResponse
    {
        abort_unless($this->groupes($this->client($request))->contains('id', $support->groupe_id), 404);
        abort_unless($support->path && Storage::disk('local')->exists($support->path), 404);

        return Storage::disk('local')->download($support->path, $support->original_name);
    }

    /** @return Collection<int, Groupe> */
    private function groupes(Client $client): Collection
    {
        $groupIds = Inscription::forClient($client)->whereNotNull('groupe_id')->where('status', '!=', 'annulee')->pluck('groupe_id');

        return Groupe::whereIn('id', $groupIds)->get();
    }

    private function client(Request $request): Client
    {
        return $request->user('client');
    }
}
