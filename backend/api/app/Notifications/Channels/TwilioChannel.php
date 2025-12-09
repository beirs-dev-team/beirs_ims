<?php

namespace App\Notifications\Channels;

use App\Notifications\Contracts\SmsMessage;
use App\Services\TwilioClient;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class TwilioChannel
{
    public function __construct(private readonly TwilioClient $client)
    {
    }

    /**
     * @param object $notifiable The notifiable entity (e.g., UserProfile)
     * @param Notification&SmsMessage $notification Notification that can format an SMS payload
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (!$notification instanceof SmsMessage) {
            return;
        }

        $data = $notification->toSms($notifiable);
        if (!empty($data['to']) && !empty($data['message'])) {
            try {
                $this->client->sendSms($data['to'], $data['message']);
            } catch (Throwable $e) {
                Log::warning('SMS delivery skipped; continuing without SMS.', [
                    'provider' => 'twilio',
                    'notifiable' => get_class($notifiable),
                    'to' => $data['to'],
                    'message' => $data['message'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
