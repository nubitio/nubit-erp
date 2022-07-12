<?php

namespace App\Model;

use App\Domain\Common\ApiResponse;
use App\Domain\Note\Note;
use DB\SQL\Mapper;

class CreditNoteModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $series
     * @return string
     * @throws \Exception
     */
    public function getSeries(string $series): string
    {
        $obj = $this->db->exec("select * from local_comprobante where serie_asociada = ?", [$series]);
        if ($obj) {
            return $obj[0]['serie'];
        } else {
            throw new \Exception("No existe una serie asociada para este documento, asociala en el submódulo de configuración > local comprobante");
        }
    }

    /**
     * @param string $series
     * @return int
     * @throws \Exception
     */
    public function getNextNumber(string $series): int
    {
        $obj = $this->db->exec("select ifnull(max(correlativo), 0) as number from nota where serie = ?", [$series]);
        if ($obj) {
            return $obj[0]['number'] + 1;
        } else {
            throw new \Exception("Unable to get the next number");
        }
    }

    /**
     * @param Note $note
     * @return ApiResponse
     */
    public function add(Note $note): ApiResponse
    {
        try {
            $this->db->begin();
            $header = new Mapper($this->db, 'nota');
            $header->copyfrom(json_decode(json_encode($note), true));
            $header->save();
            foreach ($note->getDetails() as $item) {
                $detail = new Mapper($this->db, 'nota_detalle');
                $detail->copyfrom(json_decode(json_encode($item), true));
                $detail->save();
            }
            $this->db->commit();
            return (new ApiResponse())->setSuccess(true)->setMessage('Registro insertado')->setData($note);
        } catch (\Exception $e) {
            return (new ApiResponse())->setSuccess(false)->setMessage($e->getMessage())->setData(null);
        }
    }
}