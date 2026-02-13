<?php

declare(strict_types=1);

namespace App\Onboarding\Command;

use App\Onboarding\Payment\SubscriptionManager;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;

#[AsCommand(
    name: 'app:subscription:notify-expiring',
    description: 'Notify owners whose subscriptions are about to be suspended',
)]
final class SubscriptionNotifyExpiringCommand extends Command
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Days until grace period expiry', '2');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));

        $expiring = $this->subscriptionManager->findExpiringGracePeriod($days);

        if (\count($expiring) === 0) {
            $io->success('No subscriptions expiring within ' . $days . ' days.');

            return Command::SUCCESS;
        }

        $notified = 0;

        foreach ($expiring as $sub) {
            $email = (string) ($sub['account_email'] ?? '');
            $tenantName = (string) ($sub['tenant_name'] ?? 'tu empresa');

            if ($email === '') {
                continue;
            }

            try {
                $message = new TemplatedEmail();
                $message
                    ->to($email)
                    ->subject('Tu suscripcion de eFact esta por vencer - ' . $tenantName)
                    ->htmlTemplate('onboarding/emails/subscription_expiring.html.twig')
                    ->context([
                        'tenant_name' => $tenantName,
                        'plan' => (string) ($sub['plan'] ?? ''),
                        'days_remaining' => $days,
                    ]);

                $this->mailer->send($message);

                $io->writeln(sprintf('Notified: %s (%s)', $email, $tenantName));
                ++$notified;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send subscription expiring notification', [
                    'email' => $email,
                    'subscription_id' => $sub['id'] ?? null,
                    'exception' => $e,
                ]);
            }
        }

        $io->success(sprintf('Notified %d owner(s).', $notified));

        return Command::SUCCESS;
    }
}
