<?php

namespace App\WebClientPrint;

use App\Base\BaseController;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Extension\RemoteEscposImage;
use App\Core\Infrastructure\Service\LogoProvider;
use App\Core\Infrastructure\Service\SettingsService;
use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Sale\Domain\DocType;
use Doctrine\DBAL\Exception;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PrintESCPOSController extends BaseController
{
    public function __construct(
        private readonly LogoProvider $logoProvider,
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private SettingsService $settingsService
    ) {
        parent::__construct();
    }

    #[Route('/wcp/print', name: 'wcp_print', methods: ['GET'])]
    public function print(Request $request): Response
    {
        if (!$request->query->has(WebClientPrint::CLIENT_PRINT_JOB)) {
            return new Response('No print job sent!', Response::HTTP_BAD_REQUEST);
        }

        $useDefaultPrinter = $request->query->get('useDefaultPrinter');
        $printerName = $request->query->get('printerName');
        $saleId = $request->query->get('saleId');

        $cpj = new ClientPrintJob();
        $cpj->printerCommands = $this->printCommands($saleId);
        $cpj->formatHexValues = true;

        if ($useDefaultPrinter || 'null' === $printerName) {
            $cpj->clientPrinter = new DefaultPrinter();
        } else {
            $cpj->clientPrinter = new InstalledPrinter($printerName);
        }

        return new Response($cpj->sendToClient(), Response::HTTP_OK, ['Content-Type' => 'application/octet-stream']);
    }

    #[Route('/qz/print/{saleId}', name: 'qz_raw_print', methods: ['GET'])]
    public function rawPrint(string $saleId): Response
    {
        $commands = $this->printCommands($saleId);

        return new Response($commands, Response::HTTP_OK, ['Content-Type' => 'application/octet-stream']);
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws \Exception
     */
    public function printCommands($saleId): string
    {
        $divider = str_pad('', $_ENV['PRINTER_LINE_WIDTH'], '-', STR_PAD_BOTH)."\n";

        $voucher = $this->getOneVoucherUseCase->__invoke($saleId);
        $logo = RemoteEscposImage::loadImage($this->logoProvider->getLogo());

        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $app_option_logo = $this->settingsService->get('app_option_printing_logo');
        if ('true' == $app_option_logo) {
            $printer->graphics($logo);
            $printer->feed();
        }
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text($voucher->company_name."\n");
        $printer->selectPrintMode();
        $printer->text('RUC: '.$voucher->company_id."\n");
        $printer->text($voucher->company_address."\n");
        $printer->text('Tel.: '.$voucher->company_phone."\n");
        $docType = match ($voucher->voucher_type) {
            DocType::FACTURA->value => 'FACTURA ELECTRONICA',
            DocType::BOLETA->value => 'BOLETA ELECTRONICA',
            DocType::NOTA_VENTA->value => 'NOTA DE VENTA',
            default => 'TICKET',
        };
        $ticket = $voucher->voucher_series.'-'.$voucher->voucher_number;
        $printer->text($divider);
        $printer->setEmphasis();
        $printer->text($docType."\n");
        $printer->text($ticket."\n");
        $printer->setEmphasis(false);
        $printer->setJustification();
        $printer->text($divider);
        $docDate = date('d/m/Y', strtotime($voucher->voucher_date));
        $printer->text('Fecha emision: '.$docDate."\n");
        $printer->text("Moneda: Soles\n");
        $printer->text($divider);
        $printer->text('CLIENTE: '.$voucher->customer_name."\n");
        if ($voucher->voucher_type == DocType::FACTURA->value) {
            $printer->text('RUC: '.$voucher->customer_doc_number."\n");
        } elseif ($voucher->voucher_type == DocType::BOLETA->value) {
            $printer->text('DNI: '.$voucher->customer_doc_number."\n");
        }
        $printer->text('DIRECCION: '.$voucher->customer_address."\n");
        if ($voucher->voucher_type != DocType::NOTA_VENTA->value) {
            $paymentMethod = 2 == $voucher->voucher_payment_method ? 'CREDITO' : 'CONTADO';
            $printer->text("FORMA DE PAGO: $paymentMethod\n");
        }
        $printer->text($divider);
        $printer->setEmphasis();
        $printer->text(new SaleItem('# Descripcion', 'S/'));
        $printer->setEmphasis(false);
        $printer->text($divider);

        foreach ($voucher->voucher_items as $item) {
            $str_left = $item->quantity.' '.$item->product_name;
            $saleItem = new SaleItem($str_left, $item->total);
            $printer->text($saleItem);
        }

        $printer->text($divider);

        if ($voucher->voucher_discount_percentage > 0) {
            $printer->text(new SaleItem('DTO.:', '-'.$voucher->voucher_discount_amount));
        }

        $app_option_rc_get = (int) $this->settingsService->get(Constants::CONSUMPTION_TAX);
        $app_option_igv_get = (int) $this->settingsService->get(Constants::TAX);

        if ($voucher->voucher_type != DocType::NOTA_VENTA->value) {
            $printer->text(new SaleItem('OP. GRAVADA:', $voucher->voucher_taxed_amount));
            $printer->text(new SaleItem('OP. INAFECTA:', '0.00'));
            $printer->text(new SaleItem('OP. EXONERADA:', '0.00'));
            $printer->text(new SaleItem('IGV ('.$app_option_igv_get.'%):', $voucher->voucher_tax_amount));
            if ($app_option_rc_get >= 1) {
                $printer->text(new SaleItem('RC ('.$app_option_rc_get.'%):', $voucher->voucher_consumption_surcharge_amount));
            }
        }
        $printer->text(new SaleItem('TOTAL:', $voucher->voucher_sale_amount));

        $printer->text($divider);
        $printer->text($voucher->getVoucherTotalText()."\n");
        $printer->text($divider);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if ($voucher->voucher_type != DocType::NOTA_VENTA->value) {
            if ('true' == $_ENV['PRINTER_PRINT_QR']) {
                $printer->qrCode($voucher->voucher_qr_data, Printer::QR_ECLEVEL_L, 8);
            }
            $printer->feed();
            $printer->text('Resumen: '.$voucher->voucher_hash."\n");
            $printer->feed();
            $printer->text('Representacion impresa del'."\n");
            $printer->text('comprobante electronico, consulte en'."\n");
            $printer->text($_ENV['EFACT_URL']."\n");
        }
        $printer->feed();
        $printer->text(date('d/m/Y H:i:s')."\n");
        $printer->text('** Gracias por su preferencia **'."\n");
        $printer->feed();
        $printer->cut();
        $printer->pulse();
        $commands = $connector->getData();
        $printer->close();

        return $commands;
    }
}
