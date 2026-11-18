<?php

namespace App\Services\System;

use App\Models\Formule;
use App\Models\Niveau;
use App\Models\User;
use App\Services\Paiements\Gateway\GatewayManager;
use App\Services\Paiements\PaiementService;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\File;

/**
 * « Prêt pour la mise en ligne ? » (cahier §12, phase 9) : chaque point est OK, À REVOIR (bloquant) ou CONSEIL, avec ce
 * qu'il faut faire. Affiché sur l'écran Système et par `php artisan site:verifier`.
 */
class LaunchChecklist
{
    public const OK = 'ok';

    public const BLOCKING = 'bloquant';

    public const ADVICE = 'conseil';

    public function __construct(
        private readonly SiteSettings $settings,
        private readonly PaiementService $payments,
        private readonly GatewayManager $gateways,
        private readonly TestDataCleaner $testData,
    ) {}

    /** @return list<array{label: string, state: string, help: string}> */
    public function items(): array
    {
        $production = app()->isProduction();
        $mailer = (string) config('mail.default');
        $gateway = (string) config('services.paiement.gateway');
        $testData = $this->testData->count();
        $superadmins = User::role('superadmin')->get();

        return [
            $this->item('Mode production (APP_ENV=production, APP_DEBUG=false)', $production && ! config('app.debug'), 'Dans .env : APP_ENV=production et APP_DEBUG=false, puis php artisan config:cache.'),
            $this->item('Adresse du site en HTTPS (APP_URL)', str_starts_with((string) config('app.url'), 'https://'), 'Dans .env : APP_URL=https://… (certificat SSL actif chez l\'hébergeur).'),
            $this->item('Envoi des e-mails configuré', ! in_array($mailer, ['log', 'array'], true), 'Dans .env : MAIL_MAILER=smtp et les MAIL_* fournis par l\'hébergeur ; tester avec une demande de contact.'),
            $this->item('Destinataire des alertes de l\'équipe', (bool) $this->settings->notificationEmail(), 'SITE_NOTIFICATION_EMAIL dans .env, ou l\'e-mail de contact dans Pages et paramètres.'),
            $this->item('Lien des images (php artisan storage:link)', File::exists(public_path('storage')), 'Lancer php artisan storage:link sur le serveur.'),
            $this->item('Mot de passe du superadmin personnalisé', $superadmins->isNotEmpty() && $superadmins->every(fn (User $u) => ! $u->must_change_password), 'Se connecter une première fois : le site demande de choisir un nouveau mot de passe.'),
            $this->item('Données de test supprimées', $testData === [], 'Lancer php artisan donnees-test:supprimer (retire tout ce qui est marqué « test »).', $testData ? ' — reste : '.collect($testData)->map(fn ($n, $label) => "{$label} {$n}")->implode(', ') : ''),
            $this->item('Paiement simulé désactivé', $gateway !== 'simulation', 'Dans .env : PAYMENT_GATEWAY vide (déclaration) ou cinetpay (paiement automatique).'),
            $this->item('Numéros mobile money du centre', $this->payments->merchantNumbers() !== [], 'Paiements → Moyens de paiement : Orange Money, Wave, Moov Money.', advice: true),
            $this->item('Paiement automatique (agrégateur agréé BCEAO)', $this->gateways->active() !== null && $gateway !== 'simulation', 'Facultatif : compte marchand du centre, puis clés dans .env (voir Paiements).', advice: true),
            $this->item('Formules et tarifs publiés', Formule::published()->exists() && Niveau::where('is_published', true)->exists(), 'Formations → Formules et tarifs, Niveaux.'),
            $this->item('Logo du centre', $this->settings->get('site.logo') !== '', 'Pages et paramètres → Entreprise → Logo (sinon logo fourni avec le site).', advice: true),
            $this->item('Dates et tarif de l\'excursion', $this->settings->get('excursion.dates') !== '' && $this->settings->get('excursion.price') !== '', 'Pages et paramètres → Excursion au Ghana (sinon « sur demande »).', advice: true),
            ['label' => 'Tâche planifiée (cron)', 'state' => self::ADVICE, 'help' => 'Chez l\'hébergeur, chaque minute : php artisan schedule:run (sauvegardes, e-mails, paiements en ligne en attente).'],
        ];
    }

    /** @return array{ok: int, blocking: int, advice: int} */
    public function summary(): array
    {
        $states = collect($this->items())->countBy('state');

        return ['ok' => $states[self::OK] ?? 0, 'blocking' => $states[self::BLOCKING] ?? 0, 'advice' => $states[self::ADVICE] ?? 0];
    }

    /** @return array{label: string, state: string, help: string} */
    private function item(string $label, bool $ok, string $help, string $detail = '', bool $advice = false): array
    {
        return ['label' => $label.$detail, 'state' => $ok ? self::OK : ($advice ? self::ADVICE : self::BLOCKING), 'help' => $help];
    }
}
