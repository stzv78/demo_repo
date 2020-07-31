<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class DepositCreated extends Notification
{
    use Queueable;

    public $langPrefix = 'notifications.deposits.created.';

    private $markdown = 'notifications::deposits.created';

    public $deposit;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
     public function __construct(Deposit $deposit)
     {
         $this->deposit = $deposit;
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
        return (new MailMessage)
            ->greeting(Lang::get($this->langPrefix . 'greeting'))
            ->subject(Lang::get($this->langPrefix . 'subject'))
            ->line(Lang::get($this->langPrefix . 'lines'))
            ->action(Lang::get($this->langPrefix . 'action'), url('deposits', ['deposit' => $this->deposit]))
            ->markdown($this->markdown, ['deposit' => $this->deposit]);
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
