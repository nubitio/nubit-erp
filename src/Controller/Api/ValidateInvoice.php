<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Http\ApiResponse;
use App\EInvoicing\Invoice\Domain\ValidateInvoiceUseCase;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class ValidateInvoice extends AbstractController
{
    public function __construct(
        private readonly ValidateInvoiceUseCase $validateInvoiceUseCase,
    ) {
    }

    public function __invoke(Request $request)
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
