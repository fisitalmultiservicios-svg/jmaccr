<?php

namespace App\Domain\Notifications;

use App\Domain\Sales\Privacy;
use App\Domain\Site\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class Outbox
{
    public static function direct(string $event, int $aggregate, string $recipient, array $data): void
    {
        DB::table('notifications_outbox')->insert(['deduplication_key' => $event.':'.Str::uuid(), 'event_type' => $event, 'aggregate_id' => $aggregate, 'recipient' => Privacy::encrypt($recipient), 'channel' => 'email', 'payload_json' => Privacy::encrypt(json_encode(['event' => $event, 'code' => $event === 'auth.reset' ? 'Acceso seguro' : 'Prueba SMTP', 'data' => $data])), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function enqueue(string $event, int $aggregate, string $code, array $customer, array $extra, bool $emailOptIn): void
    {
        $business = Site::setting('business', []);
        $audiences = ['owner' => $business['notify_email'] ?? ''];
        if ($emailOptIn && ! empty($customer['email'])) {
            $audiences['customer'] = $customer['email'];
        }
        foreach ($audiences as $audience => $recipient) {
            $payload = ['audience' => $audience, 'code' => $code, 'event' => $event, 'customer' => $audience === 'owner' ? $customer : ['name' => $customer['name'] ?? ''], 'extra' => $extra];
            DB::table('notifications_outbox')->insertOrIgnore(['deduplication_key' => $event.':'.$aggregate.':'.$audience, 'event_type' => $event, 'aggregate_id' => $aggregate, 'recipient' => Privacy::encrypt($recipient), 'channel' => 'email', 'payload_json' => Privacy::encrypt(json_encode($payload)), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function process(int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        $summary = ['sent' => 0, 'failed' => 0, 'configuration_pending' => 0];
        $mailer = MailConfig::configure();
        for ($i = 0; $i < $limit; $i++) {
            $claim = Str::uuid()->toString();
            $row = DB::transaction(function () use ($claim) {
                $row = DB::table('notifications_outbox')->where('retries', '<', 5)->where(function ($q) {
                    $q->whereIn('status', ['pending', 'failed'])->where(fn ($s) => $s->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))->orWhere(fn ($s) => $s->where('status', 'processing')->where('locked_at', '<', now()->subMinutes(15)));
                })->orderBy('id')->lockForUpdate()->first();
                if (! $row) {
                    return null;
                }DB::table('notifications_outbox')->where('id', $row->id)->update(['status' => 'processing', 'claim_token' => $claim, 'locked_at' => now(), 'updated_at' => now()]);

                return $row;
            }, 3);
            if (! $row) {
                break;
            }
            $payload = json_decode(Privacy::decrypt($row->payload_json), true);
            $recipient = Privacy::decrypt($row->recipient);
            if (! $recipient && ($payload['audience'] ?? '') === 'owner') {
                $recipient = Site::setting('business', [])['notify_email'] ?? '';
            }
            if (! MailConfig::available($mailer) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                DB::table('notifications_outbox')->where('id', $row->id)->where('claim_token', $claim)->update(['status' => 'pending', 'error_code' => 'CONFIGURATION_REQUIRED', 'next_retry_at' => now()->addMinutes(30), 'locked_at' => null, 'claim_token' => null, 'updated_at' => now()]);
                $summary['configuration_pending']++;

                continue;
            }
            try {
                Mail::mailer($mailer)->send(str_starts_with($payload['event'], 'auth.') || $payload['event'] === 'smtp.test' ? ['html' => 'mail.system', 'text' => 'mail.system-text'] : ['html' => 'mail.notification', 'text' => 'mail.notification-text'], ['payload' => $payload], function ($message) use ($recipient, $payload) {
                    $message->to($recipient)->subject('JMAC · '.$payload['code']);
                });
                DB::table('notifications_outbox')->where('id', $row->id)->where('claim_token', $claim)->update(['status' => 'sent', 'sent_at' => now(), 'recipient' => Privacy::encrypt($recipient), 'error_code' => null, 'next_retry_at' => null, 'locked_at' => null, 'claim_token' => null, 'updated_at' => now()]);
                $summary['sent']++;
            } catch (\Throwable $exception) {
                $retries = (int) $row->retries + 1;
                DB::table('notifications_outbox')->where('id', $row->id)->where('claim_token', $claim)->update(['status' => 'failed', 'retries' => $retries, 'error_code' => 'SMTP_DELIVERY_FAILED', 'next_retry_at' => now()->addMinutes(min(1440, 5 * (2 ** $retries))), 'locked_at' => null, 'claim_token' => null, 'updated_at' => now()]);
                $summary['failed']++;
            }
        }

        return $summary;
    }
}
