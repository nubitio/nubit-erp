<?php

namespace App\Controllers;

use App\Models\BotModel;
use Template;

class InvoiceController extends BaseController
{
    private BotModel $botModel;

    public function __construct()
    {
        parent::__construct();
        $this->botModel = new BotModel();
    }

    function beforeroute()
    {
        // avoid redirect to login
    }

    public function render()
    {
        $id = $this->app->get('PARAMS.id');

        $data = $this->botModel->getOne($id);
        $sale = $data['data'];
        if ($sale['header']['doc_type'] == '01') {
            $tipo = 'FACTURA';
        } else {
            $tipo = 'BOLETA';
        }

        $pdf = $this->app->get('BASE_URL') . 'invoice/download/' . $id . '/pdf';
        $xml = $this->app->get('BASE_URL') . 'invoice/download/' . $id . '/xml';
        $cdr = $this->app->get('BASE_URL') . 'invoice/download/' . $id . '/cdr';

        $template = new Template();
        $this->app->set('tipo', $tipo);
        $this->app->set('serie', $sale['header']['doc_series']);
        $this->app->set('numero', $sale['header']['doc_number']);
        $fecha = date("d/m/Y", strtotime($sale['header']['doc_date']));
        $this->app->set('fecha_emision', $fecha);
        $this->app->set('monto_total', $this->money_pen($sale['header']['doc_sale_amount'] - $sale['header']['doc_discount_amount']));
        $this->app->set('pdf', $pdf);
        $this->app->set('xml', $xml);
        $this->app->set('cdr', $cdr);
        echo $template->render('invoice/index.html');
    }

    public function download()
    {
        $id = $this->app->get('PARAMS.id');
        $type = $this->app->get('PARAMS.type');
        $mode = $this->app->get('GET.mode');

        $sale = $this->botModel->getOne($id);
        $header = $sale['data']['header'];

        $mimetype = '';

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