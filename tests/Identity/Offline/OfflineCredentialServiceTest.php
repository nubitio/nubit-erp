<?php

declare(strict_types=1);

namespace App\Tests\Identity\Offline;

use App\Identity\Entity\OfflineCredential;
use App\Identity\Entity\User;
use App\Identity\Offline\InvalidOfflineSignature;
use App\Identity\Offline\OfflineCredentialService;
use App\Identity\Offline\OfflineSignature;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class OfflineCredentialServiceTest extends TestCase
{
    private const string MASTER = 'test-master-key';
    private const string KEY = '11111111-2222-3333-4444-555555555555';
    private const int ISSUED = 1_799_900_000;
    private const int EXPIRES = 1_800_000_000;

    private OfflineCredential $credential;
    private ?User $user;

    protected function setUp(): void
    {
        $this->credential = new OfflineCredential('cred-1', 7, self::ISSUED, self::EXPIRES);
        $this->user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($this->user, 7);
    }

    private function service(?OfflineCredential $credential = null): OfflineCredentialService
    {
        $credential ??= $this->credential;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(fn(string $class, mixed $id): ?object => match ($class) {
            OfflineCredential::class => $id === $credential->getId() ? $credential : null,
            User::class => $this->user,
            default => null,
        });

        return new OfflineCredentialService($em, self::MASTER);
    }

    private function sign(
        string $body,
        int $signedAt,
        string $credentialId = 'cred-1',
        ?string $secretOf = null,
    ): string {
        $secret = OfflineSignature::secret(self::MASTER, $secretOf ?? $credentialId, 7, self::EXPIRES);

        return OfflineSignature::sign($secret, OfflineSignature::canonical(
            $credentialId,
            self::KEY,
            $signedAt,
            OfflineSignature::bodyHash($body),
        ));
    }

    private function verify(OfflineCredentialService $service, string $body, int $signedAt, string $signature): User
    {
        return $service->verify('cred-1', self::KEY, $signedAt, OfflineSignature::bodyHash($body), $signature);
    }

    private function assertRefused(callable $attempt, string $reason): void
    {
        try {
            $attempt();
        } catch (InvalidOfflineSignature $e) {
            self::assertStringContainsString($reason, $e->getMessage());

            return;
        }
        self::fail('Expected the signature to be refused: ' . $reason);
    }

    public function testAValidSignatureYieldsTheUserWhoSignedIt(): void
    {
        $at = self::ISSUED + 600;

        $user = $this->verify($this->service(), '{"a":1}', $at, $this->sign('{"a":1}', $at));

        self::assertSame(7, $user->getId());
    }

    public function testACarriedSaleWithAlteredContentIsRefused(): void
    {
        $at = self::ISSUED + 600;
        $signature = $this->sign('{"unitPrice":"9.80"}', $at);

        $this->assertRefused(
            fn() => $this->verify($this->service(), '{"unitPrice":"0.01"}', $at, $signature),
            'no corresponde a su contenido',
        );
    }

    public function testASignatureMadeWithAnotherKeyIsRefused(): void
    {
        $at = self::ISSUED + 600;

        $this->assertRefused(
            fn() => $this->verify($this->service(), '{}', $at, $this->sign('{}', $at, 'cred-1', 'someone-elses')),
            'no corresponde a su contenido',
        );
    }

    public function testAnUnknownCredentialIsRefused(): void
    {
        $at = self::ISSUED + 600;

        $this->assertRefused(
            fn() => $this->service()->verify(
                'ghost',
                self::KEY,
                $at,
                OfflineSignature::bodyHash('{}'),
                $this->sign('{}', $at, 'ghost'),
            ),
            'no existe',
        );
    }

    public function testASaleSignedAfterTheCredentialExpiredIsRefused(): void
    {
        $at = self::EXPIRES + 1;

        $this->assertRefused(
            fn() => $this->verify($this->service(), '{}', $at, $this->sign('{}', $at)),
            'fuera de la vigencia',
        );
    }

    public function testASaleSignedLongBeforeTheCredentialExistedIsRefusedButClockSkewIsTolerated(): void
    {
        $early = self::ISSUED - OfflineCredentialService::CLOCK_SKEW_SECONDS - 1;
        $this->assertRefused(
            fn() => $this->verify($this->service(), '{}', $early, $this->sign('{}', $early)),
            'fuera de la vigencia',
        );

        $withinSkew = self::ISSUED - OfflineCredentialService::CLOCK_SKEW_SECONDS;
        self::assertSame(
            7,
            $this->verify($this->service(), '{}', $withinSkew, $this->sign('{}', $withinSkew))->getId(),
        );
    }

    public function testASaleSignedBeforeTheRevocationStillCountsAndOneSignedAfterDoesNot(): void
    {
        $this->credential->revoke(self::ISSUED + 1000);

        $before = self::ISSUED + 900;
        self::assertSame(7, $this->verify($this->service(), '{}', $before, $this->sign('{}', $before))->getId());

        $after = self::ISSUED + 1001;
        $this->assertRefused(
            fn() => $this->verify($this->service(), '{}', $after, $this->sign('{}', $after)),
            'revocada',
        );
    }

    public function testRevokingTwiceKeepsTheFirstMoment(): void
    {
        $this->credential->revoke(100);
        $this->credential->revoke(200);

        self::assertSame(100, $this->credential->getRevokedAt());
    }

    public function testAUserThatNoLongerExistsIsRefused(): void
    {
        $at = self::ISSUED + 600;
        $this->user = null;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(fn(string $class): ?object => $class === OfflineCredential::class
            ? $this->credential
            : null);

        $this->assertRefused(
            fn() => $this->verify(new OfflineCredentialService($em, self::MASTER), '{}', $at, $this->sign('{}', $at)),
            'ya no existe',
        );
    }
}
