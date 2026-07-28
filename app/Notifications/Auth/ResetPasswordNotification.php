<?php

namespace App\Notifications\Auth;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(
        public string $token,
        public string $url,
        public int $expirationInMinutes,
    ) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->action(__('Reset Password'), $this->url)
            ->line(__('auth.passwords.expire', ['minutes' => $this->expirationInMinutes]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'url' => $this->url,
            'expirationInMinutes' => $this->expirationInMinutes,
        ];
    }
}
