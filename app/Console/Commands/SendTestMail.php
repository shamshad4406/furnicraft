<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Proves the mail setup works without placing an order.
 *
 * Mail problems are usually credentials or a sender address the provider has
 * not verified, and both fail the same way from the outside: the customer
 * hears nothing. This turns that into an answer on the command line.
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test {email : Where to send it}
                            {--mailer= : Override the configured mailer, e.g. log}';

    protected $description = 'Send a test email to check the mail configuration';

    public function handle(): int
    {
        $to = (string) $this->argument('email');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error($to.' is not a valid email address.');

            return self::FAILURE;
        }

        $mailer = $this->option('mailer') ?: config('mail.default');

        $this->line('');
        $this->line('  Mailer .......... '.$mailer);
        $this->line('  From ............ '.config('mail.from.name').' <'.config('mail.from.address').'>');

        if ($mailer === 'zeptomail') {
            $key = (string) config('mail.mailers.zeptomail.key');

            $this->line('  Endpoint ........ '.config('mail.mailers.zeptomail.endpoint'));
            // Never print the key; its length and tail are enough to tell
            // whether the right one is loaded.
            $this->line('  API key ......... '.($key === ''
                ? '<not set>'
                : strlen($key).' chars, ending '.substr($key, -6)));
        }

        $this->line('  To .............. '.$to);
        $this->line('');

        try {
            Mail::mailer($mailer)->raw(
                "This is a test message from FurniCraft.\n\n".
                "If you are reading it, order confirmations will reach your customers too.\n\n".
                'Sent '.now()->format('d M Y, H:i').'.',
                fn (Message $message) => $message->to($to)->subject('FurniCraft mail test')
            );
        } catch (\Throwable $e) {
            $this->error('Could not send.');
            $this->line('  '.$e->getMessage());
            $this->line('');
            $this->line('Common causes:');
            $this->line('  · the From address is not a verified sender in ZeptoMail');
            $this->line('  · the API key is for a different Mail Agent, or was revoked');
            $this->line('  · the endpoint is .com but the account is hosted on .in (or the reverse)');

            return self::FAILURE;
        }

        $this->info('Sent. Check the inbox'.($mailer === 'log' ? ' — or storage/logs, with the log mailer.' : ', and the spam folder.'));

        return self::SUCCESS;
    }
}
