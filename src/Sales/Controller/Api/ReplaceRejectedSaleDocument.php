<?php

declare(strict_types=1);

namespace App\Sales\Controller\Api;

use App\Sales\Port\RejectedDocumentReplacerInterface;
use Nubit\Platform\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ReplaceRejectedSaleDocument extends AbstractController
{
    public function __construct(
        private readonly RejectedDocumentReplacerInterface $rejectedDocumentReplacer,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $saleId = (string) $request->get('id');

        $result = $this->rejectedDocumentReplacer->replaceRejectedDocument($saleId);

        if (!$result->success) {
            return ApiResponse::error($result->message);
        }

        return ApiResponse::success($result->message, ['newId' => $result->newId]);
    }
}
