<?php

namespace Common\Notifications;

use Common\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ContactPageMessage extends Notification
{
    use Queueable;

    private $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $siteName = app(Settings::class)->get('branding.site_name');
        $userEmail = $this->message['email'];

        // ses only allows sending emails from authorized addresses
        // so should send mail from address specified in config
        $useDefaults = config('mail.driver') === 'ses';

        return (new MailMessage)
            ->subject("New message via $siteName contact page.")
            ->greeting("New message via $siteName contact page from '$userEmail'")
            ->salutation(' ')
            ->from(
                $useDefaults ? config('mail.from.address') : $userEmail,
                $useDefaults ? config('mail.from.name') : $this->message['name']
            )
            ->line($this->message['message']);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
