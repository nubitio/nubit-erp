<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

use JsonException;

/** Turns a raw JSON body into a ReminderRequest, or a 422 ReminderException. */
final class ReminderRequestParser
{
    private const int SALE_ID_MAX_LENGTH = 36;

    private const int JSON_DEPTH = 8;

    public function parse(string $json): ReminderRequest
    {
        $payload = $this->decode($json);
        $body = $payload['body'] ?? null;
        if ($body !== null && !is_string($body)) {
            throw ReminderException::invalid('El mensaje debe ser texto.');
        }

        return new ReminderRequest(
            saleId: $this->saleId($payload),
            channel: ReminderChannel::tryFrom((string) ($payload['channel'] ?? '')) ?? throw ReminderException::invalid(
                'El canal debe ser whatsapp o email.',
            ),
            tone: ReminderTone::tryFrom((string) ($payload['tone'] ?? '')) ?? throw ReminderException::invalid(
                'El tono debe ser friendly, direct o final.',
            ),
            body: $body,
            force: ($payload['force'] ?? false) === true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        try {
            $decoded = json_decode($json, true, self::JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ReminderException::invalid('El cuerpo debe ser JSON válido.');
        }

        return is_array($decoded) ? $decoded : throw ReminderException::invalid('El cuerpo debe ser un objeto JSON.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function saleId(array $payload): string
    {
        $saleId = $payload['saleId'] ?? null;

        return is_string($saleId) && $saleId !== '' && strlen($saleId) <= self::SALE_ID_MAX_LENGTH
            ? $saleId
            : throw ReminderException::invalid('Indica la venta.');
    }
}
