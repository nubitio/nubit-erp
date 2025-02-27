<?php

namespace App\State\Sale;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Domain\Common\ApiResponse;
use App\Dto\Sale\VoidSaleDto;
use App\Sale\Domain\SaleException;
use App\Sale\Domain\SaleVoidUseCase;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;

readonly class VoidInvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private SaleVoidUseCase $saleVoidUseCase,
        private Security $security,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param VoidSaleDto $data
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $saleId = strval($uriVariables['id']);
        $userId = $this->security->connectedUser()->userId;

        try {
            $this->saleVoidUseCase->__invoke($saleId, $data->reason, $userId);
        } catch (FilesystemException | Exception $e) {
            return new ApiResponse(
                success: false,
                message: 'Error al anular comprobante'
            );
        } catch (SaleException $e) {
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
