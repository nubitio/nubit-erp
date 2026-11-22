<?php

declare(strict_types=1);

namespace App\Identity\Offline;

/** A signed offline sale whose signature cannot be accepted; the message says why, in Spanish, for the outbox. */
final class InvalidOfflineSignature extends \RuntimeException {}
