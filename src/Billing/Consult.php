<?php

namespace App\Billing;

use Greenter\Ws\Services\ConsultCdrService;
use Greenter\Ws\Services\SoapClient;

class Consult
{
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
            return ['success' => false, 'message' => $result->getError()->getCode().': '.$result->getError()->getMessage()];
        }

        $cdr = $result->getCdrResponse();
        if (null === $cdr) {
            return ['success' => false, 'message' => 'CDR no encontrado, el comprobante no ha sido comunicado a SUNAT.'];
        }

        file_put_contents($_ENV['EFACT_DIR'].'R-'.$ruc.'-'.$type.'-'.$series.'-'.$number.'.zip', $result->getCdrZip());

        return ['success' => true, 'message' => $cdr->getDescription(), 'cdr' => $cdr];
    }
}
