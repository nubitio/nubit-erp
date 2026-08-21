<?php

declare(strict_types=1);

namespace App\Accounting\Service;

use App\Accounting\Entity\Account;
use App\Accounting\Entity\JournalEntry;
use App\Accounting\Entity\JournalEntryLine;
use App\Accounting\Repository\AccountRepository;
use App\Accounting\Repository\JournalEntryRepository;
use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class JournalPoster
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JournalEntryRepository $journalEntryRepository,
        private AccountRepository $accountRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param list<array{code: string, debit?: string, credit?: string, memo?: string}> $lines
     */
    public function post(
        string $source,
        string $sourceId,
        DateTimeImmutable $date,
        string $description,
        array $lines,
        string $currency = 'PEN',
    ): ?JournalEntry {
        $existing = $this->journalEntryRepository->findBySource($source, $sourceId);
        if ($existing instanceof JournalEntry) {
            return $existing;
        }

        $normalized = $this->normalizeLines($lines);
        if ($normalized === []) {
            return null;
        }

        $debitTotal = BigDecimal::zero();
        $creditTotal = BigDecimal::zero();
        foreach ($normalized as $line) {
            $debitTotal = $debitTotal->plus(Money::money($line['debit']));
            $creditTotal = $creditTotal->plus(Money::money($line['credit']));
        }

        if ($debitTotal->compareTo($creditTotal) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'El asiento no cuadra: débito %s, crédito %s (%s %s).',
                Money::toString($debitTotal),
                Money::toString($creditTotal),
                $source,
                $sourceId,
            ));
        }

        $entry = new JournalEntry()
            ->setId(Uuid::v7()->toRfc4122())
            ->setEntryNo($this->journalEntryRepository->nextEntryNo((int) $date->format('Y')))
            ->setSource($source)
            ->setSourceId($sourceId)
            ->setEntryDate($date)
            ->setDescription($description)
            ->setCurrency($currency);

        $lineNo = 1;
        foreach ($normalized as $line) {
            $account = $this->accountRepository->findPostableByCode($line['code']);
            if (!$account instanceof Account) {
                $this->logger->warning('Accounting skipped a line: unknown account', [
                    'code' => $line['code'],
                    'source' => $source,
                    'source_id' => $sourceId,
                ]);

                throw new InvalidArgumentException(sprintf('No existe la cuenta contable %s.', $line['code']));
            }

            $journalLine = new JournalEntryLine()
                ->setAccount($account)
                ->setLineNo($lineNo)
                ->setDebit($line['debit'])
                ->setCredit($line['credit'])
                ->setMemo($line['memo']);
            $entry->addLine($journalLine);
            ++$lineNo;
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $entry;
    }

    /**
     * @param list<array{code: string, debit?: string, credit?: string, memo?: string}> $lines
     *
     * @return list<array{code: string, debit: string, credit: string, memo: ?string}>
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];
        foreach ($lines as $line) {
            $debit = Money::money($line['debit'] ?? '0');
            $credit = Money::money($line['credit'] ?? '0');
            if ($debit->isZero() && $credit->isZero()) {
                continue;
            }

            $normalized[] = [
                'code' => $line['code'],
                'debit' => Money::toString($debit),
                'credit' => Money::toString($credit),
                'memo' => $line['memo'] ?? null,
            ];
        }

        return $normalized;
    }
}
