<?php

declare(strict_types=1);

namespace App\Billing\Controller\Api;

use App\Billing\Invoice\Domain\InvoiceEmailException;
use App\Billing\Invoice\Domain\InvoiceEmailUseCase;
use Exception;
use League\Flysystem\FilesystemException;
use Nubit\Platform\Http\ApiResponse;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendInvoiceEmail extends AbstractController
{
    public function __construct(
        private readonly InvoiceEmailUseCase $emailUseCase,
        private readonly LoggerInterface $logger,
    ) {}

    /** Optional `{"email": "..."}` body: lets the cashier send to an address the customer just gave. */
    private function recipient(Request $request): ?string
    {
        $content = trim($request->getContent());
        if ($content === '') {
            return null;
        }

        $payload = json_decode($content, true);
        $email = is_array($payload) ? $payload['email'] ?? null : null;
        if ($email === null || $email === '') {
            return null;
        }

        $email = is_string($email) ? trim($email) : '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvoiceEmailException::invalidRecipient();
        }

        return $email;
    }

    public function __invoke(Request $request): Response
    {
        try {
            $id = $request->get('id');
            $this->emailUseCase->__invoke($id, $this->recipient($request));

            return new ApiResponse(true, 'Email enviado');
        } catch (InvoiceEmailException $e) {
            return new ApiResponse(false, $e->getMessage());
        } catch (Exception|FilesystemException|TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            return new ApiResponse(false, 'Error al enviar el email', $e);
        }
    }
}
