<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Groupe;
use App\Models\GroupeMessage;
use App\Models\Inscription;
use App\Models\Seance;
use App\Models\Support;
use App\Services\Remunerations\RemunerationService;
use App\Services\Seances\DiscussionService;
use App\Services\Seances\SeanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Espace formateur (demande du centre, 2026-11-21) : SES groupes seulement (formateur du groupe). Pour chacun : prochaine
 * séance et lien Meet, apprenants (contacts), séances (ajout, annulation : les apprenants sont prévenus), supports et
 * discussion avec les apprenants. Mêmes services que l'administration (audit compris).
 */
class FormateurController extends Controller
{
    public function __construct(private readonly SeanceService $seances) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $groupes = Groupe::with('niveau')->withCount('members')->where('formateur_id', $user->id)->orderByDesc('is_active')->orderBy('name')->get();
        $ids = $groupes->pluck('id');

        return view('formateur.index', [
            'groupes' => $groupes,
            'unread' => $groupes->mapWithKeys(fn (Groupe $g) => [$g->id => GroupeMessage::unreadCount('user:'.$user->id, $g->id)]),
            'today' => Seance::with('groupe')->whereIn('groupe_id', $ids)->whereDate('starts_at', today())->orderBy('starts_at')->get(),
            'week' => Seance::with('groupe')->whereIn('groupe_id', $ids)->where('is_cancelled', false)
                ->where('starts_at', '>', now()->endOfDay())->where('starts_at', '<=', now()->addDays(7)->endOfDay())->orderBy('starts_at')->get(),
            'latest' => GroupeMessage::with(['groupe', 'client'])->whereIn('groupe_id', $ids)->whereNotNull('client_id')->latest('id')->limit(6)->get(),
            // Formations personnalisées qui lui sont confiées, et sa rémunération du mois.
            'personal' => Inscription::with('niveau')->where('formateur_id', $user->id)->whereIn('status', ['validee', 'en_cours'])->orderBy('name')->get(),
            'pay' => app(RemunerationService::class)->forTeacher($user, now()->format('Y-m')),
            'payLastMonth' => app(RemunerationService::class)->forTeacher($user, now()->subMonthNoOverflow()->format('Y-m')),
        ]);
    }

    public function show(Request $request, Groupe $groupe): View
    {
        $this->own($request, $groupe);
        $groupe->load(['niveau', 'members', 'supports', 'messages.user', 'messages.client']);
        GroupeMessage::markSeen('user:'.$request->user()->id, $groupe->id);

        return view('formateur.groupe', [
            'groupe' => $groupe,
            'upcoming' => $groupe->seances()->where('starts_at', '>=', now()->subHours(3))->get(),
            'past' => $groupe->seances()->where('starts_at', '<', now()->subHours(3))->reorder('starts_at', 'desc')->limit(5)->get(),
        ]);
    }

    public function storeMessage(Request $request, Groupe $groupe, DiscussionService $discussion): RedirectResponse
    {
        $this->own($request, $groupe);
        $body = $request->validate(['body' => ['required', 'string', 'max:3000']], [], ['body' => 'message'])['body'];
        $sent = $discussion->postFromTeam($groupe, $body, $request->user(), $request->boolean('email'));

        return $this->back($groupe, 'discussion', 'Message envoyé'.($sent ? " (et par e-mail à {$sent} apprenant(s))." : '.'));
    }

    public function destroyMessage(Request $request, Groupe $groupe, GroupeMessage $message, DiscussionService $discussion): RedirectResponse
    {
        $this->own($request, $groupe);
        abort_unless($message->groupe_id === $groupe->id, 404);
        $discussion->delete($message, $request->user());

        return $this->back($groupe, 'discussion', 'Message retiré.');
    }

    public function storeSeance(Request $request, Groupe $groupe): RedirectResponse
    {
        $this->own($request, $groupe);
        $value = trim((string) $request->input('meet_url'));
        if ($value !== '' && ! preg_match('~^https?://~i', $value)) {
            $request->merge(['meet_url' => 'https://'.$value]);
        }
        $data = $request->validate([
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'between:15,480'],
            'title' => ['nullable', 'string', 'max:200'],
            'meet_url' => SeanceService::meetRule(),
        ], ['starts_at.after' => 'Choisissez une date à venir.'], ['starts_at' => 'date et heure', 'duration_minutes' => 'durée', 'title' => 'thème', 'meet_url' => 'lien Google Meet']);
        $seance = $this->seances->saveSeance($groupe, null, $data, $request->user());

        return $this->back($groupe, 'seances', "Séance ajoutée : {$seance->when()}.");
    }

    public function cancelSeance(Request $request, Groupe $groupe, Seance $seance): RedirectResponse
    {
        $this->own($request, $groupe);
        abort_unless($seance->groupe_id === $groupe->id, 404);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:300']], [], ['reason' => 'motif'])['reason'];
        $this->seances->cancelSeance($seance, $reason, $request->user());

        return $this->back($groupe, 'seances', 'Séance annulée : les apprenants sont prévenus par e-mail.');
    }

    public function storeSupport(Request $request, Groupe $groupe): RedirectResponse
    {
        $this->own($request, $groupe);
        $value = trim((string) $request->input('url'));
        if ($value !== '' && ! preg_match('~^https?://~i', $value)) {
            $request->merge(['url' => 'https://'.$value]);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'file' => ['nullable', 'required_without:url', 'file', 'max:20480', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,odt,txt,jpg,jpeg,png,mp3,m4a,wav,ogg,mp4,webm'],
            'url' => ['nullable', 'required_without:file', 'url:https', 'max:500'],
        ], ['file.required_without' => 'Choisissez un fichier ou collez un lien.', 'url.required_without' => 'Choisissez un fichier ou collez un lien.', 'file.max' => 'Fichier trop lourd (20 Mo au plus) : pour une vidéo, mettez plutôt un lien YouTube ou Drive.'], ['title' => 'titre', 'file' => 'fichier', 'url' => 'lien']);
        $this->seances->addSupport($groupe, $data['title'], $request->file('file'), $data['url'] ?? null, $request->user());

        return $this->back($groupe, 'supports', 'Support ajouté : vos apprenants le voient dans leur espace.');
    }

    public function destroySupport(Request $request, Groupe $groupe, Support $support): RedirectResponse
    {
        $this->own($request, $groupe);
        abort_unless($support->groupe_id === $groupe->id, 404);
        $this->seances->deleteSupport($support, $request->user());

        return $this->back($groupe, 'supports', 'Support retiré.');
    }

    public function downloadSupport(Request $request, Groupe $groupe, Support $support): StreamedResponse
    {
        $this->own($request, $groupe);
        abort_unless($support->groupe_id === $groupe->id && $support->path && Storage::disk('local')->exists($support->path), 404);

        return Storage::disk('local')->download($support->path, $support->original_name);
    }

    /** Seul le formateur du groupe (ou le superadmin) y a accès ; un autre groupe n'existe pas pour lui. */
    private function own(Request $request, Groupe $groupe): void
    {
        abort_unless($groupe->formateur_id === $request->user()->id || $request->user()->isSuperadmin(), 404);
    }

    private function back(Groupe $groupe, string $anchor, string $status): RedirectResponse
    {
        return redirect()->to(route('formateur.groupes.show', $groupe).'#'.$anchor)->with('status', $status);
    }
}
