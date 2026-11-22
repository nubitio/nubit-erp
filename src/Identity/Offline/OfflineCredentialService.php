<?php

declare(strict_types=1);

namespace App\Identity\Offline;

use App\Identity\Entity\OfflineCredential;
use App\Identity\Entity\User;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Issues and checks the keys a till signs offline sales with.
 *
 * A credential lives {@see TTL_SECONDS}; asking again while more than {@see REUSE_IF_REMAINING}
 * remain returns the same one, so a till that reloads often does not pile up rows.
 */
final readonly class OfflineCredentialService
{
    /** How long a till may keep selling offline on one credential. */
    public const int TTL_SECONDS = 72 * 3600;

    /** A credential with more than this left is handed out again instead of issuing a new one. */
    public const int REUSE_IF_REMAINING = 24 * 3600;

    /** Allowed gap between the till's clock and the moment the credential was issued. */
    public const int CLOCK_SKEW_SECONDS = 300;

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.secret%')]
        private string $masterKey,
    ) {}

    public function issueFor(User $user, ?DateTimeImmutable $now = null): IssuedOfflineCredential
    {
        $userId = $user->getId() ?? throw new \LogicException('A credential is issued to a saved user.');
        $nowTs = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp();

        $reusable = $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(OfflineCredential::class, 'c')
            ->where('c.userId = :user')
            ->andWhere('c.revokedAt IS NULL')
            ->andWhere('c.expiresAt > :limit')
            ->setParameter('user', $userId)
            ->setParameter('limit', $nowTs + self::REUSE_IF_REMAINING)
            ->orderBy('c.expiresAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$reusable instanceof OfflineCredential) {
            $reusable = new OfflineCredential(Uuid::v7()->toRfc4122(), $userId, $nowTs, $nowTs + self::TTL_SECONDS);
            $this->entityManager->persist($reusable);
            $this->entityManager->flush();
        }

        return $this->present($reusable);
    }

    /**
     * Checks that a sale was signed by the key it names, within that key's life, and returns
     * who it belongs to.
     *
     * @throws InvalidOfflineSignature
     */
    public function verify(
        string $credentialId,
        string $idempotencyKey,
        int $signedAt,
        string $bodyHash,
        string $signature,
    ): User {
        $credential = $this->entityManager->find(OfflineCredential::class, $credentialId);
        if (!$credential instanceof OfflineCredential) {
            throw new InvalidOfflineSignature('La credencial con la que se firmó la venta no existe.');
        }

        $secret = OfflineSignature::secret(
            $this->masterKey,
            $credential->getId(),
            $credential->getUserId(),
            $credential->getExpiresAt(),
        );
        $expected = OfflineSignature::sign($secret, OfflineSignature::canonical(
            $credentialId,
            $idempotencyKey,
            $signedAt,
            $bodyHash,
        ));
        if (!OfflineSignature::matches($expected, $signature)) {
            throw new InvalidOfflineSignature('La firma de la venta no corresponde a su contenido.');
        }

        if (
            $signedAt < ($credential->getIssuedAt() - self::CLOCK_SKEW_SECONDS)
            || $signedAt > $credential->getExpiresAt()
        ) {
            throw new InvalidOfflineSignature('La venta se firmó fuera de la vigencia de su credencial.');
        }

        $revokedAt = $credential->getRevokedAt();
        if ($revokedAt !== null && $signedAt > $revokedAt) {
            throw new InvalidOfflineSignature('La credencial con la que se firmó la venta ya estaba revocada.');
        }

        $user = $this->entityManager->find(User::class, $credential->getUserId());
        if (!$user instanceof User) {
            throw new InvalidOfflineSignature('El usuario que firmó la venta ya no existe.');
        }

        return $user;
    }

    /** Stops accepting sales signed from now on with any of the user's credentials (those signed earlier still count). */
    public function revokeAllFor(int $userId, ?DateTimeImmutable $now = null): void
    {
        $nowTs = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp();
        /** @var list<OfflineCredential> $credentials */
        $credentials = $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(OfflineCredential::class, 'c')
            ->where('c.userId = :user')
            ->andWhere('c.revokedAt IS NULL')
            ->setParameter('user', $userId)
            ->getQuery()
            ->getResult();

        foreach ($credentials as $credential) {
            $credential->revoke($nowTs);
        }
        $this->entityManager->flush();
    }

    private function present(OfflineCredential $credential): IssuedOfflineCredential
    {
        $secret = OfflineSignature::secret(
            $this->masterKey,
            $credential->getId(),
            $credential->getUserId(),
            $credential->getExpiresAt(),
        );
        $utc = new DateTimeZone('UTC');

        return new IssuedOfflineCredential(
            $credential->getId(),
            $credential->getUserId(),
            base64_encode($secret),
            new DateTimeImmutable('@' . $credential->getIssuedAt())->setTimezone($utc),
            new DateTimeImmutable('@' . $credential->getExpiresAt())->setTimezone($utc),
        );
    }
}
