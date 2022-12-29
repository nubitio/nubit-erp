<?php

namespace App\Billing\Model\Response;

class StatusResponse
{
    public string $cdrZip;
    public CdrResponse $cdrResponse;
    public bool $success;
    public Error $error;
    public string $code;
}
