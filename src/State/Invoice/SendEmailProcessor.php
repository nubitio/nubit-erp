<?php

namespace App\State\Invoice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Http\ApiResponse;
use App\Invoice\Domain\InvoiceEmailUseCase;
use League\Flysystem\FilesystemException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

readonly class SendEmailProcessor implements ProcessorInterface
{
    public function __construct(
        private InvoiceEmailUseCase $emailUseCase,
    ) {
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        try {
            $this->emailUseCase->__invoke($uriVariables['id']);

            return new ApiResponse(true, 'Email enviado');
        } catch (\Exception | FilesystemException | TransportExceptionInterface $e) {
            return new ApiResponse(false, 'Error al enviar el email', $e);
        }
    }
}
