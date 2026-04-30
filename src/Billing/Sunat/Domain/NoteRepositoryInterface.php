<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Domain;

use App\Billing\Sunat\Model\Response\DocumentResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Sale\Note;

interface NoteRepositoryInterface
{
    public function send(Note $note): DocumentResponse;

    public function pdf(Note $note): string;

    public function getStatus(Note $note): StatusCdrResult;
}
