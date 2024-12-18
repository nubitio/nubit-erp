<?php

namespace App\Cashbox\Infrastructure;

use App\Branch\Infrastructure\BranchEntity;
use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\CashboxRepositoryInterface;
use App\CashboxDetail\Infrastructure\CashboxDetailEntity;
use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\Symfony\Security\SecurityService;
use App\User\Infrastructure\UserEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class CashboxRepository implements CashboxRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
        private SecurityService $securityService,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    /**
     * @throws ServiceException
     */
    public function getOne(string $id): Cashbox
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $id);

        $this->entityManager->initializeObject($entity);

        if (!$entity) {
            throw new ServiceException('Caja no encontrada');
        }

        return DataMapper::map($entity, Cashbox::class);
    }

    public function insert(Cashbox $cashbox): Cashbox
    {
        $entity = DataMapper::map($cashbox, CashboxEntity::class);

        $entity->local = $this->entityManager->getReference(BranchEntity::class, $cashbox->local?->local_id);
        if ($cashbox->usuario) {
            $entity->usuario = $this->entityManager->getReference(UserEntity::class, $cashbox->usuario->usuario_id);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Cashbox::class);
    }

    public function update(Cashbox $cashbox): Cashbox
    {
        /** @var ?CashboxEntity $entity */
        $entity = $this->entityManager->find(CashboxEntity::class, $cashbox->caja_id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($cashbox, $entity);

        $entity->local = $this->entityManager->getReference(BranchEntity::class, $cashbox->local?->local_id);
        if ($cashbox->usuario) {
            $entity->usuario = $this->entityManager->getReference(UserEntity::class, $cashbox->usuario->usuario_id);
        }

        $this->entityManager->flush();

        return DataMapper::map($entity, Cashbox::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function getLast(string $localId): ?Cashbox
    {
        $user = $this->securityService->connectedUser();

        $entity = $this->entityManager->getRepository(CashboxEntity::class)->findOneBy([
            'local' => $localId,
            'usuario' => $user->userId,
        ], ['apertura' => 'DESC']);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Cashbox::class);
    }

    /**
     * @throws Exception
     * @throws ORMException
     */
    public function updateValues(string $localId, int $userId): void
    {
        $cashBox = $this->getLast($localId);
        if ($cashBox) {
            $cashBoxId = $cashBox->caja_id;
            $startDate = strval($cashBox->apertura?->format('Y-m-d H:i:s'));
            $endDate = date('Y-m-d H:i:s');
            $cashBoxEntity = $this->entityManager->getReference(CashboxEntity::class, $cashBoxId);

            $totalPayments = $this->getTotalPayments($startDate, $endDate);
            $totalDisbursements = $this->getTotalDisbursements($startDate, $endDate);

            /** @var ?CashboxDetailEntity $cashBoxPayments */
            $cashBoxPayments = $this->entityManager->getRepository(CashboxDetailEntity::class)->findOneBy([
                'caja_id' => $cashBoxId,
                'descripcion' => $totalPayments['name'],
            ]);

            if (null == $cashBoxPayments) {
                $cashBoxPayments = new CashboxDetailEntity();
                $cashBoxPayments->cashbox = $cashBoxEntity;
                $cashBoxPayments->uid = $this->getNextDetailIndex($cashBoxId);
                $cashBoxPayments->tipo_id = 1;
                $cashBoxPayments->descripcion = strval($totalPayments['name']);
            }
            $cashBoxPayments->monto = floatval($totalPayments['total']);

            $this->entityManager->persist($cashBoxPayments);

            $cashBoxDisbursements = $this->entityManager->getRepository(CashboxDetailEntity::class)->findOneBy([
                'caja_id' => $cashBoxId,
                'descripcion' => $totalDisbursements['name'],
            ]);

            if (null == $cashBoxDisbursements) {
                $cashBoxDisbursements = new CashboxDetailEntity();
                $cashBoxDisbursements->cashbox = $cashBoxEntity;
                $cashBoxDisbursements->uid = $this->getNextDetailIndex($cashBoxId);
                $cashBoxDisbursements->tipo_id = 2;
                $cashBoxDisbursements->descripcion = strval($totalDisbursements['name']);
            }
            $cashBoxDisbursements->monto = floatval($totalDisbursements['total']);

            $this->entityManager->persist($cashBoxDisbursements);

            // $cashBoxEntity->balance = $this->getTotalBalance($cashBoxId);
            $this->entityManager->persist($cashBoxEntity);

            $this->entityManager->flush();
        }
    }

    private function getNextDetailIndex(string $cashBoxId): int
    {
        /** @var false|int $nextIndex */
        $nextIndex = $this->connection->fetchOne(
            'SELECT IFNULL(MAX(uid), 0) + 1 FROM caja_detalle WHERE caja_id = ?',
            [$cashBoxId]
        );

        return $nextIndex;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function getTotalPayments(string $startDate, string $endDate): array
    {
        $sql = '
            SELECT
                "Cobranza" AS name,
                SUM( monto ) AS total
            FROM
                pago
            WHERE
                deleted = 0
                AND created_at BETWEEN ? AND ?;
        ';

        /** @var array<string, mixed> $data */
        $data = $this->connection->fetchAssociative($sql, [$startDate, $endDate]);

        return $data;
    }

    private function getTotalDisbursements(string $startDate, string $endDate): array
    {
        $sql = '
            SELECT
                "Desembolso" AS name,
                SUM( monto ) AS total
            FROM
                prestamo
            WHERE
                deleted = 0
                AND created_at BETWEEN ? AND ?;
        ';

        /** @var array<int, array<string, bool|float|int|string|null>> $data */
        $data = $this->connection->fetchAssociative($sql, [$startDate, $endDate]);

        return $data;
    }

    /**
     * @throws Exception
     */
    public function getTotalBalance(string $cashboxId): float
    {
        $sql = '
            SELECT
                SUM(IF(cd.tipo_id = 1, cd.monto, cd.monto*-1)) AS total
            FROM
                caja_detalle cd
            INNER JOIN caja c ON cd.caja_id = c.caja_id
            WHERE cd.caja_id = ?
        ';

        return (float) $this->connection->fetchOne($sql, [$cashboxId]);
    }
}
