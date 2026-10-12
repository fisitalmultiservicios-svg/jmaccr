<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Domain\Notifications\MailConfig;
use App\Domain\Notifications\Outbox;
use App\Domain\Site\Site;
use App\Domain\System\Release;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    public function diagnostics()
    {
        $checks = ['PHP >=8.3' => version_compare(PHP_VERSION, '8.3', '>='), 'DB conectada' => (bool) DB::connection()->getPdo(), 'Storage escribible' => is_writable(storage_path()), 'Build estático' => is_file(public_path('build/manifest.json')), 'APP_KEY privada' => (bool) config('app.key'), 'HTTPS sesión' => config('session.secure') === true, 'Document root privado' => ! str_contains(base_path('.env'), public_path().'/')];
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'curl', 'dom', 'xmlreader', 'zip', 'gd', 'intl'] as $e) {
            $checks['Extensión '.$e] = extension_loaded($e);
        }$mailer = MailConfig::configure();
        $checks['SMTP configurado'] = MailConfig::available($mailer);

        return view('admin.diagnostics', ['checks' => $checks, 'cron' => Site::setting('system.cron', []), 'outbox' => DB::table('notifications_outbox')->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->get(), 'environment' => app()->environment(), 'release' => Release::VERSION]);
    }

    public function cache()
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Audit::record('system.cache_cleared', 'system');

        return back()->with('status', 'Caché y vistas compiladas vaciados.');
    }

    public function process()
    {
        $summary = app(Outbox::class)->process(10);
        Audit::record('system.outbox_processed', 'notifications_outbox', null, ['count' => $summary['sent']]);

        return back()->with('status', 'Proceso finito: '.json_encode($summary));
    }

    public function smtpTest(Request $request)
    {
        $d = $request->validate(['recipient' => 'required|email|max:254']);
        Outbox::direct('smtp.test', auth()->id(), $d['recipient'], []);
        $summary = app(Outbox::class)->process(10);
        Audit::record('settings.smtp_test', 'site_settings');

        return back()->with('status', 'Prueba generada y procesador ejecutado: '.json_encode($summary).'. Consulta la bandeja; confirma recepción en el buzón.');
    }

    public function audit(Request $request)
    {
        return view('admin.audit', ['events' => DB::table('audit_logs')->when($request->resource, fn ($q) => $q->where('resource', mb_substr($request->resource, 0, 80)))->when($request->action, fn ($q) => $q->where('action', mb_substr($request->action, 0, 100)))->when($request->actor, fn ($q) => $q->where('actor_id', (int) $request->actor))->when($request->from, fn ($q) => $q->whereDate('created_at', '>=', $request->from))->orderByDesc('id')->paginate(30)->withQueryString()]);
    }

    public function automation(Request $request)
    {
        $d = $request->validate(['backup_daily' => 'nullable|boolean', 'keep_backups' => 'required|integer|min:2|max:30']);
        SettingsController::put('system.automation', ['backup_daily' => $request->boolean('backup_daily'), 'keep_backups' => (int) $d['keep_backups']]);
        Audit::record('system.automation_updated', 'site_settings');

        return back()->with('status', 'Automatización guardada. Requiere Cron finito del hosting.');
    }
}
