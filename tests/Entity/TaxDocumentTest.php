<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Branch;
use App\Entity\Customer;
use App\Entity\Sale;
use App\Entity\TaxDocument;
use App\Entity\TaxDocumentLine;
use PHPUnit\Framework\TestCase;

final class TaxDocumentTest extends TestCase
{
    public function testAccessorsAndComputedFields(): void
    {
        $entity = new TaxDocument();
        $sale = new Sale();
        $branch = new Branch();
        $customer = new Customer();
        $line = (new TaxDocumentLine())->setProductName('Producto')->setLineNumber(1);
        $issueDate = new \DateTimeImmutable('2026-02-20 10:00:00');
        $createdAt = new \DateTimeImmutable('2026-02-20 11:00:00');
        $updatedAt = new \DateTimeImmutable('2026-02-20 12:00:00');

        $entity->setSale($sale)
            ->setBranch($branch)
            ->setCustomer($customer)
            ->setDocumentType('01')
            ->setIssueDate($issueDate)
            ->setSeries('F001')
            ->setNumber('123')
            ->setPaymentMethod(1)
            ->setCurrency('PEN')
            ->setSubtotal('84.75')
            ->setTaxableAmount('84.75')
            ->setIgvAmount('15.25')
            ->setRcAmount('0.00')
            ->setDiscountPercent('0.00')
            ->setDiscountAmount('0.00')
            ->setOperationType('0101')
            ->setDetractionCode('001')
            ->setDetractionPercent(4.0)
            ->setDetractionAmount(10.0)
            ->setDetractionPaymentMethod('001')
            ->setDetractionValueRef(250.0)
            ->setAffectedDocument('F001-100')
            ->setReasonCode('01')
            ->setReasonDescription('Correccion')
            ->setTotal('100.00')
            ->setVoidTicket('ticket-1')
            ->setVoidCorrelation('corr-1')
            ->setSunatStatus('accepted')
            ->setSunatMessage('ok')
            ->setDigest('digest')
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt);

        $this->setPrivateIfExists($entity, 'exchangeRate', '3.800000');

        $entity->addLine($line);
        $entity->removeLine($line);
        $entity->addLine($line);
        $this->setPrivate($entity, 'id', 'tax-doc-1');

        self::assertSame($sale, $entity->getSale());
        self::assertSame($branch, $entity->getBranch());
        self::assertSame($customer, $entity->getCustomer());
        self::assertSame('01', $entity->getDocumentType());
        self::assertSame($issueDate, $entity->getIssueDate());
        self::assertSame('F001', $entity->getSeries());
        self::assertSame('123', $entity->getNumber());
        self::assertSame(1, $entity->getPaymentMethod());
        self::assertSame('PEN', $entity->getCurrency());
        if ($this->hasPrivate($entity, 'exchangeRate')) {
            self::assertSame('3.800000', $this->getPrivate($entity, 'exchangeRate'));
        }
        self::assertSame('84.75', $entity->getSubtotal());
        self::assertSame('84.75', $entity->getTaxableAmount());
        self::assertSame('15.25', $entity->getIgvAmount());
        self::assertSame('0.00', $entity->getRcAmount());
        self::assertSame('0.00', $entity->getDiscountPercent());
        self::assertSame('0.00', $entity->getDiscountAmount());
        self::assertSame('0101', $entity->getOperationType());
        self::assertSame('001', $entity->getDetractionCode());
        self::assertSame(4.0, $entity->getDetractionPercent());
        self::assertSame(10.0, $entity->getDetractionAmount());
        self::assertSame('001', $entity->getDetractionPaymentMethod());
        self::assertSame(250.0, $entity->getDetractionValueRef());
        self::assertSame('F001-100', $entity->getAffectedDocument());
        self::assertSame('01', $entity->getReasonCode());
        self::assertSame('Correccion', $entity->getReasonDescription());
        self::assertSame('100.00', $entity->getTotal());
        self::assertSame('ticket-1', $entity->getVoidTicket());
        self::assertSame('corr-1', $entity->getVoidCorrelation());
        self::assertSame('accepted', $entity->getSunatStatus());
        self::assertSame('ok', $entity->getSunatMessage());
        self::assertSame('digest', $entity->getDigest());
        self::assertSame($createdAt, $entity->getCreatedAt());
        self::assertSame($updatedAt, $entity->getUpdatedAt());
        self::assertSame('F001-123', $entity->getFullNumber());
        self::assertTrue($entity->isProcessed());
        self::assertTrue($entity->isVoided());
        self::assertStringStartsWith('/receipt/', $entity->getPublicReceiptUrl());
        self::assertCount(1, $entity->getLines());
    }

    private function setPrivate(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionObject($object);
        $refProperty = $reflection->getProperty($property);
        $refProperty->setValue($object, $value);
    }

    private function setPrivateIfExists(object $object, string $property, mixed $value): void
    {
        if (!$this->hasPrivate($object, $property)) {
            return;
        }

        $this->setPrivate($object, $property, $value);
    }

    private function getPrivate(object $object, string $property): mixed
    {
        $reflection = new \ReflectionObject($object);
        $refProperty = $reflection->getProperty($property);

        return $refProperty->getValue($object);
    }

    private function hasPrivate(object $object, string $property): bool
    {
        $reflection = new \ReflectionObject($object);

        return $reflection->hasProperty($property);
    }
}
