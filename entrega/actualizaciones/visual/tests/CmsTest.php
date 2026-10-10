<?php

namespace Tests\Feature;

use App\Domain\Cms\Blocks;
use App\Domain\Site\Site;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use RefreshDatabase;

    private int $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
        $this->page = DB::table('pages')->where('slug', '')->value('id');
        $this->login();
    }

    private function login(string $role = 'superadmin'): void
    {
        $user = User::factory()->create(['mfa_confirmed_at' => now()]);
        $user->roles()->attach(Role::where('name', $role)->first());
        $user->refresh();
        $this->actingAs($user)->withSession(['_token' => 'cms-csrf', 'auth_version' => $user->auth_version, 'mfa_user_id' => $user->id]);
    }

    private function document(string $heading = 'Texto nuevo del dueño', string $type = 'heading'): array
    {
        return ['schema_version' => 1, 'blocks' => [['id' => 'block-new', 'type' => $type, 'data' => ['heading' => $heading, 'body' => 'Texto corporativo nuevo'], 'style' => [], 'visibility' => ['desktop' => true, 'tablet' => true, 'mobile' => true]]]];
    }

    private function save(array $doc, ?int $base = null): TestResponse
    {
        return $this->postJson('/admin/pages/'.$this->page, ['_token' => 'cms-csrf', 'title' => 'Inicio', 'slug' => '', 'document' => $doc, 'base_revision' => $base ?? DB::table('pages')->where('id', $this->page)->value('current_revision_id')]);
    }

    public function test_draft_publish_and_rollback_preserve_history_and_public_content(): void
    {
        $original = DB::table('pages')->where('id', $this->page)->value('current_revision_id');
        $revision = $this->save($this->document())->assertOk()->json('revision_id');
        $this->get('/')->assertDontSee('Texto nuevo del dueño');
        $this->post('/admin/pages/'.$this->page.'/publish', ['_token' => 'cms-csrf', 'revision_id' => $revision])->assertRedirect();
        $this->get('/')->assertSee('Texto nuevo del dueño');
        $next = $this->save($this->document('Cambio sin publicar'))->assertOk()->json('revision_id');
        $this->get('/')->assertDontSee('Cambio sin publicar')->assertSee('Texto nuevo del dueño');
        $this->post('/admin/pages/'.$this->page.'/rollback', ['_token' => 'cms-csrf', 'revision_id' => $revision, 'base_revision' => $next])->assertRedirect();
        $this->assertSame(4, DB::table('page_revisions')->where('page_id', $this->page)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'pages.saved']);
        $this->get('/admin/pages/'.$this->page.'/edit')->assertOk()->assertSee('CONSTRUCTOR VISUAL');
    }

    public function test_concurrent_edits_are_rejected_instead_of_overwriting(): void
    {
        $base = DB::table('pages')->where('id', $this->page)->value('current_revision_id');
        $this->save($this->document('Primer cambio'), $base)->assertOk();
        $this->save($this->document('Segundo cambio'), $base)->assertConflict();
        $this->assertSame(2, DB::table('page_revisions')->where('page_id', $this->page)->count());
    }

    public function test_unsafe_blocks_urls_styles_and_rich_html_cannot_execute(): void
    {
        $this->save($this->document('Inyección', 'script'))->assertUnprocessable();
        $doc = $this->document();
        $doc['blocks'][0]['data']['button_url'] = 'javascript:alert(1)';
        $this->save($doc)->assertUnprocessable();
        $doc = $this->document();
        $doc['blocks'][0]['style']['color'] = 'red; background:url(https://evil.test)';
        $this->save($doc)->assertUnprocessable();
        $clean = Blocks::richText('<p onclick="alert(1)"><strong>Seguro</strong><script>alert(1)</script><a href="javascript:alert(2)">Texto</a><img src=x onerror=alert(3)></p>');
        $this->assertStringContainsString('<strong>Seguro</strong>', $clean);
        foreach (['onclick', '<script', 'javascript:', '<img'] as $bad) {
            $this->assertStringNotContainsString($bad, $clean);
        }
    }

    public function test_candidate_media_cannot_be_published_until_owner_approval(): void
    {
        Storage::fake('local');
        $this->post('/admin/media/upload', ['_token' => 'cms-csrf', 'file' => UploadedFile::fake()->image('photo.png'), 'alt' => 'Foto nueva'])->assertRedirect();
        $media = DB::table('media')->first();
        $this->get('/media/'.$media->id)->assertNotFound();
        $doc = $this->document();
        $doc['blocks'][0]['data']['media_id'] = $media->id;
        $revision = $this->save($doc)->assertOk()->json('revision_id');
        $this->postJson('/admin/pages/'.$this->page.'/publish', ['_token' => 'cms-csrf', 'revision_id' => $revision])->assertUnprocessable();
        $this->post('/admin/media/'.$media->id.'/approve', ['_token' => 'cms-csrf', 'owner_approved' => 1])->assertRedirect();
        $this->get('/media/'.$media->id)->assertOk();
        $this->post('/admin/pages/'.$this->page.'/publish', ['_token' => 'cms-csrf', 'revision_id' => $revision])->assertRedirect();
        $this->post('/admin/media/'.$media->id.'/delete', ['_token' => 'cms-csrf'])->assertUnprocessable();
        $this->assertDatabaseCount('media', 1);
        $this->assertNotNull(json_decode($media->variants_json, true)['thumb']);
    }

    public function test_menu_draft_publish_and_url_validation(): void
    {
        $menu = DB::table('nav_menus')->first();
        $items = [['label' => 'Catálogo nuevo', 'url' => '/catalogo/', 'visible' => true]];
        $this->postJson('/admin/menus/'.$menu->id, ['_token' => 'cms-csrf', 'base_revision' => 0, 'items' => $items])->assertOk();
        $this->get('/')->assertDontSee('Catálogo nuevo');
        $revision = DB::table('menu_revisions')->first();
        $this->post('/admin/menus/'.$menu->id.'/publish', ['_token' => 'cms-csrf', 'revision_id' => $revision->id])->assertRedirect();
        $this->get('/')->assertSee('Catálogo nuevo');
        $items[0]['url'] = '//evil.test';
        $this->postJson('/admin/menus/'.$menu->id, ['_token' => 'cms-csrf', 'base_revision' => $revision->id, 'items' => $items])->assertUnprocessable();
    }

    public function test_theme_revisions_publish_and_rollback_are_independent(): void
    {
        $tokens = Site::defaults();
        $tokens['primary'] = '#123456';
        $tokens['footer_credit'] = 'Crédito nuevo del propietario';
        $this->post('/admin/theme', $tokens + ['_token' => 'cms-csrf', 'base_revision' => 1, 'name' => 'Diseño nuevo'])->assertRedirect();
        $this->get('/')->assertDontSee('--primary:#123456');
        $this->get('/')->assertSee('Creado por fisitalcr')->assertDontSee('Crédito nuevo del propietario');
        $theme = DB::table('site_themes')->where('revision', 2)->first();
        $this->post('/admin/theme/'.$theme->id.'/publish', ['_token' => 'cms-csrf'])->assertRedirect();
        $this->get('/')->assertSee('--primary:#123456', false);
        $this->get('/')->assertSee('Crédito nuevo del propietario')->assertDontSee('Creado por fisitalcr');
        $this->post('/admin/theme/'.DB::table('site_themes')->where('revision', 1)->value('id').'/rollback', ['_token' => 'cms-csrf'])->assertRedirect();
        $this->assertDatabaseCount('site_themes', 3);
        $this->get('/')->assertSee('--primary:#123456', false);
    }

    public function test_footer_credit_is_validated_and_escaped_after_publication(): void
    {
        $tokens = Site::defaults();
        $this->postJson('/admin/theme', array_replace($tokens, ['footer_credit' => str_repeat('x', 181), '_token' => 'cms-csrf', 'base_revision' => 1, 'name' => 'Crédito inválido']))->assertUnprocessable();
        $this->assertDatabaseCount('site_themes', 1);
        $tokens['footer_credit'] = '<script>alert("credit")</script>';
        $this->post('/admin/theme', $tokens + ['_token' => 'cms-csrf', 'base_revision' => 1, 'name' => 'Crédito escapado'])->assertRedirect();
        $theme = DB::table('site_themes')->where('revision', 2)->first();
        $this->post('/admin/theme/'.$theme->id.'/publish', ['_token' => 'cms-csrf'])->assertRedirect();
        $this->get('/')->assertSee('&lt;script&gt;alert(&quot;credit&quot;)&lt;/script&gt;', false)->assertDontSee('<script>alert("credit")</script>', false);
    }

    public function test_sales_cannot_write_cms_theme_or_menus(): void
    {
        $this->login('ventas');
        $this->get('/admin/pages/'.$this->page.'/edit')->assertForbidden();
        $this->save($this->document())->assertForbidden();
        $this->post('/admin/theme', ['_token' => 'cms-csrf'])->assertForbidden();
        $this->post('/admin/menus/1', ['_token' => 'cms-csrf'])->assertForbidden();
    }

    public function test_empty_pages_all_block_types_and_reusable_sections_are_supported(): void
    {
        $this->post('/admin/pages', ['_token' => 'cms-csrf', 'title' => 'Nueva página', 'slug' => 'pagina-nueva'])->assertRedirect();
        $this->assertDatabaseCount('pages', 6);
        $doc = ['schema_version' => 1, 'blocks' => []];
        foreach (Blocks::TYPES as $i => $type) {
            $block = $this->document('Bloque '.$i, $type)['blocks'][0];
            $block['id'] = 'b-'.$i;
            $block['data']['approved'] = true;
            $doc['blocks'][] = $block;
        }$revision = $this->save($doc)->assertOk()->json('revision_id');
        $this->get('/admin/pages/'.$this->page.'/preview')->assertOk();
        $this->postJson('/admin/sections', ['_token' => 'cms-csrf', 'name' => 'Sección nueva', 'document' => $this->document()])->assertOk();
        $this->assertDatabaseCount('cms_sections', 1);
    }

    public function test_pdf_is_rebuilt_before_private_storage_and_active_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 10, 'Documento nuevo');
        $file = UploadedFile::fake()->createWithContent('ficha.pdf', $pdf->Output('S'));
        $this->post('/admin/media/upload', ['_token' => 'cms-csrf', 'alt' => 'Ficha PDF', 'file' => $file])->assertRedirect();
        $this->assertDatabaseHas('media', ['mime' => 'application/pdf', 'status' => 'candidate']);
        $this->post('/admin/media/upload', ['_token' => 'cms-csrf', 'alt' => 'Falso', 'file' => UploadedFile::fake()->createWithContent('fake.pdf', '<?php echo "bad";')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('media', 1);
    }
}
