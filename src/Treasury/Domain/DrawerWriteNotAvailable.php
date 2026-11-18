<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

/** Recording into a cash drawer account arrives with WP2 (it goes through the cash register's own ledger). */
final class DrawerWriteNotAvailable extends TreasuryException {}
