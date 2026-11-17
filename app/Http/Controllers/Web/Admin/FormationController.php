<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\FormuleRequest;
use App\Http\Requests\Web\Admin\NiveauRequest;
use App\Models\Formule;
use App\Models\Niveau;
use App\Services\Audit\ActivityLogger;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Formations et tarifs (cahier ETTC §3.2, §3.3, §5) : niveaux, formules avec leurs tarifs par mode, frais du test de
 * niveau. Listes au gabarit du thème, création et modification en modale, suppression en soft delete. Les tarifs se
 * modifient à tout moment : chaque changement est audité (LogsActivity : ancien et nouveau montant).
 */
class FormationController extends Controller
{
    public function formules(SiteSettings $settings): View
    {
        Gate::authorize('viewAny', Formule::class);

        return view('formations.formules', [
            'formules' => Formule::query()->ordered()->get(),
            'testFee' => (int) $settings->get('tarifs.test_niveau'),
        ]);
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

    /** Frais du test de niveau (cahier §3.4 : 5 000 FCFA, hors commission) : réglage audité, modifiable à tout moment. */
    public function updateTestFee(Request $request, SiteSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', new Formule);

        $request->merge(['test_fee' => preg_replace('/[^\d]/u', '', (string) $request->input('test_fee'))]);
        $validated = $request->validate(
            ['test_fee' => ['required', 'integer', 'min:0', 'max:10000000']],
            [],
            ['test_fee' => 'frais du test de niveau'],
        );

        $old = (int) $settings->get('tarifs.test_niveau');
        $new = (int) $validated['test_fee'];
        if ($old !== $new) {
            $settings->save(['tarifs.test_niveau' => (string) $new]);
            $logger->log('formations.test_fee_changed', 'formations', 'Frais du test de niveau : '.Formule::money($old).' → '.Formule::money($new), null, ['test_fee' => $old], ['test_fee' => $new]);
        }

        return redirect()->route('formations.formules.index')->with('status', 'Frais du test de niveau : '.Formule::money($new).'.');
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
