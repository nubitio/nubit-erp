<?php

namespace App\Repository;

use App\Core\Exception\ServiceException;
use App\Entity\Invoice;
use App\Invoice\Domain\Voucher;
use App\Invoice\Domain\VoucherItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SerializerInterface $serializer
    ) {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * @throws Exception
     */
    public function getVoucher(string $saleId): Voucher
    {
        $voucher = $this->_em->getConnection()->executeQuery('CALL sp_sale_header(?)', [$saleId])->fetchAssociative();
        $items = $this->_em->getConnection()->executeQuery('CALL sp_sale_detail(?)', [$saleId])->fetchAllAssociative();

        if (!$voucher) {
            throw new ServiceException('La venta no existe');
        }

        $context = [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true];

        $voucher['voucher_items'] = array_map(function (array $item) use ($context) {
            return $this->serializer->denormalize($item, VoucherItem::class, null, $context);
        }, $items);

        return $this->serializer->denormalize($voucher, Voucher::class, null, $context);
    }
}
