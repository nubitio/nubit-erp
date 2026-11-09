<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use Doctrine\DBAL\Connection;
use Nubit\AdminBundle\Identity\TotpManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Which users have a confirmed second factor (an unconfirmed enrolment does not count). */
final class UserSecurityController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        // Only a service while nubit_admin.identity is enabled: without it the table may not exist.
        private readonly ?TotpManager $totp = null,
    ) {}

    #[Route('/api/user-security', name: 'api_user_security', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $this->denyAccessUnlessGranted('perm:users.view');
        if ($this->totp === null) {
            return $this->json(['enabled' => false, 'withSecondFactor' => []]);
        }

        return $this->json([
            'enabled' => true,
            'withSecondFactor' => array_map(
                strval(...),
                $this->connection->fetchFirstColumn(
                    'SELECT user_identifier FROM nubit_totp_credential WHERE confirmed_at IS NOT NULL ORDER BY user_identifier',
                ),
            ),
        ]);
    }
}
