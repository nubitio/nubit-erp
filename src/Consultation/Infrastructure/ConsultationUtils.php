<?php

namespace App\Consultation\Infrastructure;

use App\Consultation\Domain\Consultation;
use App\Core\Domain\Exception\ServiceException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class ConsultationUtils
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws ServiceException
     * @throws Exception
     */
    public function getQueue(int $physician_id): int
    {
        $date = date('Y-m-d');
        /** @var false|int $queue */
        $queue = $this->connection->fetchOne('
            SELECT
                COUNT(*) + 1 AS queue
            FROM
                consultation
            WHERE
                physician_id = ?
                AND
                `date` = ?
        ', [$physician_id, $date]);

        if (!$queue) {
            throw new ServiceException('Error al obtener numero de cola');
        }

        return $queue;
    }

    /**
     * @throws ServiceException
     * @throws Exception
     */
    public function fillQueueValue(Consultation $consultation): Consultation
    {
        if (!$consultation->physician?->id) {
            throw new ServiceException('No se encontró el id de médico');
        }

        $consultation->queue = $this->getQueue($consultation->physician->id);

        return $consultation;
    }
}
