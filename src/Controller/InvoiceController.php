<?php

namespace App\Controller;

use App\Model\BotModel;
use App\Model\NotaModel;
use App\Model\ComprobanteModel;
use Template;

class InvoiceController extends BaseController
{
    private BotModel $botModel;
    private NotaModel $noteModel;
    private ComprobanteModel $comprobanteModel;

    public function __construct()
    {
        parent::__construct();
        $this->botModel = new BotModel();
        $this->noteModel = new NotaModel();
        $this->comprobanteModel = new ComprobanteModel();
    }

    function beforeroute()
    {
        // avoid redirect to login
    }

    function render_invoice_files($id)
    {
        $data = $this->botModel->getOne($id);
        $receipt = $data['data'];

        if (!$receipt) {
            $receipt = $this->noteModel->getOne($id);
        }

        if ($receipt['header']['doc_type'] == '01') {
            $type = 'FACTURA';
        } else if ($receipt['header']['doc_type'] == '03') {
            $type = 'BOLETA';
        } else if ($receipt['header']['doc_type'] == '07') {
            $type = 'NOTA DE CRÉDITO';
        } else if ($receipt['header']['doc_type'] == '08') {
            $type = 'NOTA DE DÉBITO';
        } else {
            $type = '';
        }

        $pdf = $this->app->get('BASE_URL') . 'invoice/download/' . $id . '/pdf';
        $xml = $this->app->get('BASE_URL') . 'invoice/download/' . $id . '/xml';
        $cdr = $this->app->get('BASE_URL') . 'invoice/download/' . $id . '/cdr';

        $template = new Template();
        $this->app->set('tipo', $type);
        $this->app->set('serie', $receipt['header']['doc_series']);
        $this->app->set('numero', $receipt['header']['doc_number']);
        $fecha = date("d/m/Y", strtotime($receipt['header']['doc_date']));
        $this->app->set('fecha_emision', $fecha);
        $this->app->set('monto_total', $this->money_pen($receipt['header']['doc_sale_amount'] - $receipt['header']['doc_discount_amount']));
        $this->app->set('pdf', $pdf);
        $this->app->set('xml', $xml);
        $this->app->set('cdr', $cdr);
        echo $template->render('invoice/bill.html.twig');
    }

    function search()
    {
        $type = $this->app->get('PARAMS.type');
        $serie = $this->app->get('PARAMS.serie');
        $number = $this->app->get('PARAMS.number');
        $total = $this->app->get('PARAMS.total');
        $date = $this->app->get('PARAMS.date');

        $movimiento_id = $this->comprobanteModel->movimiento_id($type, $serie, $number, $total, $date);

        $id = $movimiento_id[0]['movimiento_id'];
        $this->render_invoice_files($id);
    }

    public function index()
    {
        $id = $this->app->get('PARAMS.id');
        $this->render_invoice_files($id);
    }

    public function download()
    {
        $id = $this->app->get('PARAMS.id');
        $type = $this->app->get('PARAMS.type');
        $mode = $this->app->get('GET.mode');

        $data = $this->botModel->getOne($id);
        $receipt = $data['data'];

        if (!$receipt) {
            $receipt = $this->noteModel->getOne($id);
        }

        $header = $receipt['header'];

        if ($type == 'pdf') {
            $filename = $header['company_id'] . '-' . $header['doc_type'] . '-' . $header['doc_series'] . '-' . $header['doc_number'] . '.pdf';
            $mimetype = 'application/pdf';
        } elseif ($type == 'xml') {
            $filename = $header['company_id'] . '-' . $header['doc_type'] . '-' . $header['doc_series'] . '-' . $header['doc_number'] . '.xml';
            $mimetype = 'application/xml';
        } elseif ($type == 'cdr') {
            $filename = 'R-' . $header['company_id'] . '-' . $header['doc_type'] . '-' . $header['doc_series'] . '-' . $header['doc_number'] . '.zip';
            $mimetype = 'application/zip';
        } else {
            $this->app->error(404, 'Not Found');
            exit();
        }

        $file = $this->app->get('EFACT_DIR') . $filename;

        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header("Content-Type: {$mimetype}");
            if ($mode == 'preview') {
                header('Content-Disposition: inline; filename=' . $filename);
            } else {
                header('Content-Disposition: attachment; filename=' . $filename);
            }
            header('Content-Transfer-Encoding: binary');
            header('Connection: Keep-Alive');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            readfile($file);
            exit();
        } else {
            $this->app->error(404, 'Not Found');
        }
    }
}