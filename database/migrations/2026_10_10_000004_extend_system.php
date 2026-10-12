<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->unsignedBigInteger('current_revision_id')->nullable();
            $t->unsignedBigInteger('published_revision_id')->nullable();
            $t->unsignedBigInteger('scheduled_revision_id')->nullable();
            $t->timestamp('scheduled_for')->nullable();
            $t->json('tags_json')->nullable();
            $t->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('pages', fn (Blueprint $t) => $t->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete());
        Schema::table('post_revisions', fn (Blueprint $t) => $t->json('meta_json')->nullable());
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $t) {
            $t->dropForeign(['scheduled_by']);
            $t->dropColumn('scheduled_by');
        });
        Schema::table('posts', fn (Blueprint $t) => $t->dropForeign(['scheduled_by']));
        Schema::table('post_revisions', fn (Blueprint $t) => $t->dropColumn('meta_json'));
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['current_revision_id', 'published_revision_id', 'scheduled_revision_id', 'scheduled_for', 'tags_json', 'scheduled_by']));
    }
};
