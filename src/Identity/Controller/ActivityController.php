<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Activity\ActivityFilter;
use App\Identity\Activity\ActivityQuery;
use App\Identity\Activity\ActivityType;
use App\Identity\Activity\CsvCell;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only view of the activity record. There is deliberately no write, edit or delete route.
 * Date filters are calendar days in the business time zone; storage is UTC.
 */
final class ActivityController extends AbstractController
{
    private const string BUSINESS_TIMEZONE = 'America/Lima';
    private const string PERMISSION = 'perm:activity.view';

    public function __construct(
        private readonly ActivityQuery $activity,
    ) {}

    #[Route('/api/activity-events', name: 'api_activity_events', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::PERMISSION);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min($request->query->getInt('limit', 50), ActivityQuery::MAX_PAGE_SIZE));

        return $this->json([
            ...$this->activity->search($this->filter($request), $page, $limit),
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/api/activity-events/summary', name: 'api_activity_events_summary', methods: ['GET'])]
    public function summary(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::PERMISSION);
        $days = max(1, min($request->query->getInt('days', 7), 90));
        $since = new DateTimeImmutable('now', new DateTimeZone('UTC'))->modify(sprintf('-%d days', $days));

        return $this->json(['days' => $days, 'items' => $this->activity->summary($since)]);
    }

    #[Route('/api/activity-events/actors', name: 'api_activity_events_actors', methods: ['GET'])]
    public function actors(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::PERMISSION);
        $since = new DateTimeImmutable('now', new DateTimeZone('UTC'))->modify('-90 days');

        return $this->json(['items' => $this->activity->actors($since)]);
    }

    #[Route('/api/activity-events/export', name: 'api_activity_events_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $this->denyAccessUnlessGranted(self::PERMISSION);
        $rows = $this->activity->forExport($this->filter($request));
        $zone = new DateTimeZone(self::BUSINESS_TIMEZONE);

        $response = new StreamedResponse(function () use ($rows, $zone): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
            fputcsv($out, ['Fecha', 'Usuario', 'Tipo', 'Sensible', 'Resumen', 'Detalle', 'IP'], escape: '\\');
            foreach ($rows as $row) {
                $when = new DateTimeImmutable((string) $row['occurredAt'])
                    ->setTimezone($zone)
                    ->format('Y-m-d H:i:s');
                fputcsv($out, array_map(CsvCell::safe(...), [
                    $when,
                    $row['actor'] ?? '',
                    ActivityType::label((string) $row['type']),
                    $row['sensitive'] ? 'Sí' : 'No',
                    $row['summary'],
                    $row['detail'] ?? '',
                    $row['ip'] ?? '',
                ]), escape: '\\');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="actividad.csv"');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function filter(Request $request): ActivityFilter
    {
        $zone = new DateTimeZone(self::BUSINESS_TIMEZONE);
        $utc = new DateTimeZone('UTC');
        $type = (string) $request->query->get('type', 'sensitive');
        if ($type !== 'sensitive' && $type !== 'all' && !in_array($type, ActivityType::GROUPS, strict: true)) {
            $type = 'sensitive';
        }

        return new ActivityFilter(
            type: $type,
            actor: self::scalar($request->query->get('actor')),
            fromUtc: self::day($request->query->get('from'), $zone, '00:00:00')?->setTimezone($utc),
            toUtc: self::day($request->query->get('to'), $zone, '23:59:59')?->setTimezone($utc),
            search: self::scalar($request->query->get('q')),
        );
    }

    private static function scalar(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function day(mixed $value, DateTimeZone $zone, string $time): ?DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value . ' ' . $time, $zone);

        return $date === false ? null : $date;
    }
}
