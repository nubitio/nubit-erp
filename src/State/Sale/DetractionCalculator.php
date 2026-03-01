<?php

declare(strict_types=1);

namespace App\State\Sale;

use App\Core\Exception\ServiceException;
use App\Entity\Sale;
use App\Repository\SunatCatalogRepository;

/**
 * Calculates and applies SUNAT detraction rules to a sale.
 */
final readonly class DetractionCalculator
{
    public const string PAYMENT_METHOD = '001';
    public const float MIN_TOTAL = 700.0;
    public const array NO_MIN_CODES = ['008', '016', '019', '021'];

    public function __construct(
        private SunatCatalogRepository $sunatCatalogRepository,
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function applyFromItems(Sale $sale): void
    {
        $customerDoc = $sale->getCliente()?->getRuc() ?? '';
        if (strlen($customerDoc) !== 11) {
            $this->clearDetraction($sale);

            return;
        }

        $detractionCatalog = null;

        foreach ($sale->getItems() as $item) {
            $catalog = $item->getProducto()?->getDetractionCatalog();
            if (!$catalog) {
                continue;
            }

            if ($detractionCatalog && $catalog->getId() !== $detractionCatalog->getId()) {
                throw new ServiceException(
                    'Los productos tienen diferentes códigos de detracción. Separe la venta en comprobantes distintos.'
                );
            }

            $detractionCatalog = $catalog;
        }

        if (!$detractionCatalog) {
            $this->clearDetraction($sale);

            return;
        }

        $total = (float) ($sale->getTotal() ?? 0);
        $detractionCode = $detractionCatalog->getCode();
        $threshold = $this->getThreshold($detractionCode);

        if ($threshold > 0 && $total <= $threshold) {
            $this->clearDetraction($sale);

            return;
        }

        $sale->setDetraccionCodigo($detractionCatalog->getCode());
        $sale->setDetraccionPorcentaje($detractionCatalog->getPercentage());
        $amount = $sale->getDetraccionPorcentaje() !== null
            ? round($sale->getTotal() * $sale->getDetraccionPorcentaje() / 100, 2)
            : null;
        $sale->setDetraccionMonto($amount);

        $paymentMethod = $this->sunatCatalogRepository->findOneBy([
            'catalog' => 59,
            'code' => self::PAYMENT_METHOD,
            'active' => true,
        ]);
        if (!$paymentMethod) {
            throw new ServiceException('No se encontró el medio de pago para detracción (catálogo 59).');
        }
        $sale->setDetraccionMedioPago(self::PAYMENT_METHOD);

        if ($sale->getTipoOperacion() === null) {
            $sale->setTipoOperacion('1001');
        }
    }

    public function getThreshold(string $code): float
    {
        if (in_array($code, self::NO_MIN_CODES, true)) {
            return 0.0;
        }

        return self::MIN_TOTAL;
    }

    private function clearDetraction(Sale $sale): void
    {
        $sale->setDetraccionCodigo(null);
        $sale->setDetraccionPorcentaje(null);
        $sale->setDetraccionMonto(null);
        $sale->setDetraccionMedioPago(null);
        $sale->setDetraccionValorReferencial(null);
        if ($sale->getTipoOperacion() === '1001') {
            $sale->setTipoOperacion(null);
        }
    }
}
