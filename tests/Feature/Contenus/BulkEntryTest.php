<?php

namespace Tests\Feature\Contenus;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Prestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Saisie en masse (repris du projet CSB : « ajouter des lignes » et « ajouter un fichier ») : un seul enregistrement crée
 * plusieurs éléments, en tout ou rien, avec les mêmes règles qu'une fiche, audité.
 */
class BulkEntryTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $gestionnaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->gestionnaire = User::factory()->gestionnaire()->create();
    }

    public function test_several_services_are_created_in_one_save(): void
    {
        $this->actingAs($this->gestionnaire)->get('/admin/prestations/lot')->assertOk();

        $this->post('/admin/prestations/lot', ['rows' => [
            ['title' => 'Cours de conversation', 'icon' => 'bx-chat', 'summary' => 'Pratiquer l\'oral en petit groupe.', 'is_published' => '1'],
            ['title' => 'Préparation aux examens', 'icon' => 'bx-award', 'summary' => 'Entraînement aux épreuves écrites et orales.', 'is_published' => '0'],
            ['title' => '', 'icon' => '', 'summary' => '', 'is_published' => '0'], // ligne vide : ignorée
        ]])->assertRedirect('/admin/prestations')->assertSessionHas('status');

        $this->assertSame(2, Prestation::count());
        $this->assertTrue(Prestation::where('title', 'Cours de conversation')->value('is_published'));
        $this->assertFalse(Prestation::where('title', 'Préparation aux examens')->value('is_published'));
        $this->assertTrue(ActivityLog::where('action', 'prestations.bulk_created')->exists());
    }

    public function test_one_bad_row_rejects_the_whole_batch_with_errors_per_cell(): void
    {
        $this->actingAs($this->gestionnaire)->from('/admin/prestations/lot')->post('/admin/prestations/lot', ['rows' => [
            ['title' => 'Service valide', 'summary' => 'Résumé valide.'],
            ['title' => '', 'summary' => 'Titre manquant.'],
        ]])->assertRedirect('/admin/prestations/lot')->assertSessionHasErrors(['rows.1.title']);

        $this->assertSame(0, Prestation::count());
        $this->post('/admin/prestations/lot', ['rows' => []])->assertSessionHasErrors('rows');
    }

    public function test_an_excel_file_is_imported_all_or_nothing(): void
    {
        $this->actingAs($this->gestionnaire);

        $template = $this->get('/admin/prestations/import/modele')->assertOk()->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $template);
        $book = IOFactory::load($path);
        $this->assertSame(['Titre *', 'Icône', 'Résumé *', 'Ordre', 'Publié'], array_slice($book->getSheet(0)->toArray()[0], 0, 5));
        $this->assertNotNull($book->getSheetByName('Mode d\'emploi'));

        $this->post('/admin/prestations/import', ['fichier' => $this->xlsx([
            ['Titre', 'Résumé', 'Icône', 'Publié'],
            ['Club de conversation', 'Pratiquer l\'oral chaque samedi.', 'Conversation', 'oui'],
            ['Anglais des affaires', 'Réunions, e-mails et présentations en anglais.', 'bx-chalkboard', 'non'],
        ])])->assertRedirect('/admin/prestations')->assertSessionHas('status', 'Import réussi : 2 services créé(e)s.');
        $this->assertSame('bx-chat', Prestation::where('title', 'Club de conversation')->value('icon'));
        $this->assertFalse(Prestation::where('title', 'Anglais des affaires')->value('is_published'));

        $this->post('/admin/prestations/import', ['fichier' => $this->xlsx([
            ['Titre', 'Résumé'],
            ['Service OK', 'Résumé OK'],
            ['', 'Titre manquant'],
        ])])->assertRedirect('/admin/prestations/lot')->assertSessionHas('import_errors', fn ($errors) => $errors[0]['line'] === 3 && $errors[0]['column'] === 'Titre');
        $this->assertSame(2, Prestation::count(), 'Rien n\'est importé quand une ligne est en erreur.');

        $this->post('/admin/prestations/import', ['fichier' => UploadedFile::fake()->create('virus.exe', 5)])->assertSessionHasErrors('fichier');
    }

    public function test_categories_need_the_family_permission_and_unique_names(): void
    {
        $editor = User::factory()->gestionnaire()->create();
        $editor->roles->first()->revokePermissionTo('articles.update');

        $this->actingAs($editor->fresh())->post('/admin/categories/lot', ['rows' => [['type' => 'article', 'name' => 'Promotions']]])->assertForbidden();
        $this->assertSame(0, Category::count());

        $editor->roles->first()->givePermissionTo('articles.update');
        $this->actingAs(User::factory()->gestionnaire()->create())
            ->post('/admin/categories/lot', ['rows' => [['type' => 'article', 'name' => 'Séjours'], ['type' => 'article', 'name' => 'séjours']]])
            ->assertSessionHasErrors('rows.1.name');
    }

    public function test_the_commercial_cannot_use_bulk_entry(): void
    {
        $this->actingAs(User::factory()->commercial()->create());
        $this->get('/admin/prestations/lot')->assertForbidden();
        $this->post('/admin/temoignages/lot', ['rows' => [['author_name' => 'X']]])->assertForbidden();
    }

    /** @param list<list<string>> $rows */
    private function xlsx(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
