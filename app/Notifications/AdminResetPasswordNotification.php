<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(
            env('FRONTEND_URL', 'http://localhost:5173'),
            '/'
        );

        $resetUrl = $frontendUrl
            . '/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode(
                $notifiable->getEmailForPasswordReset()
            );

        return (new MailMessage)
            ->subject('Admin Password Reset')
            ->greeting('Hello!')
            ->line('You requested to reset your admin password.')
            ->action('Reset Password', $resetUrl)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not request this, you can ignore this email.');
    }
}
