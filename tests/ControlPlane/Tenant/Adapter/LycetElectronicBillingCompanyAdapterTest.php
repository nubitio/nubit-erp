<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Tenant\Adapter;

use App\Billing\Sunat\Domain\LycetCompanyServiceInterface;
use App\ControlPlane\Tenant\Adapter\LycetElectronicBillingCompanyAdapter;
use PHPUnit\Framework\TestCase;

final class LycetElectronicBillingCompanyAdapterTest extends TestCase
{
    public function testDelegatesCompanyUpsertToLycetService(): void
    {
        $lycet = $this->createMock(LycetCompanyServiceInterface::class);
        $lycet
            ->expects(self::once())
            ->method('upsertCompany')
            ->with(
                '20123456789',
                '20123456789MODDATOS',
                'secret',
                'CERT_PEM',
                ['FE_URL' => 'https://example.test/fe'],
                'client-id',
                'client-secret',
            );

        $adapter = new LycetElectronicBillingCompanyAdapter($lycet);
        $adapter->upsertCompany(
            '20123456789',
            '20123456789MODDATOS',
            'secret',
            'CERT_PEM',
            ['FE_URL' => 'https://example.test/fe'],
            'client-id',
            'client-secret',
        );
    }

    public function testDelegatesLogoUploadToLycetService(): void
    {
        $lycet = $this->createMock(LycetCompanyServiceInterface::class);
        $lycet->expects(self::once())->method('uploadCompanyLogo')->with('20123456789', 'IMAGE_BYTES');

        $adapter = new LycetElectronicBillingCompanyAdapter($lycet);
        $adapter->uploadCompanyLogo('20123456789', 'IMAGE_BYTES');
    }

    public function testDelegatesCompanyRemovalToLycetService(): void
    {
        $lycet = $this->createMock(LycetCompanyServiceInterface::class);
        $lycet->expects(self::once())->method('removeCompany')->with('20123456789');

        $adapter = new LycetElectronicBillingCompanyAdapter($lycet);
        $adapter->removeCompany('20123456789');
    }
}
