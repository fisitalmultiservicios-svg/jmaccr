<?php

namespace App\Domain\Communication;

use App\Domain\Auth\Audit;
use App\Domain\Cms\Blocks;
use Illuminate\Support\Facades\DB;

final class Posts
{
    public function save(int $id, array $meta, array $document, int $base, ?int $actor): int
    {
        $document = Blocks::validate($document);

        return DB::transaction(function () use ($id, $meta, $document, $base, $actor) {
            $post = DB::table('posts')->lockForUpdate()->find($id);
            abort_unless($post, 404);
            abort_unless((int) $post->current_revision_id === $base, 409, 'Otra persona editó esta novedad. Recarga.');
            $number = (int) DB::table('post_revisions')->where('post_id', $id)->max('revision') + 1;
            $revision = DB::table('post_revisions')->insertGetId(['post_id' => $id, 'body_json' => json_encode($document), 'meta_json' => json_encode($meta), 'revision' => $number, 'author_id' => $actor, 'created_at' => now()]);
            DB::table('posts')->where('id', $id)->update(['current_revision_id' => $revision, 'updated_at' => now()]);
            Audit::record('posts.saved', 'posts', $id, ['revision' => $number]);

            return $revision;
        }, 3);
    }

    public function publish(int $id, int $revision): void
    {
        DB::transaction(function () use ($id, $revision) {
            $post = DB::table('posts')->lockForUpdate()->find($id);
            abort_unless($post, 404);
            $rev = DB::table('post_revisions')->where('post_id', $id)->find($revision);
            abort_unless($rev, 404);
            $doc = Blocks::validate(json_decode($rev->body_json, true), true);
            $meta = json_decode($rev->meta_json, true);
            abort_if(DB::table('posts')->where('slug', $meta['slug'])->where('id', '!=', $id)->exists(), 422, 'La ruta ya está ocupada.');
            if (! empty($meta['featured_media_id'])) {
                abort_unless(DB::table('media')->where('status', 'approved')->where('mime', 'like', 'image/%')->where('id', $meta['featured_media_id'])->exists(), 422, 'Aprueba primero la imagen destacada.');
            }if ($post->published_revision_id && $post->slug !== $meta['slug']) {
                DB::table('redirects')->updateOrInsert(['old_path' => '/novedades/'.$post->slug], ['target_path' => '/novedades/'.$meta['slug'], 'status_code' => 301, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
            }$categories = $meta['category_ids'];
            unset($meta['category_ids']);
            DB::table('posts')->where('id', $id)->update($meta + ['body_json' => json_encode($doc), 'published_revision_id' => $revision, 'status' => 'published', 'published_at' => now(), 'scheduled_revision_id' => null, 'scheduled_for' => null, 'updated_at' => now()]);
            DB::table('post_category_links')->where('post_id', $id)->delete();
            foreach ($categories as $category) {
                DB::table('post_category_links')->insert(['post_id' => $id, 'post_category_id' => $category]);
            }Audit::record('posts.published', 'posts', $id, ['revision' => $rev->revision]);
        }, 3);
    }
}
