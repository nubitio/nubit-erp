<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fill-time-dimension',
    description: 'Rellena la tabla time_dimension (date dimension) entre dos fechas.',
)]
class FillTimeDimensionCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('start', InputArgument::OPTIONAL, 'Fecha de inicio (Y-m-d)', '2010-01-01')
            ->addArgument('end', InputArgument::OPTIONAL, 'Fecha fin (exclusiva, Y-m-d)', null)
            ->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Locale para nombres de día/mes', 'es_PE');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $startInput = (string) $input->getArgument('start');
        $endInput = $input->getArgument('end');
        $locale = (string) $input->getOption('locale');

        $startDate = new \DateTimeImmutable($startInput);
        $endDate = $endInput ? new \DateTimeImmutable((string) $endInput) : (new \DateTimeImmutable())->modify('+1 day');

        if ($startDate >= $endDate) {
            $io->error('La fecha de inicio debe ser menor a la fecha fin.');

            return Command::FAILURE;
        }

        $formatterDay = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);
        $formatterDay->setPattern('EEEE');
        $formatterMonth = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);
        $formatterMonth->setPattern('LLLL');

        $stmt = $this->connection->prepare(
            'INSERT INTO time_dimension 
                (id, date, year, month, day, quarter, week, day_name, month_name, holiday, weekend, event)
             VALUES 
                (:id, :date, :year, :month, :day, :quarter, :week, :dayName, :monthName, :holiday, :weekend, NULL)
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
                weekend = VALUES(weekend)'
        );

        $this->connection->beginTransaction();
        try {
            $count = 0;
            for ($current = $startDate; $current < $endDate; $current = $current->modify('+1 day')) {
                $id = (int) $current->format('Y') * 10000 + (int) $current->format('m') * 100 + (int) $current->format('d');
                $year = (int) $current->format('Y');
                $month = (int) $current->format('m');
                $day = (int) $current->format('d');
                $quarter = (int) $current->format('Q');
                $week = (int) $current->format('W');
                $dayName = $formatterDay->format($current);
                $monthName = $formatterMonth->format($current);
                $isWeekend = in_array((int) $current->format('N'), [6, 7], true);

                $stmt->bindValue('id', $id);
                $stmt->bindValue('date', $current->format('Y-m-d'));
                $stmt->bindValue('year', $year);
                $stmt->bindValue('month', $month);
                $stmt->bindValue('day', $day);
                $stmt->bindValue('quarter', $quarter);
                $stmt->bindValue('week', $week);
                $stmt->bindValue('dayName', $dayName);
                $stmt->bindValue('monthName', $monthName);
                $stmt->bindValue('holiday', false, \PDO::PARAM_BOOL);
                $stmt->bindValue('weekend', $isWeekend, \PDO::PARAM_BOOL);
                $stmt->executeStatement();
                $count++;
            }

            $this->connection->commit();
            $io->success(sprintf('Dimensión de fechas completada. Filas procesadas: %d', $count));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
