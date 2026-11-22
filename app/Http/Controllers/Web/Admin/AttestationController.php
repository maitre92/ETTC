<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\FormationMode;
use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Services\Attestations\AttestationAssets;
use App\Services\Attestations\AttestationService;
use App\Services\Audit\ActivityLogger;
use App\Support\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Réglages de l'attestation (demande du centre, 2026-11-22) : signataire et lieu ; signature déposée ou tracée à
 * l'écran ; sceau déposé. Chaque image est traitée (fond détouré, encre renforcée) et montrée en aperçu SUR
 * l'attestation avant d'être enregistrée (image traitée gardée en session jusqu'à « Enregistrer »).
 */
class AttestationController extends Controller
{
    public function __construct(private readonly AttestationAssets $assets) {}

    public function edit(SiteSettings $settings): View
    {
        return view('attestations.edit', [
            'settings' => $settings,
            'preview' => $this->assets->preview(),
            'signature' => $this->assets->dataUri($this->assets->signaturePath()),
            'stamp' => $this->assets->dataUri($this->assets->stampPath()),
            'custom' => ['signature' => $this->assets->isCustom('signature'), 'stamp' => $this->assets->isCustom('stamp')],
        ]);
    }

    public function signer(Request $request, SiteSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'director_name' => ['required', 'string', 'max:60'],
            'director_title' => ['required', 'string', 'max:40'],
            'city' => ['required', 'string', 'max:60'],
        ], [], ['director_name' => 'nom du signataire', 'director_title' => 'titre', 'city' => 'lieu']);
        $values = ['attestation.director_name' => trim($data['director_name']), 'attestation.director_title' => trim($data['director_title']), 'attestation.city' => trim($data['city'])];
        $settings->save($values);
        $logger->log('attestations.signer_updated', 'attestations', 'Signataire de l\'attestation : '.$values['attestation.director_name'].' ('.$values['attestation.director_title'].')', new: $values);

        return redirect()->route('attestations.edit')->with('status', 'Signataire enregistré.');
    }

    /** Traite l'image proposée et renvoie : l'image traitée, l'aperçu de l'attestation entière et le zoom. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(AttestationAssets::KINDS))],
            'file' => ['nullable', 'required_without:drawing', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'drawing' => ['nullable', 'required_without:file', 'string'],
            'ink' => ['nullable', Rule::in(array_keys(AttestationAssets::INKS))],
            'strong' => ['nullable', 'boolean'],
        ], ['file.required_without' => 'Choisissez une image ou tracez la signature.', 'drawing.required_without' => 'Choisissez une image ou tracez la signature.'], ['file' => 'image']);
        $kind = $data['kind'];
        $png = ! empty($data['drawing']) && $kind === 'signature'
            ? $this->assets->fromDrawing($data['drawing'])
            : $this->assets->process((string) file_get_contents($request->file('file')->getRealPath()), $kind, $data['ink'] ?? 'original', $request->boolean('strong'));
        $request->session()->put("attestation_pending.{$kind}", base64_encode($png));

        return response()->json(['processed' => 'data:image/png;base64,'.base64_encode($png)] + $this->assets->preview([$kind => $png]));
    }

    /** Enregistre l'image traitée montrée en aperçu. */
    public function save(Request $request, string $kind): RedirectResponse
    {
        $pending = $request->session()->pull("attestation_pending.{$kind}");
        if (! $pending) {
            return redirect()->route('attestations.edit')->withErrors(['file' => 'Faites d\'abord l\'aperçu, puis enregistrez.']);
        }
        $this->assets->save($kind, (string) base64_decode($pending), $request->user());

        return redirect()->route('attestations.edit')->with('status', AttestationAssets::KINDS[$kind].' enregistré(e) : les prochaines attestations l\'utilisent.');
    }

    public function reset(Request $request, string $kind): RedirectResponse
    {
        $this->assets->reset($kind, $request->user());

        return redirect()->route('attestations.edit')->with('status', AttestationAssets::KINDS[$kind].' : retour à celui / celle du modèle.');
    }

    /** Attestation d'exemple (rien n'est enregistré), pour vérifier le rendu final. */
    public function sample(AttestationService $attestations): Response
    {
        $inscription = new Inscription(['name' => 'Amos Dembele', 'formule_id' => 1, 'duration_months' => 3, 'mode' => FormationMode::EnLigne, 'formule_name' => 'Formule 3 mois', 'attestation_number' => 'ETTC/CERT/'.now()->format('Y').'/0000']);
        $inscription->started_at = now()->subMonths(3);
        $inscription->finished_at = now();
        $inscription->attestation_issued_at = now();
        $inscription->created_at = now();
        $inscription->setRelation('niveau', new Niveau(['name' => 'Intermédiaire']));

        return $attestations->pdf($inscription)->stream('Attestation-exemple.pdf');
    }
}
