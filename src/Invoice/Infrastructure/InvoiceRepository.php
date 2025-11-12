<?php

namespace App\Invoice\Infrastructure;

use App\Core\Exception\ServiceException;
use App\Core\Invoice\InvoiceCalculator;
use App\Entity\Invoice;
use App\Entity\Sale;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Invoice\Domain\Voucher;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

readonly class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * @throws \Exception
     */
    #[\Override]
    public function getOneBySaleId(string $saleId): Invoice
    {
        /** @var ?Invoice $invoiceEntity */
        $invoiceEntity = $this->entityManager->getRepository(Invoice::class)->findOneBy(
            ['movimiento_id' => $saleId]
        );

        if (!$invoiceEntity) {
            throw new \Exception('Invoice not found');
        }

        return $invoiceEntity;
    }

    /**
     * @throws \Exception
     */
    #[\Override]
    public function create(Sale $sale): void
    {
        $localId = $this->connection->fetchOne(
            'select local_id from almacen where almacen_id = ?',
            [$sale->getAlmacen()?->getId()]
        );
        $invoiceCalculator = new InvoiceCalculator(
            floatval($sale->getSubtotal()),
            $sale->getDescuento() ?? 0.0,
            floatval($sale->getIgv()),
            $sale->getRecargoConsumo() ?? 0.0
        );
        $invoiceAmounts = $invoiceCalculator->calculate();
        $this->connection->executeQuery('CALL sp_make_invoice(?,?,?,?,?,?,?,?)', [
            $sale->getId(),
            $localId,
            $invoiceAmounts->getSubtotal(),
            $invoiceAmounts->getTaxedAmount(),
            $invoiceAmounts->getDiscountAmount(),
            $invoiceAmounts->getTax(),
            $invoiceAmounts->getChargesAmount(),
            $invoiceAmounts->getTotal(),
        ]);
    }

    /**
     * @throws ServiceException
     * @throws Exception
     */
    #[\Override]
    public function getVoucher(string $saleId): Voucher
    {
        $voucher = $this->connection->executeQuery('CALL sp_sale_header(?)', [$saleId])->fetchAssociative();
        $items = $this->connection->executeQuery('CALL sp_sale_detail(?)', [$saleId])->fetchAllAssociative();

        if (!$voucher) {
            throw new ServiceException('La venta no existe');
        }

        $voucher['voucher_items'] = $items;

        return $this->serializer->denormalize($voucher, Voucher::class);
    }
}
