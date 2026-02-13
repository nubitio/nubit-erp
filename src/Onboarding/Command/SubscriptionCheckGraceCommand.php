<?php

declare(strict_types=1);

namespace App\Onboarding\Command;

use App\Onboarding\Payment\SubscriptionManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:subscription:check-grace',
    description: 'Suspend tenants whose subscription grace period has expired',
)]
final class SubscriptionCheckGraceCommand extends Command
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $expired = $this->subscriptionManager->findExpiredGracePeriod();

        if (\count($expired) === 0) {
            $io->success('No subscriptions with expired grace period.');

            return Command::SUCCESS;
        }

        $suspended = 0;
        $conn = $this->cp();

        foreach ($expired as $sub) {
            $subscriptionId = (int) $sub['id'];
            $tenantId = (int) ($sub['tenant_id'] ?? 0);
            $tenantName = (string) ($sub['tenant_name'] ?? 'unknown');

            try {
                $this->subscriptionManager->suspend($subscriptionId);

                if ($tenantId > 0) {
                    $conn->executeStatement(
                        "UPDATE tenants
                         SET status = 'suspended', updated_at = NOW()
                         WHERE id = ? AND status = 'active'",
                        [$tenantId],
                        [ParameterType::INTEGER],
                    );
                }

                $this->logger->warning('Suspended tenant due to expired grace period', [
                    'subscription_id' => $subscriptionId,
                    'tenant_id' => $tenantId,
                    'tenant_name' => $tenantName,
                ]);

                $io->writeln(sprintf('Suspended: %s (subscription #%d)', $tenantName, $subscriptionId));
                ++$suspended;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to suspend subscription', [
                    'subscription_id' => $subscriptionId,
                    'exception' => $e,
                ]);

                $io->error(sprintf('Failed to suspend subscription #%d: %s', $subscriptionId, $e->getMessage()));
            }
        }

        $io->success(sprintf('Suspended %d subscription(s).', $suspended));

        return Command::SUCCESS;
    }

    private function cp(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('control_plane');

        return $connection;
    }
}
