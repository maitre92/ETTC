<?php

namespace Tests\Feature\Contenus;

use App\Enums\CategoryType;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Actualités (cahier ETTC §3.11) : rédiger, prévisualiser, publier, programmer, dépublier, catégoriser.
 */
class ArticleTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $gestionnaire;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->syncPermissions();
        $this->gestionnaire = User::factory()->gestionnaire()->create();
    }

    public function test_an_article_is_written_previewed_scheduled_then_published(): void
    {
        $conseils = Category::factory()->of(CategoryType::Article)->create(['name' => 'Conseils']);
        $this->actingAs($this->gestionnaire);

        $this->post('/admin/articles', [
            'title' => 'Bien choisir son ordinateur portable', 'body' => "## Les critères\n\nLe **processeur** compte.", 'category_id' => $conseils->id,
            'is_published' => '1', 'published_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ])->assertRedirect('/admin/articles')->assertSessionHas('status', fn ($s) => str_contains($s, 'programmé'));

        $article = Article::firstOrFail();
        $this->assertSame($this->gestionnaire->id, $article->author_id);
        $this->assertTrue($article->isScheduled());

        $this->get('/actualites')->assertDontSee('Bien choisir son ordinateur');
        $this->get('/actualites/'.$article->slug)->assertNotFound();
        $this->get("/admin/articles/{$article->id}/apercu")->assertOk()->assertSee('Aperçu')->assertSee('<strong>processeur</strong>', false)->assertSee('noindex', false);
        $html = $this->postJson('/admin/articles/markdown', ['body' => '**gras** <script>x</script>'])->assertOk()->json('html');
        $this->assertStringContainsString('<strong>gras</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);

        $article->update(['published_at' => now()->subMinute()]);
        $this->get('/actualites')->assertSee('Bien choisir son ordinateur');
        $this->get('/actualites?categorie='.$conseils->slug)->assertSee('Bien choisir son ordinateur');
        $this->get('/actualites/'.$article->slug)->assertOk()->assertSee('min de lecture')->assertSee('"@type":"Article"', false);
    }

    public function test_article_rules_and_permissions(): void
    {
        $this->actingAs($this->gestionnaire)->post('/admin/articles', ['title' => '', 'body' => '', 'category_id' => 999999])
            ->assertSessionHasErrors(['title', 'body', 'category_id']);

        $article = Article::factory()->create();
        $this->actingAs(User::factory()->commercial()->create());
        $this->get('/admin/articles')->assertForbidden();
        $this->get("/admin/articles/{$article->id}/apercu")->assertForbidden();
        $this->post("/admin/articles/{$article->id}/publication")->assertForbidden();

        $this->actingAs($this->gestionnaire)->post("/admin/articles/{$article->id}/publication")->assertRedirect('/admin/articles');
        $this->assertFalse($article->fresh()->is_published);
        $this->assertTrue(ActivityLog::where('action', 'articles.unpublished')->exists());
    }
}
