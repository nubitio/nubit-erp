<?php

namespace App\Controller;

use App\Core\Base\BaseController;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Extension\RemoteEscposImage;
use App\Core\Infrastructure\Service\LogoProvider;
use App\Core\Infrastructure\Service\SettingsService;
use App\Dto\Sale\SaleItemDto;
use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Sale\Domain\DocType;
use Doctrine\DBAL\Exception;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;
use Symfony\Component\HttpFoundation\Response;

class PrintESCPOSController extends BaseController
{
    public function __construct(
        private readonly LogoProvider $logoProvider,
        private readonly GetOneVoucherUseCase $getOneVoucherUseCase,
        private readonly SettingsService $settingsService,
    ) {
        parent::__construct();
    }

    /**
     * @throws Exception
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
     * @throws \Exception
     */
    public function printCommands(string $saleId): string
    {
        $divider = str_pad('', $_ENV['PRINTER_LINE_WIDTH'], '-', STR_PAD_BOTH)
            . "\n";

        $voucher = $this->getOneVoucherUseCase->__invoke($saleId);
        $logo = RemoteEscposImage::loadImage($this->logoProvider->__invoke());

        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        $printer->setJustification(Printer::JUSTIFY_CENTER);

        $app_option_logo = $this->settingsService->get(
            'app_option_printing_logo'
        );
        if ('true' == $app_option_logo) {
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

        $docDate = date('d/m/Y', strtotime((string) $voucher->voucher_date));
        $printer->text('Fecha emision: ' . $docDate . "\n");
        $printer->text("Moneda: Soles\n");
        $printer->text($divider);
        $printer->text('CLIENTE: ' . $voucher->customer_name . "\n");
        if ($voucher->voucher_type == DocType::FACTURA->value) {
            $printer->text('RUC: ' . $voucher->customer_doc_number . "\n");
        } elseif ($voucher->voucher_type == DocType::BOLETA->value) {
            $printer->text('DNI: ' . $voucher->customer_doc_number . "\n");
        }

        $printer->text('DIRECCION: ' . $voucher->customer_address . "\n");
        if ($voucher->voucher_type != DocType::NOTA_VENTA->value) {
            $paymentMethod = 2 == $voucher->voucher_payment_method ? 'CREDITO'
                : 'CONTADO';
            $printer->text(sprintf('FORMA DE PAGO: %s%s', $paymentMethod, PHP_EOL));
        }

        $printer->text($divider);
        $printer->setEmphasis();
        $printer->text(new SaleItemDto('# Descripcion', 'S/'));
        $printer->setEmphasis(false);
        $printer->text($divider);

        foreach ($voucher->voucher_items as $item) {
            $str_left = $item->quantity . ' ' . $item->product_name;
            $saleItem = new SaleItemDto($str_left, $item->total);
            $printer->text($saleItem);
        }

        $printer->text($divider);

        if ($voucher->voucher_discount_percentage > 0) {
            $printer->text(
                new SaleItemDto('DTO.:', '-' . $voucher->voucher_discount_amount)
            );
        }

        $app_option_rc_get = (int) $this->settingsService->get(
            Constants::CONSUMPTION_TAX
        );
        $app_option_igv_get = (int) $this->settingsService->get(Constants::TAX);

        if ($voucher->voucher_type != DocType::NOTA_VENTA->value) {
            $printer->text(
                new SaleItemDto('OP. GRAVADA:', $voucher->voucher_taxed_amount)
            );
            $printer->text(new SaleItemDto('OP. INAFECTA:', '0.00'));
            $printer->text(new SaleItemDto('OP. EXONERADA:', '0.00'));
            $printer->text(
                new SaleItemDto(
                    'IGV (' . $app_option_igv_get . '%):',
                    $voucher->voucher_tax_amount
                )
            );
            if ($app_option_rc_get >= 1) {
                $printer->text(
                    new SaleItemDto(
                        'RC (' . $app_option_rc_get . '%):',
                        $voucher->voucher_consumption_surcharge_amount
                    )
                );
            }
        }

        $printer->text(
            new SaleItemDto('TOTAL:', $voucher->voucher_sale_amount)
        );

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
}
