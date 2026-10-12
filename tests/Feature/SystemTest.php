<?php

namespace Tests\Feature;

use App\Domain\Communication\Posts;
use App\Domain\Sales\Privacy;
use App\Domain\System\Backups;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SystemTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    private function doc(string $text): array
    {
        return ['schema_version' => 1, 'blocks' => [['id' => 'qa-new', 'type' => 'rich_text', 'data' => ['body' => $text], 'style' => [], 'visibility' => ['desktop' => true, 'tablet' => true, 'mobile' => true]]]];
    }

    private function news(): array
    {
        $this->post('/admin/posts', ['_token' => 'test-csrf', 'title' => 'Noticia nueva QA', 'slug' => 'noticia-qa'])->assertRedirect();
        $id = DB::table('posts')->value('id');
        $meta = ['title' => 'Título publicado QA', 'slug' => 'noticia-qa', 'excerpt' => 'Contenido de prueba', 'seo_title' => null, 'seo_description' => null, 'featured_media_id' => null, 'tags_json' => '[]', 'category_ids' => []];
        $r = app(Posts::class)->save($id, $meta, $this->doc('<p>Contenido nuevo</p>'), 0, auth()->id());

        return [$id, $r, $meta];
    }

    public function test_news_draft_publish_revision_isolation_rollback_and_public_seo(): void
    {
        $this->authenticateRole();
        [$id,$r,$meta] = $this->news();
        $this->get('/novedades/noticia-qa/')->assertNotFound();
        $this->post('/admin/posts/'.$id.'/publish', ['_token' => 'test-csrf', 'revision_id' => $r])->assertRedirect();
        $this->get('/novedades/noticia-qa/')->assertOk()->assertSee('Contenido nuevo');
        $meta['title'] = 'Nuevo borrador aislado';
        $r2 = app(Posts::class)->save($id, $meta, $this->doc('<p>Cambio privado</p>'), $r, auth()->id());
        $this->get('/novedades/noticia-qa/')->assertDontSee('Cambio privado');
        $this->post('/admin/posts/'.$id.'/rollback', ['_token' => 'test-csrf', 'revision_id' => $r, 'base_revision' => $r2])->assertRedirect();
        $this->assertDatabaseCount('post_revisions', 3);
        $this->get('/admin/posts/'.$id.'/edit')->assertOk()->assertSee('cms-editor');
    }

    public function test_scheduling_publishes_only_authorized_actor_and_updates_cron_heartbeat(): void
    {
        $this->authenticateRole();
        [$id,$r] = $this->news();
        DB::table('posts')->where('id', $id)->update(['scheduled_for' => now()->subMinute(), 'scheduled_revision_id' => $r, 'scheduled_by' => auth()->id()]);
        $this->artisan('jmac:operations', ['--limit' => 1])->assertExitCode(0);
        $this->assertDatabaseHas('posts', ['id' => $id, 'status' => 'published', 'scheduled_revision_id' => null]);
        $this->assertDatabaseHas('site_settings', ['key' => 'system.cron']);
    }

    public function test_malformed_rich_text_and_unapproved_featured_media_cannot_publish(): void
    {
        $this->authenticateRole();
        [$id,$r,$meta] = $this->news();
        $media = DB::table('media')->insertGetId(['disk' => 'local', 'path' => 'media/'.Str::uuid().'.webp', 'mime' => 'image/webp', 'bytes' => 10, 'checksum' => str_repeat('a', 64), 'status' => 'candidate']);
        $meta['featured_media_id'] = $media;
        $r2 = app(Posts::class)->save($id, $meta, $this->doc('<script>alert(1)</script><p onclick="evil()">Seguro</p>'), $r, auth()->id());
        $this->post('/admin/posts/'.$id.'/publish', ['_token' => 'test-csrf', 'revision_id' => $r2])->assertStatus(422);
        $body = DB::table('post_revisions')->where('id', $r2)->value('body_json');
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('<script>', $body);
    }

    public function test_redirects_are_internal_and_reject_cycles_reserved_paths_and_open_redirect(): void
    {
        $this->authenticateRole();
        $data = ['_token' => 'test-csrf', 'old_path' => '/anterior', 'target_path' => '/nosotros', 'status_code' => 301];
        $this->post('/admin/seo/redirects', $data)->assertRedirect();
        $this->get('/anterior')->assertRedirect('/nosotros');
        $data['old_path'] = '/nosotros';
        $data['target_path'] = '/anterior';
        $this->postJson('/admin/seo/redirects', $data)->assertStatus(422);
        $data['old_path'] = '/admin';
        $data['target_path'] = '/contacto';
        $this->postJson('/admin/seo/redirects', $data)->assertStatus(422);
        $data['old_path'] = '/otra';
        $data['target_path'] = 'https://example.test';
        $this->postJson('/admin/seo/redirects', $data)->assertUnprocessable();
    }

    public function test_staging_robots_and_sitemap_do_not_index_any_content(): void
    {
        $this->get('/robots.txt')->assertSee('Disallow: /');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('<loc>', false);
    }

    public function test_user_invitation_is_durable_encrypted_outbox_without_default_password(): void
    {
        $this->authenticateRole();
        $role = Role::where('name', 'editor_web')->value('id');
        $this->post('/admin/users', ['_token' => 'test-csrf', 'name' => 'Editor QA', 'email' => 'nuevo@example.test', 'roles' => [$role]])->assertRedirect();
        $u = User::where('email', 'nuevo@example.test')->firstOrFail();
        $this->assertTrue($u->requiresMfa());
        $mail = DB::table('notifications_outbox')->where('event_type', 'auth.reset')->first();
        $this->assertNotNull($mail);
        $this->assertStringNotContainsString('token', $mail->payload_json);
        $this->assertStringContainsString('/admin/reset-password/', json_decode(Privacy::decrypt($mail->payload_json), true)['data']['url']);
    }

    public function test_last_active_superadmin_cannot_be_disabled_and_editor_cannot_manage_system(): void
    {
        $u = $this->authenticateRole();
        $this->postJson('/admin/users/'.$u->id, ['_token' => 'test-csrf', 'name' => $u->name, 'email' => $u->email, 'status' => 'disabled', 'roles' => [Role::where('name', 'superadmin')->value('id')]])->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $u->id, 'status' => 'active']);
        $this->authenticateRole('editor_web');
        foreach (['/admin/users', '/admin/backups', '/admin/diagnostics'] as $path) {
            $this->get($path)->assertForbidden();
        }$this->postJson('/admin/system/cache', ['_token' => 'test-csrf'])->assertForbidden();
    }

    public function test_superadmin_role_cannot_be_changed_and_empty_custom_permissions_are_allowed(): void
    {
        $this->authenticateRole();
        $this->postJson('/admin/roles', ['_token' => 'test-csrf', 'id' => Role::where('name', 'superadmin')->value('id'), 'name' => 'superadmin', 'permissions' => []])->assertStatus(422);
        $this->post('/admin/roles', ['_token' => 'test-csrf', 'name' => 'lectura_qa', 'permissions' => []])->assertRedirect();
        $this->assertDatabaseHas('roles', ['name' => 'lectura_qa']);
    }

    public function test_all_system_screens_render_and_sensitive_diagnostics_do_not_expose_keys(): void
    {
        $this->authenticateRole();
        foreach (['/admin/posts', '/admin/seo', '/admin/users', '/admin/backups', '/admin/diagnostics', '/admin/settings', '/admin/audit'] as $path) {
            $this->get($path)->assertOk()->assertDontSee(config('app.key'));
        }
    }

    public function test_encrypted_backup_restores_database_and_media_and_tampering_leaves_data_unchanged(): void
    {
        $original = storage_path();
        $dir = '/tmp/jmac-backup-'.Str::uuid();
        $this->app->useStoragePath($dir);
        mkdir($dir.'/app/private', 0700, true);
        Storage::forgetDisk('local');
        Storage::disk('local')->put('media/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee.webp', 'QA media bytes');
        try {
            $this->authenticateRole();
            DB::table('media')->insert(['disk' => 'local', 'path' => 'media/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee.webp', 'mime' => 'image/webp', 'bytes' => 14, 'checksum' => hash('sha256', 'QA media bytes'), 'status' => 'candidate']);
            $b = app(Backups::class);
            $product = DB::table('products')->insertGetId(['name' => 'Producto nuevo de respaldo QA', 'slug' => 'producto-respaldo-qa', 'sku' => 'RESP-QA-01', 'status' => 'draft']);
            $name = $b->create();
            $raw = file_get_contents($b->path($name));
            $this->assertStringNotContainsString(config('app.key'), $raw);
            $this->assertStringNotContainsString('roles', $raw);
            DB::table('brands')->insert(['name' => 'Marca nueva temporal', 'slug' => 'marca-qa']);
            DB::table('products')->where('id', $product)->update(['name' => 'Cambio posterior QA']);
            DB::table('pages')->where('slug', '')->update(['title' => 'Cambio posterior de portada']);
            Storage::disk('local')->delete('media/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee.webp');
            $b->restore($name);
            $this->assertDatabaseCount('brands', 0);
            $this->assertDatabaseHas('products', ['id' => $product, 'name' => 'Producto nuevo de respaldo QA']);
            $this->assertDatabaseHas('pages', ['slug' => '', 'title' => 'Inicio']);
            $this->assertTrue(User::first()->roles()->where('name', 'superadmin')->exists());
            $this->assertSame('QA media bytes', Storage::disk('local')->get('media/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee.webp'));
            $tampered = $raw;
            $tampered[50] = chr(ord($tampered[50]) ^ 1);
            file_put_contents($b->path($name), $tampered);
            $before = DB::table('users')->count();
            try {
                $b->restore($name);
                $this->fail('Altered backup must fail');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('alterada', $e->getMessage());
            }$this->assertSame($before, DB::table('users')->count());
        } finally {
            Storage::forgetDisk('local');
            $this->app->useStoragePath($original);
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }rmdir($dir);
        }
    }

    public function test_restore_is_blocked_in_production_before_changes(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(HttpException::class);
        try {
            app(Backups::class)->restore('anything');
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
