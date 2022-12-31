<?php

namespace App\Core\Infrastructure\Service;

interface MessengerServiceInterface
{
    public function sendMessage(string $message): void;
}
