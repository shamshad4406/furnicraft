<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Password reset mail that links to the right panel (admin or storefront).
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $guard = 'admin',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route(
            $this->guard === 'admin' ? 'admin.password.reset' : 'customer.password.reset',
            ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]
        );

        $minutes = Config::get('auth.passwords.'.($this->guard === 'admin' ? 'users' : 'customers').'.expire', 60);
        $company = (string) settings('company_name', 'FurniCraft');

        return (new MailMessage)
            ->subject($company.' — Reset your password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset the password for your '.$company.' account.')
            ->action('Reset Password', $url)
            ->line('This link will expire in '.$minutes.' minutes.')
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('Regards, '.$company);
    }
}
