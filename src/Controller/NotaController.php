<?php

namespace App\Controller;

use App\Domain\Common\ApiResponse;
use App\Domain\Note\Note;
use App\Domain\Note\NoteDetail;
use App\Model\BotModel;
use App\Model\CreditNoteModel;
use App\Model\NotaModel;

class NotaController extends BaseController
{
    private NotaModel $model;
    private CreditNoteModel $noteModel;
    private BotModel $botModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = new NotaModel();
        $this->noteModel = new CreditNoteModel();
        $this->botModel = new BotModel();
    }

    public function index()
    {
        $this->render('nota/index.html.twig');
    }

    public function get()
    {
        $this->json($this->model->get());
    }

    public function insert()
    {
        try {
            $saleResult = $this->botModel->getOne($_POST['movimiento_id']);
            if ($saleResult['success']) {
                $sale = $saleResult['data'];
                $serie = $this->noteModel->getSeries($sale['header']['doc_series']);
                $uuid = $this->botModel->getUUID();
                $note = (new Note())
                    ->setNotaId($uuid)
                    ->setMovimientoId($sale['header']['movimiento_id'])
                    ->setFecha($_POST['fecha'])
                    ->setTipoId('07')
                    ->setCodigoMotivo($_POST['codigo_motivo'])
                    ->setDescripcionMotivo($_POST['descripcion_motivo'])
                    ->setSerie($serie)
                    ->setCorrelativo($this->noteModel->getNextNumber($serie))
                    ->setCreatedBy(1)
                    ->setCreatedAt(date('Y-m-d H:i:s'));
                $details = [];
                foreach ($sale['detail'] as $row) {
                    $detail = (new NoteDetail())
                        ->setNotaId($uuid)
                        ->setProductoId($row['product_id'])
                        ->setCantidad($row['quantity'])
                        ->setPrecioUnitario($row['price'])
                        ->setPrecioTotal($row['total']);
                    array_push($details, $detail);
                }
                $note->setDetails($details);
                $response = $this->noteModel->add($note);
            } else {
                $response = (new ApiResponse())->setSuccess(false)->setMessage($saleResult['msg']);
            }
        } catch (\Exception $e) {
            $response = (new ApiResponse())->setSuccess(false)->setMessage($e->getMessage());
        }
        $this->json($response);
    }

    public function update()
    {
        $id = $this->app->get('PARAMS.id');
        $this->json($this->model->update($id));
    }

    public function delete()
    {
        $id = $this->app->get('PARAMS.id');
        $this->json($this->model->delete($id));
    }
}
