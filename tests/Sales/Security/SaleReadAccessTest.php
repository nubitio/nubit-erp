<?php

declare(strict_types=1);

namespace App\Tests\Sales\Security;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use App\Billing\Entity\TaxDocument;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use App\Sales\Entity\SaleRecord;
use App\Sales\Security\SaleReadAccess;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class SaleReadAccessTest extends TestCase
{
    /**
     * @param list<string> $grants
     * @param list<string|false|null> $answers what the database answers, in order
     */
    private function access(array $grants, ?int $userId, array $answers = []): SaleReadAccess
    {
        $security = $this->createStub(Security::class);
        $security
            ->method('hasPermission')
            ->willReturnCallback(static fn(string $permission): bool => in_array($permission, $grants, true));
        $security
            ->method('connectedUser')
            ->willReturn($userId === null ? null : new AuthUserDto(userId: $userId, username: 'cajero'));

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(...$answers);

        return new SaleReadAccess($connection, $security);
    }

    public function testWhoCanViewSalesReadsAnySale(): void
    {
        $this->access(['sales.view'], 7)->assertCanReadSale('any-sale');
        $this->access(['sales.view'], 7)->assertCanReadTaxDocument('any-document');

        $this->expectNotToPerformAssertions();
    }

    public function testACashierReadsTheirOwnSaleAndItsDocument(): void
    {
        $this->access(['sales.view_own'], 7, ['7'])->assertCanReadSale('sale-1');
        $this->access(['sales.view_own'], 7, ['sale-1', '7'])->assertCanReadTaxDocument('doc-1');

        $this->expectNotToPerformAssertions();
    }

    public function testACashierCannotReadAnotherUsersSale(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->access(['sales.view_own'], 7, ['8'])->assertCanReadSale('sale-of-someone-else');
    }

    public function testACashierDoesNotOwnASaleWithoutAHumanSeller(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->access(['sales.view_own'], 7, [null])->assertCanReadSale('integration-sale');
    }

    public function testACashierCannotReadTheDocumentOfAnotherUsersSale(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->access(['sales.view_own'], 7, ['sale-2', '8'])->assertCanReadTaxDocument('doc-2');
    }

    public function testADocumentWithoutASaleIsNobodysOwn(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->access(['sales.view_own'], 7, [null])->assertCanReadTaxDocument('legacy-doc');
    }

    public function testASaleThatDoesNotExistIsRefusedWithoutRevealingIt(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->access(['sales.view_own'], 7, [false])->assertCanReadSale('ghost');
    }

    public function testWithoutEitherPermissionNothingIsReadable(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->access(['sales.create', 'pos.access'], 7)->assertCanReadSale('sale-1');
    }

    public function testAnAnonymousCallerIsRefused(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->access(['sales.view_own'], null)->assertCanReadSale('sale-1');
    }

    /**
     * The API-level gate only lets the narrower grant through the three routes the POS uses after a sale;
     * the controller then limits it to the caller's own sales.
     */
    public function testOnlyThePostSaleRoutesAcceptTheNarrowGrant(): void
    {
        $withOwn = [];
        foreach ([SaleRecord::class, TaxDocument::class] as $class) {
            $resource = new \ReflectionClass($class)->getAttributes(ApiResource::class)[0]->newInstance();
            self::assertInstanceOf(ApiResource::class, $resource);
            foreach ($resource->getOperations() ?? [] as $operation) {
                self::assertInstanceOf(HttpOperation::class, $operation);
                $security = (string) $operation->getSecurity();
                if (str_contains($security, 'sales.view_own')) {
                    $withOwn[] = (string) ($operation->getUriTemplate() ?? $operation->getName());
                }
            }
        }
        sort($withOwn);

        self::assertSame(
            ['/sales/{id}/print-invoice', '/tax-documents/{id}/consult', '/tax-documents/{id}/email'],
            $withOwn,
        );
    }
}
