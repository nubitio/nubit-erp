<?php

declare(strict_types=1);

namespace App\Tests\Billing\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Billing\State\TaxDocumentProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(TaxDocumentProvider::class)]
final class TaxDocumentProviderTest extends TestCase
{
    /**
     * Build a provider with the given collection and item provider doubles.
     *
     * @param ProviderInterface<object> $collectionProvider
     * @param ProviderInterface<object> $itemProvider
     */
    private function buildProvider(
        ProviderInterface $collectionProvider,
        ProviderInterface $itemProvider,
    ): TaxDocumentProvider {
        $reflection = new ReflectionClass(TaxDocumentProvider::class);
        /** @var TaxDocumentProvider $provider */
        $provider = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('collectionProvider')->setValue($provider, $collectionProvider);
        $reflection->getProperty('itemProvider')->setValue($provider, $itemProvider);

        return $provider;
    }

    // -------------------------------------------------------------------------
    // TC-1: Item operation → delegates to itemProvider unchanged
    // -------------------------------------------------------------------------

    public function testItemOperationDelegatesToItemProvider(): void
    {
        /** @var ProviderInterface<object>&MockObject $itemProvider */
        $itemProvider = $this->createMock(ProviderInterface::class);
        /** @var ProviderInterface<object>&Stub $collectionProvider */
        $collectionProvider = $this->createStub(ProviderInterface::class);

        $operation = new Get();
        $uriVariables = ['id' => 42];
        $context = ['filters' => ['foo' => 'bar']];
        $expectedResult = new \stdClass();

        $itemProvider
            ->expects(self::once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($expectedResult);

        $provider = $this->buildProvider($collectionProvider, $itemProvider);
        $result = $provider->provide($operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }

    // -------------------------------------------------------------------------
    // TC-2: Collection, no type filter → sort added, no filter key
    // -------------------------------------------------------------------------

    public function testCollectionWithNoTypeFilterAddsSortOnly(): void
    {
        /** @var ProviderInterface<object>&MockObject $collectionProvider */
        $collectionProvider = $this->createMock(ProviderInterface::class);
        /** @var ProviderInterface<object>&Stub $itemProvider */
        $itemProvider = $this->createStub(ProviderInterface::class);

        $capturedContext = null;

        $collectionProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturnCallback(function (Operation $op, array $uriVars, array $ctx) use (&$capturedContext): array {
                $capturedContext = $ctx;
                return [];
            });

        $provider = $this->buildProvider($collectionProvider, $itemProvider);
        $provider->provide(new GetCollection(), [], []);

        self::assertIsArray($capturedContext);
        self::assertIsArray($capturedContext['filters']);
        self::assertArrayHasKey('sort', $capturedContext['filters']);
        self::assertSame([['selector' => 'createdAt', 'desc' => true]], $capturedContext['filters']['sort']);
        self::assertArrayNotHasKey('filter', $capturedContext['filters']);
    }

    // -------------------------------------------------------------------------
    // TC-3: Collection, type=invoice → sort + documentType in ['01','03']
    // -------------------------------------------------------------------------

    public function testCollectionWithTypeInvoiceAddsInvoiceFilter(): void
    {
        /** @var ProviderInterface<object>&MockObject $collectionProvider */
        $collectionProvider = $this->createMock(ProviderInterface::class);
        /** @var ProviderInterface<object>&Stub $itemProvider */
        $itemProvider = $this->createStub(ProviderInterface::class);

        $capturedContext = null;

        $collectionProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturnCallback(function (Operation $op, array $uriVars, array $ctx) use (&$capturedContext): array {
                $capturedContext = $ctx;
                return [];
            });

        $provider = $this->buildProvider($collectionProvider, $itemProvider);
        $provider->provide(new GetCollection(), [], ['filters' => ['type' => 'invoice']]);

        self::assertIsArray($capturedContext);
        self::assertIsArray($capturedContext['filters']);
        self::assertArrayHasKey('sort', $capturedContext['filters']);
        self::assertArrayHasKey('filter', $capturedContext['filters']);
        self::assertSame([['documentType', 'in', ['01', '03']]], $capturedContext['filters']['filter']);
    }

    // -------------------------------------------------------------------------
    // TC-4: Collection, type=note → sort + documentType in ['07','08']
    // -------------------------------------------------------------------------

    public function testCollectionWithTypeNoteAddsNoteFilter(): void
    {
        /** @var ProviderInterface<object>&MockObject $collectionProvider */
        $collectionProvider = $this->createMock(ProviderInterface::class);
        /** @var ProviderInterface<object>&Stub $itemProvider */
        $itemProvider = $this->createStub(ProviderInterface::class);

        $capturedContext = null;

        $collectionProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturnCallback(function (Operation $op, array $uriVars, array $ctx) use (&$capturedContext): array {
                $capturedContext = $ctx;
                return [];
            });

        $provider = $this->buildProvider($collectionProvider, $itemProvider);
        $provider->provide(new GetCollection(), [], ['filters' => ['type' => 'note']]);

        self::assertIsArray($capturedContext);
        self::assertIsArray($capturedContext['filters']);
        self::assertArrayHasKey('filter', $capturedContext['filters']);
        self::assertSame([['documentType', 'in', ['07', '08']]], $capturedContext['filters']['filter']);
    }

    // -------------------------------------------------------------------------
    // TC-5: Collection, type=unknown → sort added, no type filter
    // -------------------------------------------------------------------------

    public function testCollectionWithUnknownTypeAddsNoTypeFilter(): void
    {
        /** @var ProviderInterface<object>&MockObject $collectionProvider */
        $collectionProvider = $this->createMock(ProviderInterface::class);
        /** @var ProviderInterface<object>&Stub $itemProvider */
        $itemProvider = $this->createStub(ProviderInterface::class);

        $capturedContext = null;

        $collectionProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturnCallback(function (Operation $op, array $uriVars, array $ctx) use (&$capturedContext): array {
                $capturedContext = $ctx;
                return [];
            });

        $provider = $this->buildProvider($collectionProvider, $itemProvider);
        $provider->provide(new GetCollection(), [], ['filters' => ['type' => 'unknown']]);

        self::assertIsArray($capturedContext);
        self::assertIsArray($capturedContext['filters']);
        self::assertArrayHasKey('sort', $capturedContext['filters']);
        self::assertArrayNotHasKey('filter', $capturedContext['filters']);
    }

    // -------------------------------------------------------------------------
    // TC-6: Collection, type=invoice, existing filter → wraps with 'and'
    // -------------------------------------------------------------------------

    public function testCollectionWithTypeInvoiceAndExistingFilterWrapsWithAnd(): void
    {
        /** @var ProviderInterface<object>&MockObject $collectionProvider */
        $collectionProvider = $this->createMock(ProviderInterface::class);
        /** @var ProviderInterface<object>&Stub $itemProvider */
        $itemProvider = $this->createStub(ProviderInterface::class);

        $existingFilter = [['status', '=', 'active']];
        $capturedContext = null;

        $collectionProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturnCallback(function (Operation $op, array $uriVars, array $ctx) use (&$capturedContext): array {
                $capturedContext = $ctx;
                return [];
            });

        $provider = $this->buildProvider($collectionProvider, $itemProvider);
        $provider->provide(new GetCollection(), [], ['filters' => ['type' => 'invoice', 'filter' => $existingFilter]]);

        self::assertIsArray($capturedContext);
        self::assertIsArray($capturedContext['filters']);
        self::assertArrayHasKey('filter', $capturedContext['filters']);

        $filter = $capturedContext['filters']['filter'];
        self::assertSame($existingFilter, $filter[0]);
        self::assertSame('and', $filter[1]);
        self::assertSame([['documentType', 'in', ['01', '03']]], $filter[2]);
    }
}
