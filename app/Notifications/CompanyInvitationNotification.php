<?php

namespace App\Notifications;

use App\Models\CompanyInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyInvitationNotification extends Notification
{
    public function __construct(public CompanyInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->invitation->loadMissing('company');
        $companyName = str_replace(["\r", "\n"], '', (string) $this->invitation->company?->name);
        $url = route('invitations.accept', ['token' => $this->invitation->token]);

        return (new MailMessage)
            ->subject('Inbjudan till '.$companyName.' på Klartfoto')
            ->greeting('Hej!')
            ->line($companyName.' bjuder in dig att lägga upp uppdrag och se företagets rapporter.')
            ->action('Skapa ditt konto', $url)
            ->line('Länken fungerar i 14 dagar. Om du inte väntade dig inbjudan kan du ignorera mejlet.');
    }
}
