<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->text('search_text')->nullable();
            $t->json('tags_json')->nullable();
            $t->string('seo_title')->nullable();
            $t->text('seo_description')->nullable();
            $t->integer('stock')->nullable();
        });
        Schema::create('product_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->unique(['product_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_documents');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['search_text', 'tags_json', 'seo_title', 'seo_description', 'stock']));
    }
};
