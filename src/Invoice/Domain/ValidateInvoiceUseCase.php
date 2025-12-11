<?php

namespace App\Invoice\Domain;

use App\Core\Service\SettingsService;
use App\Core\Service\CompanyContext;
use App\Core\Util\Constants;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
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
        private CompanyContext $companyContext,
        private InvoiceRepository $invoiceRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ApiException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function __invoke(string $invoiceId): CpeResponse
    {
        $invoice = $this->invoiceRepository->findOneBy(
            ['sale' => $invoiceId]
        );
        $token = $this->getToken();
        $config = Configuration::getDefaultConfiguration()->setAccessToken($token);
        $apiInstance = new ConsultaApi(new Client(), $config->setHost($config->getHostFromSettings(1)));
        $customerId = $this->companyContext->getId();

        $cpeFilter = new CpeFilter()
            ->setNumRuc(strval($customerId))
            ->setCodComp(strval($invoice?->getVoucherTypeId()))
            ->setNumeroSerie(strval($invoice?->getSeries()))
            ->setNumero(strval($invoice?->getNumber()))
            ->setFechaEmision(strval($invoice?->getIssuedAt()?->format('d/m/Y')))
            ->setMonto(strval($invoice?->getTotal()));

        $response = $apiInstance->consultarCpe(strval($customerId), $cpeFilter);

        if ($response->getSuccess() && '0' === $response->getData()?->getEstadoCp()) {
            $invoice?->setSunatStatus('');
            $invoice?->setSunatMessage('');
            $this->entityManager->persist($invoice);
        }

        return $response;
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function getToken(): string
    {
        $customerID = $this->companyContext->getId();
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
