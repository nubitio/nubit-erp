<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Security\Auth\RefreshTokenService;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:token:cleanup',
    description: 'Clean up expired and revoked refresh tokens for all tenants',
)]
#[AsCronTask('0 0 * * *')] // every day at midnight
class TokenCleanupCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $count = $this->refreshTokenService->cleanExpiredTokens();

            $io->success(sprintf('Successfully cleaned up %d expired/revoked refresh tokens', $count));

            $this->logger->info('Token cleanup completed', [
                'deleted_count' => $count,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed to clean up tokens: ' . $e->getMessage());

            $this->logger->error('Token cleanup failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
