<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Sale\Note;

interface NoteRepositoryInterface
{
    public function send(Note $note): DocumentResponse;

    public function pdf(Note $note): string;

    public function getStatus(Note $note): StatusCdrResult;
}
