<?php

namespace App\Billing\Model\Response;

class SunatResponse
{
    public bool $success;
    public Error $error;
    public string $ticket;
}