<?php

namespace App\Controllers;

use App\Models\BotModel;
use App\Models\OptionsModel;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\DocumentInterface;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\Document;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Greenter\Report\HtmlReport;
use Greenter\Report\PdfReport;
use Greenter\Report\XmlUtils;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;

class EFactController extends BaseController
{
    private See $see;
    private OptionsModel $optionsDao;
    private BotModel $botModel;

    public function __construct()
    {
        parent::__construct();
        $this->see = new See();
        $this->optionsDao = new OptionsModel();
        $this->botModel = new BotModel();

        if ($this->app->get('EFACT_MODE') == 'PROD') {
            $this->see->setService(SunatEndpoints::FE_PRODUCCION);
            $this->see->setCertificate(file_get_contents($this->getOption('invoice_cert')));
            $this->see->setCredentials($this->getOption('invoice_user'), $this->getOption('invoice_password'));
        } else {
            $this->see->setService(SunatEndpoints::FE_BETA);
            $this->see->setCertificate(file_get_contents('src/certificate_beta.pem'));
            $this->see->setCredentials('20000000001MODDATOS', 'moddatos');
        }
    }

    public function getOption($name)
    {
        try {
            return $this->optionsDao->getByName($name);
        } catch (Exception $e) {
            $this->app->error(500, "No se pudo obtener la opcion {$name}");
        }
    }

    public function getCompany()
    {
        // Emisor
        $address = new Address();
        $address->setUbigueo($this->getOption('invoice_ubigeo'))
            ->setDepartamento($this->getOption('invoice_departamento'))
            ->setProvincia($this->getOption('invoice_provincia'))
            ->setDistrito($this->getOption('invoice_distrito'))
            ->setUrbanizacion($this->getOption('invoice_urbanizacion'))
            ->setDireccion($this->getOption('invoice_direccion'));

        $company = new Company();
        $company->setRuc($this->getOption('invoice_ruc'))
            ->setRazonSocial($this->getOption('invoice_razon_social'))
            ->setNombreComercial($this->getOption('invoice_nombre_comercial'))
            ->setAddress($address);
        return $company;
    }

    public function info()
    {
        echo "<pre>";
        var_dump($this->getCompany());
        echo "</pre>";
    }

    public function getPDF(DocumentInterface $invoice, $data)
    {
        $html = new HtmlReport('', [
            'cache' => 'tmp',
            'strict_variables' => true,
        ]);
        $html->setTemplate('invoice.html.twig');

        $report = new PdfReport($html);
        $report->setOptions([
            'no-outline',
            'viewport-size' => '1280x1024',
            'page-width' => '21cm',
            'page-height' => '29.7cm',
        ]);
        $report->setBinPath('wkhtmltopdf');

        $logo = file_get_contents($this->app->get('ASSETS') . 'images/logo.png');
        $params = [
            'system' => [
                'logo' => $logo,
                'hash' => $this->getHash($invoice)
            ],
            'user' => [
                'header' => '',
                'extras' => [
                    ['name' => 'OBS', 'value' => $data['Obs']]
                ],
            ]
        ];

        return $report->render($invoice, $params);
    }

    private function getHash(DocumentInterface $document)
    {
        $xml = $this->see->getXmlSigned($document);
        return (new XmlUtils())->getHashSign($xml);
    }

    public function invoice()
    {
        $id = $this->app->get('PARAMS.id');
        $sale = $this->botModel->getOne($id);
        if ($sale["success"]) {
            $result = $this->makeInvoice($sale["data"]);
            $this->botModel->updateSaleState($result);
            $this->json($result);
        } else {
            $this->json($sale);
        }
    }

