<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_revisions', fn (Blueprint $t) => $t->json('meta_json')->nullable());
        Schema::table('pages', function (Blueprint $t) {
            $t->foreignId('scheduled_revision_id')->nullable()->constrained('page_revisions')->restrictOnDelete();
            $t->timestamp('scheduled_for')->nullable();
        });
        Schema::create('cms_sections', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('blocks_json');
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('menu_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_id')->constrained('nav_menus')->restrictOnDelete();
            $t->json('items_json');
            $t->unsignedInteger('revision');
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['menu_id', 'revision']);
        });
        Schema::table('nav_menus', function (Blueprint $t) {
            $t->foreignId('current_revision_id')->nullable()->constrained('menu_revisions')->restrictOnDelete();
            $t->foreignId('published_revision_id')->nullable()->constrained('menu_revisions')->restrictOnDelete();
        });
        Schema::table('media', fn (Blueprint $t) => $t->json('variants_json')->nullable());
    }

    public function down(): void
    {
        Schema::table('media', fn (Blueprint $t) => $t->dropColumn('variants_json'));
        Schema::table('nav_menus', function (Blueprint $t) {
            $t->dropForeign(['current_revision_id']);
            $t->dropForeign(['published_revision_id']);
            $t->dropColumn(['current_revision_id', 'published_revision_id']);
        });
        Schema::dropIfExists('menu_revisions');
        Schema::dropIfExists('cms_sections');
        Schema::table('pages', function (Blueprint $t) {
            $t->dropForeign(['scheduled_revision_id']);
            $t->dropColumn(['scheduled_revision_id', 'scheduled_for']);
        });
        Schema::table('page_revisions', fn (Blueprint $t) => $t->dropColumn('meta_json'));
    }
};
