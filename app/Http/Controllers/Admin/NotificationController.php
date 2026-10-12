<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => 'nullable|in:pending,processing,sent,failed,cancelled']);

        return view('admin.notifications', ['items' => DB::table('notifications_outbox')->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->orderByDesc('id')->paginate(20)->withQueryString()]);
    }

    public function retry(int $id)
    {
        $row = DB::table('notifications_outbox')->find($id);
        abort_unless($row, 404);
        abort_if(in_array($row->status, ['sent', 'processing', 'cancelled']), 422, 'Este mensaje no puede reintentarse ahora.');
        DB::table('notifications_outbox')->where('id', $id)->update(['status' => 'pending', 'retries' => 0, 'next_retry_at' => null, 'error_code' => null, 'updated_at' => now()]);
        Audit::record('notifications.retry', 'notifications_outbox', $id, ['status' => 'pending']);

        return back()->with('status', 'Mensaje pendiente de nuevo intento por Cron.');
    }
}
