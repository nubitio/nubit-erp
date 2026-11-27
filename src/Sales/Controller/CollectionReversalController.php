<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Identity\Entity\User;
use App\Identity\Security\Security;
use App\Sales\Receivable\ReverseCollection;
use App\Settings\Capability\CapabilityKey;
use App\Settings\Capability\CapabilityNotAvailableException;
use App\Settings\Capability\TenantCapabilityResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * POST /api/sales/collections/{id}/reverse: undoes a collection registered through the receivables v2 API.
 * Needs its own permission: collecting does not imply the right to undo (separation of duties).
 */
final class CollectionReversalController extends AbstractController
{
    public function __construct(
        private readonly TenantCapabilityResolver $capabilities,
        private readonly ReverseCollection $reverseCollection,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {}

    #[Route('/api/sales/collections/{id}/reverse', name: 'sales_collection_reverse', methods: ['POST'])]
    #[IsGranted('perm:sales.collect_reverse')]
    public function reverse(string $id): JsonResponse
    {
        try {
            $this->capabilities->require(CapabilityKey::FinanceReceivablesV2);
        } catch (CapabilityNotAvailableException $e) {
            throw new AccessDeniedHttpException('Las cobranzas con varios medios de pago no están habilitadas.', $e);
        }

        $connected = $this->security->connectedUser();
        if ($connected === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }
        $user = $this->entityManager->getReference(User::class, $connected->userId);
        if (!$user instanceof User) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        return $this->json($this->reverseCollection->reverse($id, $user)->toArray());
    }
}
