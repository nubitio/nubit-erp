<?php

namespace App\Billing\Model\Response;

class CdrResponse
{
    public bool $accepted;
    public string $id;
    public string $code;
    public string $description;
    /** @var string[] */
    public array $notes;
}