    public function makeInvoice($sale)
    {
        // Cliente
        $address = new Address();
        $address->setDireccion($sale['header']['client_address']);

        $client = new Client();
        $client->setTipoDoc($sale['header']['client_doc_type'])
            ->setNumDoc($sale['header']['client_doc_number'])
            ->setRznSocial($sale['header']['client_name'])
            ->setAddress($address);

        // Venta
        $invoice = (new Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101')// Catalog. 51
            ->setTipoDoc($sale['header']['doc_type'])
            ->setSerie($sale['header']['doc_series'])
            ->setCorrelativo($sale['header']['doc_number'])
            ->setFechaEmision(new DateTime($sale['header']['doc_date'] . " 00:00:00"))
            ->setTipoMoneda($sale['header']['doc_currency'])
            ->setClient($client)
            ->setMtoOperGravadas($sale['header']['doc_taxed_amount'])
            ->setMtoIGV($sale['header']['doc_igv_amount'])
            ->setTotalImpuestos($sale['header']['doc_total_taxes'])
            ->setValorVenta($sale['header']['doc_sale_value'])
            ->setSubTotal($sale['header']['doc_sale_amount'])
            ->setMtoImpVenta($sale['header']['doc_sale_amount'])
            ->setCompany($this->getCompany());

        if ($sale['header']['doc_discount_percent'] > 0) {
            $invoice->setDescuentos([
                (new Charge())
                    ->setCodTipo('03')
                    ->setMontoBase($sale['header']['doc_sale_amount'])
                    ->setFactor($sale['header']['doc_discount_percent'] / 100)
                    ->setMonto($sale['header']['doc_discount_amount'])
            ]);
            $invoice->setSumOtrosDescuentos($sale['header']['doc_discount_amount']);
            $invoice->setMtoImpVenta($sale['header']['doc_sale_amount'] - $sale['header']['doc_discount_amount']);
        }

        $items = [];
        for ($i = 0; $i < count($sale['detail']); $i++) {
            $igv = 0.18;
            $divisor = 1 + $igv;
            $valor_venta = $sale['detail'][$i]['price'] / $divisor;
            $valor_igv = $valor_venta * $igv;
            $monto_base_igv = round($valor_venta * $sale['detail'][$i]['quantity'], 2);
            $monto_igv = round($valor_igv * $sale['detail'][$i]['quantity'], 2);
            $item = (new SaleDetail())
                ->setCodProducto($sale['detail'][$i]['code'])
                ->setUnidad('NIU')
                ->setCantidad($sale['detail'][$i]['quantity'])
                ->setMtoValorUnitario(number_format($valor_venta, 2, '.', ''))
                ->setDescripcion($sale['detail'][$i]['product'])
                ->setMtoBaseIgv(number_format($monto_base_igv, 2, '.', ''))
                ->setPorcentajeIgv(18.00)// 18%
                ->setIgv(number_format($monto_igv, 2, '.', ''))
                ->setTipAfeIgv($sale['detail'][$i]['tax_type'])
                ->setTotalImpuestos(number_format($monto_igv, 2, '.', ''))
                ->setMtoValorVenta(number_format($monto_base_igv, 2, '.', ''))
                ->setMtoPrecioUnitario(number_format($valor_venta + $valor_igv, 2, '.', ''));
            array_push($items, $item);
        }
        $formatter = new NumberFormatter("es", NumberFormatter::SPELLOUT);
        $parts = explode('.', $sale['header']['doc_sale_amount']);
        $monto = $formatter->format($parts[0]);
        $str_total = mb_strtoupper('SON ' . $monto . " CON " . $parts[1] . "/100 SOLES");

        $legend = (new Legend())
            ->setCode('1000')
            ->setValue($str_total);

        $invoice->setDetails($items)
            ->setLegends([$legend]);

        $result = $this->see->send($invoice);
        // Guardar XML
        file_put_contents($this->app->get('EFACT_DIR') . $invoice->getName() . '.xml', $this->see->getFactory()->getLastXml());
        // Guardar PDF
        file_put_contents($this->app->get('EFACT_DIR') . $invoice->getName() . '.pdf', $this->getPDF($invoice, $sale));
        if (!$result->isSuccess()) {
            return ["success" => false, "id" => $sale['header']['movimiento_id'], "hash" => $this->getHash($invoice), "code" => $result->getError()->getCode(), "msg" => $result->getError()->getMessage()];
        }
        // Guardar CDR
        file_put_contents($this->app->get('EFACT_DIR') . 'R-' . $invoice->getName() . '.zip', $result->getCdrZip());
        return ["success" => true, "id" => $sale['header']['movimiento_id'], "hash" => $this->getHash($invoice), "code" => $result->getCdrResponse()->getCode(), "msg" => $result->getCdrResponse()->getDescription()];
    }

    public function makeNote($sale)
    {
        // Cliente
        $client = new Client();
        $client->setTipoDoc($sale['header']['client_doc_type'])
            ->setNumDoc($sale['header']['client_doc_number'])
            ->setRznSocial($sale['header']['client_name']);

        // Note
        $note = new Note();
        $note
            ->setUblVersion('2.1')
            ->setTipDocAfectado($sale['header']['doc_type'])
            ->setNumDocfectado($sale['header']['doc_series'] . '-' . $sale['header']['doc_number'])
            ->setCodMotivo($sale['note']['codigo_motivo'])
            ->setDesMotivo($sale['note']['descripcion_motivo'])
            ->setTipoDoc($sale['note']['tipo_id'])
            ->setSerie($sale['note']['serie'])
            ->setFechaEmision(new DateTime($sale['note']['fecha'] . " 00:00:00"))
            ->setCorrelativo($sale['note']['correlativo'])
            ->setTipoMoneda($sale['header']['doc_currency'])
            ->setCompany($this->getCompany())
            ->setClient($client)
            ->setMtoOperGravadas($sale['header']['doc_taxed_amount'])
            ->setMtoIGV($sale['header']['doc_igv_amount'])
            ->setTotalImpuestos($sale['header']['doc_total_taxes'])
            ->setMtoImpVenta($sale['header']['doc_sale_amount']);

        $items = [];
        for ($i = 0; $i < count($sale['detail']); $i++) {
            $igv = 0.18;
            $divisor = 1 + $igv;
            $valor_venta = $sale['detail'][$i]['price'] / $divisor;
            $valor_igv = $valor_venta * $igv;
            $monto_base_igv = round($valor_venta * $sale['detail'][$i]['quantity'], 2);
            $monto_igv = round($valor_igv * $sale['detail'][$i]['quantity'], 2);
            $item = (new SaleDetail())
                ->setCodProducto($sale['detail'][$i]['code'])
                ->setUnidad('NIU')
                ->setCantidad($sale['detail'][$i]['quantity'])
                ->setDescripcion($sale['detail'][$i]['product'])
                ->setMtoBaseIgv(number_format($monto_base_igv, 2, '.', ''))
                ->setPorcentajeIgv(18.00)// 18%
                ->setIgv(number_format($monto_igv, 2, '.', ''))
                ->setTipAfeIgv($sale['detail'][$i]['tax_type'])
                ->setTotalImpuestos(number_format($monto_igv, 2, '.', ''))
                ->setMtoValorVenta(number_format($monto_base_igv, 2, '.', ''))
                ->setMtoValorUnitario(number_format($valor_venta, 2, '.', ''))
                ->setMtoPrecioUnitario(number_format($valor_venta + $valor_igv, 2, '.', ''));
            array_push($items, $item);
        }

        $formatter = new NumberFormatter("es", NumberFormatter::SPELLOUT);
        $parts = explode('.', $sale['header']['doc_sale_amount']);
        $monto = $formatter->format($parts[0]);
        $str_total = mb_strtoupper('SON ' . $monto . " CON " . $parts[1] . "/100 SOLES");

        $legend = new Legend();
        $legend->setCode('1000')
            ->setValue($str_total);

        $note->setDetails($items)
            ->setLegends([$legend]);

        $result = $this->see->send($note);
        // Guardar XML
        file_put_contents($this->app->get('EFACT_DIR') . $note->getName() . '.xml', $this->see->getFactory()->getLastXml());

        if (!$result->isSuccess()) {
            return ["success" => false, "id" => $sale['note']['nota_id'], "hash" => $this->getHash($note), "code" => $result->getError()->getCode(), "msg" => $result->getError()->getMessage()];
        }
        // Guardar CDR
        file_put_contents($this->app->get('EFACT_DIR') . 'R-' . $note->getName() . '.zip', $result->getCdrZip());
        return ["success" => true, "id" => $sale['note']['nota_id'], "hash" => $this->getHash($note), "code" => $result->getCdrResponse()->getCode(), "msg" => $result->getCdrResponse()->getDescription()];
    }

    public function makeSummary($data, $correlative, $type = '03')
    {
        $detail = [];
        foreach ($data['data'] as $item) {
            $doc = new SummaryDetail();
            if (strlen($item['cliente_id']) != 11) {
                $doc->setClienteTipo(1);
            } else {
                $doc->setClienteTipo(6);
            }
            $doc->setTipoDoc($type)
                ->setSerieNro($item['serie'] . '-' . $item['numero'])
                ->setEstado('1')
                ->setClienteNro($item['cliente_id'])
                ->setTotal($item['total'])
                ->setMtoOperGravadas($item['subtotal'])
                ->setMtoOperInafectas(0.0)
                ->setMtoOperExoneradas(0.0)
                ->setMtoOtrosCargos(0.0)
                ->setMtoIGV($item['igv']);
            if ($type == '07') {
                $affectedDoc = new Document();
                if (strlen($item['cliente_id']) != 11) {
                    $affectedDoc->setTipoDoc('03');
                } else {
                    $affectedDoc->setTipoDoc('01');
                }
                $affectedDoc->setNroDoc($item['doc_afectado']);
                $doc->setDocReferencia($affectedDoc);
            }
            array_push($detail, $doc);
        }

        $summary = new Summary();

        $summary->setFecGeneracion(new \DateTime($data[0]['fecha']))
            ->setFecResumen(new \DateTime(date('Y-m-d')))
            ->setCorrelativo($correlative)
            ->setCompany($this->getCompany())
            ->setDetails($detail);

        $result = $this->see->send($summary);

        // Guardar XML
        file_put_contents($this->app->get('EFACT_DIR') . $summary->getName() . '.xml', $this->see->getFactory()->getLastXml());

        if (!$result->isSuccess()) {
            return ["success" => false, "ticket" => null, "id" => null, "hash" => $this->getHash($summary), "code" => $result->getError()->getCode(), "msg" => $result->getError()->getMessage()];
        }

        $ticket = $result->getTicket();

        sleep(5);

        $statusResult = $this->see->getStatus($ticket);

        if (!$statusResult->isSuccess()) {
            return ["success" => false, "ticket" => $ticket, "id" => $statusResult->getCdrResponse()->getId(), "hash" => $this->getHash($summary), "code" => $statusResult->getError()->getCode(), "msg" => $statusResult->getError()->getMessage()];
        }

        // Guardar CDR
        file_put_contents($this->app->get('EFACT_DIR') . 'R-' . $summary->getName() . '.zip', $statusResult->getCdrZip());
        return ["success" => true, "ticket" => $ticket, "id" => $statusResult->getCdrResponse()->getId(), "hash" => $this->getHash($summary), "code" => $statusResult->getCdrResponse()->getCode(), "msg" => $statusResult->getCdrResponse()->getDescription()];
    }

    function checkTicketStatus($ticket)
    {
        $statusResult = $this->see->getStatus($ticket);

        if (!$statusResult->isSuccess()) {
            return ["success" => false, "ticket" => $ticket, "id" => $statusResult->getCdrResponse()->getId(), "hash" => null, "code" => $statusResult->getError()->getCode(), "msg" => $statusResult->getError()->getMessage()];
        }

        // Guardar CDR
        file_put_contents($this->app->get('EFACT_DIR') . 'R-' . $this->getOption('invoice_ruc') . '-' . $statusResult->getCdrResponse()->getId() . '.zip', $statusResult->getCdrZip());
        return ["success" => true, "ticket" => $ticket, "id" => $statusResult->getCdrResponse()->getId(), "hash" => null, "code" => $statusResult->getCdrResponse()->getCode(), "msg" => $statusResult->getCdrResponse()->getDescription()];
    }
}
