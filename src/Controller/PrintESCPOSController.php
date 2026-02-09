<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Extension\RemoteEscposImage;
use App\Core\Service\LogoProvider;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Dto\Sale\SaleItemDto;
use App\Repository\TaxDocumentRepository;
use App\Sale\Domain\DocType;
use Doctrine\DBAL\Exception;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PrintESCPOSController extends AbstractController
{
    private const array PAPER_WIDTH_MAP = [
        58 => 32,
        80 => 48,
    ];

    private const array PAPER_PIXEL_WIDTH_MAP = [
        58 => 384,
        80 => 576,
    ];

    private const string IMAGE_MODE_DEFAULT = 'bitimage';
    private const array IMAGE_MODE_MAP = [
        'bitimage' => 'bitimage',
        'graphics' => 'graphics',
    ];

    private const string LINE_STYLE_DEFAULT = 'unicode';
    private const array LINE_SOLID_CHAR = [
        'unicode' => '─', // U+2500 BOX DRAWINGS LIGHT HORIZONTAL
        'ascii' => '=',
    ];

    public function __construct(
        private readonly LogoProvider $logoProvider,
        private readonly TaxDocumentRepository $taxDocumentRepository,
        private readonly SettingsService $settingsService,
        #[Autowire('%env(PRINTER_LINE_WIDTH)%')]
        private readonly int $printerLineWidth,
    ) {
    }

    /**
     * @throws Exception
     */
    public function __invoke(string $id, Request $request): Response
    {
        $paperWidth = $request->query->getInt('paper_width');
        $lineWidth = self::PAPER_WIDTH_MAP[$paperWidth] ?? null;
        $baseUrl = $request->getSchemeAndHttpHost();
        $imageMode = $request->query->getString('image_mode', self::IMAGE_MODE_DEFAULT);
        $lineStyle = $request->query->getString('line_style', self::LINE_STYLE_DEFAULT);

        $commands = $this->printCommands($id, $lineWidth, $baseUrl, $imageMode, $lineStyle, $paperWidth);

        return new Response(
            $commands,
            Response::HTTP_OK,
            ['Content-Type' => 'application/octet-stream']
        );
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function printCommands(
        string $saleId,
        ?int $lineWidth = null,
        ?string $baseUrl = null,
        string $imageMode = self::IMAGE_MODE_DEFAULT,
        string $lineStyle = self::LINE_STYLE_DEFAULT,
        int $paperWidth = 80,
    ): string {
        $width = $lineWidth ?? max(32, $this->printerLineWidth);
        $solidChar = self::LINE_SOLID_CHAR[$lineStyle] ?? self::LINE_SOLID_CHAR['unicode'];

        $hrDashed = $this->hr('-', $width);
        $hrSolid = $this->hr($solidChar, $width);

        $voucher = $this->taxDocumentRepository->getVoucher($saleId);
        $logoMaxPixels = (int) ((self::PAPER_PIXEL_WIDTH_MAP[$paperWidth] ?? self::PAPER_PIXEL_WIDTH_MAP[80]) * 0.5);
        $logo = RemoteEscposImage::loadImage($this->logoProvider->__invoke(), $logoMaxPixels);

        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        $shouldPrintLogo = 'true' === strval(
            $this->settingsService->get(Constants::PRINT_LOGO, 'true')
        );

        // HEADER (logo + company name)
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        if ($shouldPrintLogo && $logo instanceof EscposImage) {
            $printerImageMode = self::IMAGE_MODE_MAP[$imageMode] ?? self::IMAGE_MODE_MAP[self::IMAGE_MODE_DEFAULT];
            if ($printerImageMode === 'graphics') {
                $printer->graphics($logo);
            } else {
                $printer->bitImage($logo);
            }
            $printer->feed();
        }

        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text($this->wrap($voucher->company_name, $width));
        $printer->selectPrintMode();

        if ($voucher->voucher_type !== '00') {
            $printer->text('RUC: ' . ($voucher->company_id ?? '-') . "\n");
        }

        $address = !empty($voucher->branch_address) ? $voucher->branch_address : ($voucher->company_address ?? '-');
        $phone = !empty($voucher->branch_phone) ? $voucher->branch_phone : $voucher->company_phone;

        $printer->text($this->wrap($address, $width));
        if (!empty($phone)) {
            $printer->text('Tel.: ' . $phone . "\n");
        }

        $printer->text($hrDashed);

        // DOCUMENT (type + series-number)
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

        // CUSTOMER
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

        // ITEMS (header)
        $printer->setEmphasis(true);
        $printer->text((string)new SaleItemDto('DESCRIPCION', 'P.UNIT', 'IMPORTE', $width));
        $printer->setEmphasis(false);
        $printer->text($hrDashed);

        foreach ($voucher->voucher_items as $item) {
            // Principal line: description + unit price + total
            $saleItem = new SaleItemDto($item->product_name, $item->unit_price, $item->total, $width);

            $printer->text((string)$saleItem);

            // Secondary line: quantity + unit
            $qty = $this->formatQuantity((float)$item->quantity);
            $uom = $item->product_uom ?? '';
            $printer->text('  ' . trim($qty . ' ' . $uom) . "\n");
        }

        $printer->text($hrDashed);

        // TOTALS
        if ((float)$voucher->voucher_discount_percentage > 0) {
            $printer->text((string)new SaleItemDto('DESCUENTO', '', '-' . $voucher->voucher_discount_amount, $width));
        }

        $app_option_rc_get = (int)$this->settingsService->get(Constants::CONSUMPTION_TAX);
        $app_option_igv_get = (int)$this->settingsService->get(Constants::TAX);
        $hasDetraction = $voucher->voucher_detraction_amount && $voucher->voucher_detraction_amount > 0;

        if ($voucher->voucher_type !== DocType::NOTA_VENTA->value) {
            $printer->text((string)new SaleItemDto('OP. GRAVADA', '', $voucher->voucher_taxed_amount, $width));
            $printer->text(
                (string)new SaleItemDto('IGV (' . $app_option_igv_get . '%)', '', $voucher->voucher_tax_amount, $width)
            );
            if ($app_option_rc_get >= 1 && (float)$voucher->voucher_consumption_surcharge_amount > 0) {
                $printer->text(
                    (string)new SaleItemDto(
                        'RC (' . $app_option_rc_get . '%)',
                        '',
                        $voucher->voucher_consumption_surcharge_amount,
                        $width,
                    )
                );
            }
        }

        // FINAL TOTAL
        $printer->text($hrSolid);
        $printer->setEmphasis(true);
        $printer->text((string)new SaleItemDto('TOTAL', '', $voucher->voucher_sale_amount, $width));
        $printer->setEmphasis(false);
        $printer->text($hrDashed);

        // DETRACTION DETAILS
        if ($hasDetraction) {
            $detractionPercent = $voucher->voucher_detraction_percent ?? 0;
            $printer->text(
                (string)new SaleItemDto(
                    'DETRACCION (' . $detractionPercent . '%)',
                    '',
                    $voucher->voucher_detraction_amount,
                    $width,
                )
            );
            $detractionType = trim(
                ($voucher->voucher_detraction_code ?? '') .
                ($voucher->voucher_detraction_description ? ' - ' . $voucher->voucher_detraction_description : '')
            );
            $paymentDescription = $voucher->voucher_detraction_payment_description;
            $detractionPayment = trim(
                ($voucher->voucher_detraction_payment_method ?? '') .
                ($paymentDescription ? ' - ' . $paymentDescription : '')
            );

            $printer->text($this->wrap('Tipo detracción: ' . ($detractionType !== '' ? $detractionType : '-'), $width));
            $printer->text(
                $this->wrap('Medio pago: ' . ($detractionPayment !== '' ? $detractionPayment : '-'), $width)
            );

            if (!empty($voucher->voucher_detraction_account)) {
                $printer->text($this->wrap('CTA detracción: ' . $voucher->voucher_detraction_account, $width));
            }

            $printer->text($hrDashed);
        }

        if (
            !empty($voucher->voucher_bank_name) ||
            !empty($voucher->voucher_bank_account) ||
            !empty($voucher->voucher_bank_cci)
        ) {
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

        // TOTAL IN WORDS
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text($this->wrap($voucher->getVoucherTotalText(), $width));

        // QR + HASH + legal text
        if ($voucher->voucher_type !== DocType::NOTA_VENTA->value) {
            $printer->feed();
            $printer->qrCode($voucher->voucher_qr_data, Printer::QR_ECLEVEL_L, 5);

            $printer->feed();
            $printer->text("Resumen:\n");
            $printer->text($this->wrap($voucher->voucher_hash ?? '', $width));

            $printer->feed();
            $printer->text($this->wrap('Representación impresa del comprobante electrónico.', $width));
            if ($baseUrl !== null) {
                $printer->text($this->wrap('Consulte en: ' . $baseUrl . '/buscar', $width));
            }
        }

        // FOOTER
        $printer->feed();
        $printer->text(new \DateTime('now', $tz)->format('d/m/Y H:i:s') . "\n");
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
            // Not enough space, print in two lines
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
