<?php

declare(strict_types=1);

namespace App\EInvoicing\Invoice\Domain;

use App\Core\Exception\ServiceException;
use App\Efact\Service\CompanyContext;
use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;
use App\Billing\Repository\TaxDocumentRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Greenter\Sunat\ConsultaCpe\Api\AuthApi;
use Greenter\Sunat\ConsultaCpe\Api\ConsultaApi;
use Greenter\Sunat\ConsultaCpe\ApiException;
use Greenter\Sunat\ConsultaCpe\Configuration;
use Greenter\Sunat\ConsultaCpe\Model\CpeFilter;
use Greenter\Sunat\ConsultaCpe\Model\CpeResponse;
use GuzzleHttp\ClientInterface;
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
        private TaxDocumentRepository $taxDocumentRepository,
        private EntityManagerInterface $entityManager,
        private ClientInterface $httpClient,
    ) {
    }

    /**
     * @throws ApiException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function __invoke(string $taxDocumentId): CpeResponse
    {
        $taxDocument = $this->taxDocumentRepository->find($taxDocumentId);

        if ($taxDocument === null) {
            throw new ServiceException('Invoice not found');
        }

        $voucher = $this->taxDocumentRepository->getVoucherByTaxDocumentId($taxDocumentId);
        $token = $this->getToken();
        $config = Configuration::getDefaultConfiguration()->setAccessToken($token);
        $apiInstance = new ConsultaApi($this->httpClient, $config->setHost($config->getHostFromSettings(1)));
        $customerId = $this->companyContext->getId();

        $cpeFilter = new CpeFilter()
            ->setNumRuc($customerId)
            ->setCodComp(strval($voucher->voucher_type))
            ->setNumeroSerie(strval($voucher->voucher_series))
            ->setNumero(strval($voucher->voucher_number))
            ->setFechaEmision(date('d/m/Y', strtotime(strval($voucher->voucher_date))))
            ->setMonto(strval($voucher->voucher_sale_amount));

        $response = $apiInstance->consultarCpe(strval($customerId), $cpeFilter);

        if ($response->getSuccess() && '0' === $response->getData()?->getEstadoCp()) {
            $taxDocument->setSunatStatus('');
            $taxDocument->setSunatMessage('');
            $taxDocument->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();
        }

        return $response;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getToken(): string
    {
        $customerID = $this->companyContext->getId();
        $tokenKey = $customerID . '_cpe_token';

        return $this->cache->get($tokenKey, function (ItemInterface $item) {
            $apiInstance = new AuthApi($this->httpClient);
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
