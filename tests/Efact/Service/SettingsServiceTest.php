<?php

declare(strict_types=1);

namespace App\Tests\Efact\Service;

use App\Efact\Service\SettingsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SettingsServiceTest extends TestCase
{
    private Connection&MockObject $db;
    private SettingsService $service;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Connection::class);
        $this->service = new SettingsService($this->db);
    }

    public function testGetReturnsValueWhenFound(): void
    {
        $this->db->method('fetchOne')->willReturn('true');

        self::assertSame('true', $this->service->get('my_setting'));
    }

    public function testGetReturnsDefaultWhenNotFound(): void
    {
        $this->db->method('fetchOne')->willReturn(false);

        self::assertSame('fallback', $this->service->get('missing', 'fallback'));
    }

    public function testGetReturnsNullDefaultWhenNotFound(): void
    {
        $this->db->method('fetchOne')->willReturn(false);

        self::assertNull($this->service->get('missing'));
    }

    public function testGetPassesKeyToQuery(): void
    {
        $this->db
            ->expects(self::once())
            ->method('fetchOne')
            ->with('SELECT value FROM config WHERE name = ?', ['my_key'])
            ->willReturn('val');

        self::assertSame('val', $this->service->get('my_key'));
    }

    public function testSetExecutesUpsertStatement(): void
    {
        $this->db->expects(self::once())->method('executeStatement')->with(self::stringContains('INSERT INTO config'), [
            'rate_limit',
            'string',
            '100',
            '100',
        ]);

        $this->service->set('rate_limit', 'string', '100');
    }

    public function testGetReturnsIntValue(): void
    {
        $this->db->method('fetchOne')->willReturn(42);

        self::assertSame(42, $this->service->get('count'));
    }

    public function testGetReturnsBoolValue(): void
    {
        $this->db->method('fetchOne')->willReturn(true);

        self::assertTrue($this->service->get('flag'));
    }
}
