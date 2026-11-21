<?php

declare(strict_types=1);

namespace App\Identity\Activity;

/** Event types of the activity record, with their sensitivity and the groups used by the filters. */
final class ActivityType
{
    public const string LOGIN = 'auth.login';
    public const string LOGIN_FAILED = 'auth.login_failed';
    public const string PASSWORD_RESET_REQUESTED = 'auth.password_reset_requested';
    public const string PASSWORD_RESET = 'auth.password_reset';
    public const string TOTP_ENABLED = 'auth.totp_enabled';
    public const string TOTP_DISABLED = 'auth.totp_disabled';
    public const string TOTP_RESET = 'auth.totp_reset';
    public const string USER_INVITED = 'user.invited';
    public const string INVITATION_ACCEPTED = 'user.invitation_accepted';
    public const string INVITATION_RESENT = 'user.invitation_resent';
    public const string INVITATION_REVOKED = 'user.invitation_revoked';
    public const string USER_CREATED = 'user.created';
    public const string EMAIL_CHANGED = 'user.email_changed';
    public const string USER_DELETED = 'user.deleted';
    public const string ROLE_PERMISSIONS_CHANGED = 'role.permissions_changed';

    /** Routine events: shown under "Todos" but not flagged as sensitive. */
    private const array ROUTINE = [self::LOGIN, self::PASSWORD_RESET_REQUESTED];

    /** Filter values the API accepts besides "sensitive" and "all". */
    public const array GROUPS = ['auth', 'user', 'role'];

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::LOGIN,
            self::LOGIN_FAILED,
            self::PASSWORD_RESET_REQUESTED,
            self::PASSWORD_RESET,
            self::TOTP_ENABLED,
            self::TOTP_DISABLED,
            self::TOTP_RESET,
            self::USER_INVITED,
            self::INVITATION_ACCEPTED,
            self::INVITATION_RESENT,
            self::INVITATION_REVOKED,
            self::USER_CREATED,
            self::EMAIL_CHANGED,
            self::USER_DELETED,
            self::ROLE_PERMISSIONS_CHANGED,
        ];
    }

    public static function isSensitive(string $type): bool
    {
        return !in_array($type, self::ROUTINE, strict: true);
    }

    public static function group(string $type): string
    {
        return explode('.', $type, 2)[0];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::LOGIN => 'Inicios de sesión',
            self::LOGIN_FAILED => 'Ingresos fallidos',
            self::PASSWORD_RESET_REQUESTED => 'Restablecimientos pedidos',
            self::PASSWORD_RESET => 'Contraseñas restablecidas',
            self::TOTP_ENABLED => 'Verificación en dos pasos activada',
            self::TOTP_DISABLED => 'Verificación en dos pasos desactivada',
            self::TOTP_RESET => 'Verificación en dos pasos restablecida',
            self::USER_INVITED => 'Invitaciones enviadas',
            self::INVITATION_ACCEPTED => 'Invitaciones aceptadas',
            self::INVITATION_RESENT => 'Invitaciones reenviadas',
            self::INVITATION_REVOKED => 'Invitaciones revocadas',
            self::USER_CREATED => 'Usuarios creados',
            self::EMAIL_CHANGED => 'Correos cambiados',
            self::USER_DELETED => 'Usuarios eliminados',
            self::ROLE_PERMISSIONS_CHANGED => 'Cambios de permisos de rol',
            default => $type,
        };
    }
}
