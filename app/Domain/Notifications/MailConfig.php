<?php

namespace App\Domain\Notifications;

use App\Domain\Site\Site;
use Illuminate\Support\Facades\Crypt;

final class MailConfig
{
    public static function configure(): string
    {
        $stored = Site::setting('smtp.private');
        if ($stored) {
            $smtp = json_decode(Crypt::decryptString($stored['ciphertext']), true);
            config(['mail.mailers.jmac_smtp' => ['transport' => 'smtp', 'scheme' => $smtp['scheme'], 'host' => $smtp['host'], 'port' => $smtp['port'], 'username' => $smtp['username'], 'password' => $smtp['password'], 'timeout' => 15, 'local_domain' => parse_url(config('app.url'), PHP_URL_HOST)], 'mail.from.address' => $smtp['from_address'], 'mail.from.name' => $smtp['from_name']]);

            return 'jmac_smtp';
        }

        return config('mail.default');
    }

    public static function available(string $mailer): bool
    {
        $transport = config('mail.mailers.'.$mailer.'.transport');

        return ($transport === 'smtp' && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) || (app()->environment('testing') && $transport === 'array');
    }
}
