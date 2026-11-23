<?php

namespace App\Services\Attestations;

use App\Enums\InscriptionStatus;
use App\Mail\AttestationDelivree;
use App\Models\Inscription;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Numbering\SequenceService;
use App\Services\Paiements\ReceiptService;
use App\Support\SiteSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Attestation de fin de formation — « Certificate of Training », d'après le modèle fourni par le centre (2026-11-22),
 * rédigée en anglais : délivrée UNE fois pour une formation terminée (référence ETTC/CERT/AAAA/NNNN, date, auteur,
 * audit) ; une réimpression porte la mention « DUPLICATE » et est journalisée. QR : le certificat en clair, puis le lien
 * de vérification une fois le site en ligne (HTTPS), comme les reçus. Signataire, lieu, signature et cachet : Pages et
 * paramètres → Attestations.
 */
class AttestationService
{
    public const PREFIX = 'CERT';

    /** Niveaux usuels traduits pour le certificat (sinon le nom du niveau tel quel). */
    private const LEVELS = [
        'debutant' => 'Beginner', 'grand debutant' => 'Beginner', 'elementaire' => 'Elementary', 'pre intermediaire' => 'Pre-Intermediate',
        'intermediaire' => 'Intermediate', 'intermediaire superieur' => 'Upper-Intermediate', 'avance' => 'Advanced', 'perfectionnement' => 'Proficiency',
    ];

    private const NUMBERS = [1 => 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve'];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly SequenceService $sequences,
        private readonly SiteSettings $settings,
    ) {}

    /** Délivrance (première fois) : la formation doit être terminée. */
    public function issue(Inscription $inscription, User $actor): Inscription
    {
        if ($inscription->attestation_number) {
            throw ValidationException::withMessages(['attestation' => "L'attestation a déjà été délivrée le {$inscription->attestation_issued_at->format('d/m/Y')} : imprimez un duplicata."]);
        }
        if ($inscription->status !== InscriptionStatus::Terminee) {
            throw ValidationException::withMessages(['attestation' => 'L\'attestation se délivre quand la formation est terminée (bouton « Terminer la formation »).']);
        }

        $inscription = DB::transaction(function () use ($inscription, $actor) {
            Inscription::withoutActivityLog(fn () => $inscription->update([
                'attestation_number' => $this->reference(),
                'attestation_issued_at' => now(),
                'attestation_issued_by' => $actor->id,
            ]));
            $this->logger->log('inscriptions.attestation_issued', 'inscriptions', "Attestation délivrée — {$inscription->attestation_number} ({$inscription->name}, {$inscription->number})", $inscription,
                new: ['attestation_number' => $inscription->attestation_number], actor: $actor);

            return $inscription;
        });
        $this->mailLearner($inscription);

        return $inscription;
    }

    /**
     * Prévient l'apprenant : formation en ligne → attestation jointe (délivrance à distance) ; présentiel → à retirer au
     * centre. Un échec d'envoi ne bloque jamais la délivrance (journalisé).
     */
    public function mailLearner(Inscription $inscription): void
    {
        $email = $inscription->email ?: $inscription->client?->email;
        if (! $email) {
            return;
        }
        try {
            Mail::to($email, $inscription->name)->send(new AttestationDelivree($inscription));
            Inscription::withoutActivityLog(fn () => $inscription->forceFill(['attestation_sent_at' => now()])->saveQuietly());
        } catch (Throwable $e) {
            Log::error("Attestation {$inscription->attestation_number} non envoyée à {$email} : ".Str::limit($e->getMessage(), 300));
            $this->logger->log('inscriptions.attestation_mail_failed', 'inscriptions', "Envoi de l'attestation impossible — {$inscription->attestation_number}", $inscription, new: ['error' => Str::limit($e->getMessage(), 300)]);
        }
    }

    /** Réimpression : « DUPLICATA », journalisée. */
    public function duplicate(Inscription $inscription, User $actor): Inscription
    {
        if (! $inscription->attestation_number) {
            throw ValidationException::withMessages(['attestation' => 'L\'attestation n\'a pas encore été délivrée.']);
        }

        return DB::transaction(function () use ($inscription, $actor) {
            Inscription::withoutActivityLog(fn () => $inscription->increment('attestation_copies'));
            $this->logger->log('inscriptions.attestation_duplicated', 'inscriptions', "Duplicata d'attestation n° {$inscription->attestation_copies} — {$inscription->attestation_number} ({$inscription->name})", $inscription, actor: $actor);

            return $inscription;
        });
    }

    public function pdf(Inscription $inscription, bool $duplicate = false): \Barryvdh\DomPDF\PDF
    {
        return $this->render([$this->pageData($inscription, $duplicate)]);
    }

