<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class SwedishResetPassword extends ResetPassword
{
    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Återställ ditt lösenord till Klartfoto')
            ->greeting('Hej!')
            ->line('Vi har fått en begäran om att byta lösenord för ditt konto.')
            ->action('Välj nytt lösenord', $url)
            ->line('Länken fungerar i 60 minuter. Om du inte bad om ett nytt lösenord kan du ignorera mejlet.');
    }
}
