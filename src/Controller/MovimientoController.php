<?php

namespace App\Controller;

include_once(dirname(__FILE__, 3) . '/public/WebClientPrint.php');

use App\Model\BotModel;
use App\Model\MovimientoModel;
use Neodynamic\SDK\Web\WebClientPrint;
use NumberFormatter;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Template;
use App\Model\OptionsModel;

class MovimientoController extends BaseController
{

    private MovimientoModel $model;
    private BotModel $botModel;
    private EFactController $efact;

    function __construct()
    {
        parent::__construct();
        $this->model = new MovimientoModel();
        $this->botModel = new BotModel();
        $this->efact = new EFactController();
        $this->optionsDao = new OptionsModel();
    }

    function index()
    {
        $this->render('movimientos/index.html.twig');
    }

    function ingreso()
    {
        $this->render('ingreso/index.html.twig');
    }

    function salida()
    {
        $this->render('salida/index.html.twig');
    }

    function salidaanular()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->salidaanular($id);
        $this->json($data);
    }

    function salidas()
    {
        $template = new Template;
        echo $template->render('salidas/index.html');
    }

    function venta()
    {
        $printDetectionScript = WebClientPrint::createWcppDetectionScript($this->action('WebClientPrintController'), session_id());
        $printScrip = WebClientPrint::createScript($this->action('WebClientPrintController'), $this->action('PrintESCPOSController'), session_id());
        $sessionId = session_id();
        $this->app->set('wcpscript1', $printDetectionScript);
        $this->app->set('wcpscript2', $printScrip);
        $this->app->set('session_id', $sessionId);
        $this->render('venta/index.html.twig', [
            'session_id' => $sessionId,
            'printDetectionScript' => $printDetectionScript,
            'printScript' => $printScrip
        ]);
    }

    function get()
    {
        $tipo = $this->app->get('PARAMS.tipo');
        $data = $this->model->all($tipo);
        $this->json($data);
    }

    function getsalidas()
    {
        $tipo = $this->app->get('PARAMS.tipo');
        $data = $this->model->allsalidas($tipo);
        $this->json($data);
    }

    function getall()
    {
        $data = $this->model->getall();
        $this->json($data);
    }

    function getventa()
    {
        $data = $this->model->ventas();
        $this->json($data);
    }

    function getcomprobante()
    {
        $data = $this->model->comprobantes();
        $this->json($data);
    }

    function getordenes()
    {
        $data = $this->model->getordenes();
        $this->json($data);
    }

    function getorden()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->getorden($id);
        $this->json($data);
    }

    function cerrar_orden()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->cerrar_orden($id);
        $this->json($data);
    }

    function get_list()
    {
        $this->json($this->model->get_list());
    }

    function getprecio()
    {
        $id = $this->app->get('PARAMS.producto_id');
        $data = $this->model->getprecio($id);
        echo $data[0]['precio'];
    }

    function mdetalle()
    {
        $id = $this->app->get('PARAMS.producto_id');
        $data = $this->model->mdetalle($id);
        $this->json($data);
    }

    function detalle()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->detalle($id);
        $this->json($data);
    }

    function stock()
    {
        $producto = $this->app->get('PARAMS.producto_id');
        $almacen = $this->app->get('PARAMS.almacen_id');
        $data = $this->model->saldo($producto, $almacen);
        if ($data) {
            $saldo = $data['stock'];
        } else {
            $saldo = 0;
        }
        echo $saldo;
    }

    function add()
    {
        $tipo = $this->app->get('PARAMS.tipo');
        $data = $this->model->add($tipo);
        $this->json($data);
    }

    function edit()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->edit($id);
        $this->json($data);
    }

    function delete()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->delete($id);
        $this->json($data);
    }

    function anular()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->anular($id);
        $this->json($data);
    }

    public function reporte_kardex()
    {
        $almacen = $this->app->get('PARAMS.almacen_id');
        $producto = $this->app->get('PARAMS.producto_id');
        $fi = $this->app->get('PARAMS.fi');
        $ff = $this->app->get('PARAMS.ff');
        $info = $this->model->get_saldo($almacen, $producto, $fi);
        $saldo = $info[0]['saldo'];
        $data = $this->model->articulos_kardex($almacen, $producto, $fi, $ff, $saldo);
        $result = [];
        foreach ($data as $row) {
            $row['existencias'] = $saldo + $row['ingresos'] - $row['salidas'];
            $saldo = $row['existencias'];
            array_push($result, $row);
        }
        $extras = [
            'title' => 'REPORTE DE KARDEX',
            'subtitle' => 'PRODUCTO: ' . $info[0]['producto'] . ', DEL ' . $fi . ' Al ' . $ff,
            'sumcols' => []
        ];
        $this->export($result, $extras, $info[0]['saldo']);
    }

    public function reporte()
    {
        $almacen = $this->app->get('PARAMS.almacen_id');
        $categoria = $this->app->get('PARAMS.categoria_id');
        $fi = $this->app->get('PARAMS.fi');
        $ff = $this->app->get('PARAMS.ff');
        $data = $this->model->reporte_kardex($almacen, $categoria, $fi, $ff);
        if (count($data) > 0) {
            $extras = [
                'title' => 'REPORTE DE KARDEX',
                'subtitle' => 'DEL ' . $fi . ' Al ' . $ff,
                'sumcols' => []
            ];
            $this->export($data, $extras);
        } else {
            echo "No se encontraron registros";
        }
    }

    function preview()
    {
        $id = $this->app->get('PARAMS.id');
        //get empresa info
        $cabecera = $this->model->ticket_cabecera($id);
        $detalle = $this->model->ticket_detalle($id);
        //Sample plain receipt
        $newLine = '<br>';

        $cmds = str_pad($cabecera['empresa'], 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad('RUC ' . $cabecera['ruc_empresa'], 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad($cabecera['direccion_empresa'], 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad($cabecera['direccion_local'], 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad('Telefono ' . $cabecera['telefono'], 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        if (strlen($cabecera['ruc_cliente'])>=9) {
            $tipo = 'FACTURA ELECTRONICA';
        } else {
            $tipo = 'BOLETA ELECTRONICA';
        }
        $ticket = $cabecera['serie'] . '-' . $cabecera['numero'];
        $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad($tipo, 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        //$cmds .= str_pad($ticket, 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
        $cmds .= $newLine;
        $fecha = date("d-m-Y", strtotime($cabecera['fecha']));
        $cmds .= str_pad('Fecha emision: ' . $fecha, 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $moneda = 'Moneda: Soles';
        $cmds .= str_pad($moneda, 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $total = 0.00;
        if ($cabecera['operacion_id'] == '37b1e294-c224-11e7-91af-5600005a2745') {
            $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
            $cmds .= $newLine;
            $cmds .= 'RUC Cli.: ' . $cabecera['ruc_cliente'];
            $cmds .= $newLine;
            $cmds .= 'R.Social: ' . $cabecera['cliente'];
            $cmds .= $newLine;
            $cmds .= 'Direccion: ' . $cabecera['direccion_cliente'];
            $cmds .= $newLine;
        } else {
            $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
            $cmds .= $newLine;
            $cmds .= 'DNI: ' . $cabecera['ruc_cliente'];
            $cmds .= $newLine;
            $cmds .= 'Cliente: ' . $cabecera['cliente'];
            $cmds .= $newLine;
            $cmds .= 'Direccion: ' . $cabecera['direccion_cliente'];
            $cmds .= $newLine;
        }
        $cmds .= 'Forma Pago: Contado';
        $cmds .= $newLine;
        $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad('#', 3) . str_pad('Descripcion', 24) . str_pad('Precio', 6, " ", STR_PAD_LEFT);
        $cmds .= $newLine;
        $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
        $cmds .= $newLine;
        for ($i = 0; $i < count($detalle); $i++) {
            $cantidad = floor($detalle[$i]['cantidad']);
            $producto = strlen($detalle[$i]['producto']) > 22 ? substr($detalle[$i]['producto'], 0, 22) : $detalle[$i]['producto'];
            $cmds .= str_pad($cantidad, 2, '0', STR_PAD_LEFT) . ' ' . str_pad($producto, 24) . str_pad($detalle[$i]['precio'], 6, " ", STR_PAD_LEFT);
            $cmds .= $newLine;
            $total += $detalle[$i]['precio'];
        }
        if ($cabecera['descuento'] > 0) {
            $descuento = $total * $cabecera['descuento'] / 100;
            $descuento = number_format($descuento, 2, '.', '');
            $cmds .= str_pad('1', 2, '0', STR_PAD_LEFT) . ' ' . str_pad('DESCUENTO', 24) . str_pad('-' . $descuento, 6, " ", STR_PAD_LEFT);
            $cmds .= $newLine;
            $total = $total - $descuento;
        }
        //if ($cabecera['operacion_id'] == '37b1e294-c224-11e7-91af-5600005a2745') {
        if (1 == 1) {
            $igv = 0.18;
            $app_option_rc_get = (int)$this->getOption('app_option_rc');
            $rc = $app_option_rc_get/100;
            $divisor = 1 + $igv + $rc;
            $valor_venta = $total / $divisor;
            $valor_igv = $valor_venta*$igv;
            $valor_rc = $valor_venta*$rc;

            $str_opg = $this->money_pen($valor_venta);
            $str_igv = $this->money_pen($valor_igv);
            $str_rc = $this->money_pen($valor_rc);
            $str_total = $this->money_pen($total);
            $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
            $cmds .= $newLine;
            $cmds .= str_pad('IMPORTE', 23) . str_pad($str_total, 10, " ", STR_PAD_LEFT);
            $cmds .= $newLine;
            $cmds .= str_pad('OP. GRAVADA', 23) . str_pad($str_opg, 10, " ", STR_PAD_LEFT);
            $cmds .= $newLine;
            $cmds .= str_pad('OP. INAFECTA', 23) . str_pad('S/ 0.00', 10, " ", STR_PAD_LEFT);
            $cmds .= $newLine;
            $cmds .= str_pad('OP. EXONERADA', 23) . str_pad('S/ 0.00', 10, " ", STR_PAD_LEFT);
            $cmds .= $newLine;
            $cmds .= str_pad('IGV 18%', 23) . str_pad($str_igv, 10, " ", STR_PAD_LEFT);
            $cmds .= $newLine;

            if($app_option_rc_get>=1)
            {
                $cmds .= str_pad('RC '.$app_option_rc_get.'%: ', 23) . str_pad($str_rc, 10, " ", STR_PAD_LEFT);
                $cmds .= $newLine;
            }
            $cmds .= str_pad('TOTAL', 23) . str_pad($str_total, 10, " ", STR_PAD_LEFT);
            $cmds .= $newLine;
            $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
            $cmds .= $newLine;
            $formatter = new NumberFormatter("es", NumberFormatter::SPELLOUT);
            $formatted_total = str_replace(',', '', number_format($total, 2));
            $parts = explode('.', $formatted_total);
            $monto = $formatter->format($parts[0]);
            $str_monto = mb_strtoupper($monto . " Y " . $parts[1] . "/100");
            $cmds .= str_pad($str_monto, 33, " ", STR_PAD_BOTH);
            $cmds .= $newLine;
            $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
            $cmds .= $newLine;
        } else {
            $total = $this->money_pen($total);
            $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
            $cmds .= $newLine;
            $cmds .= str_pad('TOTAL', 23) . str_pad($total, 10, " ", STR_PAD_LEFT);
            $cmds .= $newLine;
            $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
            $cmds .= $newLine;
        }
        $cmds .= $newLine;
        $cmds .= str_pad("Representacion impresa del", 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad("comprobante electronico, consulte en", 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad($this->app->get('EFACT_URL'), 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= $newLine;
        $cmds .= str_pad(date("d-m-Y H:i:s"), 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad("** Gracias por su preferencia **", 33, " ", STR_PAD_BOTH);

        echo '<div style="padding: 15px"><pre>' . $cmds . '</pre></div>';
    }

    public function guardar_comprobante()
    {
        $this->json($this->model->guardar_comprobante());
    }

    public function get_one_venta()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->get_one_venta($id);
        $this->json($data);
    }

    public function update_venta()
    {
        $this->json($this->model->update_venta());
    }

    public function send_email()
    {
        $this->json(["success" => false, "msg" => 'Error del servidor de correos', "data" => null]);
        exit();
        $id = $this->app->get('PARAMS.id');

        $data = $this->model->ticket_cabecera($id);
        if ($data['operacion_id'] == '37b1e294-c224-11e7-91af-5600005a2745') {
            $tipo_id = '01';
            $tipo = 'FACTURA';
        } else {
            $tipo_id = '03';
            $tipo = 'BOLETA';
        }

        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = 'mail.jgm90.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'facturacion@compuexpress.pe';
            $mail->Password = 'Admin.3000';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            //Recipients
            $mail->setFrom('facturacion@compuexpress.pe', 'Facturacion');
            if ($this->app->get('EFACT_MODE') == 'PROD') {
                if (!empty($data['email_cliente'])) {
                    $mail->addAddress($data['email_cliente']);
                } else {
                    $mail->addAddress('contabilidadjpaqp@gmail.com');
                }
                $mail->addCC('contabilidadjpaqp@gmail.com');
                $mail->addBCC('adm.jpaqp@gmail.com');
            } else {
                $mail->addAddress('johangm90@gmail.com');
            }

            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = '13 Monjas: Tu comprobante de pago electrónico - ' . $data['serie'] . '-' . $data['numero'];
            // Attachments
            $xml = $this->app->get('EFACT_DIR') . $data['ruc_empresa'] . '-' . $tipo_id . '-' . $data['serie'] . '-' . $data['numero'] . '.xml';
            $pdf = $this->app->get('EFACT_DIR') . $data['ruc_empresa'] . '-' . $tipo_id . '-' . $data['serie'] . '-' . $data['numero'] . '.pdf';
            $mail->addAttachment($xml);
            $mail->addAttachment($pdf);

            $template = new Template();
            $this->app->set('logo', $this->app->get('BASE_URL') . 'src/assets/images/logo.png');
            $this->app->set('tipo', $tipo);
            $this->app->set('serie', $data['serie']);
            $this->app->set('numero', $data['numero']);
            $fecha = date("d/m/Y", strtotime($data['fecha']));
            $this->app->set('fecha_emision', $fecha);
            $this->app->set('monto_total', $this->money_pen($data['total']));
            $url = $this->app->get('BASE_URL') . 'invoice/' . $id;
            $this->app->set('invoice_url', $url);
            $message = $template->render('templates/efact/efact.html');
            $mail->Body = $message;
            $mail->AltBody = '13 Monjas: Tu comprobante de pago electrónico - ' . $data['serie'] . '-' . $data['numero'];

            $mail->send();
            $this->json(["success" => true, "msg" => "Mensaje enviado", "data" => null]);
        } catch (Exception $e) {
            $this->json(["success" => false, "msg" => $mail->ErrorInfo, "data" => null]);
        }
    }

    function orderNote()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->get_one_venta($id);
        $sale = $data['venta'];
        $logo_path = $this->app->get('ASSETS') . 'images/logo.png';
        $total = $sale['cabecera']['total'];
        $discount = $sale['cabecera']['descuento'];
        $discountAmount = 0.00;
        if ($sale['cabecera']['descuento'] > 0) {
            $discountAmount = $total * $discount / 100;
            $discountAmount = number_format($discountAmount, 2, '.', '');
            $this->app->set('discount', $this->money_pen($discountAmount));
            $total = $total - $discountAmount;
        }
        $this->app->set('total', $this->money_pen($total));
        $this->app->set('obs', $sale['cabecera']['glosa']);
        $content = $this->renderView('reportes/invoice.html.twig', [
            'title'  => 'Nota de pedido',
            'logo'   => $this->base64($logo_path),
            'ruc' => $sale['cabecera']['ruc_empresa'],
            'serie' => $sale['cabecera']['serie'],
            'numero' => $sale['cabecera']['numero'],
            'cliente' => $sale['cabecera']['cliente'],
            'fecha' => date('d/m/Y', strtotime($sale['cabecera']['fecha'])),
            'direccion' => $sale['cabecera']['direccion_cliente'],
            'detalle' => $sale['detalle'],
            'descuento' => $sale['cabecera']['descuento'],
            'valor_descuento' => $discountAmount,
            'total' =>  $total,
        ]);
        $this->report($content);    }

    function makeInvoice()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->model->makeInvoice($id);
        if ($result['success']) {
            $sale = $this->botModel->getOne($id);
            if ($sale['success']) {
                $info = $this->efact->makeInvoice($sale['data']);
                $this->botModel->updateSaleState($info);
            }
        }
        $this->json($result);
    }

    public function getOption($name)
    {
        try {
            return $this->optionsDao->getByName($name);
        } catch (Exception $e) {
            $this->app->error(500, "No se pudo obtener la opcion {$name}");
        }
    }
}
