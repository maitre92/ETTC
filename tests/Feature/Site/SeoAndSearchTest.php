<?php

namespace Tests\Feature\Site;

use App\Models\Article;
use App\Models\Prestation;
use App\Models\Redirection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Référencement (cahier §11.1, REC-10) et recherche : sitemap, robots.txt, redirections des adresses modifiées,
 * recherche et suggestions sur les seuls contenus publiés.
 */
class SeoAndSearchTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    public function test_the_sitemap_lists_public_pages_and_published_contents_only(): void
    {
        Article::factory()->create(['slug' => 'conseil-du-jour']);
        Article::factory()->draft()->create(['slug' => 'secret']);
        Article::factory()->create(['slug' => 'futur', 'published_at' => now()->addWeek()]);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

        $this->assertStringContainsString('/services</loc>', $xml);
        $this->assertStringContainsString('/actualites/conseil-du-jour</loc>', $xml);
        $this->assertStringContainsString('/contact</loc>', $xml);
        $this->assertStringNotContainsString('secret', $xml);
        $this->assertStringNotContainsString('futur', $xml);
        $this->assertStringNotContainsString('/admin', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_robots_txt_hides_the_administration_and_any_non_production_site(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');

        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin/')->assertSee('Sitemap: ');
    }

    public function test_a_changed_address_redirects_permanently_and_chains_are_shortened(): void
    {
        $this->syncPermissions();
        $article = Article::factory()->create(['title' => 'Rentrée', 'slug' => 'rentree']);

        $article->update(['slug' => 'rentree-2026']);
        $article->update(['slug' => 'rentree-octobre']);

        $this->get('/actualites/rentree')->assertRedirect('/actualites/rentree-octobre')->assertStatus(301);
        $this->get('/actualites/rentree-2026')->assertRedirect('/actualites/rentree-octobre');
        $this->get('/actualites/rentree-octobre')->assertOk();
        $this->assertSame(1, Redirection::where('from_path', '/actualites/rentree')->value('hits'));

        // Retour à une ancienne adresse : elle ne redirige plus.
        $article->update(['slug' => 'rentree-2026']);
        $this->get('/actualites/rentree-2026')->assertOk();
        $this->get('/actualites/rentree-octobre')->assertRedirect('/actualites/rentree-2026');
    }

    public function test_an_unknown_address_is_a_useful_404(): void
    {
        $this->get('/ancienne-page-inconnue')->assertNotFound()->assertSee('Page introuvable');
        $this->actingAs(User::factory()->create())->get('/admin/inconnu')->assertNotFound()->assertSee('Retour à l\'administration', false);
    }

    public function test_search_and_live_suggestions_cover_published_contents(): void
    {
        Prestation::factory()->create(['title' => 'Traduction professionnelle', 'summary' => 'Traduction de vos documents.', 'is_published' => true]);
        Prestation::factory()->create(['title' => 'Traduction secrète', 'summary' => 'Brouillon de traduction.', 'is_published' => false]);
        Article::factory()->create(['title' => 'Ouverture des inscriptions', 'body' => 'Traduction et formations : la session commence.']);

        $this->get('/recherche?q=traduction')->assertOk()->assertSee('Traduction professionnelle')->assertSee('Ouverture des inscriptions')->assertDontSee('Traduction secrète')->assertSee('noindex', false);
        $this->get('/recherche?q=introuvable')->assertOk()->assertSee('Aucun résultat');

        $this->getJson('/recherche/suggestions?q=tr')->assertOk()->assertJsonPath('groups.0.label', 'Formations et services')->assertJsonPath('groups.0.items.0.title', 'Traduction professionnelle');
        $this->getJson('/recherche/suggestions?q=s')->assertOk()->assertJsonPath('groups', []);
        $this->getJson('/recherche/suggestions?q=%25')->assertOk()->assertJsonPath('groups', []);
    }
}
