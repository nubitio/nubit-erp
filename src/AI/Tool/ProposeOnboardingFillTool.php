<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Entity\AssistantProposedAction;
use App\AI\Service\ProposedActionFactory;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'propose_onboarding_fill',
    description: 'Propose filling onboarding wizard fields. Does NOT upload certificates or submit the wizard. '
    . 'fieldsJson is a JSON object with any of: ruc, businessName, fiscalAddress, tradeName, phone, '
    . 'branchName, warehouseName, invoiceSeries, boletaSeries. Never include SOL password or certificates.',
    method: 'proposeOnboardingFill',
)]
final readonly class ProposeOnboardingFillTool
{
    private const array ALLOWED_FIELDS = [
        'ruc',
        'businessName',
        'fiscalAddress',
        'tradeName',
        'phone',
        'branchName',
        'warehouseName',
        'invoiceSeries',
        'boletaSeries',
    ];

    public function __construct(
        private ProposedActionFactory $factory,
    ) {}

    /**
     * @return array{error: string}|array{id: string, kind: string, summary: string, payload: array<string, mixed>, expiresAt: string}
     */
    public function proposeOnboardingFill(string $fieldsJson): array
    {
        $parsed = json_decode($fieldsJson, true);
        if (!is_array($parsed) || $parsed === []) {
            return ['error' => 'fieldsJson debe ser un objeto JSON con los campos a completar.'];
        }

        $payload = [];
        foreach (self::ALLOWED_FIELDS as $field) {
            if (!isset($parsed[$field])) {
                continue;
            }
            $value = trim((string) $parsed[$field]);
            if ($value === '') {
                continue;
            }
            $payload[$field] = $value;
        }

        if ($payload === []) {
            return ['error' => 'No hay campos permitidos para completar. No envíes clave SOL ni certificados.'];
        }

        $labels = implode(', ', array_keys($payload));
        $action = $this->factory->propose(
            AssistantProposedAction::KIND_FILL_ONBOARDING,
            'Completar datos del asistente de inicio: ' . $labels,
            $payload,
        );

        return $action->toCard();
    }
}
