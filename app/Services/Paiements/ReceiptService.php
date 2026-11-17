<?php

namespace App\Services\Paiements;

use App\Models\Paiement;
use App\Support\SiteSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Reçus de paiement en PDF (cahier §3.8 C), comme KalanNet : format A5 (feuille) et ticket thermique 80 mm. Chaque reçu
 * porte un QR code vers la page publique de vérification (empreinte liée au numéro et au montant : un reçu modifié à la
 * main ne passe pas la vérification).
 */
class ReceiptService
{
    public const FORMATS = ['a5' => 'Reçu A5', 'thermique' => 'Ticket thermique 80 mm'];

    public function __construct(private readonly SiteSettings $settings) {}

    public function pdf(Paiement $paiement, string $format): \Barryvdh\DomPDF\PDF
    {
        $paiement->loadMissing(['inscription.niveau', 'recorder', 'confirmer']);
        $thermal = $format === 'thermique';
        $pdf = Pdf::loadView($thermal ? 'pdf.recu-thermique' : 'pdf.recu-a5', [
            'paiement' => $paiement,
            'inscription' => $paiement->inscription,
            'site' => $this->settings,
            'logo' => $this->logo(),
            'qr' => $this->qr($this->verificationUrl($paiement), $thermal ? 150 : 170),
            'verifyUrl' => $this->verificationUrl($paiement),
        ]);

        // 80 mm = 226,77 pt ; hauteur généreuse (le papier thermique est coupé après l'impression).
        return $thermal ? $pdf->setPaper([0, 0, 226.77, 590], 'portrait') : $pdf->setPaper('a5', 'portrait');
    }

    public function filename(Paiement $paiement, string $format): string
    {
        return ($format === 'thermique' ? 'Ticket_' : 'Recu_').$paiement->number.'.pdf';
    }

    public function verificationUrl(Paiement $paiement): string
    {
        return route('site.recu.verify', ['number' => $paiement->number, 'key' => $this->key($paiement)]);
    }

    public function matches(Paiement $paiement, string $key): bool
    {
        return hash_equals($this->key($paiement), $key);
    }

    private function key(Paiement $paiement): string
    {
        return substr(hash_hmac('sha256', $paiement->number.'|'.$paiement->amount.'|'.$paiement->inscription_id, (string) config('app.key')), 0, 20);
    }

    private function qr(string $data, int $size): string
    {
        $qr = (new Builder)->build(writer: new PngWriter, data: $data, size: $size, margin: 4);

        return 'data:image/png;base64,'.base64_encode($qr->getString());
    }

    /** Logo local en data URI (dompdf ne charge aucune ressource distante). */
    private function logo(): ?string
    {
        $path = public_path('assets/site/img/logo-ettc.png');

        return is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }
}
