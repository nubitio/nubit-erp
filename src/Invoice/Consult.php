<?php

namespace App\Invoice;

use Greenter\Ws\Services\ConsultCdrService;
use Greenter\Ws\Services\SoapClient;

class Consult
{
    protected \Base $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @throws \SoapFault
     */
    public function consult($ruc, $user, $pass, $type, $series, $number)
    {
        $wsdlUrl = 'https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService?wsdl';
        $soap = new SoapClient($wsdlUrl);
        $soap->setCredentials($user, $pass);

        $service = new ConsultCdrService();
        $service->setClient($soap);

        $result = $service->getStatusCdr($ruc, $type, $series, $number);

        if (!$result->isSuccess()) {
            dump($result->getError());
            return;
        }

        $cdr = $result->getCdrResponse();
        if ($cdr === null) {
            echo 'CDR no encontrado, el comprobante no ha sido comunicado a SUNAT.';
            return;
        }

        file_put_contents($this->app->get('EFACT_DIR') . 'R-' . $ruc . '-' . $type . '-' . $series . '-' . $number . '.zip', $result->getCdrZip());
        dump($cdr);
    }
}