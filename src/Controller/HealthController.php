<?php

declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Throwable;

class HealthController extends AbstractController
{
    /**
     * Liveness probe — returns 200 if the server process is running.
     * Used by the frontend to detect network connectivity.
     * Does NOT check downstream dependencies (DB, Redis, queues).
     */
    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function __invoke(
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
    ): JsonResponse {
        $checks = [];
        $overall = 'ok';

        // Database
        try {
            $entityManager->getConnection()->executeQuery('SELECT 1');
            $checks['database'] = 'ok';
        } catch (Throwable) {
            $checks['database'] = 'degraded';
            $overall = 'degraded';
        }

        // Redis (via cache adapter ping)
        try {
            // Store and retrieve a value to verify Redis connectivity
            $cache->get('health_check_ping', fn () => true);
            $checks['redis'] = 'ok';
        } catch (Throwable) {
            $checks['redis'] = 'degraded';
            $overall = 'degraded';
        }

        // Worker queue (pending messages in Redis transport)
        try {
            $transportDsn = $_ENV['MESSENGER_TRANSPORT_DSN'] ?? $_SERVER['MESSENGER_TRANSPORT_DSN'] ?? null;
            if ($transportDsn !== null && str_starts_with($transportDsn, 'redis://')) {
                $parsed = parse_url($transportDsn);
                $host = $parsed['host'] ?? 'redis';
                $port = $parsed['port'] ?? 6379;
                $path = ltrim($parsed['path'] ?? '/messages', '/');

                $redis = new \Redis();
                $redis->connect($host, $port, 2.0);
                $pending = $redis->xLen($path);
                $redis->close();

                $checks['worker_queue'] = $pending < 1000 ? 'ok' : 'degraded';
                if ($checks['worker_queue'] === 'degraded') {
                    $overall = 'degraded';
                }
            } else {
                $checks['worker_queue'] = 'ok';
            }
        } catch (Throwable) {
            $checks['worker_queue'] = 'degraded';
            $overall = 'degraded';
        }

        // Disk space on /app/var
        try {
            $freeBytes = disk_free_space('/app/var');
            // Degraded if less than 100MB free
            $checks['disk'] = $freeBytes !== false && $freeBytes > 100 * 1024 * 1024 ? 'ok' : 'degraded';
            if ($checks['disk'] === 'degraded') {
                $overall = 'degraded';
            }
        } catch (Throwable) {
            $checks['disk'] = 'degraded';
            $overall = 'degraded';
        }

        $statusCode = $overall === 'ok' ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return $this->json(
            [
                'status' => $overall,
                'checks' => $checks,
                'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            ],
            $statusCode
        );
    }
}
