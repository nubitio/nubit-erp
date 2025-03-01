<?php

namespace App\Core\Infrastructure\Service;

use MessageBird\Client;
use MessageBird\Objects\Message;

class SmsService implements MessengerServiceInterface
{
    /**
     * @throws \MessageBird\Exceptions\BalanceException
     * @throws \MessageBird\Exceptions\AuthenticateException
     * @throws \JsonException
     * @throws \MessageBird\Exceptions\HttpException
     */
    #[\Override]
    public function sendMessage(string $message): void
    {
        $transport = new Client($_ENV['MESSAGEBIRD_API_KEY']);

        $sms = new Message();
        $sms->originator = $_ENV['MESSAGEBIRD_ORIGINATOR'];
        $sms->recipients = ['+51929739711', '+51925244066'];
        $sms->body = $message;

        $transport->messages->create($sms);
    }
}
