<?php

declare(strict_types=1);

namespace App\Tests\Catalog\State;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Catalog\Entity\VoucherSeries;
use App\Catalog\Entity\VoucherType;
use App\Catalog\State\VoucherSeriesWriteProcessor;
use App\Organization\Entity\Branch;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VoucherSeriesWriteProcessor::class)]
final class VoucherSeriesWriteProcessorTest extends TestCase
{
    public function testDuplicateInAnotherBranchIsAFieldError(): void
    {
        $data = $this->series();
        $connection = $this->connection();
        $connection
            ->expects($this->exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(static fn(string $sql, array $params) => match ($params) {
                ['01'] => '01',
                ['01', 'F001', 0] => 'branch-with-duplicate',
                ['branch-with-duplicate'] => 'Principal',
            });
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects($this->never())->method('process');

        $this->assertDuplicate(
            static fn() => new VoucherSeriesWriteProcessor($connection, $persist)->process($data, new Post()),
            'Principal',
        );
    }

    public function testPutDoesNotConflictWithItsOwnSeries(): void
    {
        $data = $this->series();
        $existing = $this->series(3);
        $connection = $this->connection();
        $connection->expects($this->exactly(2))->method('fetchOne')->willReturnOnConsecutiveCalls('01', 3);
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects($this->once())->method('process')->willReturn($existing);

        $processor = new VoucherSeriesWriteProcessor($connection, $persist);
        static::assertSame($existing, $processor->process($data, new Put(), ['id' => '3']));
    }

    public function testExistingDatabaseIndexViolationProducesTheSameFieldError(): void
    {
        $connection = $this->connection();
        $connection->expects($this->exactly(2))->method('fetchOne')->willReturnOnConsecutiveCalls('01', false);
        $persist = $this->createStub(ProcessorInterface::class);
        $driverError = new class("Duplicate entry for key 'uq_local_comprobante_branch_type_series'") extends
            \RuntimeException implements DriverException {
            public function getSQLState(): string
            {
                return '23000';
            }
        };
        $persist->method('process')->willThrowException(new UniqueConstraintViolationException($driverError, null));

        $this->assertDuplicate(
            fn() => new VoucherSeriesWriteProcessor($connection, $persist)->process($this->series(), new Post()),
            'Otra sucursal',
        );
    }

    private function connection(): Connection&\PHPUnit\Framework\MockObject\MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn(callable $run) => $run($connection));

        return $connection;
    }

    private function assertDuplicate(callable $save, string $branch): void
    {
        try {
            $save();
            static::fail('Expected a duplicate series violation.');
        } catch (ValidationException $failure) {
            static::assertSame(422, $failure->getStatusCode());
            static::assertCount(1, $failure->getConstraintViolationList());
            $violation = $failure->getConstraintViolationList()->get(0);
            static::assertSame('series', $violation->getPropertyPath());
            static::assertSame(
                "La serie F001 ya está registrada en {$branch} para este tipo de comprobante. Usa otro código.",
                $violation->getMessage(),
            );
        }
    }

    private function series(?int $id = null): VoucherSeries
    {
        $series = new VoucherSeries()
            ->setBranch(new Branch()->setName('Otra sucursal'))
            ->setVoucherType(new VoucherType()->setCode('01'))
            ->setSeries(' f001 ');
        if ($id !== null) {
            new \ReflectionProperty(VoucherSeries::class, 'id')->setValue($series, $id);
        }

        return $series;
    }
}
