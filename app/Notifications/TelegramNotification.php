<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramNotification extends Notification
{
    use Queueable;

    protected ?string $chatId;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(public array $messages, ?string $chatId = null)
    {
        $this->chatId = $chatId;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['telegram'];
    }

    public function toTelegram($notifiable)
    {
        return $this->builtMessages();
    }

    private function builtMessages()
    {
        // Use provided chat ID, fall back to notifiable's routeNotificationFor method,
        // then fall back to config value
        $groupChatId = $this->chatId ?? (is_string($notifiable = $this->getNotifiable()) ? $notifiable : config('services.telegram.chat_id'));

        $tele = TelegramMessage::create()
            ->to($groupChatId);

        foreach ($this->messages as $idx => $m) {
            if (is_numeric($idx) || $idx === 'line') {
                $tele = $tele->line($m);
            } elseif ($idx === 'button') {
                foreach ($m as $text => $link) {
                    $tele = $tele->button($text, $link);
                }
            } else {
                throw new \Exception('Invalid telegram type');
            }
        }

        return $tele;
    }

    /**
     * Get the notifiable entity
     *
     * @return mixed
     */
    private function getNotifiable()
    {
        return app('Illuminate\Notifications\ChannelManager')
            ->deliversVia('telegram')
            ->getNotifiable();
    }
}
