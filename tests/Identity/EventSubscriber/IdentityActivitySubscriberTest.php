<?php

declare(strict_types=1);

namespace App\Tests\Identity\EventSubscriber;

use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\EventSubscriber\IdentityActivitySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class IdentityActivitySubscriberTest extends TestCase
{
    public function testRecordsASecondFactorConfirmedByItsOwner(): void
    {
        $recorder = $this->createMock(ActivityRecorderInterface::class);
        $recorder
            ->expects(self::once())
            ->method('record')
            ->with('auth.totp_enabled', 'ana activó la verificación en dos pasos');

        $this->subscriber(
            $recorder,
            new User()
                ->setUsername('ana')
                ->setRole(new Role()),
        )->onResponse($this->event('nubit_admin_totp_confirm', 200));
    }

    public function testRecordsARemovedSecondFactor(): void
    {
        $recorder = $this->createMock(ActivityRecorderInterface::class);
        $recorder
            ->expects(self::once())
            ->method('record')
            ->with('auth.totp_disabled', 'ana desactivó la verificación en dos pasos');

        $this->subscriber(
            $recorder,
            new User()
                ->setUsername('ana')
                ->setRole(new Role()),
        )->onResponse($this->event('nubit_admin_totp_disable', 204));
    }

    public function testAWrongCodeLeavesNoEnabledEntry(): void
    {
        $recorder = $this->createMock(ActivityRecorderInterface::class);
        $recorder->expects(self::never())->method('record');

        $this->subscriber(
            $recorder,
            new User()
                ->setUsername('ana')
                ->setRole(new Role()),
        )->onResponse($this->event('nubit_admin_totp_confirm', 400));
    }

    public function testIgnoresOtherRoutesSubrequestsAndAnonymousCallers(): void
    {
        $recorder = $this->createMock(ActivityRecorderInterface::class);
        $recorder->expects(self::never())->method('record');
        $user = new User()
            ->setUsername('ana')
            ->setRole(new Role());

        $this->subscriber($recorder, $user)->onResponse($this->event('api_users_get_collection', 200));
        $this->subscriber($recorder, $user)->onResponse($this->event(
            'nubit_admin_totp_confirm',
            200,
            HttpKernelInterface::SUB_REQUEST,
        ));
        $this->subscriber($recorder, null)->onResponse($this->event('nubit_admin_totp_confirm', 200));
    }

    private function subscriber(ActivityRecorderInterface $recorder, ?User $user): IdentityActivitySubscriber
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new IdentityActivitySubscriber($recorder, $security);
    }

    private function event(string $route, int $status, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            $type,
            new Response('', $status),
        );
    }
}
