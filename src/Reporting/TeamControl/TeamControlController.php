<?php

declare(strict_types=1);

namespace App\Reporting\TeamControl;

use App\Identity\Auth\BranchContextServiceInterface;
use DateTimeImmutable;
use DateTimeZone;
use Nubit\Platform\Exception\ServiceException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Control de caja y equipo": who sold, voided, discounted and closed short, for the owner.
 *
 * Gated by the same permission as the activity record: it is oversight of other people's work.
 */
#[Route('/api/team-control')]
#[IsGranted('perm:activity.view')]
final class TeamControlController extends AbstractController
{
    private const string TIMEZONE = 'America/Lima';
    private const int DEFAULT_DAYS = 7;
    private const int MAX_DAYS = 366;

    public function __construct(
        private readonly TeamControlQuery $query,
        private readonly TeamControlDetailQuery $detail,
        private readonly TeamControlRules $rules,
        private readonly BranchContextServiceInterface $branchContext,
    ) {}

    #[Route('', name: 'team_control_overview', methods: ['GET'])]
    public function overview(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);
        $data = $this->query->compute($this->branch(), $from, $to);

        $members = [];
        $unattributed = ['voidCount' => 0, 'voidTotal' => 0.0, 'creditNoteCount' => 0, 'creditNoteTotal' => 0.0];
        foreach ($this->rules->assess($data['members']) as $member) {
            if ($member['userId'] === null) {
                $unattributed = [
                    'voidCount' => $member['metrics']['voidCount'],
                    'voidTotal' => $member['metrics']['voidTotal'],
                    'creditNoteCount' => $member['metrics']['creditNoteCount'],
                    'creditNoteTotal' => $member['metrics']['creditNoteTotal'],
                ];
                continue;
            }
            $members[] = $member;
        }

        return $this->json([
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'cashTolerance' => TeamControlQuery::CASH_TOLERANCE,
            'team' => [
                'salesCount' => array_sum(array_map(static fn(array $m): int => $m['metrics']['salesCount'], $members)),
                'salesTotal' => round(
                    array_sum(array_map(static fn(array $m): float => $m['metrics']['salesTotal'], $members)),
                    2,
                ),
                'membersToReview' => count(array_filter(
                    $members,
                    static fn(array $m): bool => $m['level'] === 'review',
                )),
            ],
            'members' => $members,
            'unattributed' => $unattributed,
            'cashDifferences' => $data['cashDifferences'],
        ]);
    }

    /** @param int $userId 0 asks for the records that predate author tracking */
    #[Route('/members/{userId}', name: 'team_control_member', requirements: ['userId' => '\d+'], methods: ['GET'])]
    public function member(int $userId, Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return $this->json([
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'events' => $this->detail->detail($this->branch(), $userId === 0 ? null : $userId, $from, $to),
        ]);
    }

    private function branch(): string
    {
        return $this->branchContext->getCurrentBranchId() ?? throw new ServiceException('Selecciona una sucursal.');
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    private function period(Request $request): array
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        $today = new DateTimeImmutable('today', $tz);
        $to = $this->date($request->query->getString('to'), $tz) ?? $today;
        $from = $this->date($request->query->getString('from'), $tz) ?? $to->modify(sprintf(
            '-%d days',
            self::DEFAULT_DAYS - 1,
        ));

        if ($from > $to) {
            throw new ServiceException('La fecha inicial no puede ser posterior a la final.');
        }
        if ($from < $to->modify(sprintf('-%d days', self::MAX_DAYS - 1))) {
            throw new ServiceException('El periodo no puede superar un año.');
        }

        return [$from, $to];
    }

    private function date(string $value, DateTimeZone $tz): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new ServiceException('Fecha inválida. Usa el formato AAAA-MM-DD.');
        }

        return $date;
    }
}
