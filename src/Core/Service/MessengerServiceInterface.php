<?php

declare(strict_types=1);

namespace App\Core\Service;

interface MessengerServiceInterface
{
    public function sendMessage(string $message): void;
}