    /** Plusieurs attestations dans un seul PDF (un groupe), une page chacune. @param  iterable<Inscription>  $inscriptions */
    public function batchPdf(iterable $inscriptions): \Barryvdh\DomPDF\PDF
    {
        $pages = [];
        foreach ($inscriptions as $inscription) {
            $pages[] = $this->pageData($inscription, false);
        }

        return $this->render($pages);
    }

    /** @param  list<array<string, mixed>>  $pages */
    private function render(array $pages): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.attestation', ['pages' => $pages])->setPaper([0, 0, 841.89, 561.0]);
    }

    /** Données d'une page d'attestation. @return array<string, mixed> */
    public function pageData(Inscription $inscription, bool $duplicate = false): array
    {
        $inscription->loadMissing('niveau');
        $assets = app(AttestationAssets::class);
        static $background = null;
        $background ??= 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(AttestationAssets::background()));

        return [
            'inscription' => $inscription,
            'background' => $background,
            'signature' => $assets->dataUri($assets->signaturePath()),
            'signatureFit' => $assets->fitMm($assets->signaturePath(), 150, 64),
            'stamp' => $assets->dataUri($assets->stampPath()),
            'stampFit' => $assets->fitMm($assets->stampPath(), 140, 140),
            'signer' => $assets->name(),
            'signerTitle' => mb_strtoupper($assets->title()),
            'training' => $this->trainingName($inscription),
            'period' => $this->period($inscription),
            'duration' => $this->duration($inscription),
            'mode' => $inscription->mode->value === 'en_ligne' ? 'online' : 'in person at the center',
            'city' => trim($this->settings->get('attestation.city')) ?: 'Ségou, Mali',
            'issued' => ($inscription->attestation_issued_at ?? now())->locale('en')->isoFormat('MMMM Do, YYYY'),
            'qr' => $this->qr($inscription),
            'duplicate' => $duplicate,
        ];
    }

    public function filename(Inscription $inscription): string
    {
        return 'Certificate_'.str_replace('/', '-', (string) $inscription->attestation_number).'_'.Str::slug($inscription->name, '_').'.pdf';
    }

    /** « INTERMEDIATE ENGLISH TRAINING » ; formation personnalisée : « CUSTOMIZED ENGLISH TRAINING ». */
    public function trainingName(Inscription $inscription): string
    {
        if (! $inscription->formule_id) {
            return 'Customized English Training';
        }
        $level = $inscription->niveau?->name;
        $key = $level ? trim(preg_replace('/[^a-z]+/', ' ', Str::lower(Str::ascii($level)))) : null;

        return trim(($key ? (self::LEVELS[$key] ?? $level) : '').' English Training');
    }

    /** « from December 2025 to February 2026 ». */
    public function period(Inscription $inscription): string
    {
        $start = ($inscription->started_at ?? $inscription->validated_at ?? $inscription->created_at)->locale('en');
        $end = ($inscription->finished_at ?? now())->locale('en');

        return 'from '.$start->isoFormat('MMMM YYYY').' to '.$end->isoFormat('MMMM YYYY');
    }

    /** « three (3) months ». */
    public function duration(Inscription $inscription): string
    {
        $months = max(1, (int) $inscription->duration_months);
        $word = self::NUMBERS[$months] ?? (string) $months;

        return $word.' ('.$months.') '.($months > 1 ? 'months' : 'month');
    }

    public function verificationUrl(Inscription $inscription): string
    {
        return route('site.certificat.verify', ['inscription' => $inscription->id, 'key' => $this->key($inscription)]);
    }

    public function matches(Inscription $inscription, string $key): bool
    {
        return $inscription->attestation_number !== null && hash_equals($this->key($inscription), $key);
    }

    /** Référence « ETTC/CERT/2026/0001 » (numérotation annuelle sous verrou). */
    private function reference(): string
    {
        [, $year, $number] = explode('-', $this->sequences->next(self::PREFIX));

        return Str::upper(Str::ascii($this->settings->get('company.short_name') ?: 'ETTC')).'/CERT/'.$year.'/'.$number;
    }

    private function key(Inscription $inscription): string
    {
        return substr(hash_hmac('sha256', $inscription->attestation_number.'|'.$inscription->id.'|'.$inscription->name, (string) config('app.key')), 0, 20);
    }

    /** QR : le certificat en clair, puis le lien de vérification une fois le site en ligne (comme les reçus). */
    private function qr(Inscription $inscription): string
    {
        $lines = [
            'Certificate '.$inscription->attestation_number,
            $inscription->name,
            $this->trainingName($inscription).' — '.$this->period($inscription),
            'Issued by '.$this->settings->get('company.name'),
        ];
        if ($inscription->exists && app(ReceiptService::class)->isOnline()) {
            $lines[] = 'Verify: '.$this->verificationUrl($inscription);
        }
        $qr = (new Builder)->build(writer: new PngWriter, data: implode("\n", $lines), size: 220, margin: 4);

        return 'data:image/png;base64,'.base64_encode($qr->getString());
    }
}
