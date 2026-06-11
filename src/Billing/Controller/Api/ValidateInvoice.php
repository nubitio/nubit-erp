<?php

declare(strict_types=1);

namespace App\Billing\Controller\Api;

use Nubit\Platform\Http\ApiResponse;
use App\Billing\Invoice\Domain\ValidateInvoiceUseCase;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateInvoice extends AbstractController
{
    public function __construct(
        private readonly ValidateInvoiceUseCase $validateInvoiceUseCase,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->get('id');
        try {
            $result = $this->validateInvoiceUseCase->__invoke($id);

            if ($result->getSuccess()) {
                return new ApiResponse(
                    true,
                    $result->getMessage() ?? '',
                    $result
                );
            }

            return new ApiResponse(
                false,
                $result->getMessage() ?? '',
                $result
            );
        } catch (Exception | InvalidArgumentException $e) {
            return new ApiResponse(false, $e->getMessage(), $e);
        }
    }
}
