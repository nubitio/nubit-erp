<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use Greenter\Model\Sale\Note;

interface NoteRepositoryInterface
{
    public function send(Note $note): DocumentResponse;

    public function pdf(Note $note): string;
}
