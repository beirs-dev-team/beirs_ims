<?php

namespace App\Services;

use App\Exceptions\MessagingException;
use GuzzleHttp\Client;

class TwilioClient
{
    public function __construct(
        private readonly Client $http = new Client(['base_uri' => 'https://api.twilio.com/2010-04-01/'])
    ) {
    }

    public function sendSms(string $to, string $message): void
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $from = config('services.twilio.from');

        if (!$accountSid || !$authToken || !$from) {
            throw MessagingException::failed(['message' => 'Twilio credentials are missing.']);
        }

        $response = $this->http->post("Accounts/{$accountSid}/Messages.json", [
            'auth' => [$accountSid, $authToken],
            'form_params' => [
                'To' => $to,
                'From' => $from,
                'Body' => $message,
            ],
            'timeout' => 15,
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw MessagingException::failed([
                'message' => "Twilio SMS failed with status {$status}",
            ]);
        }
    }
}
