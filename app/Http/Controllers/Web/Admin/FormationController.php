<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\FormuleRequest;
use App\Http\Requests\Web\Admin\NiveauRequest;
use App\Models\Formule;
use App\Models\Niveau;
use Illuminate\Http\RedirectResponse;
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

        return view('formations.formules', ['formules' => Formule::query()->ordered()->get()]);
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
