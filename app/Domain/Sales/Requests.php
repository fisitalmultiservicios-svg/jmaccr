<?php

namespace App\Domain\Sales;

use App\Domain\Auth\Audit;
use App\Domain\Notifications\Outbox;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Requests
{
    public static function sequence(string $name, string $prefix): array
    {
        $year = (int) now()->format('Y');
        DB::table('sequences')->insertOrIgnore(['sequence_name' => $name, 'year' => $year, 'next_number' => 1]);
        $row = DB::table('sequences')->where('sequence_name', $name)->where('year', $year)->lockForUpdate()->first();
        $seq = (int) $row->next_number;
        DB::table('sequences')->where('id', $row->id)->update(['next_number' => $seq + 1]);

        return [$year, $seq, $prefix.'-'.$year.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT)];
    }

    public function preorder(array $data, string $session): array
    {
        Privacy::requireConsent($data['consent_version']);
        $customer = $data['customer'];
        $customer['phone'] = Privacy::phone($customer['phone']);
        $customer['name'] = trim($customer['name']);
        $customer['email'] = mb_strtolower(trim($customer['email'] ?? ''));
        $customer['company'] = trim($customer['company'] ?? '');
        $items = array_map(fn ($line) => ['product_id' => (int) $line['product_id'], 'quantity' => (int) $line['quantity']], $data['items']);
        usort($items, fn ($a, $b) => $a['product_id'] <=> $b['product_id']);
        $payload = ['customer' => $customer, 'items' => $items, 'notes' => $data['notes'] ?? '', 'consent_version' => $data['consent_version'], 'email_opt_in' => (bool) ($data['email_opt_in'] ?? false)];
        $hash = Privacy::hash(json_encode($payload));
        $sessionHash = Privacy::hash($session);
        $key = $data['idempotency_key'];
        $execute = function () use ($key, $hash, $sessionHash, $payload) {
            $existing = DB::table('preorders')->where('idempotency_key', $key)->first();
            if ($existing) {
                return $this->existing($existing, $hash, $sessionHash);
            }
            $ids = array_column($payload['items'], 'product_id');
            $products = DB::table('products')->whereIn('id', $ids)->where('status', 'published')->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== count($ids)) {
                throw ValidationException::withMessages(['items' => 'Un producto dejó de estar disponible. Revisa tu lista antes de enviar.']);
            }
            [$year,$seq,$code] = self::sequence('preorders', 'JMAC-PV');
            $customer = $payload['customer'];
            $fields = [];
            foreach (['name', 'phone', 'email', 'company'] as $field) {
                $fields['customer_'.$field] = Privacy::encrypt($customer[$field] ?? '');
            }
            $id = DB::table('preorders')->insertGetId($fields + ['year' => $year, 'seq' => $seq, 'public_code' => $code, 'idempotency_key' => $key, 'request_hash' => $hash, 'session_hash' => $sessionHash, 'customer_phone_hash' => Privacy::hash($customer['phone']), 'observations' => Privacy::encrypt($payload['notes']), 'consent_version' => $payload['consent_version'], 'email_opt_in' => $payload['email_opt_in'], 'status' => 'recibida', 'created_at' => now(), 'updated_at' => now()]);
            $lines = [];
            foreach ($payload['items'] as $line) {
                $product = $products->get($line['product_id']);
                $attributes = DB::table('product_attributes')->where('product_id', $product->id)->orderBy('position')->get(['name', 'value', 'unit'])->toArray();
                $snapshot = ['preorder_id' => $id, 'product_id' => $product->id, 'sku_snapshot' => $product->sku, 'name_snapshot' => $product->name, 'unit_snapshot' => $product->unit, 'quantity' => $line['quantity'], 'metadata_snapshot' => json_encode(['attributes' => $attributes]), 'created_at' => now(), 'updated_at' => now()];
                DB::table('preorder_items')->insert($snapshot);
                $lines[] = ['sku' => $product->sku, 'name' => $product->name, 'quantity' => $line['quantity']];
            }
            DB::table('preorder_events')->insert(['preorder_id' => $id, 'to_status' => 'recibida', 'created_at' => now()]);
            Outbox::enqueue('preorder.received', $id, $code, $customer, ['lines' => $lines, 'notes' => $payload['notes']], $payload['email_opt_in']);
            Audit::record('preorders.received', 'preorders', $id, ['status' => 'recibida', 'count' => count($lines)]);

            return ['code' => $code, 'created' => true];
        };
        try {
            return DB::transaction($execute, 5);
        } catch (QueryException $exception) {
            $existing = DB::table('preorders')->where('idempotency_key', $key)->first();
            if ($existing) {
                return $this->existing($existing, $hash, $sessionHash);
            }throw $exception;
        }
    }

    public function contact(array $data, string $session): array
    {
        Privacy::requireConsent($data['consent_version']);
        $payload = array_intersect_key($data, array_flip(['name', 'phone', 'email', 'company', 'message', 'consent_version', 'email_opt_in']));
        $payload['phone'] = Privacy::phone($data['phone']);
        $payload['email'] = mb_strtolower(trim($data['email'] ?? ''));
        $payload['company'] = $data['company'] ?? '';
        $payload['email_opt_in'] = (bool) ($data['email_opt_in'] ?? false);
        $hash = Privacy::hash(json_encode($payload));
        $sessionHash = Privacy::hash($session);
        $key = $data['idempotency_key'];
        $execute = function () use ($payload, $hash, $sessionHash, $key) {
            $existing = DB::table('contact_submissions')->where('idempotency_key', $key)->first();
            if ($existing) {
                return $this->existing($existing, $hash, $sessionHash);
            }[$year,$seq,$code] = self::sequence('contacts', 'JMAC-CO');
            $fields = [];
            foreach (['name', 'phone', 'email', 'company', 'message'] as $field) {
                $fields[$field] = Privacy::encrypt($payload[$field] ?? '');
            }$id = DB::table('contact_submissions')->insertGetId($fields + ['public_code' => $code, 'idempotency_key' => $key, 'request_hash' => $hash, 'session_hash' => $sessionHash, 'phone_hash' => Privacy::hash($payload['phone']), 'email_hash' => Privacy::hash($payload['email']), 'consent_version' => $payload['consent_version'], 'email_opt_in' => $payload['email_opt_in'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('contact_events')->insert(['contact_id' => $id, 'status' => 'nuevo', 'created_at' => now()]);
            Outbox::enqueue('contact.received', $id, $code, $payload, ['message' => $payload['message']], $payload['email_opt_in']);
            Audit::record('contacts.received', 'contact_submissions', $id, ['status' => 'nuevo']);

            return ['code' => $code, 'created' => true];
        };
        try {
            return DB::transaction($execute, 5);
        } catch (QueryException $exception) {
            $existing = DB::table('contact_submissions')->where('idempotency_key', $key)->first();
            if ($existing) {
                return $this->existing($existing, $hash, $sessionHash);
            }throw $exception;
        }
    }

    private function existing(object $existing, string $hash, string $sessionHash): array
    {
        abort_unless(hash_equals($existing->request_hash ?? '', $hash) && hash_equals($existing->session_hash ?? '', $sessionHash), 409, 'El token pertenece a otro intento. Vuelve a revisar el formulario.');

        return ['code' => $existing->public_code, 'created' => false];
    }
}
