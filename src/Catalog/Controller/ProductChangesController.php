<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Service\ProductChangeFeed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * GET /api/products-changes[?since=<cursor>] — the catalogue delta the till polls.
 *
 *   - without `since`: only a fresh cursor. A client that is about to download the whole
 *     catalogue asks for this FIRST, so nothing that changes during the download is missed.
 *   - with `since`: the products changed since then, in the same shape as GET /api/products,
 *     and the cursor to use next time.
 */
final class ProductChangesController extends AbstractController
{
    public function __construct(
        private readonly ProductChangeFeed $feed,
        private readonly NormalizerInterface $normalizer,
    ) {}

    #[Route('/api/products-changes', name: 'catalog_products_changes', methods: ['GET'])]
    #[IsGranted('perm:catalog.view')]
    public function __invoke(Request $request): JsonResponse
    {
        $since = $request->query->get('since');
        $cursor = $this->feed->cursor();

        if ($since === null || $since === '') {
            return $this->json(['cursor' => $cursor, 'products' => []]);
        }

        if (!is_string($since) || !ProductChangeFeed::isValidCursor($since)) {
            return $this->json([
                'error' => 'since must be a cursor returned by this endpoint.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $products = $this->normalizer->normalize($this->feed->changedSince($since), 'json', [
            'groups' => ['product:read'],
            // Same as GET /api/products, which leaves out what is null.
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);

        return $this->json(['cursor' => $cursor, 'products' => $products]);
    }
}
