<?php

namespace App\Core\Service;

interface MessengerServiceInterface
{
    public function sendMessage(string $message): void;
}
