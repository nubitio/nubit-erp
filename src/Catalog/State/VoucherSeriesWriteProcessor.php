<?php

declare(strict_types=1);

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Catalog\Entity\VoucherSeries;
use App\Catalog\Service\VoucherSeriesAllocation;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/** @implements ProcessorInterface<VoucherSeries, VoucherSeries> */
final readonly class VoucherSeriesWriteProcessor implements ProcessorInterface
{
    /** @param ProcessorInterface<VoucherSeries, VoucherSeries> $persist */
    public function __construct(
        private Connection $connection,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persist,
    ) {}

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): VoucherSeries {
        // A standard PUT can pass a fresh object before the persistence processor merges it.
        $currentId = $data->getId() ?? (int) ($uriVariables['id'] ?? 0);
        $type = $data->getVoucherType()->getCode();

        try {
            return VoucherSeriesAllocation::withTypeLock($this->connection, $type, function () use (
                $data,
                $operation,
                $uriVariables,
                $context,
                $currentId,
                $type,
            ): VoucherSeries {
                // Allow maintenance of a legacy assignment, but not moving it to a different branch.
                $unchanged =
                    $currentId !== 0
                    && $this->connection->fetchOne('SELECT id FROM local_comprobante WHERE id = ? AND local_id = ? AND tipo_comprobante_id = ? AND series = ? FOR UPDATE', [
                        $currentId,
                        $data->getBranch()->getId(),
                        $type,
                        $data->getSeries(),
                    ]) !== false;

                if (!$unchanged) {
                    $duplicateBranch = VoucherSeriesAllocation::conflictingBranch(
                        $this->connection,
                        $type,
                        $data->getSeries(),
                        $currentId,
                    );
                    if ($duplicateBranch !== null) {
                        throw $this->duplicateViolation($data, $duplicateBranch);
                    }
                }

                return $this->persist->process($data, $operation, $uriVariables, $context);
            });
        } catch (UniqueConstraintViolationException $failure) {
            if (!str_contains($failure->getMessage(), VoucherSeries::UNIQUE_INDEX)) {
                throw $failure;
            }

            throw $this->duplicateViolation($data, $data->getBranch()->getName(), $failure);
        }
    }

    private function duplicateViolation(
        VoucherSeries $series,
        ?string $branch,
        ?UniqueConstraintViolationException $previous = null,
    ): ValidationException {
        $message = VoucherSeriesAllocation::duplicateMessage($series->getSeries(), $branch);

        return new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($message, null, [], $series, 'series', $series->getSeries()),
        ]), previous: $previous);
    }
}
