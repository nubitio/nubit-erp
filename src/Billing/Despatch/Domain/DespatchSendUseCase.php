<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Domain;

use App\Billing\Despatch\Builder\DespatchBuilder;
use App\Billing\Despatch\Entity\DespatchGuide;
use App\Billing\Despatch\Repository\DespatchGuideRepository;
use App\Billing\Sunat\Model\Response\DocumentResponse;
use App\Efact\Config\EfactConstants as Constants;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\NotFoundException;
use Nubit\Platform\Filesystem\FileManager;
use Psr\Log\LoggerInterface;

/**
 * Orquesta el envío de una Guía de Remisión Electrónica a SUNAT: construye el
 * modelo Greenter, lo envía vía lycet, guarda el XML/CDR y persiste el estado.
 */
readonly class DespatchSendUseCase
{
    public function __construct(
        private DespatchGuideRepository $guideRepository,
        private DespatchBuilder $builder,
        private DespatchRepositoryInterface $despatchRepository,
        private EntityManagerInterface $entityManager,
        private FileManager $fileManager,
        private LoggerInterface $billingLogger,
    ) {
    }

    public function __invoke(string $despatchGuideId): DocumentResponse
    {
        $guide = $this->guideRepository->find($despatchGuideId);
        if (!$guide instanceof DespatchGuide) {
            throw new NotFoundException('Guía de remisión no encontrada.');
        }

        $despatch = $this->builder->build($guide);
        $response = $this->despatchRepository->sendDespatch($despatch);
        $response->name = $despatch->getName();

        $this->persistResponse($guide, $response);

        return $response;
    }

    private function persistResponse(DespatchGuide $guide, DocumentResponse $response): void
    {
        $guide->setSunatTicket($response->hash ?? null);

        $docDir = Constants::INVOICE_DIR . '/' . $response->name;
        if (isset($response->xml) && !in_array($response->xml, ['', '0'], true)) {
            $this->fileManager->write($docDir . '/' . $response->name . '.xml', $response->xml);
        }

        $sunatResponse = $response->sunatResponse;
        if ($sunatResponse->isSuccess()) {
            $guide->setSunatStatus($sunatResponse->getCdrResponse()?->getCode() ?? '0');
            $guide->setSunatMessage($sunatResponse->getCdrResponse()?->getDescription() ?? '');

            $cdrZip = $sunatResponse->getCdrZip();
            if (!in_array($cdrZip, [null, '', '0'], true)) {
                $cdrPath = $docDir . '/R-' . $response->name . '.zip';
                $this->fileManager->write($cdrPath, base64_decode($cdrZip));
                $guide->setCdrPath($cdrPath);
            }
        } else {
            $guide->setSunatStatus($sunatResponse->getError()?->getCode() ?? 'ERROR');
            $guide->setSunatMessage($sunatResponse->getError()?->getMessage() ?? '');
        }

        $guide->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $this->billingLogger->info('despatch.persisted', [
            'despatch_guide_id' => $guide->getId(),
            'status' => $guide->getSunatStatus(),
        ]);
    }
}
