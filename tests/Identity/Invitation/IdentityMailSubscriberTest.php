<?php

declare(strict_types=1);

namespace App\Tests\Identity\Invitation;

use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Entity\User;
use App\Identity\Invitation\IdentityMailSubscriber;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Nubit\AdminBundle\Identity\Event\PasswordResetRequested;
use Nubit\AdminBundle\Identity\Event\UserInvited;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * ADR-020, D3: the e-mailed secret travels in the URL fragment so it never reaches the server's
 * access log. These tests pin that, because nothing else would notice the token sliding back
 * into the path or the query string.
 */
final class IdentityMailSubscriberTest extends TestCase
{
    private const string SAMPLE_VALUE = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testTheInvitationLinkCarriesTheTokenInTheFragment(): void
    {
        $sent = $this->deliver(static fn(IdentityMailSubscriber $subscriber) => $subscriber->onUserInvited(
            new UserInvited('ana@example.test', self::SAMPLE_VALUE, [], new DateTimeImmutable('2026-12-01')),
        ));

        static::assertCount(1, $sent);
        static::assertStringContainsString(
            'https://demo.example.test/accept-invitation#token=' . self::SAMPLE_VALUE,
            $this->body($sent[0]),
        );
        $this->assertTokenIsOnlyInTheFragment($this->body($sent[0]));
    }

    public function testThePasswordResetLinkCarriesTheTokenInTheFragment(): void
    {
        $sent = $this->deliver(static fn(IdentityMailSubscriber $subscriber) => $subscriber->onPasswordResetRequested(
            new PasswordResetRequested('ana', self::SAMPLE_VALUE, new DateTimeImmutable('2026-12-01 10:30')),
        ), account: $this->user());

        static::assertCount(1, $sent);
        static::assertStringContainsString(
            'https://demo.example.test/reset-password#token=' . self::SAMPLE_VALUE,
            $this->body($sent[0]),
        );
        $this->assertTokenIsOnlyInTheFragment($this->body($sent[0]));
    }

    public function testNothingIsSentForAnUnknownAccount(): void
    {
        $sent = $this->deliver(static fn(IdentityMailSubscriber $subscriber) => $subscriber->onPasswordResetRequested(
            new PasswordResetRequested('ghost', self::SAMPLE_VALUE, new DateTimeImmutable('2026-12-01')),
        ), account: null);

        static::assertSame([], $sent);
    }

    private function assertTokenIsOnlyInTheFragment(string $body): void
    {
        static::assertSame(1, substr_count($body, self::SAMPLE_VALUE), 'the token appears exactly once');
        static::assertStringNotContainsString('?token=', $body);
        static::assertStringNotContainsString('&token=', $body);
        static::assertStringNotContainsString('/#/', $body, 'hash-router URLs are gone');
        static::assertDoesNotMatchRegularExpression(
            '~/[^\s#]*' . self::SAMPLE_VALUE . '~',
            $body,
            'not in a path segment',
        );
    }

    /**
     * @param callable(IdentityMailSubscriber): void $act
     * @return list<Email>
     */
    private function deliver(callable $act, ?User $account = null): array
    {
        $sent = [];

        $mailer = $this->createStub(MailerInterface::class);
        $mailer
            ->method('send')
            ->willReturnCallback(static function (Email $email) use (&$sent): void {
                $sent[] = $email;
            });

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($account);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $requests = new RequestStack();
        $requests->push(Request::create('https://demo.example.test/api/anything'));

        $act(
            new IdentityMailSubscriber(
                $mailer,
                $this->createStub(ActivityRecorderInterface::class),
                $requests,
                $entityManager,
                new NullLogger(),
                'no-reply@example.test',
            ),
        );

        return $sent;
    }

    private function user(): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'username')->setValue($user, 'ana');
        new ReflectionProperty(User::class, 'email')->setValue($user, 'ana@example.test');

        return $user;
    }

    private function body(Email $email): string
    {
        return (string) $email->getTextBody();
    }
}
