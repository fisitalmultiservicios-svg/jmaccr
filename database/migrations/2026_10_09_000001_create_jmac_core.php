<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('status')->default('active')->index();
            $t->text('mfa_secret')->nullable();
            $t->timestamp('mfa_confirmed_at')->nullable();
            $t->bigInteger('mfa_last_step')->nullable();
            $t->json('mfa_recovery_codes')->nullable();
            $t->unsignedInteger('auth_version')->default(1);
            $t->timestamp('last_login_at')->nullable();
        });
        foreach (['roles', 'permissions'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->timestamps();
            });
        }
        Schema::create('role_permissions', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });
        Schema::create('user_roles', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['user_id', 'role_id']);
        });
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('disk')->default('local');
            $t->string('path')->unique();
            $t->string('mime');
            $t->unsignedBigInteger('bytes');
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->string('alt')->default('');
            $t->string('checksum', 64);
            $t->string('status')->default('candidate')->index();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('site_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('json_value');
            $t->string('scope')->default('private');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('site_themes', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('design_tokens');
            $t->string('status')->default('draft');
            $t->unsignedInteger('revision')->unique();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->string('template')->default('blocks');
            $t->string('status')->default('draft')->index();
            $t->string('seo_title')->nullable();
            $t->text('seo_description')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('page_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('page_id')->constrained()->restrictOnDelete();
            $t->json('blocks_json');
            $t->unsignedInteger('revision');
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['page_id', 'revision']);
        });
        Schema::table('pages', function (Blueprint $t) {
            $t->foreignId('current_revision_id')->nullable()->constrained('page_revisions')->restrictOnDelete();
            $t->foreignId('published_revision_id')->nullable()->constrained('page_revisions')->restrictOnDelete();
        });
        Schema::create('nav_menus', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('nav_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_id')->constrained('nav_menus')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('nav_items')->restrictOnDelete();
            $t->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $t->string('label');
            $t->string('url');
            $t->unsignedInteger('position')->default(0);
            $t->boolean('visible')->default(true);
            $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('summary')->nullable();
            $t->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->string('status')->default('hidden');
            $t->timestamps();
        });
        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->string('status')->default('hidden');
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->nullable()->unique();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('summary')->nullable();
            $t->longText('description')->nullable();
            $t->foreignId('category_primary_id')->nullable()->constrained('categories')->restrictOnDelete();
            $t->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('status')->default('draft');
            $t->string('unit')->default('unidad');
            $t->boolean('is_featured')->default(false);
            $t->boolean('request_only')->default(true);
            $t->unsignedInteger('position')->default(0);
            $t->decimal('private_price_crc', 14, 2)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['status', 'name']);
        });
        Schema::create('category_product', function (Blueprint $t) {
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->primary(['category_id', 'product_id']);
        });
        Schema::create('product_media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->boolean('is_primary')->default(false);
            $t->unique(['product_id', 'media_id']);
        });
        Schema::create('product_attributes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('value');
            $t->string('unit')->nullable();
            $t->unsignedInteger('position')->default(0);
        });
        Schema::create('sequences', function (Blueprint $t) {
            $t->id();
            $t->string('sequence_name');
            $t->unsignedSmallInteger('year');
            $t->unsignedBigInteger('next_number')->default(1);
            $t->unique(['sequence_name', 'year']);
        });
        Schema::create('preorders', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('year');
            $t->unsignedBigInteger('seq');
            $t->string('public_code')->unique();
            $t->uuid('idempotency_key')->unique();
            $t->string('request_hash', 64);
            $t->string('session_hash', 64);
            foreach (['name', 'phone', 'email', 'company'] as $field) {
                $t->text('customer_'.$field)->nullable();
            }
            $t->text('observations')->nullable();
            $t->string('consent_version');
            $t->string('status')->default('recibida');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['year', 'seq']);
            $t->index(['status', 'created_at']);
            $t->index(['assigned_to', 'created_at']);
        });
        Schema::create('preorder_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('preorder_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('sku_snapshot');
            $t->string('name_snapshot');
            $t->string('unit_snapshot');
            $t->unsignedInteger('quantity');
            $t->json('metadata_snapshot');
            $t->timestamps();
        });
        Schema::create('preorder_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('preorder_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->text('note')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('contact_submissions', function (Blueprint $t) {
            $t->id();
            $t->uuid('idempotency_key')->unique();
            foreach (['name', 'phone', 'email', 'company', 'message'] as $field) {
                $t->text($field)->nullable();
            }
            $t->string('status')->default('nuevo');
            $t->string('consent_version');
            $t->string('source')->default('web');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->text('excerpt')->nullable();
            $t->json('body_json');
            $t->string('status')->default('draft');
            $t->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('seo_title')->nullable();
            $t->text('seo_description')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'published_at']);
        });
        Schema::create('post_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained()->restrictOnDelete();
            $t->json('body_json');
            $t->unsignedInteger('revision');
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['post_id', 'revision']);
        });
        Schema::create('post_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });
        Schema::create('post_category_links', function (Blueprint $t) {
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('post_category_id')->constrained()->restrictOnDelete();
            $t->primary(['post_id', 'post_category_id']);
        });
        Schema::create('notifications_outbox', function (Blueprint $t) {
            $t->id();
            $t->string('deduplication_key')->unique();
            $t->string('event_type');
            $t->unsignedBigInteger('aggregate_id');
            $t->text('recipient')->nullable();
            $t->string('channel');
            $t->text('payload_json');
            $t->string('status')->default('pending');
            $t->unsignedSmallInteger('retries')->default(0);
            $t->timestamp('next_retry_at')->nullable();
            $t->string('error_code')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'next_retry_at']);
        });
        Schema::create('redirects', function (Blueprint $t) {
            $t->id();
            $t->string('old_path')->unique();
            $t->string('target_path');
            $t->unsignedSmallInteger('status_code')->default(301);
            $t->boolean('enabled')->default(false);
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('resource');
            $t->unsignedBigInteger('resource_id')->nullable();
            $t->string('action');
            $t->json('changes')->nullable();
            $t->string('ip_hash', 64)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['resource', 'resource_id', 'created_at']);
        });
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('mapping')->nullable();
            $t->string('status')->default('disabled');
            $t->json('counts')->nullable();
            $t->timestamps();
        });
        Schema::create('import_errors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('row_number');
            $t->string('safe_error');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $t) {
            $t->dropForeign(['current_revision_id']);
            $t->dropForeign(['published_revision_id']);
            $t->dropColumn(['current_revision_id', 'published_revision_id']);
        });
        foreach (['import_errors', 'import_batches', 'audit_logs', 'redirects', 'notifications_outbox', 'post_category_links', 'post_categories', 'post_revisions', 'posts', 'contact_submissions', 'preorder_events', 'preorder_items', 'preorders', 'sequences', 'product_attributes', 'product_media', 'category_product', 'products', 'brands', 'categories', 'nav_items', 'nav_menus', 'page_revisions', 'pages', 'site_themes', 'site_settings', 'media', 'user_roles', 'role_permissions', 'permissions', 'roles'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex('users_status_index'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['status', 'mfa_secret', 'mfa_confirmed_at', 'mfa_last_step', 'mfa_recovery_codes', 'auth_version', 'last_login_at']));
    }
};
