<?php

namespace App\Controllers;

include dirname(dirname(__FILE__)) . '/WebClientPrint.php';

use App\Models\MovimientoModel;
use App\Utils\SaleItem;
use Neodynamic\SDK\Web\WebClientPrint;
use Neodynamic\SDK\Web\DefaultPrinter;
use Neodynamic\SDK\Web\InstalledPrinter;
use Neodynamic\SDK\Web\ClientPrintJob;

use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use NumberFormatter;

class PrintESCPOSController extends BaseController
{

    private MovimientoModel $model;

    function __construct()
    {
        parent::__construct();
        $this->model = new MovimientoModel();
    }

    function beforeroute()
    {
        //needed for avoid redirect loop
    }

    public function printCommands()
    {
        // Process request
        // Generate ClientPrintJob? only if clientPrint param is in the query string
        $urlParts = parse_url($_SERVER['REQUEST_URI']);
        $str_line = str_pad('', $this->app->get('LINE_WIDTH'), "-", STR_PAD_BOTH) . "\n";

        if (isset($urlParts['query'])) {
            $rawQuery = $urlParts['query'];
            parse_str($rawQuery, $qs);
            if (isset($qs[WebClientPrint::CLIENT_PRINT_JOB])) {

                $useDefaultPrinter = ($qs['useDefaultPrinter'] === 'checked');
                $printerName = urldecode($qs['printerName']);

                $id = urldecode($qs['ticket']);
                $digest = urldecode($qs['digest']);

                $cabecera = $this->model->ticket_cabecera($id);
                $detalle = $this->model->ticket_detalle($id);

                $logo = EscposImage::load("src/assets/images/logo.png", false);

                $connector = new DummyPrintConnector();
                $printer = new Printer($connector);
                //Create ESC/POS commands
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                /* Print top logo */
                //$printer->graphics($logo);
                $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
                $printer->text($cabecera['empresa'] . "\n");
                $printer->selectPrintMode();
                $printer->text('RUC ' . $cabecera['ruc_empresa'] . "\n");
                $printer->text($cabecera['direccion_empresa'] . "\n");
                $printer->text($cabecera['direccion_local'] . "\n");
                $printer->text('Telefono ' . $cabecera['telefono'] . "\n");
                if ($cabecera['operacion_id'] == '37b1e294-c224-11e7-91af-5600005a2745') {
                    $tipo = 'FACTURA ELECTRONICA';
                } else {
                    $tipo = 'BOLETA ELECTRONICA';
                }
                $ticket = $cabecera['serie'] . '-' . $cabecera['numero'];
                $printer->text($str_line);
                $printer->setEmphasis(true);
                $printer->text($tipo . "\n");
                $printer->text($ticket . "\n");
                $printer->setEmphasis(false);
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->text($str_line);
                $fecha = date("d-m-Y", strtotime($cabecera['fecha']));
                $printer->text('Fecha emision: ' . $fecha . "\n");
                $printer->text("Moneda: Soles\n");
                $total = 0;
                $printer->text($str_line);
                if ($cabecera['operacion_id'] == '37b1e294-c224-11e7-91af-5600005a2745') {
                    $printer->text('RUC Cli.: ' . $cabecera['ruc_cliente'] . "\n");
                    $printer->text('R.Social: ' . $cabecera['cliente'] . "\n");
                    $printer->text('Direccion: ' . $cabecera['direccion_cliente'] . "\n");
                } else {
                    $printer->text('DNI: ' . $cabecera['ruc_cliente'] . "\n");
                    $printer->text('Cliente: ' . $cabecera['cliente'] . "\n");
                    $printer->text('Direccion: ' . $cabecera['direccion_cliente'] . "\n");
                }
                $printer->text($str_line);
                $printer->text(new SaleItem('# Descripcion', "S/"));
                $printer->text($str_line);

                for ($i = 0; $i < count($detalle); $i++) {
                    $str_left = floor($detalle[$i]['cantidad']) . ' ' . $detalle[$i]['producto'];
                    $item = new SaleItem($str_left, $detalle[$i]['precio']);
                    $printer->text($item);
                    $total += $detalle[$i]['precio'];
                }
                if ($cabecera['descuento'] > 0) {
                    $descuento = $total * $cabecera['descuento'] / 100;
                    $descuento = number_format($descuento, 2, '.', '');
                    $printer->text(new SaleItem("Descuento", '-' . $descuento));
                    $total = $total - $descuento;
                }

                $igv = 0.18;
                $divisor = 1 + $igv;
                $valor_venta = $total / $divisor;
                $valor_igv = $valor_venta * $igv;
                $str_opg = number_format($valor_venta, 2, '.', '');
                $str_igv = number_format($valor_igv, 2, '.', '');
                $str_total = number_format($total, 2, '.', '');
                //if ($cabecera['operacion_id'] == '37b1e294-c224-11e7-91af-5600005a2745') {
                $printer->text($str_line);
                if (1 == 1) {
                    $printer->text(new SaleItem('IMPORTE', $str_total));
                    $printer->text(new SaleItem('OP. GRAVADA', $str_opg));
                    $printer->text(new SaleItem('OP. INAFECTA', '0.00'));
                    $printer->text(new SaleItem('OP. EXONERADA', '0.00'));
                    $printer->text(new SaleItem('IGV 18%', $str_igv));
                    $printer->text(new SaleItem('TOTAL', $str_total));

                    $formatter = new NumberFormatter("es", NumberFormatter::SPELLOUT);
                    $parts = explode('.', $str_total);
                    $monto = $formatter->format($parts[0]);
                    $str_monto = mb_strtoupper($monto . " Y " . $parts[1] . "/100");

                    $printer->text($str_line);
                    $printer->text($str_monto . "\n");
                } else {
                    $printer->text(new SaleItem('TOTAL', $str_total));
                }
                $printer->text($str_line);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                if ($this->app->get('PRINT_QR')) {
                    if ($cabecera['operacion_id'] == '37b1e294-c224-11e7-91af-5600005a2745') {
                        $tipo_id = '01';
                    } else {
                        $tipo_id = '03';
                    }
                    $qr_data = $cabecera['ruc_cliente'] . "|" . $tipo_id . "|" . $cabecera['serie'] . "|" . $cabecera['numero'] . "|" . $str_igv . "|" . $str_total . "|" . $cabecera['fecha'] . "|0|0|" . $digest;
                    $printer->qrCode($qr_data, Printer::QR_ECLEVEL_L, 8);
                }
                $printer->feed();
                $printer->text("Resumen: " . $digest . "\n");
                $printer->feed();
                $printer->text("Representacion impresa del" . "\n");
                $printer->text("comprobante electronico, consulte en" . "\n");
                $printer->text($this->app->get('EFACT_URL') . "\n");
                $printer->feed();
                $printer->text(date("d-m-Y H:i:s") . "\n");
                $printer->text("** Gracias por su preferencia **" . "\n");
                $printer->feed();
                $printer->cut();
                $printer->pulse();
                //Create a ClientPrintJob obj that will be processed at the client side by the WCPP
                $cpj = new ClientPrintJob();
                //set ESCPOS commands to print...
                $cpj->printerCommands = $connector->getData();
                $printer->close();
                $cpj->formatHexValues = true;

                if ($useDefaultPrinter || $printerName === 'null') {
                    $cpj->clientPrinter = new DefaultPrinter();
                } else {
                    $cpj->clientPrinter = new InstalledPrinter($printerName);
                }

                //Send ClientPrintJob back to the client
                ob_start();
                ob_clean();
                header('Content-type: application/octet-stream');
                echo $cpj->sendToClient();
                ob_end_flush();
                exit();
            }
        }
    }
}
