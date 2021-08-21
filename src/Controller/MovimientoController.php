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
        echo 'Preview not working';
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
        $this->report($template->render('reportes/invoice.html'));
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
