<?php

namespace App\WebClientPrint;

use App\Base\BaseController;
use App\Sale\DocType;
use App\Sale\Domain\SaleGetOneById;
use App\Service\SettingsService;
use App\Util\SaleItem;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;
use NumberFormatter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PrintESCPOSController extends BaseController
{
    /**
     * @throws \Exception
     */
    #[Route('/wcp/print', name: 'wcp_print', methods: ['GET'])]
    public function printCommands(Request $request, SaleGetOneById $getOneById, SettingsService $settingsService): Response
    {
        if (!$request->query->has(WebClientPrint::CLIENT_PRINT_JOB)) {
            return new Response('No print job sent!', 400);
        }
        $useDefaultPrinter = $request->query->get('useDefaultPrinter');
        $printerName = $request->query->get('printerName');
        $saleId = $request->query->get('saleId');

        $divider = str_pad('', $_ENV['PRINTER_LINE_WIDTH'], "-", STR_PAD_BOTH) . "\n";

        $sale = $getOneById($saleId);
        $header = $sale['header'];
        $details = $sale['details'];
        $logo = EscposImage::load(dirname(__FILE__, 3) . "/public/assets/images/logo.png");

        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if ($_ENV['PRINTER_PRINT_LOGO']) {
            $printer->graphics($logo);
            $printer->feed();
        }
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text($header['company_name'] . "\n");
        $printer->selectPrintMode();
        $printer->text('RUC ' . $header['company_id'] . "\n");
        $printer->text($header['company_address'] . "\n");
        $printer->text('Telefono ' . $header['company_phone'] . "\n");
        $docType = match ($header['doc_type']) {
            DocType::FACTURA->value => 'FACTURA ELECTRONICA',
            DocType::BOLETA->value => 'BOLETA ELECTRONICA',
            DocType::NOTA_VENTA->value => 'NOTA DE VENTA',
            default => 'TICKET',
        };
        $ticket = $header['doc_series'] . '-' . $header['doc_number'];
        $printer->text($divider);
        $printer->setEmphasis();
        $printer->text($docType . "\n");
        $printer->text($ticket . "\n");
        $printer->setEmphasis(false);
        $printer->setJustification();
        $printer->text($divider);
        $docDate = date("d/m/Y", strtotime($header['doc_date']));
        $printer->text('Fecha emision: ' . $docDate . "\n");
        $printer->text("Moneda: Soles\n");
        $total = 0;
        $printer->text($divider);
        if ($header['doc_type'] == DocType::FACTURA->value) {
            $printer->text('RUC Cli.: ' . $header['client_doc_number'] . "\n");
            $printer->text('R.Social: ' . $header['client_name'] . "\n");
        } elseif ($header['doc_type'] == DocType::BOLETA->value) {
            $printer->text('DNI: ' . $header['client_doc_number'] . "\n");
            $printer->text('Cliente: ' . $header['client_name'] . "\n");
        } else {
            $printer->text('Cliente: ' . $header['client_name'] . "\n");
        }
        $printer->text('Direccion: ' . $header['client_address'] . "\n");
        if ($header['doc_type'] != DocType::NOTA_VENTA->value) {
            $printer->text("Forma de pago: CONTADO\n");
        }
        $printer->text($divider);
        $printer->text(new SaleItem('# Descripcion', "S/"));
        $printer->text($divider);

        for ($i = 0; $i < count($details); $i++) {
            $str_left = floor($details[$i]['quantity']) . ' ' . $details[$i]['product'];
            $item = new SaleItem($str_left, $details[$i]['price']);
            $printer->text($item);
            $total += $details[$i]['price'];
        }
        if ($header['doc_discount_percent'] > 0) {
            $discount = $total * $header['doc_discount_percent'] / 100;
            $discount = number_format($discount, 2, '.', '');
            $printer->text(new SaleItem("Descuento", '-' . $discount));
            $total = $total - $discount;
        }

        $app_option_rc_get = (int)$settingsService->get('app_option_rc');
        $app_option_igv_get = (int)$settingsService->get('app_option_igv');
        $valor_rc = 0;
        $igv = $app_option_igv_get / 100;
        if ($app_option_rc_get >= 1) {
            $rc = $app_option_rc_get / 100;
            $divisor = 1 + $igv + $rc;
            $valor_venta = $total / $divisor;
            $valor_igv = $valor_venta * $igv;
            $valor_rc = $valor_venta * $rc;
        } else {
            $divisor = 1 + $igv;
            $valor_venta = $total / $divisor;
            $valor_igv = $valor_venta * $igv;
        }

        $str_opg = number_format($valor_venta, 2, '.', '');
        $str_igv = number_format($valor_igv, 2, '.', '');
        $str_consumo = number_format($valor_rc, 2, '.', '');
        $str_total = number_format($total, 2, '.', '');
        $printer->text($divider);
        if ($header['doc_type'] != DocType::NOTA_VENTA->value) {
            $printer->text(new SaleItem('IMPORTE', $str_total));
            $printer->text(new SaleItem('OP. GRAVADA', $str_opg));
            $printer->text(new SaleItem('OP. INAFECTA', '0.00'));
            $printer->text(new SaleItem('OP. EXONERADA', '0.00'));
            $printer->text(new SaleItem('IGV ' . $app_option_igv_get . '% :', $str_igv));
            if ($app_option_rc_get >= 1) {
                $printer->text(new SaleItem('RC' . $app_option_rc_get . '% :', $str_consumo));
            }
        }
        $printer->text(new SaleItem('TOTAL', $str_total));
        $formatter = new NumberFormatter("es", NumberFormatter::SPELLOUT);
        $parts = explode('.', $str_total);
        $amount = $formatter->format($parts[0]);
        $strAmount = mb_strtoupper($amount . " Y " . $parts[1] . "/100");

        $printer->text($divider);
        $printer->text($strAmount . "\n");
        $printer->text($divider);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if ($header['doc_type'] != DocType::NOTA_VENTA->value) {
            if ($_ENV['PRINTER_PRINT_QR']) {
                $qr_data = $header['client_doc_number'] . "|" . $header['doc_type'] . "|" . $header['doc_series'] . "|" . $header['doc_number'] . "|" . $str_igv . "|" . $str_total . "|" . $header['doc_date'] . "|0|0|" . $header['doc_digest'];
                $printer->qrCode($qr_data, Printer::QR_ECLEVEL_L, 8);
            }
            $printer->feed();
            $printer->text("Resumen: " . $header['doc_digest'] . "\n");
            $printer->feed();
            $printer->text("Representacion impresa del" . "\n");
            $printer->text("comprobante electronico, consulte en" . "\n");
            $printer->text($_ENV['EFACT_URL'] . "\n");
        }
        $printer->feed();
        $printer->text(date("d-m-Y H:i:s") . "\n");
        $printer->text("** Gracias por su preferencia **" . "\n");
        $printer->feed();
        $printer->cut();
        $printer->pulse();
        $cpj = new ClientPrintJob();
        $cpj->printerCommands = $connector->getData();
        $printer->close();
        $cpj->formatHexValues = true;

        if ($useDefaultPrinter || $printerName === 'null') {
            $cpj->clientPrinter = new DefaultPrinter();
        } else {
            $cpj->clientPrinter = new InstalledPrinter($printerName);
        }
        echo '<pre>' . $cpj->printerCommands . '</pre>';
        exit();
        return new Response($cpj->sendToClient(), 200, ['Content-Type' => 'application/octet-stream']);
    }
}
