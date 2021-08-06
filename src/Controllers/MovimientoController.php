<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

include_once(dirname(dirname(__FILE__)) . '/WebClientPrint.php');

use Neodynamic\SDK\Web\WebClientPrint;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class MovimientoController extends BaseController
{

    private $model;
    private $botModel;
    private $efact;

    function __construct()
    {
        parent::__construct();
        $this->model = new MovimientoModel();
        $this->botModel = new BotModel();
        $this->efact = new EFactController();
    }

    function render()
    {
        $template = new Template;
        echo $template->render('movimientos/index.html');
    }

    function ingreso()
    {
        $template = new Template;
        echo $template->render('ingreso/index.html');
    }

    function salida()
    {
        $template = new Template;
        echo $template->render('salida/index.html');
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
        $wcppScript1 = WebClientPrint::createWcppDetectionScript($this->action('WebClientPrintController'), session_id());
        $wcppScript2 = WebClientPrint::createScript($this->action('WebClientPrintController'), $this->action('PrintESCPOSController'), session_id());
        $session_id = session_id();
        $this->app->set('wcpscript1', $wcppScript1);
        $this->app->set('wcpscript2', $wcppScript2);
        $this->app->set('session_id', $session_id);
        $template = new Template;
        $mode = $this->app->get('MODE');
        if ($mode == 'REST') {
            echo $template->render('venta/index.html');
        } else {
            echo $template->render('venta_pos/index.html');
        }
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

    function fixstock()
    {
        $data = $this->model->fixstock();
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
        if ($cabecera['operacion_id'] == '37b1e294-c224-11e7-91af-5600005a2745') {
            $tipo = 'FACTURA ELECTRONICA';
        } else {
            $tipo = 'BOLETA ELECTRONICA';
        }
        $ticket = $cabecera['serie'] . '-' . $cabecera['numero'];
        $cmds .= str_pad('', 33, "-", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad($tipo, 33, " ", STR_PAD_BOTH);
        $cmds .= $newLine;
        $cmds .= str_pad($ticket, 33, " ", STR_PAD_BOTH);
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
            $divisor = 1 + $igv;
            $valor_venta = $total / $divisor;
            $valor_igv = $valor_venta * $igv;
            $str_opg = $this->money_pen($valor_venta);
            $str_igv = $this->money_pen($valor_igv);
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
        return $this->json($this->model->guardar_comprobante());
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
        $this->app->set('title', 'Nota de pedido');
        $logo_path = $this->app->get('ASSETS') . 'images/logo.png';
        $this->app->set('logo', $this->base64($logo_path));
        $this->app->set('ruc', $sale['cabecera']['ruc_empresa']);
        $this->app->set('series', $sale['cabecera']['serie']);
        $this->app->set('number', $sale['cabecera']['numero']);
        $this->app->set('customer', $sale['cabecera']['cliente']);
        $this->app->set('date', date('d/m/Y', strtotime($sale['cabecera']['fecha'])));
        $this->app->set('address', $sale['cabecera']['direccion_cliente']);
        $this->app->set('detail', $sale['detalle']);
        $this->app->set('total', $this->money_pen($sale['cabecera']['total']));
        $this->app->set('obs', $sale['cabecera']['glosa']);
        $template = new Template;
        //echo $template->render('templates/invoice.html');
        $this->report($template->render('templates/invoice.html'));
    }

    function makeInvoice()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->model->makeInvoice($id);
        $sale = $this->botModel->getOne($id);
        if ($result['success']) {
            if ($sale['success']) {
                $info = $this->efact->makeInvoice($sale['data']);
                $this->botModel->updateSaleState($info);
            }
        }
        $this->json($result);
    }
}
