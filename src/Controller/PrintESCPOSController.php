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
use InvalidArgumentException;
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
        $width = max(32, (int)($_ENV['PRINTER_LINE_WIDTH'] ?? 42)); // 42 típico 80mm, 32 típico 58mm

        $hrDashed = $this->hr('-', $width);
        $hrSolid = $this->hr('=', $width);

        $voucher = $this->invoiceRepository->getVoucher($saleId);
        $logo = RemoteEscposImage::loadImage($this->logoProvider->__invoke());

        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        $shouldPrintLogo = 'true' === strval(
            $this->settingsService->get(Constants::PRINT_LOGO, 'true')
        );

        // HEADER (logo + empresa)
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        if ($shouldPrintLogo && $logo instanceof EscposImage) {
            $printer->graphics($logo);
            $printer->feed();
        }

        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text($this->wrap($voucher->company_name, $width));
        $printer->selectPrintMode();

        if ($voucher->voucher_type !== '00') {
            $printer->text('RUC: ' . ($voucher->company_id ?? '-') . "\n");
        }

        $printer->text($this->wrap($voucher->company_address ?? '-', $width));
        if (!empty($voucher->company_phone)) {
            $printer->text('Tel.: ' . $voucher->company_phone . "\n");
        }

        $printer->text($hrDashed);

        // DOCUMENTO (tipo + serie-num)
        $docType = match ($voucher->voucher_type) {
            DocType::FACTURA->value => 'FACTURA ELECTRONICA',
            DocType::BOLETA->value => 'BOLETA ELECTRONICA',
            DocType::NOTA_VENTA->value => 'NOTA DE VENTA',
            default => 'TICKET',
        };

        $ticket = $voucher->voucher_series . ' - ' . $voucher->voucher_number;

        $printer->setEmphasis();
        $printer->text($docType . "\n");
        $printer->text($ticket . "\n");
        $printer->setEmphasis(false);

        $printer->text($hrSolid);

        // DATOS BÁSICOS (2 columnas: label izquierda, valor derecha)
        $tz = new \DateTimeZone('America/Lima');
        $date = $voucher->voucher_date ? new \DateTime((string)$voucher->voucher_date, $tz) : new \DateTime('now', $tz);

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($this->lr('Fecha:', $date->format('d/m/Y'), $width));
        $printer->text($this->lr('Moneda:', $voucher->voucher_currency ?: 'PEN', $width));

        if ($voucher->voucher_type !== DocType::NOTA_VENTA->value) {
            $paymentMethod = ((int)$voucher->voucher_payment_method === 2) ? 'CREDITO' : 'CONTADO';
            $printer->text($this->lr('Pago:', $paymentMethod, $width));
        }

        $printer->text($hrDashed);

        // CLIENTE
        $printer->text('CLIENTE: ' . ($voucher->customer_name ?? '-') . "\n");
        if (!empty($voucher->customer_doc_number)) {
            if ($voucher->voucher_type === DocType::FACTURA->value) {
                $printer->text('RUC: ' . $voucher->customer_doc_number . "\n");
            } elseif ($voucher->voucher_type === DocType::BOLETA->value) {
                $printer->text('DNI: ' . $voucher->customer_doc_number . "\n");
            } else {
                $printer->text('DOC: ' . $voucher->customer_doc_number . "\n");
            }
        }
        $printer->text($this->wrap('Direccion: ' . ($voucher->customer_address ?? '-'), $width));

        $printer->text($hrSolid);

        // ITEMS (cabecera)
        $printer->setEmphasis(true);
        $printer->text((string)new SaleItemDto('DESCRIPCION', 'P.UNIT', 'IMPORTE'));
        $printer->setEmphasis(false);
        $printer->text($hrDashed);

        foreach ($voucher->voucher_items as $item) {
            // Línea principal: nombre + columnas
            $saleItem = new SaleItemDto($item->product_name, $item->unit_price, $item->total);

            // (Opcional) enfatizar el nombre: si tu SaleItemDto imprime el nombre dentro de la columna,
            // el modo emphasis aplica a toda la línea. Si no te gusta, quítalo.
            $printer->text((string)$saleItem);

            // Línea secundaria: cantidad + uom (alineada a la izquierda)
            $qty = $this->formatQuantity((float)$item->quantity);
            $uom = $item->product_uom ?? '';
            $printer->text('  ' . trim($qty . ' ' . $uom) . "\n");
        }

        $printer->text($hrDashed);

        // TOTALES
        if ((float)$voucher->voucher_discount_percentage > 0) {
            $printer->text((string)new SaleItemDto('DESCUENTO', '', '-' . $voucher->voucher_discount_amount));
        }

        $app_option_rc_get = (int)$this->settingsService->get(Constants::CONSUMPTION_TAX);
        $app_option_igv_get = (int)$this->settingsService->get(Constants::TAX);
        $hasDetraction = $voucher->voucher_detraction_amount && $voucher->voucher_detraction_amount > 0;

        if ($voucher->voucher_type !== DocType::NOTA_VENTA->value) {
            $printer->text((string)new SaleItemDto('OP. GRAVADA', '', $voucher->voucher_taxed_amount));
            $printer->text(
                (string)new SaleItemDto('IGV (' . $app_option_igv_get . '%)', '', $voucher->voucher_tax_amount)
            );
            if ($app_option_rc_get >= 1 && (float)$voucher->voucher_consumption_surcharge_amount > 0) {
                $printer->text(
                    (string)new SaleItemDto(
                        'RC (' . $app_option_rc_get . '%)',
                        '',
                        $voucher->voucher_consumption_surcharge_amount
                    )
                );
            }
            if ($hasDetraction) {
                $detractionPercent = $voucher->voucher_detraction_percent ?? 0;
                $printer->text(
                    (string)new SaleItemDto(
                        'DETRACCION (' . $detractionPercent . '%)',
                        '',
                        $voucher->voucher_detraction_amount
                    )
                );
            }
        }

        // TOTAL destacado: línea sólida + emphasis
        $printer->text($hrSolid);
        $printer->setEmphasis(true);
        $printer->text((string)new SaleItemDto('TOTAL', '', $voucher->voucher_sale_amount));
        $printer->setEmphasis(false);
        $printer->text($hrDashed);

        // DETRACCIÓN (si aplica)
        if ($hasDetraction) {
            $detractionType = trim(
                ($voucher->voucher_detraction_code ?? '') .
                ($voucher->voucher_detraction_description ? ' - ' . $voucher->voucher_detraction_description : '')
            );
            $detractionPayment = trim(
                ($voucher->voucher_detraction_payment_method ?? '') .
                ($voucher->voucher_detraction_payment_description ? ' - ' . $voucher->voucher_detraction_payment_description : '')
            );

            $printer->text($this->wrap('Tipo detraccion: ' . ($detractionType !== '' ? $detractionType : '-'), $width));
            $printer->text(
                $this->wrap('Medio pago: ' . ($detractionPayment !== '' ? $detractionPayment : '-'), $width)
            );

            if (!empty($voucher->voucher_detraction_account)) {
                $printer->text($this->wrap('CTA detraccion: ' . $voucher->voucher_detraction_account, $width));
            }

            $printer->text($hrDashed);
        }

        if (!empty($voucher->voucher_bank_name) || !empty($voucher->voucher_bank_account) || !empty($voucher->voucher_bank_cci)) {
            $printer->text('Datos bancarios para depósito / transferencia:' . "\n");
            $printer->text('Banco: ' . ($voucher->voucher_bank_name ?: '-') . "\n");
            if (!empty($voucher->voucher_bank_account)) {
                $printer->text('Cuenta: ' . $voucher->voucher_bank_account . "\n");
            }
            if (!empty($voucher->voucher_bank_cci)) {
                $printer->text('CCI: ' . $voucher->voucher_bank_cci . "\n");
            }
        }

        $printer->text($hrSolid);

        // MONTO EN LETRAS (centrado)
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text($this->wrap($voucher->getVoucherTotalText(), $width));

        // QR + HASH + texto legal
        if ($voucher->voucher_type !== DocType::NOTA_VENTA->value) {
            if ('true' === (string)($_ENV['PRINTER_PRINT_QR'] ?? 'false')) {
                $printer->feed();
                $printer->qrCode($voucher->voucher_qr_data, Printer::QR_ECLEVEL_L, 8);
            }

            $printer->feed();
            $printer->text("Resumen:\n");
            $printer->text($this->wrap($voucher->voucher_hash ?? '', $width));

            $printer->feed();
            $printer->text($this->wrap('Representacion impresa del comprobante electronico.', $width));
            $printer->text($this->wrap('Consulte en: ' . ($_ENV['EFACT_URL'] ?? ''), $width));
        }

        // FOOTER
        $printer->feed();
        $printer->text((new \DateTime('now', $tz))->format('d/m/Y H:i:s') . "\n");
        $printer->text('Gracias por su preferencia' . "\n");

        $printer->feed();
        $printer->cut();
        $printer->pulse();

        $commands = $connector->getData();
        $printer->close();

        return $commands;
    }

    private function hr(string $char, int $width): string
    {
        return str_repeat($char, $width) . "\n";
    }

    /**
     * Left/Right line within printer width.
     */
    private function lr(string $left, string $right, int $width): string
    {
        $left = rtrim($left);
        $right = ltrim($right);

        $space = $width - mb_strlen($left) - mb_strlen($right);
        if ($space < 1) {
            // Si no entra, hacemos wrap en dos líneas
            return $left . "\n" . str_pad($right, $width, ' ', STR_PAD_LEFT) . "\n";
        }

        return $left . str_repeat(' ', $space) . $right . "\n";
    }

    /**
     * Word wrap that respects printer width.
     */
    private function wrap(string $text, int $width): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return "-\n";
        }

        return wordwrap($text, $width, "\n", true) . "\n";
    }


    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 2, '.', '');
    }
}
