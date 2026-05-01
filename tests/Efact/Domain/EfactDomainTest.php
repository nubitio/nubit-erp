<?php

declare(strict_types=1);

namespace App\Tests\Efact\Domain;

use App\Efact\Audit\AuditLogMessage;
use App\Efact\Domain\AuditableEntity;
use App\Efact\RateLimit\RateLimitResult;
use App\Efact\Traits\SoftDeletableTrait;
use App\Efact\Traits\TimestampableTrait;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Efact domain value objects, traits, and the AuditableEntity base class.
 */
final class EfactDomainTest extends TestCase
{
    // ── AuditLogMessage ───────────────────────────────────────────────────────

    public function testAuditLogMessageHoldsAllFields(): void
    {
        $msg = new AuditLogMessage(
            action: 'user.login',
            metadata: '{"ip":"127.0.0.1"}',
            tenantId: 7,
            actor: 'juan',
            channel: 'http',
            requestId: 'req-abc',
        );

        self::assertSame('user.login', $msg->action);
        self::assertSame('{"ip":"127.0.0.1"}', $msg->metadata);
        self::assertSame(7, $msg->tenantId);
        self::assertSame('juan', $msg->actor);
        self::assertSame('http', $msg->channel);
        self::assertSame('req-abc', $msg->requestId);
    }

    public function testAuditLogMessageNullableFields(): void
    {
        $msg = new AuditLogMessage('action', 'meta', null, 'system', null, null);

        self::assertNull($msg->tenantId);
        self::assertNull($msg->channel);
        self::assertNull($msg->requestId);
    }

    // ── RateLimitResult ───────────────────────────────────────────────────────

    public function testRateLimitResultAllowed(): void
    {
        $r = new RateLimitResult(allowed: true, limit: 100, remaining: 95, retryAfter: 0);

        self::assertTrue($r->allowed);
        self::assertSame(100, $r->limit);
        self::assertSame(95, $r->remaining);
        self::assertSame(0, $r->retryAfter);
    }

    public function testRateLimitResultBlocked(): void
    {
        $r = new RateLimitResult(allowed: false, limit: 60, remaining: 0, retryAfter: 30);

        self::assertFalse($r->allowed);
        self::assertSame(0, $r->remaining);
        self::assertSame(30, $r->retryAfter);
    }

    // ── TimestampableTrait ────────────────────────────────────────────────────

    public function testTimestampableTraitSettersAndGetters(): void
    {
        $entity = new class() {
            use TimestampableTrait;
        };

        $createdAt = new DateTime('2026-01-01 10:00:00');
        $updatedAt = new DateTime('2026-04-30 12:00:00');

        $entity->setCreatedAt($createdAt);
        $entity->setUpdatedAt($updatedAt);

        self::assertSame($createdAt, $entity->getCreatedAt());
        self::assertSame($updatedAt, $entity->getUpdatedAt());
    }

    public function testTimestampableUpdatedAtDefaultsToNull(): void
    {
        $entity = new class() {
            use TimestampableTrait;
        };

        self::assertNull($entity->getUpdatedAt());
    }

    public function testTimestampableUpdateTimestampsOnPersistSetsCreatedAt(): void
    {
        $entity = new class() {
            use TimestampableTrait;
        };

        $before = new DateTime();
        $entity->updateTimestampsOnPersist();
        $after = new DateTime();

        self::assertGreaterThanOrEqual($before, $entity->getCreatedAt());
        self::assertLessThanOrEqual($after, $entity->getCreatedAt());
    }

    public function testTimestampableUpdateTimestampsOnUpdateSetsUpdatedAt(): void
    {
        $entity = new class() {
            use TimestampableTrait;
        };

        $before = new DateTime();
        $entity->updateTimestampsOnUpdate();
        $after = new DateTime();

        self::assertNotNull($entity->getUpdatedAt());
        self::assertGreaterThanOrEqual($before, $entity->getUpdatedAt());
        self::assertLessThanOrEqual($after, $entity->getUpdatedAt());
    }

    // ── SoftDeletableTrait ────────────────────────────────────────────────────

    public function testSoftDeletableNotDeletedByDefault(): void
    {
        $entity = new class() {
            use SoftDeletableTrait;
        };

        self::assertFalse($entity->isDeleted());
        self::assertNull($entity->getDeletedAt());
    }

    public function testSoftDeletableSoftDeleteSetsDeletedAt(): void
    {
        $entity = new class() {
            use SoftDeletableTrait;
        };

        $before = new DateTime();
        $result = $entity->softDelete();
        $after = new DateTime();

        self::assertSame($entity, $result); // fluent
        self::assertTrue($entity->isDeleted());
        self::assertNotNull($entity->getDeletedAt());
        self::assertGreaterThanOrEqual($before, $entity->getDeletedAt());
        self::assertLessThanOrEqual($after, $entity->getDeletedAt());
    }

    public function testSoftDeletableRestoreClearsDeletedAt(): void
    {
        $entity = new class() {
            use SoftDeletableTrait;
        };

        $entity->softDelete();
        self::assertTrue($entity->isDeleted());

        $result = $entity->restore();

        self::assertSame($entity, $result); // fluent
        self::assertFalse($entity->isDeleted());
        self::assertNull($entity->getDeletedAt());
    }

    public function testSoftDeletableSetDeletedAt(): void
    {
        $entity = new class() {
            use SoftDeletableTrait;
        };

        $date = new DateTime('2026-01-01');
        $entity->setDeletedAt($date);

        self::assertSame($date, $entity->getDeletedAt());
        self::assertTrue($entity->isDeleted());
    }

    // ── AuditableEntity ───────────────────────────────────────────────────────

    public function testAuditableEntityDefaultValues(): void
    {
        $entity = new class() extends AuditableEntity {};

        self::assertTrue($entity->isActive());
        self::assertNull($entity->getCreatedBy());
        self::assertNull($entity->getUpdatedBy());
    }

    public function testAuditableEntitySettersAndGetters(): void
    {
        $entity = new class() extends AuditableEntity {};

        $entity->setCreatedBy(1);
        $entity->setUpdatedBy(2);
        $entity->setActive(false);

        self::assertSame(1, $entity->getCreatedBy());
        self::assertSame(2, $entity->getUpdatedBy());
        self::assertFalse($entity->isActive());
    }

    public function testAuditableEntityActivateDeactivate(): void
    {
        $entity = new class() extends AuditableEntity {};

        $r1 = $entity->deactivate();
        self::assertFalse($entity->isActive());
        self::assertSame($entity, $r1); // fluent

        $r2 = $entity->activate();
        self::assertTrue($entity->isActive());
        self::assertSame($entity, $r2); // fluent
    }
}
