<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Audit;
use App\Domain\Notifications\Outbox;
use App\Domain\Sales\Privacy;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    private function kind(): string
    {
        return request()->segment(2) === 'contacts' ? 'contacts' : 'preorders';
    }

    private function table(): string
    {
        return $this->kind() === 'contacts' ? 'contact_submissions' : 'preorders';
    }

    private function query(Request $request)
    {
        $kind = $this->kind();
        $data = $request->validate(['q' => 'nullable|string|max:120', 'status' => 'nullable|string|max:30', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $query = DB::table($this->table())->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))->when($data['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
        if (! empty($data['q'])) {
            $hash = Privacy::hash(Privacy::phone($data['q']));
            $phoneColumn = $kind === 'contacts' ? 'phone_hash' : 'customer_phone_hash';
            $query->where(fn ($q) => $q->where('public_code', 'like', '%'.$data['q'].'%')->orWhere($phoneColumn, $hash));
        }

        return $query;
    }

    private function decode(object $row): object
    {
        return Privacy::person($row, $this->kind() === 'contacts' ? ['name', 'phone', 'email', 'company', 'message'] : ['customer_name', 'customer_phone', 'customer_email', 'customer_company', 'observations']);
    }

    public function index(Request $request)
    {
        $rows = $this->query($request)->orderByDesc('id')->paginate(20)->withQueryString();
        foreach ($rows->items() as $row) {
            $this->decode($row);
        }

        return view('admin.sales', ['kind' => $this->kind(), 'items' => $rows]);
    }

    public function show(int $id)
    {
        $kind = $this->kind();
        $item = DB::table($this->table())->find($id);
        abort_unless($item, 404);
        $this->decode($item);
        $events = DB::table($kind === 'contacts' ? 'contact_events' : 'preorder_events')->where($kind === 'contacts' ? 'contact_id' : 'preorder_id', $id)->orderBy('id')->get();
        foreach ($events as $event) {
            $event->note = Privacy::decrypt($event->note);
        }

        return view('admin.sale-detail', ['kind' => $kind, 'item' => $item, 'events' => $events, 'lines' => $kind === 'preorders' ? DB::table('preorder_items')->where('preorder_id', $id)->get() : collect(), 'users' => User::where('status', 'active')->whereHas('roles.permissions', fn ($q) => $q->where('permissions.name', $kind.'.view'))->get(['users.id', 'users.name']), 'notifications' => DB::table('notifications_outbox')->where('aggregate_id', $id)->where('event_type', 'like', $kind === 'contacts' ? 'contact.%' : 'preorder.%')->get(['id', 'status', 'error_code', 'sent_at'])]);
    }

    public function update(Request $request, int $id)
    {
        $kind = $this->kind();
        $states = $kind === 'contacts' ? ['nuevo', 'en_proceso', 'respondido', 'cerrado'] : ['recibida', 'en_revision', 'cotizada', 'gestionada', 'cerrada', 'cancelada'];
        $data = $request->validate(['status' => 'required|in:'.implode(',', $states), 'assigned_to' => 'nullable|integer|exists:users,id', 'note' => 'nullable|string|max:3000', 'override' => 'nullable|boolean']);
        DB::transaction(function () use ($id, $kind, $data, $request) {
            $row = DB::table($this->table())->lockForUpdate()->find($id);
            abort_unless($row, 404);
            if ((int) ($data['assigned_to'] ?? 0) !== (int) $row->assigned_to) {
                if ($kind === 'preorders') {
                    abort_unless($request->user()->hasPermission('preorders.assign'), 403);
                }if (! empty($data['assigned_to'])) {
                    $assigned = User::find($data['assigned_to']);
                    abort_unless($assigned && $assigned->hasPermission($kind.'.view'), 422, 'La persona asignada no puede acceder a esta bandeja.');
                }
            }
            $transitions = ['recibida' => ['en_revision', 'cancelada'], 'en_revision' => ['cotizada', 'cancelada'], 'cotizada' => ['gestionada', 'cancelada'], 'gestionada' => ['cerrada', 'cancelada'], 'cerrada' => [], 'cancelada' => [], 'nuevo' => ['en_proceso', 'cerrado'], 'en_proceso' => ['respondido', 'cerrado'], 'respondido' => ['cerrado'], 'cerrado' => []];
            if ($data['status'] !== $row->status && ! in_array($data['status'], $transitions[$row->status] ?? [], true)) {
                abort_unless(($data['override'] ?? false) && $request->user()->hasPermission('users.manage'), 422, 'La transición de estado no está permitida.');
            }
            DB::table($this->table())->where('id', $id)->update(['status' => $data['status'], 'assigned_to' => $data['assigned_to'] ?? null, 'updated_at' => now()]);
            $event = ['actor_id' => $request->user()->id, 'note' => Privacy::encrypt($data['note'] ?? ''), 'created_at' => now()];
            if ($kind === 'preorders') {
                $event += ['preorder_id' => $id, 'from_status' => $row->status, 'to_status' => $data['status']];
                $eventId = DB::table('preorder_events')->insertGetId($event);
                if ($data['status'] !== $row->status) {
                    Outbox::enqueue('preorder.status.'.$eventId, $id, $row->public_code, ['name' => Privacy::decrypt($row->customer_name), 'email' => Privacy::decrypt($row->customer_email)], ['status' => $data['status']], (bool) $row->email_opt_in);
                }
            } else {
                DB::table('contact_events')->insert($event + ['contact_id' => $id, 'status' => $data['status']]);
            }Audit::record($kind.'.updated', $this->table(), $id, ['status' => $data['status']]);
        }, 3);

        return back()->with('status', 'Seguimiento guardado.');
    }

    public function export(Request $request)
    {
        $kind = $this->kind();
        Audit::record($kind.'.exported', $this->table());

        return response()->streamDownload(function () use ($request, $kind) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Consecutivo', 'Fecha', 'Estado', 'Nombre', 'Teléfono', 'Empresa', 'Correo'], ',', '"', '');
            foreach ($this->query($request)->orderBy('id')->limit(10000)->cursor() as $row) {
                $this->decode($row);
                $prefix = $kind === 'contacts' ? '' : 'customer_';
                $cells = [$row->public_code, $row->created_at, $row->status, $row->{$prefix.'name'}, $row->{$prefix.'phone'}, $row->{$prefix.'company'}, $row->{$prefix.'email'}];
                $cells = array_map(fn ($cell) => preg_match('/^[=+@\-\t\r]/', (string) $cell) ? "'".$cell : $cell, $cells);
                fputcsv($stream, $cells, ',', '"', '');
            }fclose($stream);
        }, 'jmac-'.$kind.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function anonymize(Request $request, int $id)
    {
        $request->validate(['confirm' => 'accepted']);
        $kind = $this->kind();
        DB::transaction(function () use ($kind, $id) {
            $fields = $kind === 'contacts' ? ['name', 'phone', 'email', 'company', 'message', 'phone_hash', 'email_hash'] : ['customer_name', 'customer_phone', 'customer_email', 'customer_company', 'observations', 'customer_phone_hash'];
            $update = array_fill_keys($fields, null);
            $update += ['email_opt_in' => false, 'request_hash' => null, 'session_hash' => null, 'updated_at' => now()];
            if ($kind === 'preorders') {
                unset($update['request_hash'],$update['session_hash']);
            }DB::table($this->table())->where('id', $id)->update($update);
            DB::table($kind === 'contacts' ? 'contact_events' : 'preorder_events')->where($kind === 'contacts' ? 'contact_id' : 'preorder_id', $id)->update(['note' => null]);
            foreach (DB::table('notifications_outbox')->where('aggregate_id', $id)->where('event_type', 'like', $kind === 'contacts' ? 'contact.%' : 'preorder.%')->get() as $message) {
                DB::table('notifications_outbox')->where('id', $message->id)->update(['recipient' => null, 'payload_json' => Privacy::encrypt(json_encode(['code' => 'Registro anonimizado', 'event' => $message->event_type, 'audience' => 'owner', 'customer' => [], 'extra' => []])), 'status' => 'cancelled']);
            }Audit::record($kind.'.anonymized', $this->table(), $id);
        });

        return back()->with('status', 'Datos personales anonimizados; histórico técnico preservado.');
    }
}
