<?php

namespace Database\Seeders;

use App\Enums\DemandeStatus;
use App\Enums\DemandeType;
use App\Enums\NeedType;
use App\Enums\PaymentMethod;
use App\Enums\TestMode;
use App\Enums\TestStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\Demande;
use App\Models\Niveau;
use App\Models\Temoignage;
use App\Models\TestNiveau;
use App\Models\User;
use App\Services\Numbering\SequenceService;
use App\Support\SiteSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Données de TEST (pour remplir les écrans pendant le développement) : actualités, témoignages, comptes apprenants et
 * demandes. Tout est FICTIF : à vider avant la mise en ligne.
 * Jamais en production (refus). Idempotent : relancer ne crée pas de doublon.
 *
 *   php artisan db:seed --class=DonneesTestSeeder
 */
class DonneesTestSeeder extends Seeder
{
    /** Domaine des adresses de test (permet de les retrouver et de les supprimer). */
    public const EMAIL_DOMAIN = 'test.ettc.ml';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DonneesTestSeeder : données fictives, interdites en production.');
        }

        // Aucun e-mail réel pendant l'amorçage.
        Mail::fake();

        $this->articles();
        $this->temoignages();
        $password = $this->clients();
        $this->demandes();
        $this->tests();

        $this->command?->info('Données de test créées. Apprenants : *@'.self::EMAIL_DOMAIN.($password ? ", mot de passe : {$password}" : ' (mot de passe inchangé)'));
    }

    /** Candidats fictifs au test de niveau : un à planifier, un planifié payé, un passé avec résultat. */
    private function tests(): void
    {
        if (TestNiveau::where('email', 'like', '%@'.self::EMAIL_DOMAIN)->exists()) {
            return;
        }
        $agent = User::orderBy('id')->value('id');
        $niveau = Niveau::ordered()->first();
        $sequences = app(SequenceService::class);
        $fee = (int) app(SiteSettings::class)->get('tarifs.test_niveau');
        $items = [
            ['name' => 'Awa Diakité', 'email' => 'awa@'.self::EMAIL_DOMAIN, 'phone' => '+22370000001', 'test_mode' => TestMode::Distance, 'preferred_slot' => 'Samedi matin (test)'],
            ['name' => 'Moussa Keïta', 'email' => 'moussa@'.self::EMAIL_DOMAIN, 'phone' => '+22370000002', 'status' => TestStatus::Planifie, 'scheduled_at' => now()->addDay()->setTime(9, 0), 'location' => 'Salle 1 (test)',
                'paid_at' => now()->subDay(), 'payment_method' => PaymentMethod::Especes, 'payment_recorded_by' => $agent],
            ['name' => 'Fatoumata Sangaré', 'email' => 'fatoumata@'.self::EMAIL_DOMAIN, 'phone' => '+22370000003', 'status' => TestStatus::Passe, 'scheduled_at' => now()->subDays(3)->setTime(10, 0),
                'paid_at' => now()->subDays(4), 'payment_method' => PaymentMethod::OrangeMoney, 'payment_reference' => 'OM-TEST-0001', 'payment_recorded_by' => $agent,
                'niveau_id' => $niveau?->id, 'score' => '28 / 50', 'result_notes' => 'Bonne base à l\'oral (test).', 'result_at' => now()->subDays(3), 'result_by' => $agent],
        ];
        foreach ($items as $item) {
            TestNiveau::create($item + ['number' => $sequences->next('TST'), 'status' => TestStatus::Demande, 'source' => 'site', 'fee' => $fee, 'consent_at' => now(), 'test_mode' => TestMode::Centre]);
        }
    }

    private function articles(): void
    {
        $category = fn (string $name) => Category::where('type', 'article')->where('name', $name)->value('id');
        $author = User::orderBy('id')->value('id');

        $items = [
            ['Ouverture des inscriptions de la prochaine session (test)', 'Sessions et inscriptions', 3,
                'Les inscriptions sont ouvertes pour tous les niveaux, en présentiel et en ligne.',
                "Les inscriptions de la prochaine session sont ouvertes.\n\n## Comment s'inscrire\n\n1. Passez le **test de niveau** ;\n2. choisissez votre formule (2 ou 3 mois, en présentiel ou en ligne) ;\n3. réglez en ligne ou au centre.\n\nTexte FICTIF de test."],
            ['Club de conversation du samedi (test)', 'Événements', 8,
                'Un moment d\'échange en anglais ouvert aux apprenants de tous niveaux.',
                "Chaque samedi, le centre organise un **club de conversation** pour pratiquer l'anglais dans une ambiance détendue.\n\nTexte FICTIF de test."],
            ['Excursion linguistique au Ghana : informations à venir (test)', 'Excursions', 15,
                'Pratiquer l\'anglais en situation réelle : le programme de l\'excursion sera bientôt publié.',
                "Le centre prépare une **excursion au Ghana** pour pratiquer l'anglais sur le terrain.\n\nDates, programme et tarif seront communiqués après validation. Texte FICTIF de test."],
        ];
        foreach ($items as [$title, $categoryName, $daysAgo, $excerpt, $body]) {
            $slug = Str::slug($title);
            if (Article::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }
            Article::create([
                'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'body' => $body, 'category_id' => $category($categoryName),
                'author_id' => $author, 'is_published' => true, 'published_at' => now()->subDays($daysAgo),
            ]);
        }
    }

    private function temoignages(): void
    {
        $items = [
            ['Aminata Coulibaly', 'Étudiante', null, 'Après trois mois au niveau intermédiaire, je comprends mes cours en anglais et j\'ose enfin parler. (témoignage fictif de test)'],
            ['Oumar Traoré', 'Commerçant', 'Ségou (test)', 'Les cours en ligne avec le formateur sont vivants : on parle beaucoup, ce n\'est pas une vidéo à regarder seul. (témoignage fictif de test)'],
            ['Seydou Diarra', 'Responsable administratif', 'ONG locale (test)', 'Le centre a traduit nos rapports rapidement et avec soin. (témoignage fictif de test)'],
        ];
        foreach ($items as $order => [$name, $title, $organization, $content]) {
            Temoignage::firstOrCreate(['author_name' => $name], [
                'author_title' => $title, 'organization' => $organization, 'content' => $content, 'sort_order' => $order, 'is_published' => true,
            ]);
        }
    }

    /** Comptes apprenants de test ; renvoie le mot de passe généré (null si les comptes existaient déjà). */
    private function clients(): ?string
    {
        $password = null;
        $items = [
            ['Awa Diakité', null, 'awa', '+22370000001', 'Ségou'],
            ['Moussa Keïta', null, 'moussa', '+22370000002', 'Bamako'],
            ['Fatoumata Sangaré', 'ONG locale (test)', 'fatoumata', '+22370000003', 'Ségou'],
        ];
        foreach ($items as [$name, $organization, $local, $phone, $city]) {
            $email = $local.'@'.self::EMAIL_DOMAIN;
            if (Client::withTrashed()->where('email', $email)->exists()) {
                continue;
            }
            $password ??= 'Test-'.Str::upper(Str::random(6)).'-'.random_int(10, 99);
            Client::create([
                'name' => $name, 'organization' => $organization, 'email' => $email, 'phone' => $phone, 'city' => $city,
                'password' => $password, 'is_active' => true, 'email_verified_at' => now(),
            ]);
        }

        return $password;
    }

    private function demandes(): void
    {
        if (Demande::where('email', 'like', '%@'.self::EMAIL_DOMAIN)->exists()) {
            return;
        }
        $client = fn (string $local) => Client::where('email', $local.'@'.self::EMAIL_DOMAIN)->value('id');
        $agent = User::orderBy('id')->value('id');

        $items = [
            ['type' => DemandeType::Devis, 'status' => DemandeStatus::Nouvelle, 'name' => 'Fatoumata Sangaré', 'organization' => 'ONG locale (test)', 'email' => 'fatoumata@'.self::EMAIL_DOMAIN, 'phone' => '+22370000003', 'client_id' => $client('fatoumata'),
                'need_type' => NeedType::Traduction, 'message' => 'Traduction anglais → français d\'un rapport annuel de 20 pages.', 'deadline' => 'Dans deux semaines', 'days' => 0],
            ['type' => DemandeType::Devis, 'status' => DemandeStatus::EnCours, 'name' => 'Ibrahim Touré', 'email' => 'ibrahim@'.self::EMAIL_DOMAIN, 'phone' => '+22370000004',
                'need_type' => NeedType::FormationPersonnalisee, 'message' => 'Préparer un entretien d\'embauche en anglais dans le secteur minier.', 'days' => 2, 'assigned_to' => $agent],
            ['type' => DemandeType::Devis, 'status' => DemandeStatus::DevisEnvoye, 'name' => 'Moussa Keïta', 'email' => 'moussa@'.self::EMAIL_DOMAIN, 'phone' => '+22370000002', 'client_id' => $client('moussa'),
                'need_type' => NeedType::Excursion, 'message' => 'Quelles sont les dates et le tarif de l\'excursion au Ghana ?', 'days' => 5, 'assigned_to' => $agent],
            ['type' => DemandeType::Contact, 'status' => DemandeStatus::Gagnee, 'name' => 'Awa Diakité', 'email' => 'awa@'.self::EMAIL_DOMAIN, 'phone' => '+22370000001', 'client_id' => $client('awa'),
                'subject' => 'Test de niveau', 'message' => 'Quand puis-je passer le test de niveau avant la prochaine session ?', 'days' => 9, 'assigned_to' => $agent],
            ['type' => DemandeType::Contact, 'status' => DemandeStatus::Perdue, 'name' => 'Bakary Samaké', 'email' => 'bakary@'.self::EMAIL_DOMAIN, 'phone' => '+22370000005',
                'subject' => 'Cours du soir', 'message' => 'Proposez-vous des cours le soir après 20 h ?', 'days' => 20, 'closed_reason' => 'Horaires non disponibles (test)'],
        ];
        foreach ($items as $item) {
            $days = $item['days'];
            unset($item['days']);
            $demande = Demande::create($item + ['consent_at' => now()->subDays($days), 'source_url' => url('/'), 'ip_address' => '127.0.0.1', 'status_changed_at' => now()->subDays($days)]);
            $demande->forceFill(['created_at' => now()->subDays($days)->subHours(3)])->saveQuietly();
        }
    }
}
