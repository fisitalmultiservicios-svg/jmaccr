<?php

namespace App\Domain\Cms;

use App\Domain\Auth\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Pages
{
    public function save(int $id, array $meta, array $document, int $base, ?int $actor): int
    {
        $document = Blocks::validate($document);

        return DB::transaction(function () use ($id, $meta, $document, $base, $actor) {
            $page = DB::table('pages')->lockForUpdate()->find($id);
            abort_unless($page, 404);
            abort_unless((int) $page->current_revision_id === $base, 409, 'Otra persona guardó cambios. Recarga antes de continuar.');
            $version = (int) DB::table('page_revisions')->where('page_id', $id)->max('revision') + 1;
            $revision = DB::table('page_revisions')->insertGetId(['page_id' => $id, 'blocks_json' => json_encode($document), 'meta_json' => json_encode($meta), 'revision' => $version, 'author_id' => $actor, 'created_at' => now()]);
            $changes = ['current_revision_id' => $revision, 'updated_at' => now()];
            if (! $page->published_revision_id) {
                $changes += array_intersect_key($meta, array_flip(['title', 'slug', 'seo_title', 'seo_description']));
            }
            DB::table('pages')->where('id', $id)->update($changes);
            Audit::record('pages.saved', 'pages', $id, ['revision' => $version]);

            return $revision;
        }, 3);
    }

    public function publish(int $id, int $revisionId, ?int $actor): void
    {
        DB::transaction(function () use ($id, $revisionId) {
            $page = DB::table('pages')->lockForUpdate()->find($id);
            abort_unless($page, 404);
            $revision = DB::table('page_revisions')->where('page_id', $id)->find($revisionId);
            abort_unless($revision, 404);
            Blocks::validate(Blocks::normalize(json_decode($revision->blocks_json, true)), true);
            $meta = $revision->meta_json ? json_decode($revision->meta_json, true) : [];
            if (isset($meta['slug']) && DB::table('pages')->where('slug', $meta['slug'])->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages(['slug' => 'La ruta ya está ocupada.']);
            }
            if (isset($meta['slug']) && $meta['slug'] !== $page->slug) {
                DB::table('redirects')->updateOrInsert(['old_path' => '/'.$page->slug], ['target_path' => '/'.$meta['slug'], 'status_code' => 301, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('pages')->where('id', $id)->update(array_intersect_key($meta, array_flip(['title', 'slug', 'seo_title', 'seo_description'])) + ['published_revision_id' => $revisionId, 'status' => 'published', 'published_at' => now(), 'scheduled_revision_id' => null, 'scheduled_for' => null, 'updated_at' => now()]);
            Audit::record('pages.published', 'pages', $id, ['revision' => $revision->revision]);
        }, 3);
    }
}
