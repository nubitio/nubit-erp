<?php

declare(strict_types=1);

namespace App\Sales\Controller\Api;

use App\Identity\Security\Security;
use App\Efact\Http\ApiResponse;
use App\Sales\Domain\SaleException;
use App\Sales\Port\SaleInvoicingPortInterface;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class VoidSale extends AbstractController
{
    public function __construct(
        private readonly SaleInvoicingPortInterface $saleInvoicing,
        private readonly Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $saleId = $request->attributes->get('id');
        $reason = $request->get('reason');
        if ($reason === null) {
            try {
                $payload = $request->toArray();
                $reason = $payload['reason'] ?? null;
            } catch (\Throwable) {
                $payload = json_decode((string)$request->getContent(), true);
                $reason = is_array($payload) ? ($payload['reason'] ?? null) : null;
            }
        }
        $userId = $this->security->connectedUser()->userId;

        if (!is_string($reason) || trim($reason) === '') {
            return new ApiResponse(
                success: false,
                message: 'Debe indicar el motivo de anulación',
            );
        }

        try {
            $this->saleInvoicing->voidSale((string) $saleId, $reason, $userId);
        } catch (FilesystemException | SaleException $e) {
            $message = ($e instanceof SaleException) ? $e->getMessage() : 'Error al anular el comprobante';

            return new ApiResponse(
                success: false,
                message: $message,
                payload: $e->getMessage()
            );
        } catch (\Exception $e) {
            return new ApiResponse(
                success: false,
                message: $e->getMessage()
            );
        }

        return new ApiResponse(
            success: true,
            message: 'Comprobante anulado correctamente'
        );
    }
}
