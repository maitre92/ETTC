<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Billing;
use App\Enums\FormationMode;
use App\Enums\PaymentChoice;
use App\Enums\PaymentMethod;
use App\Enums\TestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\InscriptionRequest;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\TestNiveau;
use App\Services\Inscriptions\InscriptionService;
use App\Services\Paiements\PaiementService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    /**
     * Inscription au centre (cahier §3.8 B) : en présentiel, l'inscription et le paiement se font ENSEMBLE — le premier
     * paiement est encaissé dans la même fenêtre, le reçu est proposé à l'impression aussitôt.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Inscription::class);
        $data = $this->validated($request, ['niveau_id' => ['nullable', 'exists:niveaux,id']]);

        return $this->register($data, $request);
    }

    /** Inscription d'un candidat dont le test est passé : identité et niveau attribué repris du test. */
    public function fromTest(Request $request, TestNiveau $test): RedirectResponse
    {
        Gate::authorize('create', Inscription::class);
        abort_unless($test->status === TestStatus::Passe, 404);

        // Identité et niveau repris du test ; le niveau attribué peut être masqué sur le site : contrôle sans « publié ».
        $request->merge(['name' => $test->name, 'phone' => $test->phone ?? $request->input('phone'), 'email' => $test->email, 'niveau_id' => $test->niveau_id]);
        $data = $this->validated($request, ['niveau_id' => ['nullable', 'exists:niveaux,id']]);

        return $this->register($data, $request, $test);
    }

    /**
     * Personne déjà connue (compte apprenant, test de niveau, inscription précédente), retrouvée par son téléphone pour
     * remplir la fenêtre d'inscription toute seule.
     */
    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('create', Inscription::class);
        $phone = PhoneNumber::normalize((string) $request->query('phone'));
        if (! $phone) {
            return response()->json(['found' => false]);
        }
        $client = Client::where('phone', $phone)->first();
        $test = TestNiveau::with('niveau')->where('phone', $phone)->latest('id')->first();
        $previous = Inscription::where('phone', $phone)->latest('id')->first();
        $open = Inscription::where('phone', $phone)->whereIn('status', ['a_valider', 'validee', 'en_cours'])->latest('id')->first();
        if (! $client && ! $test && ! $previous) {
            return response()->json(['found' => false]);
        }
        $source = $previous ?? $client ?? $test;
        $sources = array_filter([
            $client ? 'compte apprenant' : null,
            $test ? 'test '.$test->number.($test->niveau ? ' (niveau '.$test->niveau->name.')' : '') : null,
            $previous ? 'inscription '.$previous->number : null,
        ]);

        return response()->json([
            'found' => true,
            'label' => 'Déjà connu : '.$source->name.' — '.implode(', ', $sources).'.',
            'warning' => $open ? "Attention : {$open->name} a déjà une inscription en cours ({$open->number}, reste ".Formule::money($open->balance()).').' : null,
            'fields' => array_filter([
                'name' => $source->name,
                'email' => $previous?->email ?? $client?->email ?? $test?->email,
                'gender' => ($previous?->gender ?? $client?->gender)?->value,
                'birth_date' => ($previous?->birth_date ?? $client?->birth_date)?->format('Y-m-d'),
                'address' => $previous?->address ?? $client?->address,
                'city' => $previous?->city ?? $client?->city,
                'niveau_id' => $test?->status === TestStatus::Passe ? $test->niveau_id : $previous?->niveau_id,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extraRules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $extraRules): array
    {
        // Paiement déduit du mode : présentiel → au centre, en ligne → mobile money (modifiable).
        if (! $request->filled('payment_choice')) {
            $request->merge(['payment_choice' => $request->input('mode') === FormationMode::EnLigne->value ? PaymentChoice::EnLigne->value : PaymentChoice::AuCentre->value]);
        }
        $rules = $extraRules + InscriptionRequest::baseRules() + [
            'pay_now' => ['nullable', 'boolean'],
            'pay_amount' => ['nullable', 'required_if:pay_now,1', 'integer', 'min:1', 'max:100000000'],
            'pay_method' => ['nullable', 'required_if:pay_now,1', Rule::enum(PaymentMethod::class)],
            'pay_reference' => ['nullable', 'string', 'max:100'],
        ];
        $request->validate($rules, (new InscriptionRequest)->messages() + [
            'pay_amount.required_if' => 'Indiquez le montant payé maintenant.',
        ], (new InscriptionRequest)->attributes() + ['pay_amount' => 'montant payé', 'pay_method' => 'moyen de paiement', 'pay_reference' => 'référence']);

        $data = $request->only(array_keys($rules));
        if ($request->boolean('pay_now')) {
            $method = PaymentMethod::from($data['pay_method']);
            if ($method->isMobileMoney() && blank($data['pay_reference'] ?? null)) {
                throw ValidationException::withMessages(['pay_reference' => 'Indiquez la référence de la transaction mobile money.']);
            }
            $quote = $this->service->quote(Formule::findOrFail($data['formule_id']), FormationMode::from($data['mode']));
            if ((int) $data['pay_amount'] > $quote['total_amount']) {
                throw ValidationException::withMessages(['pay_amount' => 'Le montant dépasse le total de la formation ('.Formule::money($quote['total_amount']).').']);
            }
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function register(array $data, Request $request, ?TestNiveau $test = null): RedirectResponse
    {
        $inscription = $this->service->create($data, $request->user(), $test);
        $message = 'Inscription enregistrée'.($test ? " depuis le test {$test->number}" : '')." : {$inscription->number}.";

        if (! empty($data['pay_now']) && $request->user()->can('paiements.record')) {
            $paiement = app(PaiementService::class)->record($inscription, [
                'amount' => (int) $data['pay_amount'], 'method' => $data['pay_method'], 'reference' => $data['pay_reference'] ?? null, 'period' => $inscription->billing === Billing::Mensuel ? 'Mois 1' : null,
            ], $request->user());
            $message = "Inscription {$inscription->number} enregistrée et payée : reçu {$paiement->number}.";
            session()->flash('receipt_id', $paiement->id);
        }

        return redirect()->route('inscriptions.show', $inscription)->with('status', $message);
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
