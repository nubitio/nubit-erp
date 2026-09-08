<?php

declare(strict_types=1);

namespace App\Tests\Pharmacy;

use ApiPlatform\Metadata\Post;
use App\Pharmacy\Entity\Prescription;
use App\Pharmacy\State\PrescriptionInput;
use App\Pharmacy\State\PrescriptionInputProcessor;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class PrescriptionTest extends TestCase
{
    public function testEntityConstructionAndCoverage(): void
    {
        $prescription = new Prescription('Ana Pérez', 'Dr. Rojas', new DateTimeImmutable('2026-09-01'), [11, 22]);
        $prescription
            ->setPatientDocument('12345678')
            ->setPrescriberRegistration('CMP-9999')
            ->setNotes('tomar cada 8h')
            ->linkToSale('sale-42');

        self::assertNull($prescription->getId());
        self::assertSame('Ana Pérez', $prescription->getPatientName());
        self::assertSame('12345678', $prescription->getPatientDocument());
        self::assertSame('Dr. Rojas', $prescription->getPrescriberName());
        self::assertSame('CMP-9999', $prescription->getPrescriberRegistration());
        self::assertSame('sale-42', $prescription->getSaleId());
        self::assertSame([11, 22], $prescription->getProductIds());
        self::assertTrue($prescription->covers(22));
        self::assertFalse($prescription->covers(33));
    }

    public function testProcessorBuildsAndPersistsFromInput(): void
    {
        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted = $entity;
        });

        $input = new PrescriptionInput();
        $input->patientName = 'Juan Díaz';
        $input->prescriberName = 'Dra. León';
        $input->issuedOn = '2026-08-15';
        $input->productIds = [7];
        $input->saleId = 'sale-7';

        $result = new PrescriptionInputProcessor($em)->process($input, new Post());

        self::assertSame($persisted, $result);
        self::assertSame('Juan Díaz', $result->getPatientName());
        self::assertSame([7], $result->getProductIds());
        self::assertSame('sale-7', $result->getSaleId());
        self::assertSame('2026-08-15', $result->getIssuedOn()->format('Y-m-d'));
    }
}
