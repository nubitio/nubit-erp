<?php

class CreditNoteModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $serie
     * @return string
     * @throws Exception
     */
    public function getSeries($serie)
    {
        $obj = $this->db->exec("select * from local_comprobante where serie_asociada = ?", [$serie]);
        if ($obj) {
            return $obj[0]['serie'];
        } else {
            throw new Exception("No associated series found");
        }
    }

    /**
     * @param string $serie
     * @return int
     * @throws Exception
     */
    public function getNextNumber($serie)
    {
        $obj = $this->db->exec("select ifnull(max(correlativo), 0) as number from nota where serie = ?", [$serie]);
        if ($obj) {
            return $obj[0]['number'] + 1;
        } else {
            throw new Exception("Unable to get the next number");
        }
    }

    /**
     * @param Note $note
     * @return ApiResponse
     */
    public function add(Note $note)
    {
        try {
            $this->db->begin();
            $header = new \DB\SQL\Mapper($this->db, 'nota');
            $header->copyfrom(json_decode(json_encode($note), true));
            $header->save();
            foreach ($note->getDetails() as $item) {
                $detail = new \DB\SQL\Mapper($this->db, 'nota_detalle');
                $detail->copyfrom(json_decode(json_encode($item), true));
                $detail->save();
            }
            $this->db->commit();
            return (new ApiResponse())->setSuccess(true)->setMessage('Registro insertado')->setData($note);
        } catch (Exception $e) {
            return (new ApiResponse())->setSuccess(false)->setMessage($e->getMessage())->setData(null);
        }
    }
}