<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\TestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\InscriptionRequest;
use App\Models\ActivityLog;
use App\Models\Formule;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\TestNiveau;
use App\Services\Inscriptions\InscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Inscriptions (cahier §3.5, §5) : liste filtrable (statut, niveau, formule, mode, session), inscription au centre ou
 * depuis un test de niveau passé, fiche de traitement (validation, début et fin de formation, session, annulation).
 * Contrôleur mince : les règles vivent dans InscriptionService ; formulaires envoyés en AJAX (crud-ajax.js).
 */
class InscriptionController extends Controller
{
    public function __construct(private readonly InscriptionService $service) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Inscription::class);

        return view('inscriptions.index', [
            'inscriptions' => Inscription::with('niveau')->latest('id')->get(),
            'niveaux' => Niveau::ordered()->get(),
            'formules' => Formule::published()->ordered()->get(),
            'sessions' => Inscription::whereNotNull('session')->distinct()->orderBy('session')->pluck('session'),
        ]);
    }

    public function store(InscriptionRequest $request): RedirectResponse
    {
        Gate::authorize('create', Inscription::class);

        $inscription = $this->service->create($request->validated(), $request->user());

        return redirect()->route('inscriptions.show', $inscription)->with('status', "Inscription enregistrée : {$inscription->number}.");
    }

    /** Inscription d'un candidat dont le test est passé : identité et niveau attribué repris du test. */
    public function fromTest(Request $request, TestNiveau $test): RedirectResponse
    {
        Gate::authorize('create', Inscription::class);
        abort_unless($test->status === TestStatus::Passe, 404);

        // Identité et niveau repris du test ; le niveau attribué peut être masqué sur le site : contrôle sans « publié ».
        $request->merge(['name' => $test->name, 'phone' => $test->phone ?? $request->input('phone'), 'email' => $test->email, 'niveau_id' => $test->niveau_id]);
        $rules = ['niveau_id' => ['nullable', 'exists:niveaux,id']] + InscriptionRequest::baseRules();
        $data = $request->validate($rules, (new InscriptionRequest)->messages(), (new InscriptionRequest)->attributes());

        $inscription = $this->service->create($data, $request->user(), $test);

        return redirect()->route('inscriptions.show', $inscription)->with('status', "Inscription enregistrée depuis le test {$test->number} : {$inscription->number}.");
    }

    public function show(Inscription $inscription): View
    {
        Gate::authorize('view', $inscription);

        return view('inscriptions.show', [
            'inscription' => $inscription->load(['niveau', 'formule', 'client', 'testNiveau', 'validator', 'paiements']),
            'sessions' => Inscription::whereNotNull('session')->distinct()->orderBy('session')->pluck('session'),
            'history' => ActivityLog::where('subject_type', $inscription->getMorphClass())->where('subject_id', $inscription->id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function validateFile(Request $request, Inscription $inscription): RedirectResponse
    {
        Gate::authorize('validate', $inscription);
        $this->service->validateFile($inscription, $request->user());

        return redirect()->route('inscriptions.show', $inscription)->with('status', 'Dossier validé : l\'inscription attend le paiement.');
    }

    public function start(Request $request, Inscription $inscription): RedirectResponse
    {
        Gate::authorize('validate', $inscription);
        $this->service->start($inscription, $request->user());

        return redirect()->route('inscriptions.show', $inscription)->with('status', 'Formation commencée.');
    }

    public function finish(Request $request, Inscription $inscription): RedirectResponse
    {
        Gate::authorize('validate', $inscription);
        $this->service->finish($inscription, $request->user());

        return redirect()->route('inscriptions.show', $inscription)->with('status', 'Formation terminée.');
    }

    public function organize(Request $request, Inscription $inscription): RedirectResponse
    {
        Gate::authorize('organize', $inscription);
        $data = $request->validate(['session' => ['nullable', 'string', 'max:100'], 'internal_notes' => ['nullable', 'string', 'max:3000']], [], ['session' => 'session', 'internal_notes' => 'notes internes']);

        $this->service->organize($inscription, isset($data['session']) ? trim($data['session']) : null, isset($data['internal_notes']) ? trim($data['internal_notes']) : null, $request->user());

        return redirect()->route('inscriptions.show', $inscription)->with('status', 'Dossier mis à jour.');
    }

    public function cancel(Request $request, Inscription $inscription): RedirectResponse
    {
        Gate::authorize('cancel', $inscription);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:300']], [], ['cancel_reason' => 'motif d\'annulation']);

        $this->service->cancel($inscription, $data['cancel_reason'], $request->user());

        return redirect()->route('inscriptions.show', $inscription)->with('status', 'Inscription annulée.');
    }

    public function destroy(Inscription $inscription): RedirectResponse
    {
        Gate::authorize('delete', $inscription);

        $inscription->delete();

        return redirect()->route('inscriptions.index')->with('status', "Inscription {$inscription->number} supprimée.");
    }
}
