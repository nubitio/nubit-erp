<?php

declare(strict_types=1);

namespace App\CashRegister\Contract;

use DomainException;

/** The drawer cannot take the movement (closed or unknown session, bad amount, unknown category). */
final class CashMovementRejected extends DomainException {}
