<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramNotification extends Notification
{
    use Queueable;

    private string $format = 'Markdown';

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(public array $messages, private ?string $chatId = null)
    {
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

    /**
     * Set the format for this notification (Markdown or HTML)
     *
     * @return $this
     */
    public function formatAs(string $format)
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Convert notification to Telegram format
     *
     * @param  mixed  $notifiable
     * @return TelegramMessage
     */
    public function toTelegram($notifiable)
    {
        // Use provided chat ID, or notifiable value if string, or config value
        $chatId = $this->chatId ??
            (is_string($notifiable) ? $notifiable : config('services.telegram.chat_id'));

        $tele = TelegramMessage::create()
            ->to($chatId)
            ->options(['parse_mode' => $this->format]);

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
}
