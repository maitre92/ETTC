<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Annonce;
use App\Models\Groupe;
use App\Models\GroupeMessage;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Seance;
use App\Models\Support;
use App\Services\Attestations\AttestationService;
use App\Services\Inscriptions\InscriptionService;
use App\Services\Seances\AnnonceService;
use App\Services\Seances\DiscussionService;
use App\Services\Seances\SeanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Séances et groupes (cahier §3.6, §5) : liste des groupes et séances du jour, fiche d'un groupe (apprenants proposés
 * automatiquement, séances en série, annulation, supports). Contrôleur mince : SeanceService ; formulaires en AJAX.
 */
class GroupeController extends Controller
{
    public function __construct(private readonly SeanceService $service) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Groupe::class);

        return view('groupes.index', [
            'groupes' => Groupe::with('niveau')->withCount('members')->orderByDesc('is_active')->orderBy('name')->get(),
            'today' => Seance::with('groupe')->whereDate('starts_at', today())->orderBy('starts_at')->get(),
            'niveaux' => Niveau::ordered()->get(),
            'withoutGroup' => Inscription::whereNull('groupe_id')->whereIn('status', ['validee', 'en_cours'])->count(),
            'annonces' => Annonce::with('groupe')->latest('id')->limit(10)->get(),
            'plan' => $this->service->distributionPlan(15),
        ]);
    }

    /** Répartition automatique des apprenants sans groupe (par niveau et mode, capacité au plus). */
    public function distribute(Request $request): RedirectResponse
    {
        Gate::authorize('create', Groupe::class);
        $capacity = (int) $request->validate(['capacity' => ['required', 'integer', 'between:2,200']], [], ['capacity' => 'apprenants par groupe'])['capacity'];
        $result = $this->service->distribute($capacity, null, $request->user());
        $created = collect($result['created'])->pluck('name');

        return redirect()->route('groupes.index')->with('status', "{$result['placed']} apprenant(s) répartis."
            .($created->isNotEmpty() ? ' Nouveaux groupes : '.$created->implode(', ').' — choisissez leur formateur et programmez leurs séances.' : ''));
    }

    /**
     * Attestations du groupe en une fois : (option) termine la formation des apprenants en cours, délivre l'attestation
     * de chaque apprenant terminé qui n'en a pas encore (e-mail à chacun), puis ouvre un PDF avec toutes les pages.
     */
    public function attestations(Request $request, Groupe $groupe, AttestationService $attestations, InscriptionService $inscriptions): Response|RedirectResponse
    {
        Gate::authorize('view', $groupe);
        $members = $groupe->members()->get();
        if ($request->boolean('finish')) {
            foreach ($members->where('status', InscriptionStatus::EnCours) as $member) {
                $inscriptions->finish($member, $request->user());
            }
            $members = $groupe->members()->get();
        }
        $issued = $members->filter(fn (Inscription $m) => $m->status === InscriptionStatus::Terminee && ! $m->attestation_number)
            ->map(fn (Inscription $m) => $attestations->issue($m, $request->user()))->values();
        if ($issued->isEmpty()) {
            return redirect()->route('groupes.show', $groupe)->withErrors(['attestations' => 'Aucune attestation à délivrer : terminez d\'abord la formation des apprenants (ou cochez « Terminer d\'abord… »).']);
        }

        return $attestations->batchPdf($issued)->stream('Attestations_'.Str::slug($groupe->name, '_').'.pdf');
    }

    /** Message de l'équipe dans la discussion du groupe (lue par les apprenants et le formateur). */
    public function storeMessage(Request $request, Groupe $groupe, DiscussionService $discussion): RedirectResponse
    {
        Gate::authorize('update', $groupe);
        $body = $request->validate(['body' => ['required', 'string', 'max:3000']], [], ['body' => 'message'])['body'];
        $sent = $discussion->postFromTeam($groupe, $body, $request->user(), $request->boolean('email'));

        return redirect()->to(route('groupes.show', $groupe).'#discussion')->with('status', 'Message envoyé au groupe'.($sent ? " et par e-mail à {$sent} apprenant(s)." : '.'));
    }

    public function destroyMessage(Request $request, Groupe $groupe, GroupeMessage $message, DiscussionService $discussion): RedirectResponse
    {
        Gate::authorize('update', $groupe);
        abort_unless($message->groupe_id === $groupe->id, 404);
        $discussion->delete($message, $request->user());

        return redirect()->to(route('groupes.show', $groupe).'#discussion')->with('status', 'Message retiré.');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Groupe::class);
        $groupe = $this->service->saveGroupe(null, $this->groupeData($request), $request->user());

        return redirect()->route('groupes.show', $groupe)->with('status', "Groupe « {$groupe->name} » créé. Ajoutez maintenant ses apprenants et ses séances.");
    }

    public function show(Groupe $groupe): View
    {
        Gate::authorize('view', $groupe);
        $groupe->load(['niveau', 'members', 'supports', 'formateur', 'messages.user', 'messages.client']);
        GroupeMessage::markSeen('user:'.auth()->id(), $groupe->id);

        return view('groupes.show', [
            'groupe' => $groupe,
            'upcoming' => $groupe->seances()->where('starts_at', '>=', now()->subHours(3))->get(),
            'past' => $groupe->seances()->where('starts_at', '<', now()->subHours(3))->reorder('starts_at', 'desc')->limit(10)->get(),
            'candidates' => $this->service->candidates($groupe),
            'niveaux' => Niveau::ordered()->get(),
            'history' => ActivityLog::where('subject_type', $groupe->getMorphClass())->where('subject_id', $groupe->id)->latest('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request, Groupe $groupe): RedirectResponse
    {
        Gate::authorize('update', $groupe);
        $this->service->saveGroupe($groupe, $this->groupeData($request), $request->user());

        return redirect()->route('groupes.show', $groupe)->with('status', 'Groupe mis à jour.');
    }

    public function addMembers(Request $request, Groupe $groupe): RedirectResponse
    {
        Gate::authorize('update', $groupe);
        $ids = $request->validate(['inscriptions' => ['required', 'array', 'min:1'], 'inscriptions.*' => ['integer', 'exists:inscriptions,id']], ['inscriptions.required' => 'Cochez au moins un apprenant.'])['inscriptions'];
        $count = $this->service->addMembers($groupe, array_map('intval', $ids), $request->user());

        return redirect()->route('groupes.show', $groupe)->with('status', "{$count} apprenant(s) ajouté(s) au groupe.");
    }

    public function removeMember(Request $request, Groupe $groupe, Inscription $inscription): RedirectResponse
    {
        Gate::authorize('update', $groupe);
        $this->service->removeMember($groupe, $inscription, $request->user());

        return redirect()->route('groupes.show', $groupe)->with('status', "{$inscription->name} retiré(e) du groupe.");
    }

    public function planSeries(Request $request, Groupe $groupe): RedirectResponse
    {
        Gate::authorize('update', $groupe);
        $data = $request->validate([
            'days' => ['required', 'array', 'min:1'], 'days.*' => ['integer', 'between:1,7'],
            'time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'between:15,480'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'title' => ['nullable', 'string', 'max:200'],
        ], ['days.required' => 'Cochez au moins un jour.'], ['time' => 'heure', 'duration_minutes' => 'durée', 'from' => 'date de début', 'to' => 'date de fin', 'title' => 'thème']);
        $seances = $this->service->planSeries($groupe, $data, $request->user());

        return redirect()->route('groupes.show', $groupe)->with('status', "{$seances->count()} séance(s) programmée(s). Les apprenants ont reçu leur calendrier par e-mail.");
    }

    public function storeSeance(Request $request, Groupe $groupe): RedirectResponse
    {
        Gate::authorize('update', $groupe);
        $this->service->saveSeance($groupe, null, $this->seanceData($request), $request->user());

        return redirect()->route('groupes.show', $groupe)->with('status', 'Séance ajoutée.');
    }

    public function updateSeance(Request $request, Groupe $groupe, Seance $seance): RedirectResponse
    {
        Gate::authorize('update', $groupe);
        abort_unless($seance->groupe_id === $groupe->id, 404);
        $this->service->saveSeance($groupe, $seance, $this->seanceData($request), $request->user());

        return redirect()->route('groupes.show', $groupe)->with('status', 'Séance modifiée.');
    }

    public function cancelSeance(Request $request, Groupe $groupe, Seance $seance): RedirectResponse
    {
        Gate::authorize('update', $groupe);
        abort_unless($seance->groupe_id === $groupe->id, 404);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:300']], [], ['reason' => 'motif'])['reason'];
        $this->service->cancelSeance($seance, $reason, $request->user());

        return redirect()->route('groupes.show', $groupe)->with('status', 'Séance annulée : les apprenants sont prévenus par e-mail.');
    }

    /** Annonce aux apprenants (tous, ou un groupe), avec prévenance par e-mail cochée d'office. */
    public function storeAnnonce(Request $request, AnnonceService $annonces): RedirectResponse
    {
        Gate::authorize('create', Groupe::class);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:3000'],
            'groupe_id' => ['nullable', 'exists:groupes,id'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
        ], [], ['title' => 'titre', 'body' => 'message', 'groupe_id' => 'destinataires', 'expires_on' => 'date de fin']);
        $annonce = $annonces->publish($data, $request->boolean('email'), $request->user());
        $back = $request->input('back') ? route('groupes.show', (int) $request->input('back')) : route('groupes.index');

        return redirect()->to($back)->with('status', 'Annonce publiée'.($annonce->emailed_count ? " et envoyée par e-mail à {$annonce->emailed_count} apprenant(s)." : '.'));
    }

    public function destroyAnnonce(Request $request, Annonce $annonce, AnnonceService $annonces): RedirectResponse
    {
        Gate::authorize('create', Groupe::class);
        $annonces->delete($annonce, $request->user());

        return back()->with('status', 'Annonce retirée.');
    }

    public function storeSupport(Request $request, Groupe $groupe): RedirectResponse
    {
        Gate::authorize('manageSupports', $groupe);
        $this->normalizeLinks($request, 'url');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'file' => ['nullable', 'required_without:url', 'file', 'max:20480', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,odt,txt,jpg,jpeg,png,mp3,m4a,wav,ogg,mp4,webm'],
            'url' => ['nullable', 'required_without:file', 'url:https', 'max:500'],
        ], ['file.required_without' => 'Choisissez un fichier ou collez un lien.', 'url.required_without' => 'Choisissez un fichier ou collez un lien.', 'file.max' => 'Fichier trop lourd (20 Mo au plus) : pour une vidéo, mettez plutôt un lien YouTube ou Drive.'], ['title' => 'titre', 'file' => 'fichier', 'url' => 'lien']);
        $this->service->addSupport($groupe, $data['title'], $request->file('file'), $data['url'] ?? null, $request->user());

        return redirect()->route('groupes.show', $groupe)->with('status', 'Support ajouté : les apprenants du groupe le voient dans leur espace.');
    }

    public function destroySupport(Request $request, Groupe $groupe, Support $support): RedirectResponse
    {
        Gate::authorize('manageSupports', $groupe);
        abort_unless($support->groupe_id === $groupe->id, 404);
        $this->service->deleteSupport($support, $request->user());

        return redirect()->route('groupes.show', $groupe)->with('status', 'Support retiré.');
    }

    public function downloadSupport(Groupe $groupe, Support $support): StreamedResponse
    {
        Gate::authorize('view', $groupe);
        abort_unless($support->groupe_id === $groupe->id && $support->path && Storage::disk('local')->exists($support->path), 404);

        return Storage::disk('local')->download($support->path, $support->original_name);
    }

    /** Lien collé sans « https:// » (meet.google.com/…) : complété, pour ne pas faire échouer l'enregistrement. */
    private function normalizeLinks(Request $request, string ...$fields): void
    {
        foreach ($fields as $field) {
            $value = trim((string) $request->input($field));
            if ($value !== '' && ! preg_match('~^https?://~i', $value)) {
                $request->merge([$field => 'https://'.$value]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function groupeData(Request $request): array
    {
        $this->normalizeLinks($request, 'meet_url');
        $online = $request->input('mode') === FormationMode::EnLigne->value;

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'niveau_id' => ['nullable', 'exists:niveaux,id'],
            'mode' => ['required', Rule::enum(FormationMode::class)],
            'teacher' => ['nullable', 'string', 'max:120'],
            'formateur_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'capacity' => ['nullable', 'integer', 'between:1,500'],
            'meet_url' => [...SeanceService::meetRule(), Rule::requiredIf($online)],
            'location' => ['nullable', 'string', 'max:200'],
            'schedule' => ['nullable', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', 'boolean'],
        ], ['meet_url.required' => 'Un groupe en ligne a besoin de son lien Google Meet (vous pourrez le changer).', 'meet_url.url' => 'Collez l\'adresse complète du lien (meet.google.com/…).'], [
            'name' => 'nom du groupe', 'teacher' => 'formateur', 'formateur_id' => 'formateur', 'capacity' => 'places', 'meet_url' => 'lien Google Meet', 'location' => 'salle', 'schedule' => 'horaire', 'starts_on' => 'début', 'ends_on' => 'fin',
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    /** @return array<string, mixed> */
    private function seanceData(Request $request): array
    {
        $this->normalizeLinks($request, 'meet_url');

        return $request->validate([
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'between:15,480'],
            'title' => ['nullable', 'string', 'max:200'],
            'meet_url' => SeanceService::meetRule(),
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['starts_at' => 'date et heure', 'duration_minutes' => 'durée', 'title' => 'thème', 'meet_url' => 'lien Google Meet', 'notes' => 'informations pratiques']);
    }
}
