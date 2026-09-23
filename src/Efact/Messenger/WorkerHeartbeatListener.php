<?php

declare(strict_types=1);

namespace App\Efact\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Throwable;

/** Publishes a progress signal for the Messenger event loop. */
final class WorkerHeartbeatListener
{
    private int $lastWriteAt = 0;

    public function __construct(
        #[Autowire('%env(default:nubit_worker_heartbeat_file:NUBIT_WORKER_HEARTBEAT_FILE)%')]
        private readonly string $heartbeatFile,
        private readonly LoggerInterface $logger,
    ) {}

    #[AsEventListener(event: WorkerStartedEvent::class)]
    public function onWorkerStarted(): void
    {
        $this->writeHeartbeat();
    }

    #[AsEventListener(event: WorkerRunningEvent::class)]
    public function onWorkerRunning(): void
    {
        if ((time() - $this->lastWriteAt) >= 5) {
            $this->writeHeartbeat();
        }
    }

    private function writeHeartbeat(): void
    {
        $directory = dirname($this->heartbeatFile);

        try {
            if (
                !is_dir($directory)
                && !mkdir(directory: $directory, permissions: 0o775, recursive: true)
                && !is_dir($directory)
            ) {
                throw new \RuntimeException(sprintf('Unable to create heartbeat directory "%s".', $directory));
            }

            if (!touch($this->heartbeatFile)) {
                throw new \RuntimeException(sprintf('Unable to update heartbeat file "%s".', $this->heartbeatFile));
            }

            $this->lastWriteAt = time();
        } catch (Throwable $exception) {
            $this->logger->error('Messenger worker heartbeat failed', [
                'heartbeat_file' => $this->heartbeatFile,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
