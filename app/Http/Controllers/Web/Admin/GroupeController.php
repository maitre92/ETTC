<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\FormationMode;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Annonce;
use App\Models\Groupe;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Seance;
use App\Models\Support;
use App\Services\Seances\AnnonceService;
use App\Services\Seances\SeanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
        ]);
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
        $groupe->load(['niveau', 'members', 'supports']);

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
            'meet_url' => [...SeanceService::meetRule(), Rule::requiredIf($online)],
            'location' => ['nullable', 'string', 'max:200'],
            'schedule' => ['nullable', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', 'boolean'],
        ], ['meet_url.required' => 'Un groupe en ligne a besoin de son lien Google Meet (vous pourrez le changer).', 'meet_url.url' => 'Collez l\'adresse complète du lien (meet.google.com/…).'], [
            'name' => 'nom du groupe', 'teacher' => 'formateur', 'meet_url' => 'lien Google Meet', 'location' => 'salle', 'schedule' => 'horaire', 'starts_on' => 'début', 'ends_on' => 'fin',
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
