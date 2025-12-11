<?php

declare(strict_types=1);

namespace App\State\Invoice;

use Override;
use Exception;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Http\ApiResponse;
use App\Invoice\Domain\InvoiceEmailUseCase;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

readonly class SendEmailProcessor implements ProcessorInterface
{
    public function __construct(
        private InvoiceEmailUseCase $emailUseCase,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        try {
            $this->emailUseCase->__invoke($uriVariables['id']);

            return new ApiResponse(true, 'Email enviado');
        } catch (Exception | FilesystemException | TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return new ApiResponse(false, 'Error al enviar el email', $e);
        }
    }
}
