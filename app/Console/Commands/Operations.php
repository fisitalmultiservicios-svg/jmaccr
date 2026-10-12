<?php

namespace App\Console\Commands;

use App\Domain\Auth\Audit;
use App\Domain\Catalog\CatalogImports;
use App\Domain\Cms\Pages;
use App\Domain\Communication\Posts;
use App\Domain\Notifications\Outbox;
use App\Domain\Site\Site;
use App\Domain\System\Backups;
use App\Http\Controllers\Admin\SettingsController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Operations extends Command
{
    protected $signature = 'jmac:operations {--limit=20}';

    protected $description = 'Cron acotado: publicaciones, outbox y copia diaria opcional';

    public function handle(): int
    {
        $lock = fopen(storage_path('app/private/operations.lock'), 'c');
        chmod(storage_path('app/private/operations.lock'), 0600);
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $this->info('Otra ejecución está trabajando.');

            return 0;
        }$failures = 0;
        try {
            app(CatalogImports::class)->cleanup();
            foreach (['pages' => Pages::class, 'posts' => Posts::class] as $table => $service) {
                foreach (DB::table($table)->whereNotNull('scheduled_revision_id')->where('scheduled_for', '<=', now())->limit(10)->get() as $row) {
                    try {
                        $actor = User::where('status', 'active')->find($row->scheduled_by);
                        if (! $actor || ! $actor->hasPermission($table.'.publish')) {
                            throw new \RuntimeException('Autorización revocada');
                        }auth()->login($actor);
                        app($service)->publish($row->id, $row->scheduled_revision_id, ...($table === 'pages' ? [$actor->id] : []));
                    } catch (\Throwable $e) {
                        $failures++;
                        DB::table($table)->where('id', $row->id)->update(['scheduled_for' => null, 'scheduled_revision_id' => null]);
                        Audit::record($table.'.schedule_failed', $table, $row->id);
                    } finally {
                        auth()->logout();
                    }
                }
            }$summary = app(Outbox::class)->process((int) $this->option('limit'));
            $auto = Site::setting('system.automation', []);
            $backup = Site::setting('system.last_backup', []);
            if (($auto['backup_daily'] ?? false) && ($backup['date'] ?? '') !== now()->toDateString()) {
                $name = app(Backups::class)->create();
                SettingsController::put('system.last_backup', ['date' => now()->toDateString(), 'name' => $name]);
                $copies = app(Backups::class)->inventory();
                foreach (array_slice($copies, max(2, min(30, (int) ($auto['keep_backups'] ?? 7)))) as $old) {
                    unlink(app(Backups::class)->path($old['name']));
                }
            }SettingsController::put('system.cron', ['last_run' => now()->toIso8601String(), 'result' => $failures ? 'publication_errors' : 'ok', 'outbox' => $summary]);
            $this->info(json_encode(['outbox' => $summary, 'publication_errors' => $failures]));

            return $failures ? 1 : 0;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
