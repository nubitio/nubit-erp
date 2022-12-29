<?php

namespace App\Infrastructure\Service;

interface MessengerServiceInterface
{
    public function sendMessage(string $message): void;
}
