<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Controller;

use App\Billing\Despatch\Entity\DespatchGuide;
use App\Billing\Despatch\Message\SendDespatchMessage;
use App\Billing\Despatch\Repository\DespatchGuideRepository;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Http\ApiResponse;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Encola el envío asíncrono de una Guía de Remisión Electrónica a SUNAT.
 */
final class SendDespatchGuide extends AbstractController
{
    public function __construct(
        private readonly DespatchGuideRepository $guideRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): Response
    {
        $id = (string) $request->get('id');
        $guide = $this->guideRepository->find($id);

        if (!$guide instanceof DespatchGuide) {
            throw new ServiceException('Guía de remisión no encontrada.');
        }

        if ($guide->isProcessed()) {
            throw new ServiceException('La guía de remisión ya fue enviada a SUNAT.');
        }

        $this->messageBus->dispatch(new SendDespatchMessage(
            $id,
            (string) ($this->tenantContext->getTenantId() ?? 'unknown'),
        ));

        return new ApiResponse(
            success: true,
            message: 'Guía de remisión encolada para envío a SUNAT.',
            payload: ['id' => $id],
        );
    }
}
