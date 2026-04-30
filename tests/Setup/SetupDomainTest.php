<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Setup\Dto\InitialSetupInput;
use App\Setup\Dto\SetupStatusDto;
use App\Setup\InitialSetupService;
use App\Setup\Infrastructure\Mercure\E2eTolerantHub;
use App\Setup\State\SetupProcessor;
use App\Setup\State\SetupStatusProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;

final class SetupDomainTest extends TestCase
{
    // ── InitialSetupInput ─────────────────────────────────────────────────────

    public function testInitialSetupInputHoldsAllFields(): void
    {
        $input = new InitialSetupInput();
        $input->adminUsername = 'admin';
        $input->adminPassword = 'secret123';
        $input->companyRuc = '20123456789';
        $input->companyBusinessName = 'Mi Empresa S.A.C.';
        $input->companyTradeName = 'Mi Empresa';
        $input->companyFiscalAddress = 'Av. Arequipa 1234';
        $input->companyPhone = '01-234-5678';
        $input->branchName = 'Sede Central';
        $input->branchAddress = 'Av. Arequipa 1234';
        $input->branchPhone = '01-234-5678';

        self::assertSame('admin', $input->adminUsername);
        self::assertSame('20123456789', $input->companyRuc);
        self::assertSame('Mi Empresa S.A.C.', $input->companyBusinessName);
        self::assertSame('Mi Empresa', $input->companyTradeName);
        self::assertSame('Sede Central', $input->branchName);
    }

    public function testInitialSetupInputNullableDefaults(): void
    {
        $input = new InitialSetupInput();

        self::assertNull($input->companyTradeName);
        self::assertNull($input->companyPhone);
        self::assertNull($input->branchAddress);
        self::assertNull($input->branchPhone);
    }

    // ── SetupStatusDto ────────────────────────────────────────────────────────

    public function testSetupStatusDtoHoldsFields(): void
    {
        $dto = new SetupStatusDto(
            configured: true,
            userCount: 2,
            companyCount: 1,
            branchCount: 3,
        );

        self::assertTrue($dto->configured);
        self::assertSame(2, $dto->userCount);
        self::assertSame(1, $dto->companyCount);
        self::assertSame(3, $dto->branchCount);
    }

    public function testSetupStatusDtoNotConfigured(): void
    {
        $dto = new SetupStatusDto(false, 0, 0, 0);

        self::assertFalse($dto->configured);
    }

    // ── SetupProcessor ────────────────────────────────────────────────────────

    public function testSetupProcessorDelegatesToService(): void
    {
        $expected = new SetupStatusDto(true, 1, 1, 1);
        $input = new InitialSetupInput();

        $service = $this->createMock(InitialSetupService::class);
        $service->expects(self::once())
            ->method('configure')
            ->with($input)
            ->willReturn($expected);

        $processor = new SetupProcessor($service);
        $result = $processor->process($input, new Post());

        self::assertSame($expected, $result);
    }

    // ── SetupStatusProvider ───────────────────────────────────────────────────

    public function testSetupStatusProviderDelegatesToService(): void
    {
        $expected = new SetupStatusDto(false, 0, 0, 0);

        $service = $this->createMock(InitialSetupService::class);
        $service->expects(self::once())->method('status')->willReturn($expected);

        $provider = new SetupStatusProvider($service);
        $result = $provider->provide(new Get());

        self::assertSame($expected, $result);
    }

    // ── E2eTolerantHub ────────────────────────────────────────────────────────

    private function makeUpdate(): Update
    {
        return new Update('https://example.com/topic', '{"msg":"hello"}');
    }

    public function testPublishDelegatesToInnerOnSuccess(): void
    {
        $inner = $this->createMock(HubInterface::class);
        $inner->expects(self::once())->method('publish')->willReturn('msg-id-1');

        $hub = new E2eTolerantHub($inner, new NullLogger());
        $result = $hub->publish($this->makeUpdate());

        self::assertSame('msg-id-1', $result);
    }

    public function testPublishReturnsEmptyStringAndLogsWhenE2EEnabled(): void
    {
        $_SERVER['E2E_SEED_ENABLED'] = '1';

        $inner = $this->createStub(HubInterface::class);
        $inner->method('publish')->willThrowException(new \RuntimeException('Mercure down'));

        $hub = new E2eTolerantHub($inner, new NullLogger());
        $result = $hub->publish($this->makeUpdate());

        self::assertSame('', $result);

        unset($_SERVER['E2E_SEED_ENABLED']);
    }

    public function testPublishReturnsEmptyStringAndLogsWhenNotE2E(): void
    {
        unset($_SERVER['E2E_SEED_ENABLED']);

        $inner = $this->createStub(HubInterface::class);
        $inner->method('publish')->willThrowException(new \RuntimeException('Mercure down'));

        $hub = new E2eTolerantHub($inner, new NullLogger());
        $result = $hub->publish($this->makeUpdate());

        self::assertSame('', $result);
    }

    public function testGetPublicUrlDelegatesToInner(): void
    {
        $inner = $this->createStub(HubInterface::class);
        $inner->method('getPublicUrl')->willReturn('https://mercure.example.com');

        $hub = new E2eTolerantHub($inner, new NullLogger());
        self::assertSame('https://mercure.example.com', $hub->getPublicUrl());
    }

    public function testGetFactoryDelegatesToInner(): void
    {
        $factory = $this->createStub(TokenFactoryInterface::class);

        $inner = $this->createStub(HubInterface::class);
        $inner->method('getFactory')->willReturn($factory);

        $hub = new E2eTolerantHub($inner, new NullLogger());
        self::assertSame($factory, $hub->getFactory());
    }
}
