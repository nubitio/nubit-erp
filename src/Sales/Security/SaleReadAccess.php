<?php

declare(strict_types=1);

namespace App\Sales\Security;

use App\Identity\Security\Security;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Who may read a sale's receipt, its SUNAT state or send it by e-mail.
 *
 * `sales.view` reads every sale of the branch. `sales.view_own` is the narrower grant a cashier needs
 * for what the point of sale does after charging (print the ticket, show the SUNAT state, e-mail the
 * receipt): it is limited to sales the user rang up themselves, so the POS works without giving the
 * cashier the branch's sales ledger.
 */
readonly class SaleReadAccess
{
    public function __construct(
        private Connection $connection,
        private Security $security,
    ) {}

    public function assertCanReadSale(string $saleId): void
    {
        if ($this->security->hasPermission('sales.view')) {
            return;
        }

        if ($this->security->hasPermission('sales.view_own') && $this->isOwnSale($saleId)) {
            return;
        }

        throw new AccessDeniedException();
    }

    public function assertCanReadTaxDocument(string $taxDocumentId): void
    {
        if ($this->security->hasPermission('sales.view')) {
            return;
        }

        if ($this->security->hasPermission('sales.view_own')) {
            $saleId = $this->connection->fetchOne('SELECT sale_id FROM tax_document WHERE tax_document_id = ?', [
                $taxDocumentId,
            ]);

            // A document without a sale (legacy, or a credit note) is nobody's own sale.
            if (is_string($saleId) && $this->isOwnSale($saleId)) {
                return;
            }
        }

        throw new AccessDeniedException();
    }

    private function isOwnSale(string $saleId): bool
    {
        $user = $this->security->connectedUser();
        if ($user === null) {
            return false;
        }

        $seller = $this->connection->fetchOne('SELECT seller_user_id FROM sales WHERE id = ? AND deleted_at IS NULL', [
            $saleId,
        ]);

        return $seller !== false && $seller !== null && (int) $seller === $user->userId;
    }
}
