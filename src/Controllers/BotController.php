<?php

namespace App\Controllers;

class BotController extends BaseController
{
    private $model;
    private $efact;
    private $noteModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = new BotModel();
        $this->efact = new EFactController();
        $this->noteModel = new CreditNoteModel();
    }

    public function beforeroute()
    {
        //avoid redirect to login
    }

    public function sendPendingInvoices()
    {
        $pending = $this->model->getPendingInvoices();
        $this->sendInvoices($pending);
    }

    public function sendWrongInvoices()
    {
        $pending = $this->model->getWrongInvoices();
        $this->sendInvoices($pending);
    }

    public function sendPendingNotes()
    {
        $pending = $this->model->getPendingNotes();
        $fillables = $this->model->getVoided();
        $this->sendNotes($pending, $fillables);
    }

    public function sendWrongNotes()
    {
        $pending = $this->model->getWrongNotes();
        $fillables = $this->model->getVoided();
        $this->sendNotes($pending, $fillables);
    }

    public function sendRejectedNotes()
    {
        $pending = $this->model->getPendingNotes();
        $fillables = $this->model->getRejected();
        $this->sendNotes($pending, $fillables);
    }

    private function sendInvoices($pending)
    {
        $data = [];
        $filled = $this->model->fillPending();
        if ($pending["success"]) {
            for ($i = 0; $i < count($pending["data"]); $i++) {
                $sale = $this->model->getOne($pending["data"][$i]['movimiento_id']);
                if ($sale["success"]) {
                    $result = $this->efact->makeInvoice($sale["data"]);
                    $this->model->updateSaleState($result);
                    $data[$i] = [
                        "id" => $sale["data"]["header"]["movimiento_id"],
                        "comprobante" => $sale["data"]["header"]["doc_series"] . "-" . $sale["data"]["header"]["doc_number"],
                        "result_code" => $result["code"],
                        "result_message" => $result["msg"]
                    ];
                }
            }
        }
        $response = ["filled" => $filled["msg"], "pending" => $pending["msg"], "result" => $data];
        $this->json($response);
    }

    private function sendNotes($pending, $fillables)
    {
        $data = [];
        $filled = $this->fillNotes($fillables);
        if ($pending["success"]) {
            for ($i = 0; $i < count($pending["data"]); $i++) {
                $sale = $this->model->getOne($pending["data"][$i]['movimiento_id']);
                $sale['data']['note'] = $pending['data'][$i];
                if ($sale["success"]) {
                    $result = $this->efact->makeNote($sale['data']);
                    $this->model->updateNoteState($result);
                    array_push($data, [
                        "id" => $sale["data"]["note"]["nota_id"],
                        "comprobante" => $sale["data"]["note"]["serie"] . "-" . $sale["data"]["note"]["correlativo"],
                        "result_code" => $result["code"],
                        "result_message" => $result["msg"]
                    ]);
                }
            }
        }
        $response = ["filled" => $filled, "pending" => $pending["msg"], "result" => $data];
        $this->json($response);
    }

    private function fillNotes($fillables)
    {
        $counter = 0;
        $total = count($fillables);
        foreach ($fillables as $item) {
            try {
                $saleResult = $this->model->getOne($item['movimiento_id']);
                if ($saleResult['success']) {
                    $sale = $saleResult['data'];
                    $serie = $this->noteModel->getSeries($item['serie']);
                    $uuid = $this->model->getUUID();
                    $note = (new Note())
                        ->setNotaId($uuid)
                        ->setMovimientoId($item['movimiento_id'])
                        ->setFecha(date('Y-m-d'))
                        ->setTipoId('07')
                        ->setCodigoMotivo('01')
                        ->setDescripcionMotivo('ERROR AL EMITIR COMPROBANTE')
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
                    $insert = $this->noteModel->add($note);
                    if ($insert->isSuccess()) {
                        $counter++;
                    }
                }
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }
        return "{$counter}/{$total} Registros insertados";
    }
}