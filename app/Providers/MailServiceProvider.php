<?php

namespace App\Providers;

use App\Mail\Transport\ZeptoMailTransport;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Registers the "zeptomail" mail driver.
 *
 * Once registered, MAIL_MAILER=zeptomail is all it takes: every Mailable in
 * the app goes out through Zoho without knowing it, and the test suite keeps
 * using the array mailer and Mail::fake() exactly as before.
 */
class MailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('zeptomail', function (array $config) {
            $key = (string) ($config['key'] ?? '');

            if ($key === '') {
                // Caught here rather than at the API, which would answer with
                // a generic 401 and send us looking in the wrong place.
                throw new TransportException(
                    'ZEPTOMAIL/ZOHO_MAIL_API_KEY is not set, so mail cannot be sent through Zoho.'
                );
            }

            return new ZeptoMailTransport(
                $this->app->make(HttpFactory::class),
                $key,
                (string) ($config['endpoint'] ?? 'https://api.zeptomail.com/v1.1/email'),
                (int) ($config['timeout'] ?? 15),
            );
        });
    }
}
