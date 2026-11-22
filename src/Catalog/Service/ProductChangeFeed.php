<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Product;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * "What changed since I last asked" for the catalogue, so a till can stay current
 * without downloading every product each time.
 *
 * The cursor is the database's own clock (the same one that stamps `producto.updated_at`),
 * as an opaque string. It is taken BEFORE the changes are read, and the window starts a
 * few seconds earlier than the cursor, so a write that was still in flight when the
 * previous answer was given is returned again rather than lost. Rows returned twice are
 * harmless: the client replaces by id.
 *
 * A product that is deleted for good leaves no trace here. Clients reconcile with a full
 * download now and then.
 */
final readonly class ProductChangeFeed
{
    public const int OVERLAP_SECONDS = 10;
    private const string FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
    ) {}

    /** The database's current time, as a cursor. */
    public function cursor(): string
    {
        return (string) $this->connection->fetchOne('SELECT DATE_FORMAT(NOW(), \'%Y-%m-%d %H:%i:%s\')');
    }

    /** Whether $cursor is something {@see cursor()} could have returned. */
    public static function isValidCursor(string $cursor): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!' . self::FORMAT, $cursor);

        return $parsed !== false && $parsed->format(self::FORMAT) === $cursor;
    }

    /** First instant to look at for a client that last synced at $cursor. */
    public static function windowStart(string $cursor): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!' . self::FORMAT, $cursor);
        if ($parsed === false) {
            throw new \InvalidArgumentException('Invalid cursor.');
        }

        return $parsed->modify('-' . self::OVERLAP_SECONDS . ' seconds');
    }

    /** @return list<Product> */
    public function changedSince(string $cursor): array
    {
        /** @var list<Product> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.updatedAt >= :from')
            ->setParameter('from', self::windowStart($cursor))
            ->orderBy('p.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
