<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Command;

use App\Billing\Recurring\Entity\CustomerSubscription;
use App\Billing\Recurring\Service\SubscriptionChargeGenerator;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates a SaleRecord (the "charge" for the period) for every active
 * CustomerSubscription whose nextBillingDate has arrived, then advances the
 * subscription to its next period.
 *
 * The generated sale has no SalePayment rows, so balanceDue equals grandTotal
 * — this is what makes it a "cuenta por cobrar" without a separate entity.
 *
 * The SUNAT voucher is NOT auto-issued here: InvoiceCreationService (shared
 * with the manual POS flow) marks a sale's paid_total = grandTotal and
 * balanceDue = 0 as soon as a voucher is created for it, because in this
 * system "invoiced" has always meant "collected". Auto-invoicing at
 * generation time would zero out the very receivable this command exists to
 * create. Staff issue the voucher manually (same "Emitir comprobante" action
 * as any other credit sale) once payment is actually collected.
 *
 * sellerUserId is required by the sales schema (POS legacy constraint) even
 * though no human cashier is involved — the first ROLE_SUPER_ADMIN/ROLE_ADMIN
 * user is used as the system "seller" of record.
 *
 * Schedule: daily via Symfony Scheduler (see Schedule.php).
 *
 * Usage:
 *   php bin/console app:billing:generate-recurring-sales              # all tenants
 *   php bin/console app:billing:generate-recurring-sales --tenant=foo # one tenant
 */
#[AsCommand(
    name: 'app:billing:generate-recurring-sales',
    description: 'Generate pending sales for subscriptions whose billing period is due',
)]
final class GenerateRecurringSalesCommand extends PerTenantCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly SubscriptionChargeGenerator $chargeGenerator,
    ) {
        parent::__construct();
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = (string) ($this->currentTenantName ?? 'default');

        try {
            $today = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));

            /** @var list<CustomerSubscription> $dueSubscriptions */
            $dueSubscriptions = $this->entityManager
                ->getRepository(CustomerSubscription::class)
                ->createQueryBuilder('cs')
                ->andWhere('cs.status = :status')
                ->andWhere('cs.nextBillingDate <= :today')
                ->setParameter('status', 'active')
                ->setParameter('today', $today)
                ->getQuery()
                ->getResult();

            if ($dueSubscriptions === []) {
                $io->text(sprintf('[%s] No subscriptions due.', $tenantName));

                return self::SUCCESS;
            }

            $systemSellerId = $this->resolveSystemSellerId();
            if ($systemSellerId === null) {
                $io->error(sprintf(
                    '[%s] No ROLE_ADMIN/ROLE_SUPER_ADMIN user found — cannot attribute recurring sales.',
                    $tenantName,
                ));

                return self::FAILURE;
            }

            $generated = 0;

            foreach ($dueSubscriptions as $subscription) {
                $endDate = $subscription->getEndDate();
                if ($endDate !== null && $subscription->getNextBillingDate() > $endDate) {
                    $subscription->setStatus('cancelled');
                    $this->entityManager->flush();

                    continue;
                }

                $this->chargeGenerator->generate($subscription, $today, $systemSellerId, 'scheduler');
                ++$generated;
            }

            $io->success(sprintf('[%s] %d recurring sale(s) generated.', $tenantName, $generated));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('GenerateRecurringSalesCommand: failed for tenant', [
                'tenant' => $tenantName,
                'exception' => $e->getMessage(),
            ]);
            $io->error(sprintf('[ERROR] Failed for %s: %s', $tenantName, $e->getMessage()));

            return self::FAILURE;
        }
    }

    /**
     * Returns the first ROLE_SUPER_ADMIN (rol_id=1) or ROLE_ADMIN (rol_id=2)
     * user, used to attribute system-generated recurring sales.
     */
    private function resolveSystemSellerId(): ?int
    {
        $userId = $this->connection->fetchOne(
            'SELECT usuario_id FROM usuario WHERE rol_id IN (1, 2) ORDER BY usuario_id ASC LIMIT 1',
        );

        return is_numeric($userId) ? (int) $userId : null;
    }
}
