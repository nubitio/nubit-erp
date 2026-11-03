<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

/**
 * Writes the collection reminder text. Pure: no I/O, so every wording rule is unit-tested.
 */
final class ReminderMessageComposer
{
    public function subject(ReminderContext $context): string
    {
        $document = $context->documentNumber ?? 'su saldo pendiente';

        return sprintf('Recordatorio de pago: %s', $document);
    }

    public function body(ReminderContext $context, ReminderTone $tone): string
    {
        $paragraphs = match ($tone) {
            ReminderTone::Friendly => [
                sprintf('Hola %s, te escribimos de %s.', $context->contactName, $context->companyName),
                sprintf(
                    'Te recordamos que %s por %s %s.',
                    $this->documentLabel($context, $tone),
                    $context->amount,
                    $this->dueClause($context),
                ),
                'Si ya realizaste el pago, ignora este mensaje y disculpa la molestia.',
            ],
            ReminderTone::Direct => [
                sprintf('Hola %s, le escribimos de %s.', $context->contactName, $context->companyName),
                sprintf(
                    '%s por %s %s%s. ¿Nos confirma la fecha de pago?',
                    ucfirst($this->documentLabel($context, $tone)),
                    $context->amount,
                    $this->dueClause($context),
                    $context->daysOverdue > 0 ? ' y sigue pendiente' : '',
                ),
            ],
            ReminderTone::Final => [
                sprintf('%s, este es nuestro último aviso de %s.', $context->contactName, $context->companyName),
                sprintf(
                    '%s por %s %s%s.',
                    ucfirst($this->documentLabel($context, $tone)),
                    $context->amount,
                    $this->dueClause($context),
                    $context->daysOverdue > 0 ? sprintf(' (%s)', $this->overdueDays($context->daysOverdue)) : '',
                ),
                'Le pedimos regularizar el pago hoy o comunicarse con nosotros.',
            ],
        };

        if ($context->documentUrl !== null) {
            $paragraphs[] = sprintf('Puede ver el comprobante aquí: %s', $context->documentUrl);
        }
        if ($tone !== ReminderTone::Friendly) {
            $paragraphs[] = 'Si ya pagó, responda con la constancia y la registramos. Gracias.';
        } else {
            $paragraphs[] = '¡Gracias!';
        }

        return implode("\n\n", $paragraphs);
    }

    private function documentLabel(ReminderContext $context, ReminderTone $tone): string
    {
        if ($context->documentNumber !== null) {
            return sprintf('el comprobante %s', $context->documentNumber);
        }

        // The friendly tone uses "tú"; the others address the customer as "usted".
        return $tone === ReminderTone::Friendly ? 'tu compra' : 'su compra';
    }

    private function dueClause(ReminderContext $context): string
    {
        if ($context->dueDate === null) {
            return 'está pendiente de pago';
        }

        return sprintf('%s el %s', $context->daysOverdue > 0 ? 'venció' : 'vence', $context->dueDate->format('d/m/Y'));
    }

    private function overdueDays(int $days): string
    {
        return $days === 1 ? '1 día de atraso' : sprintf('%d días de atraso', $days);
    }
}
