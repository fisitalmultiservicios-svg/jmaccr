<?php

namespace App\Domain\Auth;

use Illuminate\Support\Facades\DB;

final class Audit
{
    public static function record(string $action, string $resource, ?int $id = null, array $changes = []): void
    {
        // Only explicitly safe metadata can enter the audit log.
        $safe = array_intersect_key($changes, array_flip(['status', 'role', 'revision', 'count', 'mime']));
        DB::table('audit_logs')->insert([
            'actor_id' => auth()->id(), 'resource' => $resource, 'resource_id' => $id,
            'action' => $action, 'changes' => json_encode($safe, JSON_THROW_ON_ERROR),
            'ip_hash' => app()->runningInConsole() ? null : hash_hmac('sha256', request()->ip() ?? '', config('app.key')),
            'created_at' => now(),
        ]);
    }
}
