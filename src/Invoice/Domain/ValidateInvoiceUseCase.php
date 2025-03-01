<?php

namespace App\Invoice\Domain;

use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\SettingsService;
use Doctrine\DBAL\Exception;
use Greenter\Sunat\ConsultaCpe\Api\AuthApi;
use Greenter\Sunat\ConsultaCpe\Api\ConsultaApi;
use Greenter\Sunat\ConsultaCpe\ApiException;
use Greenter\Sunat\ConsultaCpe\Configuration;
use Greenter\Sunat\ConsultaCpe\Model\CpeFilter;
use Greenter\Sunat\ConsultaCpe\Model\CpeResponse;
use GuzzleHttp\Client;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class ValidateInvoiceUseCase
{
    private const string GRANT_TYPE = 'client_credentials';

    private const string SCOPE = 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes';

    public function __construct(
        private CacheInterface $cache,
        private SettingsService $settings,
        private GetOneInvoiceUseCase $getOneInvoiceUseCase,
        private UpdateInvoiceUseCase $updateInvoiceUseCase,
    ) {
    }

    /**
     * @throws ApiException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function __invoke(string $invoiceId): CpeResponse
    {
        $invoice = $this->getOneInvoiceUseCase->__invoke($invoiceId);
        $token = $this->getToken();
        $config = Configuration::getDefaultConfiguration()->setAccessToken($token);
        $apiInstance = new ConsultaApi(new Client(), $config->setHost($config->getHostFromSettings(1)));
        $customerId = $this->settings->get(Constants::COMPANY_ID);

        $cpeFilter = (new CpeFilter())
            ->setNumRuc(strval($customerId))
            ->setCodComp(strval($invoice->tipo_comprobante_id))
            ->setNumeroSerie(strval($invoice->serie))
            ->setNumero(strval($invoice->numero))
            ->setFechaEmision(strval($invoice->fecha?->format('d/m/Y')))
            ->setMonto(strval($invoice->total));

        $response = $apiInstance->consultarCpe(strval($customerId), $cpeFilter);

        if ($response->getSuccess() && '0' === $response->getData()?->getEstadoCp()) {
            $invoice->envio_sunat = '';
            $invoice->mensaje_sunat = '';
            $this->updateInvoiceUseCase->__invoke($invoice);
        }

        return $response;
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function getToken(): string
    {
        $customerID = $this->settings->get(Constants::COMPANY_ID);
        $tokenKey = $customerID . '_cpe_token';

        return $this->cache->get($tokenKey, function (ItemInterface $item) {
            $apiInstance = new AuthApi(new Client());
            $item->expiresAfter(3600);
            $result = $apiInstance->getToken(
                self::GRANT_TYPE,
                self::SCOPE,
                strval($this->settings->get(Constants::CPE_CLIENT_ID)),
                strval($this->settings->get(Constants::CPE_CLIENT_SECRET))
            );

            return $result->getAccessToken() ?? '';
        });
    }
}
