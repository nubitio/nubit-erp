<?php

namespace App\Service;

use DateTimeImmutable;
use IntlDateFormatter;
use Throwable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class TimeDimensionSeeder
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function seed(
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        int $batchSize = 500,
        string $locale = 'es_PE',
        ?callable $progressCallback = null,
    ): int {
        $batchSize = max(1, $batchSize);

        $dayFormatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $dayFormatter->setPattern('EEEE');

        $monthFormatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $monthFormatter->setPattern('LLLL');

        $current = $startDate;
        $batch = [];
        $count = 0;

        $this->connection->beginTransaction();

        try {
            while ($current < $endDate) {
                $id = (int) $current->format('Ymd');
                $batch[] = [
                    'id' => $id,
                    'date' => $current->format('Y-m-d'),
                    'year' => (int) $current->format('Y'),
                    'month' => (int) $current->format('m'),
                    'day' => (int) $current->format('d'),
                    'quarter' => (int) $current->format('Q'),
                    'week' => (int) $current->format('W'),
                    'dayName' => $dayFormatter->format($current) ?: '',
                    'monthName' => $monthFormatter->format($current) ?: '',
                    'holiday' => false,
                    'weekend' => in_array((int) $current->format('N'), [6, 7], true),
                    'event' => null,
                ];

                if (count($batch) === $batchSize) {
                    $this->insertBatch($batch);
                    $count += count($batch);

                    if ($progressCallback !== null) {
                        $progressCallback(count($batch));
                    }

                    $batch = [];
                }

                $current = $current->modify('+1 day');
            }

            if ($batch !== []) {
                $this->insertBatch($batch);
                $count += count($batch);

                if ($progressCallback !== null) {
                    $progressCallback(count($batch));
                }
            }

            $this->connection->commit();

            return $count;
        } catch (Throwable $throwable) {
            $this->connection->rollBack();
            throw $throwable;
        }
    }

    /**
     * @param array<int, array{
     *     id:int,
     *     date:string,
     *     year:int,
     *     month:int,
     *     day:int,
     *     quarter:int,
     *     week:int,
     *     dayName:string,
     *     monthName:string,
     *     holiday:bool,
     *     weekend:bool,
     *     event: string|null
     * }> $rows
     *
     * @throws Exception
     */
    private function insertBatch(array $rows): void
    {
        $placeholders = [];
        $params = [];

        foreach ($rows as $row) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $params[] = $row['id'];
            $params[] = $row['date'];
            $params[] = $row['year'];
            $params[] = $row['month'];
            $params[] = $row['day'];
            $params[] = $row['quarter'];
            $params[] = $row['week'];
            $params[] = $row['dayName'];
            $params[] = $row['monthName'];
            $params[] = $row['holiday'] ? 1 : 0;
            $params[] = $row['weekend'] ? 1 : 0;
            $params[] = $row['event'];
        }

        $sql = sprintf(
            'INSERT INTO time_dimension (
            id, date, year, month, day, quarter, week, day_name, month_name, holiday, weekend, event
            ) VALUES %s
             ON DUPLICATE KEY UPDATE
               date = VALUES(date),
               year = VALUES(year),
               month = VALUES(month),
               day = VALUES(day),
               quarter = VALUES(quarter),
               week = VALUES(week),
               day_name = VALUES(day_name),
               month_name = VALUES(month_name),
               holiday = VALUES(holiday),
               weekend = VALUES(weekend),
               event = VALUES(event)',
            implode(',', $placeholders)
        );

        $this->connection->executeStatement($sql, $params);
    }
}
