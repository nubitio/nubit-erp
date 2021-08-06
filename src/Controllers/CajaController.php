<?php

namespace App\Controllers;

class CajaController extends BaseController
{

    private $caja;

    function __construct()
    {
        parent::__construct();
        $this->caja = new CajaModel();
    }

    function render()
    {
        $template = new Template;
        echo $template->render('caja/index.html');
    }

    function consulta()
    {
        $template = new Template;
        echo $template->render('caja-consulta/index.html');
    }

    function check()
    {
        $data = $this->caja->check();
        if ($data) {
            $this->json($data);
        } else {
            echo "false";
        }
    }

    function get()
    {
        $data = $this->caja->all();
        $this->json($data);
    }

    function detalle()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->caja->detalle($id);
        $this->json($data);
    }

    function add()
    {
        $data = $this->caja->add();
        $this->json($data);
    }

    function flujo()
    {
        $data = $this->caja->flujo();
        $this->json($data);
    }

    function edit()
    {
        $uid = $this->app->get('PARAMS.uid');
        $caja_id = $this->app->get('PARAMS.caja_id');
        $data = $this->caja->edit($uid, $caja_id);
        $this->json($data);
    }

    function delete()
    {
        $uid = $this->app->get('PARAMS.uid');
        $caja_id = $this->app->get('PARAMS.caja_id');
        $data = $this->caja->delete($uid, $caja_id);
        $this->json($data);
    }

    function cerrar()
    {
        $data = $this->caja->cerrar();
        $this->json($data);
    }

    public function reporte()
    {
        $id = $this->app->get('PARAMS.id');
        $cabecera = $this->caja->cabecera($id);
        $detalle = $this->caja->detalle_reporte($id);

        $usuario = $cabecera['usuario'];
        $apertura = $cabecera['apertura'];
        $cierre = $cabecera['cierre'];
        $contado = $cabecera['contado'];
        $credito = $cabecera['credito'];
        $total = $cabecera['total'];

        $info = $this->caja->get_caja_info($apertura, empty($cierre) ? date('Y-m-d H:i:s') : $cierre);

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('JGM - POS');
        $pdf->SetTitle('Reporte Caja');
        $pdf->SetKeywords('reporte, caja, pdf');
        $pdf->SetSubject('Modulo caja');

        // set default header data
        //$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE.' 006', PDF_HEADER_STRING);
        //$pdf->SetHeaderData('../public/img/logo.png', 12, 'Reporte Caja '.$caja, 'Usuario: ' . $usuario);

        // set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP - 10, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // set font
        $pdf->SetFont('helvetica', '', 10);

        // add a page
        $pdf->AddPage();

        $dta = new DateTime($apertura);
        $adate = $dta->format('d/m/Y');
        $atime = $dta->format('H:i:s a');

        $dtc = new DateTime($cierre);
        $cdate = $dtc->format('d/m/Y');
        $ctime = $dtc->format('H:i:s a');

        $tabla1 = '<table style="text-align:center;">
          <tr>
            <td style="width:25%"></td>
            <td style="width:50%">
              <table border="1" cellpadding="2">
                <tr><td></td><td>Fecha</td><td>Hora</td></tr>
                <tr><td>Apertura</td><td>' . $adate . '</td><td>' . $atime . '</td></tr>
                <tr><td>Cierre</td><td>' . $cdate . '</td><td>' . $ctime . '</td></tr>
              </table>
            </td>
            <td style="width:25%"></td>
          </tr>
        </table>';

        // create some HTML content

        $tabla2 = '
        <table border="1" cellpadding="2">
          <tr>
            <th colspan="7" style="text-align:center">VENTAS POR TIPO DE DOCUMENTO</th>
          </tr>
          <tr>
            <td rowspan="2">Documento</td>
            <td rowspan="2">Serie</td>
            <td rowspan="2">Del</td>
            <td rowspan="2">Al</td>
            <td colspan="3" style="text-align:center">Moneda Nacional</td>
          </tr>
          <tr>
            <td>Contado</td>
            <td>Credito</td>
            <td><b>Total</b></td>
          </tr>';
        $total_efectivo = 0;
        $total_credito = 0;
        for ($i = 0; $i < count($info); $i++) {
            $tabla2 .= '<tr>
            <td>' . $info[$i]['tipo'] . '</td>
            <td>' . $info[$i]['serie'] . '</td>
            <td>' . $info[$i]['inicio'] . '</td>
            <td>' . $info[$i]['fin'] . '</td>
            <td style="text-align:right">' . number_format($info[$i]['total_efectivo'], 2) . '</td>
            <td style="text-align:right">' . number_format($info[$i]['total_credito'], 2) . '</td>
            <td style="text-align:right">' . number_format($info[$i]['total_efectivo'] + $info[$i]['total_credito'], 2) . '</td>
          </tr>';
            $total_efectivo += $info[$i]['total_efectivo'];
            $total_credito += $info[$i]['total_credito'];
        }

        $tabla2 .= '<tr>
            <td colspan="4"><b>Total</b></td>
            <td style="text-align:right">' . number_format($total_efectivo, 2) . '</td>
            <td style="text-align:right">' . number_format($total_credito, 2) . '</td>
            <td style="text-align:right">' . number_format($total_efectivo + $total_credito, 2) . '</td>
          </tr>';

        $tabla2 .= '</table>';

        $tabla3_header = '
        <table border="1" cellpadding="2">
          <tr>
            <th colspan="5" style="text-align:center">MOVIMIENTO DE CAJA</th>
          </tr>
          <tr>
            <td rowspan="2" style="width:35%">Concepto</td>
            <td colspan="3" style="text-align:center;width:40%">Moneda Nacional</td>
            <td rowspan="2" style="width:25%">Referencia</td>
          </tr>
          <tr>
            <td>Ingresos</td>
            <td>Egresos</td>
            <td>Saldo</td>
          </tr>';
        $tabla3_body = '';
        $saldo = 0;
        $tingreso = 0;
        $tegreso = 0;
        for ($i = 0; $i < count($detalle); $i++) {
            if ($detalle[$i]['descripcion'] == 'FONDO DE CAJA') {
                $fondo = $detalle[$i]['monto'];
            }
            if ($detalle[$i]['tipo_id'] == 1 && $detalle[$i]['descripcion'] != 'VENTA TARJETA') {
                $ingreso = $detalle[$i]['monto'];
                $egreso = '0.00';
                $saldo = $saldo + $detalle[$i]['monto'];
                $tingreso = $tingreso + $ingreso;
            } elseif ($detalle[$i]['tipo_id'] == 2) {
                $ingreso = '0.00';
                $egreso = $detalle[$i]['monto'];
                $saldo = $saldo - $detalle[$i]['monto'];
                $tegreso = $tegreso + $egreso;
            }
            if ($detalle[$i]['descripcion'] != 'VENTA TARJETA') {
                $tabla3_body .= '
        <tr>
          <td>' . $detalle[$i]['descripcion'] . '</td>
          <td style="text-align:right">' . $ingreso . '</td>
          <td style="text-align:right">' . $egreso . '</td>
          <td style="text-align:right">' . number_format($saldo, 2) . '</td>
          <td>' . $detalle[$i]['referencia'] . '</td>
        </tr>';
            }
        }
        $saldo = $saldo - $fondo;
        $tegreso = $tegreso + $fondo;
        $tabla3_body .= '
        <tr>
            <td>ARQUEO DE CAJA</td>
            <td style="text-align:right">0.00</td>
            <td style="text-align:right">' . $fondo . '</td>
            <td style="text-align:right">' . number_format($saldo, 2) . '</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td style="text-align:right">' . number_format($tingreso, 2) . '</td>
            <td style="text-align:right">' . number_format($tegreso, 2) . '</td>
            <td style="text-align:right"></td>
            <td></td>
        </tr>';
        $tabla3_footer = '</table>';

        $tabla3 = $tabla3_header . $tabla3_body . $tabla3_footer;

        $tabla4 = '<table style="text-align:center;">
          <tr>
            <td style="width:30%"></td>
            <td style="width:40%">
              <table border="1" cellpadding="2">
                <tr><td colspan="2"><b>RESUMEN</b></td></tr>
                <tr><td><b>Efectivo</b></td><td><b>' . number_format($saldo, 2) . '</b></td></tr>
              </table>
            </td>
            <td style="width:30%"></td>
          </tr>
        </table>';

        // output the HTML content
        $pdf->writeHTML($tabla1, true, false, true, false, '');
        $pdf->writeHTML($tabla2, true, false, true, false, '');
        $pdf->writeHTML($tabla3, true, false, true, false, '');
        $pdf->writeHTML($tabla4, true, false, true, false, '');

        // reset pointer to the last page
        $pdf->lastPage();

        //Close and output PDF document
        $pdf->Output('reporte_caja.pdf', 'I');
    }
}
