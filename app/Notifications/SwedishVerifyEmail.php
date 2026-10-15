<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class SwedishVerifyEmail extends VerifyEmail
{
    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $address = str_replace(["\r", "\n"], '', (string) $notifiable->getEmailForVerification());

        return (new MailMessage)
            ->subject('Bekräfta din e-postadress')
            ->greeting('Hej!')
            ->line('Bekräfta att '.$address.' är din adress. Du kan använda Klartfoto även innan du bekräftar.')
            ->action('Bekräfta e-postadressen', $this->verificationUrl($notifiable))
            ->line('Länken fungerar i 60 minuter. Om du inte skapade kontot kan du ignorera mejlet.');
    }
}
