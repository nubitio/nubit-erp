<?php

namespace App\Controller;

class ConsultController extends BaseController
{
    private string $url;

    public function __construct()
    {
        parent::__construct();
        $this->url = "https://consultapi.efact.app/api/v1/";
    }

    public function beforeroute()
    {
        //public api
    }

    function getRucInfo()
    {
        $ruc = $this->app->get('PARAMS.ruc');
        $curl = curl_init($this->url . 'ruc/' . $ruc . '?token=abcxyz');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        header('content-type: application/json; charset=utf-8');
        echo $response;
    }

    function getDniInfo()
    {
        $dni = $this->app->get('PARAMS.dni');
        $curl = curl_init($this->url . 'dni/' . $dni . '?token=abcxyz');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        header('content-type: application/json; charset=utf-8');
        echo $response;
    }
}