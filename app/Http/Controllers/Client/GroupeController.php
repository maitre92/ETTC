<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Groupe;
use App\Models\GroupeMessage;
use App\Models\Inscription;
use App\Services\Seances\DiscussionService;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * « Mes groupes » de l'apprenant (demande du centre, 2026-11-21) : son formateur, ses prochaines séances, ses supports et
 * la discussion du groupe, où il écrit à son formateur et aux autres apprenants. Seulement ses groupes (inscriptions non
 * annulées) : un autre groupe n'existe pas pour lui.
 */
class GroupeController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $client = $request->user('client');
        $groupes = self::groupesOf($client)->load(['niveau', 'formateur']);
        if ($groupes->count() === 1) {
            return redirect()->route('client.groupes.show', $groupes->first());
        }

        return view('client.groupes', [
            'groupes' => $groupes,
            'unread' => $groupes->mapWithKeys(fn (Groupe $g) => [$g->id => GroupeMessage::unreadCount('client:'.$client->id, $g->id)]),
            'seo' => Seo::make('Mes groupes')->noIndex(),
        ]);
    }

    public function show(Request $request, Groupe $groupe): View
    {
        $client = $request->user('client');
        abort_unless(self::groupesOf($client)->contains('id', $groupe->id), 404);
        $groupe->load(['niveau', 'formateur', 'supports', 'messages.user', 'messages.client']);
        GroupeMessage::markSeen('client:'.$client->id, $groupe->id);

        return view('client.groupe', [
            'groupe' => $groupe,
            'upcoming' => $groupe->seances()->where('starts_at', '>=', now()->subHours(3))->limit(6)->get(),
            'seo' => Seo::make('Groupe '.$groupe->name)->noIndex(),
        ]);
    }

    public function storeMessage(Request $request, Groupe $groupe, DiscussionService $discussion): RedirectResponse
    {
        $client = $request->user('client');
        abort_unless(self::groupesOf($client)->contains('id', $groupe->id), 404);
        $body = $request->validate(['body' => ['required', 'string', 'max:2000']], ['body.required' => 'Écrivez votre message.'], ['body' => 'message'])['body'];
        $discussion->postFromLearner($groupe, $body, $client);

        return redirect()->to(route('client.groupes.show', $groupe).'#discussion')->with('status', 'Message envoyé : votre formateur le verra dans son espace.');
    }

    /** Groupes de l'apprenant : ceux de ses inscriptions non annulées. @return Collection<int, Groupe> */
    public static function groupesOf(Client $client): Collection
    {
        $ids = Inscription::forClient($client)->whereNotNull('groupe_id')->where('status', '!=', 'annulee')->pluck('groupe_id');

        return Groupe::whereIn('id', $ids)->orderBy('name')->get();
    }
}
