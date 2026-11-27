<?php

declare(strict_types=1);

namespace App\AccountsPayable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\AccountsPayable\Entity\SupplierBill;
use App\Efact\Domain\Money;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * PATCH /supplier-bills/{id}.
 *
 * `amount`, `currency`, `supplier` and `branch` define the debt the ledger already booked
 * (`SUPPLIER_BILL` event) and the balance payments were checked against. Nothing recomputes
 * `balance_due` or the posting when they change, so editing them left a bill whose balance and books
 * disagreed (G-12). They are therefore immutable after creation: void the bill and create the right one.
 * A voided bill is frozen. Due date, document data, category and note stay editable.
 *
 * @implements ProcessorInterface<SupplierBill, SupplierBill>
 */
final readonly class SupplierBillUpdateProcessor implements ProcessorInterface
{
    /** @param ProcessorInterface<mixed, mixed> $persistProcessor */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {}

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $previous = $context['previous_data'] ?? null;
        if ($previous instanceof SupplierBill) {
            self::assertEditable($previous, $data);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    public static function assertEditable(SupplierBill $before, SupplierBill $after): void
    {
        if ($before->getStatus() === SupplierBill::VOID) {
            throw new UnprocessableEntityHttpException('Una factura anulada no se puede modificar.');
        }

        if (!Money::money($before->getAmount())->isEqualTo(Money::money($after->getAmount()))) {
            throw new UnprocessableEntityHttpException(
                'El importe no se puede cambiar. Anule la factura y registre una nueva.',
            );
        }
        if ($before->getCurrency() !== $after->getCurrency()) {
            throw new UnprocessableEntityHttpException(
                'La moneda no se puede cambiar. Anule la factura y registre una nueva.',
            );
        }
        if ($before->getSupplier()->getId() !== $after->getSupplier()->getId()) {
            throw new UnprocessableEntityHttpException('El proveedor no se puede cambiar una vez registrada la factura.');
        }
        if ($before->getBranch()->getId() !== $after->getBranch()->getId()) {
            throw new UnprocessableEntityHttpException('La sucursal no se puede cambiar una vez registrada la factura.');
        }
    }
}
