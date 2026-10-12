<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Domain\Cms\Blocks;
use App\Domain\Sales\Privacy;
use App\Domain\Site\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings', ['business' => Site::setting('business', []), 'privacy' => Privacy::legal(), 'catalog' => Site::setting('catalog.ui', []), 'smtpConfigured' => (bool) Site::setting('smtp.private')]);
    }

    public static function put(string $key, array $value): void
    {
        DB::table('site_settings')->updateOrInsert(['key' => $key], ['json_value' => json_encode($value), 'scope' => 'private', 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function save(Request $request)
    {
        $data = $request->validate(['company_name' => 'required|string|max:180', 'phone' => 'nullable|string|max:40|regex:/^[+0-9 ()-]+$/', 'whatsapp' => 'nullable|regex:/^\+[1-9][0-9]{7,14}$/', 'email' => 'nullable|email|max:254', 'notify_email' => 'nullable|email|max:254', 'address' => 'nullable|string|max:500', 'hours' => 'nullable|string|max:500', 'map_url' => 'nullable|string|max:1000', 'facebook' => 'nullable|string|max:1000', 'instagram' => 'nullable|string|max:1000', 'catalog_heading' => 'required|string|max:180', 'empty_heading' => 'required|string|max:180', 'empty_body' => 'required|string|max:1000', 'cart_label' => 'required|string|max:180', 'max_quantity' => 'required|integer|min:1|max:999']);
        foreach (['map_url', 'facebook', 'instagram'] as $field) {
            if (! empty($data[$field])) {
                abort_unless(Blocks::safeUrl($data[$field]), 422, 'Enlace externo inseguro.');
            }
        }DB::transaction(function () use ($data) {
            self::put('business', array_intersect_key($data, array_flip(['company_name', 'phone', 'whatsapp', 'email', 'notify_email', 'address', 'hours', 'map_url', 'facebook', 'instagram'])));
            self::put('catalog.ui', ['heading' => $data['catalog_heading'], 'empty_heading' => $data['empty_heading'], 'empty_body' => $data['empty_body'], 'cart_label' => $data['cart_label'], 'max_quantity' => (int) $data['max_quantity']]);
            Audit::record('settings.business_updated', 'site_settings');
        });

        return back()->with('status', 'Empresa y catálogo configurados.');
    }

    public function privacy(Request $request)
    {
        $data = $request->validate(['version' => 'required|string|max:80', 'body' => 'required|string|min:50|max:50000', 'owner_approved' => 'accepted']);
        DB::transaction(function () use ($data) {
            self::put('privacy.document', ['approved' => true, 'version' => $data['version'], 'body' => Blocks::richText($data['body']), 'approved_at' => now()->toIso8601String(), 'approved_by' => auth()->id()]);
            Audit::record('settings.privacy_approved', 'site_settings');
        });

        return back()->with('status', 'Política aprobada. Formularios habilitados.');
    }

    public function smtp(Request $request)
    {
        $data = $request->validate(['host' => 'required|regex:/^[a-zA-Z0-9.-]+$/|max:253', 'port' => 'required|integer|min:1|max:65535', 'scheme' => 'required|in:smtp,smtps', 'username' => 'required|string|max:254', 'password' => 'nullable|string|max:1000', 'from_address' => 'required|email|max:254', 'from_name' => 'required|string|max:180']);
        if (empty($data['password'])) {
            $old = Site::setting('smtp.private');
            abort_unless($old, 422, 'La primera configuración requiere contraseña.');
            $data['password'] = json_decode(Crypt::decryptString($old['ciphertext']), true)['password'];
        }self::put('smtp.private', ['ciphertext' => Crypt::encryptString(json_encode($data))]);
        Audit::record('settings.smtp_updated', 'site_settings');

        return back()->with('status', 'SMTP guardado cifrado. La entrega aún debe probarse con tu proveedor.');
    }
}
