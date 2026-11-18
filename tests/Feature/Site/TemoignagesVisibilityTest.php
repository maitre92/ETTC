<?php

namespace Tests\Feature\Site;

use App\Models\Temoignage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Où le public voit les témoignages : accueil (texte et vidéo), page « Témoignages », pied de page. */
class TemoignagesVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_written_and_video_testimonials_are_reachable_from_home_and_footer(): void
    {
        Temoignage::factory()->create(['author_name' => 'Aminata Écrit', 'content' => 'Une très bonne formation.']);
        Temoignage::factory()->create(['author_name' => 'Ibrahim Vidéo', 'content' => null, 'video_url' => 'https://youtu.be/abcdef']);

        $this->get('/')->assertOk()->assertSee('Aminata Écrit')->assertSee('Ibrahim Vidéo')->assertSee('Voir la vidéo')
            ->assertSee('Tous les témoignages (vidéos, photos, textes)')->assertSee(route('site.temoignages.index'), false);
        $this->get('/temoignages')->assertOk()->assertSee('Ibrahim Vidéo')->assertSee('Aminata Écrit');
    }

    public function test_without_testimonials_the_home_invites_to_share_one(): void
    {
        $this->get('/')->assertOk()->assertSee('Partager mon expérience')->assertSee(route('site.temoignages.create'), false);
    }
}
