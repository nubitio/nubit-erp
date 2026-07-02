<?php

declare(strict_types=1);

namespace App\Identity\Notification;

/**
 * Canonical notification type slugs.
 * Keep in sync with the user_notification.type COMMENT in the migration.
 */
final class NotificationType
{
    public const string SUNAT_ERROR = 'sunat_error';
    public const string SUNAT_BLOCKED = 'sunat_blocked';
    public const string STOCK_LOW = 'stock_low';
    public const string CASH_NOT_OPENED = 'cash_not_opened';
    public const string QUOTA_WARNING = 'quota_warning';
    public const string DAILY_BRIEFING = 'daily_briefing';
    public const string CERTIFICATE_EXPIRING = 'certificate_expiring';
}
