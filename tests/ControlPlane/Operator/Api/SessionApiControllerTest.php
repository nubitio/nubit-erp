<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Operator\Api;

use App\ControlPlane\Operator\Api\SessionApiController;
use Symfony\Component\HttpFoundation\Response;

final class SessionApiControllerTest extends OperatorApiTestCase
{
    public function testInvokeReturnsAnonymousSessionWhenNoUser(): void
    {
        $controller = new SessionApiController();
        $this->setControllerContainer($controller);

        $response = $controller();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertNull($payload['user']);
        $this->assertSame([], $payload['roles']);
    }
}