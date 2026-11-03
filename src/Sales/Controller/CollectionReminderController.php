<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Sales\Entity\CollectionReminder;
use App\Sales\Reminder\CollectionReminderService;
use App\Sales\Reminder\ReminderException;
use App\Sales\Reminder\ReminderRequestParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Manual payment reminders for overdue sales (Figma 80:2634).
 *
 *   POST /api/receivables/reminders/preview   message and recipient, no side effects
 *   POST /api/receivables/reminders           record it (and send it, for e-mail)
 *   GET  /api/receivables/reminders           history by saleId or customerId
 */
#[Route('/api/receivables/reminders')]
#[IsGranted('perm:receivables.remind')]
final class CollectionReminderController extends AbstractController
{
    public function __construct(
        private readonly CollectionReminderService $service,
        private readonly ReminderRequestParser $parser,
    ) {}

    #[Route('/preview', name: 'api_receivable_reminder_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        return $this->guarded(function () use ($request): JsonResponse {
            $input = $this->parser->parse($request->getContent());

            return $this->json($this->service->preview($input->saleId, $input->channel, $input->tone));
        });
    }

    #[Route('', name: 'api_receivable_reminder_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->guarded(function () use ($request): JsonResponse {
            $input = $this->parser->parse($request->getContent());
            $outcome = $this->service->record(
                $input->saleId,
                $input->channel,
                $input->tone,
                $input->body,
                $input->force,
            );

            return $this->json(
                $this->item($outcome->reminder) + ['whatsappUrl' => $outcome->whatsappUrl],
                Response::HTTP_CREATED,
            );
        });
    }

    #[Route('', name: 'api_receivable_reminder_history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        return $this->guarded(function () use ($request): JsonResponse {
            $saleId = $request->query->get('saleId');
            $customerId = $request->query->get('customerId');
            if ($customerId !== null && !ctype_digit((string) $customerId)) {
                throw ReminderException::invalid('El cliente no es válido.');
            }

            $items = $this->service->history(
                is_string($saleId) && $saleId !== '' ? $saleId : null,
                $customerId !== null ? (int) $customerId : null,
            );

            return $this->json(array_map(fn(CollectionReminder $reminder): array => $this->item($reminder), $items));
        });
    }

    /**
     * @param callable(): JsonResponse $action
     */
    private function guarded(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (ReminderException $exception) {
            $body = ['error' => $exception->getMessage()];
            if ($exception->lastReminderAt !== null) {
                $body['lastReminderAt'] = $exception->lastReminderAt->format(DATE_ATOM);
            }

            return $this->json($body, $exception->status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function item(CollectionReminder $reminder): array
    {
        return [
            'id' => $reminder->getId(),
            'saleId' => $reminder->getSaleRecordId(),
            'customerId' => $reminder->getCustomerId(),
            'channel' => $reminder->getChannel()->value,
            'tone' => $reminder->getTone()->value,
            'status' => $reminder->getStatus()->value,
            'body' => $reminder->getBody(),
            'recipientPhone' => $reminder->getRecipientPhone(),
            'recipientEmail' => $reminder->getRecipientEmail(),
            'failureReason' => $reminder->getFailureReason(),
            'createdAt' => $reminder->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
