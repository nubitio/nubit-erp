<?php

declare(strict_types=1);

namespace App\Tests\Identity\Offline;

use App\Identity\Entity\OfflineCredential;
use App\Identity\Entity\User;
use App\Identity\Offline\InvalidOfflineSignature;
use App\Identity\Offline\OfflineActorResolver;
use App\Identity\Offline\OfflineCredentialService;
use App\Identity\Offline\OfflineSignature;
use App\Identity\Security\PermissionCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;

final class OfflineActorResolverTest extends TestCase
{
    private const string MASTER = 'test-master-key';
    private const string KEY = '11111111-2222-3333-4444-555555555555';
    private const int ISSUED = 1_799_900_000;
    private const int EXPIRES = 1_800_000_000;
    private const string BODY = '{"writeItems":[]}';

    /** @param list<string> $granted */
    private function resolver(array $granted): OfflineActorResolver
    {
        $credential = new OfflineCredential('cred-1', 7, self::ISSUED, self::EXPIRES);
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, 7);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(static fn(string $class): object => $class === OfflineCredential::class
            ? $credential
            : $user);
        $permissions = $this->createStub(PermissionCheckerInterface::class);
        $permissions->method('permissionsFor')->willReturn($granted);

        return new OfflineActorResolver(new OfflineCredentialService($em, self::MASTER), $permissions);
    }

    /** @param array<string, string> $headers */
    private function request(array $headers, string $body = self::BODY): Request
    {
        $request = Request::create('/api/sales', 'POST', content: $body);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    /** @return array<string, string> */
    private function signedHeaders(string $body = self::BODY, int $at = self::ISSUED + 600): array
    {
        $secret = OfflineSignature::secret(self::MASTER, 'cred-1', 7, self::EXPIRES);

        return [
            'X-Offline-Credential' => 'cred-1',
            'X-Offline-Signed-At' => (string) $at,
            'X-Offline-Signature' => OfflineSignature::sign($secret, OfflineSignature::canonical(
                'cred-1',
                self::KEY,
                $at,
                OfflineSignature::bodyHash($body),
            )),
        ];
    }

    public function testASaleWithoutSignatureHeadersHasNoActor(): void
    {
        self::assertNull($this->resolver([])->resolve($this->request([]), self::KEY));
    }

    public function testASignedSaleResolvesToTheSignerWithTheSignersPermissions(): void
    {
        $actor = $this->resolver(['pos.offline_sale', 'sales.create'])->resolve(
            $this->request($this->signedHeaders()),
            self::KEY,
        );

        self::assertNotNull($actor);
        self::assertSame(7, $actor->userId);
        self::assertTrue($actor->can('pos.offline_sale'));
        self::assertFalse($actor->can('pos.price_override'));
    }

    public function testAWildcardGrantCoversEveryPermission(): void
    {
        $actor = $this->resolver(['*'])->resolve($this->request($this->signedHeaders()), self::KEY);

        self::assertNotNull($actor);
        self::assertTrue($actor->can('pos.price_override'));
    }

    public function testTheBodyThatArrivesIsTheOneThatWasSigned(): void
    {
        $this->expectException(InvalidOfflineSignature::class);

        $this->resolver(['*'])->resolve(
            $this->request($this->signedHeaders(), '{"writeItems":[{"unitPrice":"0.01"}]}'),
            self::KEY,
        );
    }

    public function testASignatureCannotBeReusedUnderAnotherIdempotencyKey(): void
    {
        $this->expectException(InvalidOfflineSignature::class);

        $this->resolver(['*'])->resolve($this->request($this->signedHeaders()), '99999999-2222-3333-4444-555555555555');
    }

    public function testAClaimedSignatureWithMissingPartsIsRefusedNotIgnored(): void
    {
        $headers = $this->signedHeaders();
        unset($headers['X-Offline-Signature']);

        $this->expectException(InvalidOfflineSignature::class);
        $this->expectExceptionMessage('faltan datos');

        $this->resolver(['*'])->resolve($this->request($headers), self::KEY);
    }

    public function testASignedAtThatIsNotANumberIsRefused(): void
    {
        $headers = $this->signedHeaders();
        $headers['X-Offline-Signed-At'] = 'ayer';

        $this->expectException(InvalidOfflineSignature::class);

        $this->resolver(['*'])->resolve($this->request($headers), self::KEY);
    }
}
