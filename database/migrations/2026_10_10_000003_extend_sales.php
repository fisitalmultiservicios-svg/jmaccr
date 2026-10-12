<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preorders', function (Blueprint $t) {
            $t->string('customer_phone_hash', 64)->nullable()->index();
            $t->boolean('email_opt_in')->default(false);
        });
        Schema::table('contact_submissions', function (Blueprint $t) {
            $t->string('public_code')->nullable()->unique();
            $t->string('request_hash', 64)->nullable();
            $t->string('session_hash', 64)->nullable();
            $t->string('phone_hash', 64)->nullable()->index();
            $t->string('email_hash', 64)->nullable()->index();
            $t->boolean('email_opt_in')->default(false);
        });
        Schema::create('contact_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contact_id')->constrained('contact_submissions')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status');
            $t->text('note')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::table('notifications_outbox', function (Blueprint $t) {
            $t->timestamp('locked_at')->nullable();
            $t->uuid('claim_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notifications_outbox', fn (Blueprint $t) => $t->dropColumn(['locked_at', 'claim_token']));
        Schema::dropIfExists('contact_events');
        Schema::table('contact_submissions', function (Blueprint $t) {
            $t->dropUnique(['public_code']);
            $t->dropIndex(['phone_hash']);
            $t->dropIndex(['email_hash']);
            $t->dropColumn(['public_code', 'request_hash', 'session_hash', 'phone_hash', 'email_hash', 'email_opt_in']);
        });
        Schema::table('preorders', function (Blueprint $t) {
            $t->dropIndex(['customer_phone_hash']);
            $t->dropColumn(['customer_phone_hash', 'email_opt_in']);
        });
    }
};
