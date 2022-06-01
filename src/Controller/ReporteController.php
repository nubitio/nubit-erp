<?php

namespace App\Controller;

use App\Model\ReporteModel;
use Template;

class ReporteController extends BaseController
{

    private ReporteModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ReporteModel();
    }

    public function index()
    {
        $this->render('reporte/index.html.twig');
    }

    public function get()
    {
        $data = $this->model->all();
        $this->json($data);
    }

    public function lista()
    {
        $filtered = (bool)$this->app->get('PARAMS.filtered');
        $extended = (bool)$this->app->get('PARAMS.extended');
        $data = $this->model->lista($filtered, $extended);
        $this->json($data);
    }

    public function listafull()
    {
        $data = $this->model->listafull();
        $this->json($data);
    }

    public function reporte_articulo()
    {
        $fi = $this->app->get('PARAMS.fi');
        $ff = $this->app->get('PARAMS.ff');
        $data = $this->model->articulos($fi, $ff);
        $extras = [
            'title' => 'REPORTE DE VENTAS POR ARTICULO',
            'subtitle' => 'PERIODO ' . $fi . ' AL ' . $ff,
            'sumcols' => [2, 3, 5]
        ];
        $this->export($data, $extras);
    }

    public function reporte_detallado()
    {
        $fi = $this->app->get('PARAMS.fi');
        $ff = $this->app->get('PARAMS.ff');
        $data = $this->model->articulos_detalle($fi, $ff);
        $extras = [
            'title' => 'REPORTE DE VENTAS DETALLADO',
            'subtitle' => 'PERIODO ' . $fi . ' AL ' . $ff,
            'sumcols' => []
        ];
        $this->export($data, $extras);
    }

    public function reporte_dia()
    {
        $fi = $this->app->get('PARAMS.fi');
        $data = $this->model->articulos_dia($fi);
        $extras = [
            'title' => 'REPORTE DE POR DIA',
            'subtitle' => 'DIA ' . $fi
        ];
        $this->export($data, $extras);
    }

    public function reporte_contable()
    {
        $fi = $this->app->get('PARAMS.fi');
        $ff = $this->app->get('PARAMS.ff');
        $data = $this->model->contable($fi, $ff);
        $extras = [
            'title' => 'REPORTE DE VENTAS CONTABLE',
            'subtitle' => 'PERIODO ' . $fi . ' AL ' . $ff,
            'sumcols' => [6, 7, 8]
        ];
        $this->export($data, $extras);
    }

    public function reporte_aeropuerto()
    {
        $fecha = $this->app->get('PARAMS.fecha');
        $data = $this->model->aeropuerto($fecha);
        $extras = [
            'title' => 'FORMATO',
            'subtitle' => 'PLANILLA DE VENTAS GCO-FO-004',
            'sumcols' => [4, 6]
        ];
        $this->export($data, $extras);
    }

    public function reporte_anulados()
    {
        $fi = $this->app->get('PARAMS.fi');
        $ff = $this->app->get('PARAMS.ff');
        $data = $this->model->anulados($fi, $ff);
        $extras = [
            'title' => 'VENTAS ANULADAS',
            'subtitle' => 'PERIODO ' . $fi . ' AL ' . $ff,
            'sumcols' => []
        ];
        $this->export($data, $extras);
    }

    public function reporte_notasCredito()
    {
        $fi = $this->app->get('PARAMS.fi');
        $ff = $this->app->get('PARAMS.ff');
        $data = $this->model->notasCredito($fi, $ff);
        $extras = [
            'title' => 'NOTAS DE CREDITO',
            'subtitle' => 'PERIODO ' . $fi . ' AL ' . $ff,
            'sumcols' => []
        ];
        $this->export($data, $extras);
    }

    public function asistencia_diaria()
    {
        $fecha = $this->app->get('PARAMS.fecha');
        $data = $this->model->asistencia_diaria($fecha);
        if (count($data) > 0) {
            $this->app->set('empleado', '');
            $result = [];
            for ($i = 0; $i < count($data); $i++) {
                $times = explode('_', $data[$i]['times']);
                $ingreso = min($times);
                $salida = (count($times) >= 2) ? max($times) : '';
                $obs = (count($times) >= 2) ? 'ASISTE' : 'INCOMPLETO';
                $result[$i] = [
                    'dni' => $data[$i]['dni'],
                    'empleado' => $data[$i]['empleado'],
                    'ingreso' => $ingreso,
                    'salida' => $salida,
                    'estado' => $obs];
            }
            $this->app->set('data', $result);
            $extra = "Del " . $fecha;
            $this->app->set('extra', $extra);
            $template = new Template;
            echo $template->render('templates/asistencia_diaria.html');
        } else {
            echo "No se encontraron registros";
        }
    }

    public function asistencia_mensual()
    {
        $empleado = $this->app->get('PARAMS.empleado');
        $desde = $this->app->get('PARAMS.desde');
        $hasta = $this->app->get('PARAMS.hasta');
        $data = $this->model->asistencia_mensual($empleado, $desde, $hasta);
        if (count($data) > 0) {
            $this->app->set('empleado', $data[0]['empleado']);
            $result = [];
            for ($i = 0; $i < count($data); $i++) {
                $times = explode('_', $data[$i]['times']);
                $ingreso = min($times);
                $salida = (count($times) >= 2) ? max($times) : '';
                $obs = (count($times) >= 2) ? 'ASISTE' : 'INCOMPLETO';
                $result[$i] = [
                    'fecha' => $data[$i]['fecha'],
                    'dni' => $data[$i]['dni'],
                    'empleado' => $data[$i]['empleado'],
                    'ingreso' => $ingreso,
                    'salida' => $salida,
                    'estado' => $obs
                ];
            }
            $this->app->set('data', $result);
            if ($desde == $hasta) {
                $extra = "Del " . $desde;
            } else {
                $extra = "Desde {$desde} Hasta {$hasta}";
            }
            $this->app->set('extra', $extra);
            $template = new Template;
            echo $template->render('templates/asistencia_mensual.html');
        } else {
            echo "No se encontraron registros";
        }
    }

    public function asistencia_resumen()
    {
        $empleado = $this->app->get('PARAMS.empleado');
        $desde = $this->app->get('PARAMS.desde');
        $hasta = $this->app->get('PARAMS.hasta');
        $data = $this->model->asistencia_resumen($empleado, $desde, $hasta);
        if (count($data) > 0) {
            $this->app->set('empleado', $data[0]['empleado']);
            $this->app->set('data', $data);
            if ($desde == $hasta) {
                $extra = "Del " . $desde;
            } else {
                $extra = "Desde {$desde} Hasta {$hasta}";
            }
            $this->app->set('extra', $extra);
            $template = new Template;
            echo $template->render('templates/asistencia_resumen.html');
        } else {
            echo "No se encontraron registros";
        }
    }
}
