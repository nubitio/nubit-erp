<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Identity\Entity\User;
use App\Identity\Security\Security;
use App\Sales\Receivable\CollectionRequestParser;
use App\Sales\Receivable\RegisterCollection;
use App\Settings\Capability\CapabilityKey;
use App\Settings\Capability\CapabilityNotAvailableException;
use App\Settings\Capability\TenantCapabilityResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * POST /api/sales/collections: money received from a customer, however it arrived and whatever it settles.
 *
 * Internal API behind `finance.receivables_v2`; the legacy `/sales/collect-on-account` keeps working untouched.
 * The `Idempotency-Key` header is mandatory here: this endpoint has no legacy callers to protect.
 */
final class CollectionController extends AbstractController
{
    public function __construct(
        private readonly TenantCapabilityResolver $capabilities,
        private readonly RegisterCollection $registerCollection,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly CollectionRequestParser $parser,
    ) {}

    #[Route('/api/sales/collections', name: 'sales_collection_register', methods: ['POST'])]
    #[IsGranted('perm:sales.collect')]
    public function register(Request $request): JsonResponse
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

        $result = $this->registerCollection->register($this->parser->parse($request, $user, (int) $connected->userId));

        return $this->json($result->toArray(), $result->replayed ? Response::HTTP_OK : Response::HTTP_CREATED);
    }
}
