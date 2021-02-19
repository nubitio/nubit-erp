<?php


class SummaryController extends BaseController
{
    private $summaryModel;
    private $efact;

    public function __construct()
    {
        parent::__construct();
        $this->summaryModel = new SummaryModel();
        $this->efact = new EFactController();
    }

    function getPending()
    {
        $type = $this->app->get('PARAMS.type');
        if ($type == 1) {
            $this->json($this->summaryModel->getPending());
        } elseif ($type == 2) {
            $this->json($this->summaryModel->getPendingNotes());
        } else {
            $this->app->error(400, 'Bad Request');
        }
    }

    function sendPending()
    {
        $type = $this->app->get('PARAMS.type');
        $date = $this->app->get('PARAMS.date');
        if ($type != 1 && $type != 2) {
            $this->app->error(400, 'Bad Request');
            exit();
        }
        if ($type == 1) {
            $data = $this->summaryModel->getPendingDetail($date);
        } else {
            $data = $this->summaryModel->getPendingNotesDetail($date);
        }
        if (count($data['data']) > 0) {
            $correlative = $this->summaryModel->getNextNumber(date('Y-m-d'));
            $correlative = str_pad($correlative, 3, '0', STR_PAD_LEFT);
            if ($type == 1) {
                $result = $this->efact->makeSummary($data, $correlative, '03');
            } else {
                $result = $this->efact->makeSummary($data, $correlative, '07');
            }
        } else {
            $result = ["success" => false, "data" => null, "message" => "No hay comprobantes pendientes de envio"];
        }
        if ($result['success']) {
            $this->summaryModel->insertSummary($result);
            if ($type == 1) {
                $this->summaryModel->updatePending($data, $result);
            } else {
                $this->summaryModel->updatePendingNotes($data, $result);
            }
        }
        $this->json($result);
    }

    function checkTicket()
    {
        $ticket = $this->app->get('PARAMS.id');
        $result = $this->efact->checkTicketStatus($ticket);
        $this->json($result);
    }
}