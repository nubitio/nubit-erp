<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Entity\AssistantProposedAction;
use App\AI\Service\ProposedActionFactory;
use App\Contacts\Repository\CustomerRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'propose_customer',
    description: 'Propose creating a customer. Does NOT save. The UI shows a confirmation card. '
    . 'Pass ruc (8 DNI or 11 RUC digits) and businessName. Optional fiscalAddress, phone, email.',
    method: 'proposeCustomer',
)]
final readonly class ProposeCustomerTool
{
    public function __construct(
        private ProposedActionFactory $factory,
        private CustomerRepository $customerRepository,
    ) {}

    /**
     * @return array{error: string}|array{id: string, kind: string, summary: string, payload: array<string, mixed>}
     */
    public function proposeCustomer(
        string $ruc,
        string $businessName,
        ?string $fiscalAddress = null,
        ?string $phone = null,
        ?string $email = null,
    ): array {
        $ruc = trim($ruc);
        $businessName = trim($businessName);

        if (!preg_match('/^\d{8,11}$/', $ruc)) {
            return ['error' => 'El RUC/DNI debe tener 8 u 11 dígitos.'];
        }
        if ($businessName === '') {
            return ['error' => 'La razón social es obligatoria.'];
        }

        $existing = $this->customerRepository->findOneBy(['ruc' => $ruc]);
        if ($existing !== null) {
            return ['error' => sprintf('Ya existe un cliente con el documento %s.', $ruc)];
        }

        $payload = array_filter(
            [
                'ruc' => $ruc,
                'businessName' => $businessName,
                'fiscalAddress' => $fiscalAddress !== null ? trim($fiscalAddress) : null,
                'phone' => $phone !== null ? trim($phone) : null,
                'email' => $email !== null ? trim($email) : null,
            ],
            static fn(mixed $value): bool => $value !== null && $value !== '',
        );

        $summary = sprintf('Crear cliente %s · %s', $businessName, $ruc);
        $action = $this->factory->propose(AssistantProposedAction::KIND_CREATE_CUSTOMER, $summary, $payload);

        return $action->toCard();
    }
}
