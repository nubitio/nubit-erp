<?php

declare(strict_types=1);

namespace App\Onboarding\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final readonly class AccountService
{
    private const int MAX_VERIFICATION_ATTEMPTS = 3;

    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{id:int,email:string}
     */
    public function register(string $email, string $plainPassword): array
    {
        $email = mb_strtolower(trim($email));
        $plainPassword = trim($plainPassword);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email invalido.');
        }

        if (mb_strlen($plainPassword) < 8) {
            throw new InvalidArgumentException('La password debe tener al menos 8 caracteres.');
        }

        $hash = password_hash($plainPassword, PASSWORD_ARGON2ID);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = $this->getVerificationTtl();

        $conn = $this->cp();
        $account = $conn->fetchAssociative('SELECT id, verified_at FROM accounts WHERE email = ?', [$email]);

        if ($account !== false && $account['verified_at'] !== null) {
            throw new InvalidArgumentException('Ya existe una cuenta verificada con este email.');
        }

        if ($account === false) {
            $conn->insert('accounts', [
                'email' => $email,
                'password_hash' => $hash,
                'verification_code' => $code,
                'verification_expires_at' => (new \DateTimeImmutable(
                    sprintf('+%d seconds', $ttl)
                ))->format('Y-m-d H:i:s'),
                'verification_attempts' => 0,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $accountId = (int) $conn->lastInsertId();
        } else {
            $accountId = (int) $account['id'];
            $conn->executeStatement(
                'UPDATE accounts
                 SET password_hash = ?,
                     verification_code = ?,
                     verification_expires_at = ?,
                     verification_attempts = 0,
                     updated_at = NOW()
                 WHERE id = ?',
                [
                    $hash,
                    $code,
                    (new \DateTimeImmutable(sprintf('+%d seconds', $ttl)))->format('Y-m-d H:i:s'),
                    $accountId,
                ],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]
            );
        }

        $this->sendVerificationCode($email, $code);

        return ['id' => $accountId, 'email' => $email];
    }

    public function verifyEmailCode(string $email, string $code): int
    {
        $email = mb_strtolower(trim($email));
        $code = trim($code);

        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            throw new InvalidArgumentException('Codigo invalido.');
        }

        $conn = $this->cp();
        $account = $conn->fetchAssociative(
            'SELECT id, verified_at, verification_code, verification_expires_at, verification_attempts
             FROM accounts
             WHERE email = ?',
            [$email]
        );

        if ($account === false) {
            throw new InvalidArgumentException('Cuenta no encontrada.');
        }

        if ($account['verified_at'] !== null) {
            return (int) $account['id'];
        }

        if ((int) ($account['verification_attempts'] ?? 0) >= self::MAX_VERIFICATION_ATTEMPTS) {
            throw new InvalidArgumentException(
                'Demasiados intentos. Registra nuevamente para recibir un nuevo codigo.'
            );
        }

        $expiresAt = (string) ($account['verification_expires_at'] ?? '');
        if ($expiresAt === '' || new \DateTimeImmutable($expiresAt) < new \DateTimeImmutable('now')) {
            throw new InvalidArgumentException('Codigo expirado. Registra nuevamente para recibir uno nuevo.');
        }

        $storedCode = (string) ($account['verification_code'] ?? '');
        if (!hash_equals($storedCode, $code)) {
            $conn->executeStatement(
                'UPDATE accounts
                 SET verification_attempts = verification_attempts + 1, updated_at = NOW()
                 WHERE id = ?',
                [(int) $account['id']],
                [ParameterType::INTEGER]
            );

            throw new InvalidArgumentException('Codigo incorrecto.');
        }

        $conn->executeStatement(
            'UPDATE accounts
             SET verified_at = NOW(),
                 verification_code = NULL,
                 verification_expires_at = NULL,
                 verification_attempts = 0,
                 updated_at = NOW()
             WHERE id = ?',
            [(int) $account['id']],
            [ParameterType::INTEGER]
        );

        return (int) $account['id'];
    }

    /**
     * @return array{id:int,email:string}|null
     */
    public function authenticate(string $email, string $plainPassword): ?array
    {
        $email = mb_strtolower(trim($email));

        $account = $this->cp()->fetchAssociative(
            'SELECT id, email, password_hash, verified_at FROM accounts WHERE email = ?',
            [$email]
        );

        if ($account === false) {
            return null;
        }

        if ($account['verified_at'] === null) {
            throw new InvalidArgumentException('Tu email aun no fue verificado.');
        }

        if (!password_verify($plainPassword, (string) $account['password_hash'])) {
            return null;
        }

        return [
            'id' => (int) $account['id'],
            'email' => (string) $account['email'],
        ];
    }

    /**
     * @return array{id:int,email:string,verified_at:?string}|null
     */
    public function findById(int $accountId): ?array
    {
        $row = $this->cp()->fetchAssociative(
            'SELECT id, email, verified_at FROM accounts WHERE id = ?',
            [$accountId],
            [ParameterType::INTEGER]
        );

        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'verified_at' => $row['verified_at'] !== null ? (string) $row['verified_at'] : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAccountTenants(int $accountId): array
    {
        return $this->cp()->fetchAllAssociative(
            'SELECT t.id, t.name, t.status, t.plan, t.created_at, d.domain AS primary_domain, at.role
             FROM account_tenants at
             INNER JOIN tenants t ON t.id = at.tenant_id
             LEFT JOIN tenant_domains d ON d.tenant_id = t.id AND d.is_primary = 1
             WHERE at.account_id = ? AND t.status != ?
             ORDER BY at.id DESC',
            [$accountId, 'deleted'],
            [ParameterType::INTEGER, ParameterType::STRING]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAccountProvisions(int $accountId, int $limit = 20): array
    {
        return $this->cp()->fetchAllAssociative(
            'SELECT p.id, p.tenant_id, p.tenant_name, p.domain, p.plan, p.status, p.error_message,
                    p.created_at, p.started_at, p.completed_at
             FROM tenant_provisions p
             WHERE p.account_id = ?
             ORDER BY p.id DESC
             LIMIT ?',
            [$accountId, $limit],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );
    }

    private function sendVerificationCode(string $email, string $code): void
    {
        $message = new TemplatedEmail();
        $message
            ->to($email)
            ->subject('Codigo de verificacion - eFact')
            ->htmlTemplate('onboarding/emails/verification_code.html.twig')
            ->context([
                'code' => $code,
                'ttl_minutes' => max(1, intdiv($this->getVerificationTtl(), 60)),
            ]);

        try {
            $this->mailer->send($message);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send onboarding verification email', [
                'email' => $email,
                'exception' => $e,
            ]);

            throw new InvalidArgumentException('No se pudo enviar el email de verificacion. Intenta nuevamente.');
        }
    }

    private function cp(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('control_plane');

        return $connection;
    }

    private function getVerificationTtl(): int
    {
        $raw = $_ENV['ONBOARDING_VERIFICATION_TTL']
            ?? $_SERVER['ONBOARDING_VERIFICATION_TTL']
            ?? getenv('ONBOARDING_VERIFICATION_TTL')
            ?: '900';

        $ttl = (int) $raw;

        return $ttl > 0 ? $ttl : 900;
    }
}
