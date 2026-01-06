<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Extension\RemoteEscposImage;
use App\Core\Service\LogoProvider;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Dto\Sale\SaleItemDto;
use App\Repository\InvoiceRepository;
use App\Sale\Domain\DocType;
use Doctrine\DBAL\Exception;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class PrintESCPOSController extends AbstractController
{
    public function __construct(
        private readonly LogoProvider $logoProvider,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly SettingsService $settingsService,
    ) {
    }

    /**
     * @throws Exception|ExceptionInterface
     */
    public function __invoke(string $id): Response
    {
        $commands = $this->printCommands($id);

        return new Response(
            $commands,
            Response::HTTP_OK,
            ['Content-Type' => 'application/octet-stream']
        );
    }

    /**
     * @throws Exception
     * @throws \Exception|ExceptionInterface
     */
    public function printCommands(string $saleId): string
    {
        $divider = str_pad(
            '',
            (int)$_ENV['PRINTER_LINE_WIDTH'],
            '-',
            STR_PAD_BOTH
        ) . "\n";

        $voucher = $this->invoiceRepository->getVoucher($saleId);
        $logo = RemoteEscposImage::loadImage($this->logoProvider->__invoke());

        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        $printer->setJustification(Printer::JUSTIFY_CENTER);

        $shouldPrintLogo = 'true' === strval(
            $this->settingsService->get(Constants::PRINT_LOGO, 'true')
        );

        if ($shouldPrintLogo && $logo instanceof EscposImage) {
            $printer->graphics($logo);
            $printer->feed();
        }

        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text($voucher->company_name . "\n");
        $printer->selectPrintMode();
        $printer->text('RUC: ' . $voucher->company_id . "\n");
        $printer->text($voucher->company_address . "\n");
        $printer->text('Tel.: ' . $voucher->company_phone . "\n");

        $docType = match ($voucher->voucher_type) {
            DocType::FACTURA->value => 'FACTURA ELECTRONICA',
            DocType::BOLETA->value => 'BOLETA ELECTRONICA',
            DocType::NOTA_VENTA->value => 'NOTA DE VENTA',
            default => 'TICKET',
        };
        $ticket = $voucher->voucher_series . '-' . $voucher->voucher_number;
        $printer->text($divider);
        $printer->setEmphasis();
        $printer->text($docType . "\n");
        $printer->text($ticket . "\n");
        $printer->setEmphasis(false);
        $printer->setJustification();
        $printer->text($divider);

        $docDate = date('d/m/Y', strtotime((string)$voucher->voucher_date));
        $printer->text('Fecha: ' . $docDate . "\n");
        $printer->text('Moneda: ' . ($voucher->voucher_currency ?: 'PEN') . "\n");
        $printer->text($divider);
        $printer->text('CLIENTE: ' . $voucher->customer_name . "\n");
        if ($voucher->voucher_type == DocType::FACTURA->value) {
            $printer->text('RUC: ' . $voucher->customer_doc_number . "\n");
        } elseif ($voucher->voucher_type == DocType::BOLETA->value) {
            $printer->text('DNI: ' . $voucher->customer_doc_number . "\n");
        }

        $printer->text('Direccion: ' . $voucher->customer_address . "\n");
        if ($voucher->voucher_type != DocType::NOTA_VENTA->value) {
            $paymentMethod = 2 == $voucher->voucher_payment_method ? 'CREDITO'
                : 'CONTADO';
            $printer->text(sprintf('Pago: %s%s', $paymentMethod, PHP_EOL));
        }

        $printer->text($divider);
        $printer->setEmphasis();
        // Header with columns: description, unit price, total
        $printer->text((string)new SaleItemDto('DESCRIPCION', 'P. UNIT', 'IMPORTE'));
        $printer->setEmphasis(false);
        $printer->text($divider);

        foreach ($voucher->voucher_items as $item) {
            $saleItem = new SaleItemDto($item->product_name, $item->unit_price, $item->total);
            $printer->text((string)$saleItem);
            $qty = $this->formatQuantity((float)$item->quantity);
            $uom = $item->product_uom ?? '';
            $printer->text('  ' . trim($qty . ' ' . $uom) . "\n");
        }

        $printer->text($divider);

        if ($voucher->voucher_discount_percentage > 0) {
            $printer->text(
                (string)new SaleItemDto('DTO.:', '-' . $voucher->voucher_discount_amount)
            );
        }

        $app_option_rc_get = (int)$this->settingsService->get(
            Constants::CONSUMPTION_TAX
        );
        $app_option_igv_get = (int)$this->settingsService->get(Constants::TAX);

        if ($voucher->voucher_type != DocType::NOTA_VENTA->value) {
            $printer->text(
                (string)new SaleItemDto(name: 'OP. GRAVADA:', total: $voucher->voucher_taxed_amount)
            );
            $printer->text((string)new SaleItemDto(name: 'OP. INAFECTA:', total: '0.00'));
            $printer->text((string)new SaleItemDto(name: 'OP. EXONERADA:', total: '0.00'));
            $printer->text(
                (string)new SaleItemDto(
                    name: 'IGV (' . $app_option_igv_get . '%):',
                    total: $voucher->voucher_tax_amount
                )
            );
            if ($app_option_rc_get >= 1) {
                $printer->text(
                    (string)new SaleItemDto(
                        name: 'RC (' . $app_option_rc_get . '%):',
                        total: $voucher->voucher_consumption_surcharge_amount
                    )
                );
            }
        }

        $printer->text(
            (string)new SaleItemDto(name: 'TOTAL:', total: $voucher->voucher_sale_amount)
        );

        $hasDetraction = $voucher->voucher_detraction_amount
            && $voucher->voucher_detraction_amount > 0;
        if ($hasDetraction) {
            $detractionPercent = $voucher->voucher_detraction_percent ?? 0;
            $printer->text(
                (string)new SaleItemDto(
                    name: sprintf('DETRACCION (%s%%):', $detractionPercent),
                    total: $voucher->voucher_detraction_amount
                )
            );

            $detractionType = trim(
                ($voucher->voucher_detraction_code ?? '') .
                ($voucher->voucher_detraction_description ? ' - ' . $voucher->voucher_detraction_description : '')
            );
            $detractionPayment = trim(
                ($voucher->voucher_detraction_payment_method ?? '') .
                ($voucher->voucher_detraction_payment_description ? ' - ' .
                    $voucher->voucher_detraction_payment_description : '')
            );

            $printer->text('TIPO DETRACCION: ' . ($detractionType !== '' ? $detractionType : '-') . "\n");
            $printer->text('MEDIO PAGO: ' . ($detractionPayment !== '' ? $detractionPayment : '-') . "\n");

            if (!empty($voucher->voucher_detraction_account)) {
                $printer->text('CTA DETRACCION: ' . $voucher->voucher_detraction_account . "\n");
            }

            if (
                !empty($voucher->voucher_bank_name) ||
                !empty($voucher->voucher_bank_account) ||
                !empty($voucher->voucher_bank_cci)
            ) {
                $printer->text('BANCO: ' . ($voucher->voucher_bank_name ?: '-') . "\n");
                if (!empty($voucher->voucher_bank_account)) {
                    $printer->text('CUENTA: ' . $voucher->voucher_bank_account . "\n");
                }
                if (!empty($voucher->voucher_bank_cci)) {
                    $printer->text('CCI: ' . $voucher->voucher_bank_cci . "\n");
                }
            }
        }

        $printer->text($divider);
        $printer->text($voucher->getVoucherTotalText() . "\n");
        $printer->text($divider);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if ($voucher->voucher_type != DocType::NOTA_VENTA->value) {
            if ('true' == $_ENV['PRINTER_PRINT_QR']) {
                $printer->qrCode(
                    $voucher->voucher_qr_data,
                    Printer::QR_ECLEVEL_L,
                    8
                );
            }

            $printer->feed();
            $printer->text('Resumen: ' . $voucher->voucher_hash . "\n");
            $printer->feed();
            $printer->text('Representacion impresa del' . "\n");
            $printer->text('comprobante electronico, consulte en' . "\n");
            $printer->text($_ENV['EFACT_URL'] . "\n");
        }

        $printer->feed();
        $printer->text(date('d/m/Y H:i:s') . "\n");
        $printer->text('** Gracias por su preferencia **' . "\n");
        $printer->feed();
        $printer->cut();
        $printer->pulse();

        $commands = $connector->getData();
        $printer->close();

        return $commands;
    }

    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 2, '.', '');
    }
}
