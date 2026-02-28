<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Branch;
use App\Entity\Customer;
use App\Entity\LogisticOperation;
use App\Entity\PaymentMethod;
use App\Entity\PriceList;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Supplier;
use App\Entity\TaxDocument;
use App\Entity\Warehouse;
use PHPUnit\Framework\TestCase;

final class SaleTest extends TestCase
{
    public function testSaleAccessorsAndTaxDocumentDerivedFields(): void
    {
        $sale = new Sale();
        $item = (new SaleItem())
            ->setCantidad(2.0)
            ->setPrecioUnitario(15.5)
            ->setPrecioTotal('31.00');
        $operation = (new LogisticOperation())->setName('Venta');
        $warehouse = (new Warehouse())->setName('Principal');
        $branch = (new Branch())->setName('Sucursal 1');
        $customer = (new Customer())->setBusinessName('Cliente Uno');
        $priceList = (new PriceList())->setName('General');
        $supplier = (new Supplier())->setBusinessName('Proveedor Uno');
        $paymentMethod = (new PaymentMethod())->setName('Efectivo');
        $date = new \DateTimeImmutable('2026-02-20 08:00:00');
        $taxDocument = (new TaxDocument())
            ->setDocumentType('01')
            ->setSeries('F001')
            ->setNumber('1')
            ->setSunatStatus('accepted');
        $this->setPrivate($taxDocument, 'id', 'td-1');
        $this->setPrivate($sale, 'taxDocument', $taxDocument);

        $sale->setId('sale-1')
            ->setOperacion($operation)
            ->setAlmacen($warehouse)
            ->setBranch($branch)
            ->setCliente($customer)
            ->setPriceList($priceList)
            ->setTipoId(2)
            ->setFecha($date)
            ->setProveedor($supplier)
            ->setFormaPago(1)
            ->setTipoPago($paymentMethod)
            ->setTipoTarjeta(2)
            ->setSerie('F001')
            ->setNumero('100')
            ->setDescuento('1.50')
            ->setGlosa('observacion')
            ->setOrdenId(8)
            ->setHasInvoice(true)
            ->setEmitInvoice(true)
            ->setCurrency('PEN')
            ->setExchangeRate('3.8000')
            ->setPayments([['method' => 'cash', 'amount' => '100.00']])
            ->setIsConsume(true)
            ->setCajaId(4)
            ->setEstado(1)
            ->setDateCreated($date)
            ->setDateUpdated($date)
            ->setCreatedBy(1)
            ->setUpdatedBy(2)
            ->setIgv('18.00')
            ->setRecargoConsumo(0)
            ->setTipoOperacion('0101')
            ->setDetraccionCodigo('001')
            ->setDetraccionPorcentaje(4.0)
            ->setDetraccionMonto(40.0)
            ->setDetraccionMedioPago('001')
            ->setDetraccionValorReferencial(1000.0)
            ->setInvoice('F001-100')
            ->setSubtotal('84.75')
            ->setTotal('100.00')
            ->addItem($item);

        self::assertSame('sale-1', $sale->getId());
        self::assertSame($operation, $sale->getOperacion());
        self::assertSame($warehouse, $sale->getAlmacen());
        self::assertSame($branch, $sale->getBranch());
        self::assertSame($customer, $sale->getCliente());
        self::assertSame($priceList, $sale->getPriceList());
        self::assertSame(2, $sale->getTipoId());
        self::assertSame($date, $sale->getFecha());
        self::assertSame($supplier, $sale->getProveedor());
        self::assertSame(1, $sale->getFormaPago());
        self::assertSame($paymentMethod, $sale->getTipoPago());
        self::assertSame(2, $sale->getTipoTarjeta());
        self::assertSame('F001', $sale->getSerie());
        self::assertSame('100', $sale->getNumero());
        self::assertSame('1.50', $sale->getDescuento());
        self::assertSame('observacion', $sale->getGlosa());
        self::assertSame(8, $sale->getOrdenId());
        self::assertTrue((bool) $sale->isHasInvoice());
        self::assertTrue((bool) $sale->getEmitInvoice());
        self::assertSame('PEN', $sale->getCurrency());
        self::assertSame('3.8000', $sale->getExchangeRate());
        self::assertCount(1, $sale->getPayments());
        self::assertTrue((bool) $sale->isIsConsume());
        self::assertSame(4, $sale->getCajaId());
        self::assertSame(1, $sale->getEstado());
        self::assertSame($date, $sale->getDateCreated());
        self::assertSame($date, $sale->getDateUpdated());
        self::assertSame(1, $sale->getCreatedBy());
        self::assertSame(2, $sale->getUpdatedBy());
        self::assertSame('18.00', $sale->getIgv());
        self::assertSame(0, $sale->getRecargoConsumo());
        self::assertSame('0101', $sale->getTipoOperacion());
        self::assertSame('001', $sale->getDetraccionCodigo());
        self::assertSame(4.0, $sale->getDetraccionPorcentaje());
        self::assertSame(40.0, $sale->getDetraccionMonto());
        self::assertSame('001', $sale->getDetraccionMedioPago());
        self::assertSame(1000.0, $sale->getDetraccionValorReferencial());
        self::assertSame('F001-100', $sale->getInvoice());
        self::assertSame('84.75', $sale->getSubtotal());
        self::assertSame('100.00', $sale->getTotal());
        self::assertSame('accepted', $sale->getInvoiceSunatStatus());
        self::assertSame(1, $sale->getInvoiceVoided());
        self::assertStringStartsWith('/receipt/', (string) $sale->getPublicReceiptUrl());
        self::assertCount(1, $sale->getItems());

        $sale->removeItem($item);
        self::assertCount(0, $sale->getItems());
    }

    private function setPrivate(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionObject($object);
        $refProperty = $reflection->getProperty($property);
        $refProperty->setValue($object, $value);
    }
}
