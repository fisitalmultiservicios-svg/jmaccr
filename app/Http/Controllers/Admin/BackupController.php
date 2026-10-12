<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Domain\System\Backups;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BackupController extends Controller
{
    public function index(Backups $backups)
    {
        return view('admin.backups', ['backups' => $backups->inventory()]);
    }

    public function create(Backups $backups)
    {
        try {
            $name = $backups->create();

            return back()->with('status', 'Copia cifrada creada: '.$name);
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }
    }

    public function download(string $name, Backups $backups)
    {
        return response()->download($backups->path($name), $name, ['Content-Type' => 'application/octet-stream']);
    }

    public function key(Request $request, Backups $backups)
    {
        $request->validate(['current_password' => 'required|string']);
        abort_unless(Hash::check($request->current_password, $request->user()->password), 422, 'Contraseña incorrecta.');
        abort_unless(is_file($backups->keyPath()), 404, 'Crea primero una copia.');
        Audit::record('backups.key_exported', 'backups');

        return response()->download($backups->keyPath(), 'JMAC-recuperacion-privada.key', ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store']);
    }

    public function restore(Request $request, string $name, Backups $backups)
    {
        abort_if(app()->isProduction(), 403);
        $request->validate(['confirm' => 'accepted', 'phrase' => 'required|in:RESTAURAR STAGING', 'current_password' => 'required|string']);
        abort_unless(Hash::check($request->current_password, $request->user()->password), 422, 'Contraseña incorrecta.');
        try {
            $backups->restore($name);
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/admin/login')->with('status', 'Restauración terminada; inicia sesión de nuevo.');
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }
    }

    public function import(Request $request, Backups $backups)
    {
        $request->validate(['file' => 'required|file|max:262144']);
        try {
            $backups->import($request->file('file')->getRealPath());

            return back()->with('status', 'Copia cifrada importada. Restaurar verificará autenticidad y compatibilidad.');
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }
    }
}
