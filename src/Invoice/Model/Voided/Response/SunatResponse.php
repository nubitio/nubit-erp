<?php

namespace App\Invoice\Model\Voided\Response;

class SunatResponse
{
    public bool $success;
    public Error $error;
    public string $ticket;
}