<?php

declare(strict_types=1);

namespace App\EInvoicing\Sale;

use App\Core\Exception\ServiceException;
use App\Core\Http\ApiResponse;
use App\EInvoicing\Sale\Void\CashRefundRegistrar;
use App\EInvoicing\Sale\Void\SunatVoidService;
use App\EInvoicing\Sale\Void\TaxDocumentVoidUpdater;
use App\Entity\Sale;
use App\Repository\TaxDocumentRepository;
use App\Sale\Domain\DocType;
use App\Sale\Domain\SaleException;
use Brick\Math\Exception\MathException;
use DateTime;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

readonly class SaleVoidUseCase
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaxDocumentRepository $taxDocumentRepository,
        private SunatVoidService $sunatVoidService,
        private TaxDocumentVoidUpdater $taxDocumentVoidUpdater,
        private CashRefundRegistrar $cashRefundRegistrar,
    ) {
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \DateMalformedStringException
     * @throws \Throwable
     * @throws MathException
     * @throws Exception
     */
    public function __invoke(
        string $saleId,
        string $reason,
        int $userId,
    ): ApiResponse {
        $voucher = $this->taxDocumentRepository->getVoucher($saleId);
        $sale = $this->entityManager->find(Sale::class, $saleId);
        if (!$sale instanceof Sale) {
            throw new ServiceException('La venta no existe.');
        }

        if (2 === $sale->getEstado()) {
            throw SaleException::alreadyVoided();
        }

        if (
            $sale->getFecha()?->format('Y-m-d') < date(
                'Y-m-d',
                strtotime('-7 days')
            )
        ) {
            throw SaleException::tooOld();
        }

        $documentType = (string)$voucher->voucher_type;
        if (!in_array($documentType, [DocType::FACTURA->value, DocType::BOLETA->value], true)) {
            throw new ServiceException('Tipo de comprobante no soportado para anulación.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $sunatResponse = $this->sunatVoidService->void($documentType, $saleId, $reason);

            $taxDocument = $this->taxDocumentRepository->findOneBy([
                'sale' => $saleId,
                'documentType' => $documentType,
            ]);
            if ($taxDocument === null) {
                throw new ServiceException('Comprobante no encontrado');
            }

            $voidedStatus = $sunatResponse->success ? 2 : 3;
            $this->taxDocumentVoidUpdater->applyVoidResult($taxDocument, $sunatResponse, $voidedStatus);

            $sale->setGlosa($reason);
            $sale->setEstado(2);
            $sale->setUpdatedBy($userId);
            $sale->setDateUpdated(new DateTime());
            $this->entityManager->persist($sale);

            $this->cashRefundRegistrar->register($sale, $userId);

            $this->entityManager->flush();
            $connection->commit();

            $message = $sunatResponse->success
                ? 'Anulado correctamente'
                : ($sunatResponse->message ?? 'Error al anular');

            return new ApiResponse($sunatResponse->success, $message);
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }
    }
}
