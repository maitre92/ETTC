<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\FormuleRequest;
use App\Http\Requests\Web\Admin\NiveauRequest;
use App\Http\Requests\Web\Admin\PromotionRequest;
use App\Models\Formule;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Services\Audit\ActivityLogger;
use App\Services\Formations\Tarification;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Formations et tarifs (cahier ETTC §3.2, §3.3, §5) : niveaux, formules avec leurs tarifs par mode (le test de niveau,
 * lui, est gratuit : décision du centre, 2026-11-17). Listes au gabarit du thème, création et modification en modale, suppression en soft delete. Les tarifs se
 * modifient à tout moment : chaque changement est audité (LogsActivity : ancien et nouveau montant).
 */
class FormationController extends Controller
{
    public function formules(): View
    {
        Gate::authorize('viewAny', Formule::class);

        return view('formations.formules', [
            'formules' => Formule::query()->ordered()->get(),
            'fee' => app(Tarification::class)->registrationFee(),
        ]);
    }

    /** Frais d'inscription (une fois par inscription, avec le premier paiement) : 0 = aucun. Audité. */
    public function saveFee(Request $request, SiteSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        $fee = (int) preg_replace('/\D/', '', (string) $request->input('registration_fee'));
        $request->merge(['registration_fee' => $fee]);
        $request->validate(['registration_fee' => ['required', 'integer', 'min:0', 'max:10000000']], [], ['registration_fee' => 'frais d\'inscription']);
        $old = app(Tarification::class)->registrationFee();
        $settings->save(['formation.registration_fee' => (string) $fee]);
        $logger->log('formations.fee_updated', 'formations', 'Frais d\'inscription : '.Formule::money($old).' → '.Formule::money($fee), old: ['registration_fee' => $old], new: ['registration_fee' => $fee]);

        return redirect()->route('formations.formules.index')->with('status', $fee > 0 ? 'Frais d\'inscription : '.Formule::money($fee).', ajoutés aux nouvelles inscriptions.' : 'Plus de frais d\'inscription pour les nouvelles inscriptions.');
    }

    public function promotions(): View
    {
        Gate::authorize('viewAny', Promotion::class);

        return view('formations.promotions', [
            'promotions' => Promotion::with('formule')->latest('id')->get(),
            'formules' => Formule::query()->ordered()->get(),
        ]);
    }

    public function storePromotion(PromotionRequest $request): RedirectResponse
    {
        $promotion = Promotion::create($request->validated());

        return redirect()->route('formations.promotions.index')->with('status', "Promotion « {$promotion->name} » créée".($promotion->code ? " : code {$promotion->code}." : ' : elle s\'applique toute seule pendant sa période.'));
    }

    public function updatePromotion(PromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $promotion->update($request->validated());

        return redirect()->route('formations.promotions.index')->with('status', "Promotion « {$promotion->name} » modifiée.");
    }

    public function destroyPromotion(Promotion $promotion): RedirectResponse
    {
        Gate::authorize('delete', $promotion);
        $promotion->delete();

        return redirect()->route('formations.promotions.index')->with('status', "Promotion « {$promotion->name} » supprimée (les inscriptions déjà faites gardent leur réduction).");
    }

    public function storeFormule(FormuleRequest $request): RedirectResponse
    {
        $formule = Formule::create($request->validated());

        return redirect()->route('formations.formules.index')->with('status', "Formule « {$formule->name} » créée.");
    }

    public function updateFormule(FormuleRequest $request, Formule $formule): RedirectResponse
    {
        $formule->update($request->validated());

        return redirect()->route('formations.formules.index')->with('status', "Formule « {$formule->name} » modifiée : les nouveaux tarifs sont en ligne.");
    }

    public function destroyFormule(Formule $formule): RedirectResponse
    {
        Gate::authorize('delete', $formule);

        $formule->delete();

        return redirect()->route('formations.formules.index')->with('status', "Formule « {$formule->name} » supprimée.");
    }

    public function niveaux(): View
    {
        Gate::authorize('viewAny', Niveau::class);

        return view('formations.niveaux', ['niveaux' => Niveau::query()->ordered()->get()]);
    }

    public function storeNiveau(NiveauRequest $request): RedirectResponse
    {
        $niveau = Niveau::create($request->validated());

        return redirect()->route('formations.niveaux.index')->with('status', "Niveau « {$niveau->name} » créé.");
    }

    public function updateNiveau(NiveauRequest $request, Niveau $niveau): RedirectResponse
    {
        $niveau->update($request->validated());

        return redirect()->route('formations.niveaux.index')->with('status', "Niveau « {$niveau->name} » modifié.");
    }

    public function destroyNiveau(Niveau $niveau): RedirectResponse
    {
        Gate::authorize('delete', $niveau);

        $niveau->delete();

        return redirect()->route('formations.niveaux.index')->with('status', "Niveau « {$niveau->name} » supprimé.");
    }
}
