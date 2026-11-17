<?php

namespace Tests\Feature\Site;

use App\Models\Article;
use App\Models\Client;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accès à l'espace client pour un public peu habitué : bouton « Mon compte client » libellé dans l'en-tête, et
 * réalisations et matériels de CSB qui défilent derrière les pages de connexion, d'inscription et du conseiller.
 */
class ClientAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_header_shows_an_explicit_account_button(): void
    {
        $this->get('/')->assertOk()->assertSee('class="ettc-account"', false)->assertSee('Mon espace')->assertSee('Se connecter / S&#039;inscrire', false);

        $client = Client::factory()->create(['name' => 'Moussa Diarra']);
        $this->actingAs($client, 'client')->get('/')->assertOk()->assertSee('Bonjour Moussa');
    }

    public function test_news_visuals_scroll_behind_the_access_pages(): void
    {
        $this->get('/espace-client/connexion')->assertOk()->assertDontSee('auth-showcase-row', false);

        Article::factory()->create(['title' => 'Nouvelle session', 'cover_media_id' => Media::factory()->create()->id, 'is_published' => true, 'published_at' => now()->subDay()]);
        Article::factory()->create(['title' => 'Brouillon Secret', 'cover_media_id' => Media::factory()->create()->id, 'is_published' => false]);
        Article::factory()->create(['title' => 'Sans Photo', 'is_published' => true, 'published_at' => now()->subDay()]);

        foreach (['/espace-client/connexion', '/espace-client/inscription'] as $page) {
            $this->get($page)->assertOk()->assertSee('auth-showcase-row', false)->assertSee('Nouvelle session')
                ->assertDontSee('Brouillon Secret')->assertDontSee('Sans Photo');
        }
    }
}
