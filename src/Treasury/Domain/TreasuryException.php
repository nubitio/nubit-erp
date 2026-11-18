<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

use DomainException;

/** Base of every rule violation Treasury raises; callers translate it to a 422. */
class TreasuryException extends DomainException {}
