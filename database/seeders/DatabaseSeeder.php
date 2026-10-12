<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $groups = [
                'pages' => ['view', 'create', 'update', 'publish'], 'theme' => ['view', 'update', 'publish'],
                'menus' => ['view', 'update'], 'media' => ['view', 'create', 'update', 'approve', 'delete'],
                'categories' => ['view', 'create', 'update', 'delete'], 'brands' => ['view', 'create', 'update', 'delete'],
                'products' => ['view', 'create', 'update', 'publish', 'delete'], 'preorders' => ['view', 'assign', 'update', 'export'],
                'contacts' => ['view', 'update', 'export', 'delete'], 'posts' => ['view', 'create', 'update', 'publish', 'delete'],
                'seo' => ['view', 'update'], 'settings' => ['view', 'update', 'sensitive'],
                'users' => ['manage'], 'backups' => ['view', 'create', 'restore'], 'audit' => ['view'], 'system' => ['diagnostics', 'cache_clear'],
            ];
            $all = [];
            foreach ($groups as $resource => $actions) {
                foreach ($actions as $action) {
                    $all[] = Permission::firstOrCreate(['name' => "$resource.$action"])->id;
                }
            }
            $grants = [
                'superadmin' => array_keys($groups),
                'administrador' => ['pages', 'theme', 'menus', 'media', 'categories', 'brands', 'products', 'preorders', 'contacts', 'posts', 'seo', 'settings', 'system'],
                'editor_web' => ['pages', 'theme', 'menus', 'media', 'posts', 'seo'],
                'gestor_catalogo' => ['categories', 'brands', 'products', 'media'],
                'ventas' => ['preorders', 'contacts'], 'consulta' => [],
            ];
            foreach ($grants as $name => $resources) {
                $role = Role::firstOrCreate(['name' => $name]);
                // Re-running setup does not overwrite permissions edited by the owner.
                if ($role->wasRecentlyCreated) {
                    $names = [];
                    foreach ($resources as $resource) {
                        foreach ($groups[$resource] as $action) {
                            if ($name !== 'superadmin' && in_array("$resource.$action", ['settings.sensitive', 'media.approve', 'system.cache_clear'])) {
                                continue;
                            }
                            if ($name === 'editor_web' && in_array("$resource.$action", ['theme.publish', 'media.delete'])) {
                                continue;
                            }
                            $names[] = "$resource.$action";
                        }
                    }
                    $role->permissions()->sync(Permission::whereIn('name', $names)->pluck('id'));
                }
            }
            $source = json_decode(file_get_contents(__DIR__.'/corporate-draft.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach (['categories', 'brands', 'products', 'blog_posts', 'orders', 'customers'] as $field) {
                if ($source[$field] !== []) {
                    throw new \LogicException('La semilla contractual debe tener catálogo vacío.');
                }
            }
            $now = now();
            DB::table('site_settings')->insertOrIgnore([
                ['key' => 'brand.draft', 'json_value' => json_encode($source['brand']), 'scope' => 'draft', 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'privacy.status', 'json_value' => json_encode('pending_owner_approval'), 'scope' => 'private', 'created_at' => $now, 'updated_at' => $now],
            ]);
            if (! DB::table('site_themes')->exists()) {
                DB::table('site_themes')->insert([
                    'name' => 'Propuesta JMAC azul/blanco', 'design_tokens' => json_encode($source['brand']['theme_proposal']),
                    'status' => 'draft', 'revision' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            foreach ($source['pages'] as $page) {
                $slug = trim($page['path'], '/');
                if (DB::table('pages')->where('slug', $slug)->exists()) {
                    continue;
                }
                $id = DB::table('pages')->insertGetId(['slug' => $slug, 'title' => $page['title'], 'status' => 'draft', 'created_at' => $now, 'updated_at' => $now]);
                $blocks = [];
                foreach ($page['blocks'] as $i => $block) {
                    $blocks[] = [
                        'id' => "seed-$id-$i", 'type' => $block['type'], 'data' => $block, 'style' => [],
                        'visibility' => ['desktop' => true, 'tablet' => true, 'mobile' => true],
                    ];
                }
                $revision = DB::table('page_revisions')->insertGetId(['page_id' => $id, 'revision' => 1, 'blocks_json' => json_encode(['schema_version' => 1, 'blocks' => $blocks]), 'created_at' => $now]);
                DB::table('pages')->where('id', $id)->update(['current_revision_id' => $revision]);
            }
            if (! DB::table('nav_menus')->where('name', 'principal')->exists()) {
                $menu = DB::table('nav_menus')->insertGetId(['name' => 'principal', 'created_at' => $now, 'updated_at' => $now]);
                foreach ($source['navigation'] as $position => $item) {
                    DB::table('nav_items')->insert([
                        'menu_id' => $menu, 'label' => $item['label'], 'url' => $item['path'], 'position' => $position,
                        'page_id' => DB::table('pages')->where('slug', trim($item['path'], '/'))->value('id'),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        });
    }
}
