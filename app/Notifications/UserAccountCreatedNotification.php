<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAccountCreatedNotification extends Notification
{
    public function __construct(
        public readonly string $temporaryPassword,
        public readonly string $applicationUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu acesso ao FISIO1 foi criado')
            ->view('mail.user-account-created', [
                'user' => $notifiable,
                'temporaryPassword' => $this->temporaryPassword,
                'applicationUrl' => $this->applicationUrl,
            ]);
    }
}
