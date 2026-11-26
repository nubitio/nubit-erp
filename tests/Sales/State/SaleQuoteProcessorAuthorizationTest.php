<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Auth\OperationalBranchGuard;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Sales\Entity\SaleQuote;
use App\Sales\Service\SaleQuoteService;
use App\Sales\State\SaleQuoteProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class SaleQuoteProcessorAuthorizationTest extends TestCase
{
    public function testAnonymousWriteIsRejectedBeforePersistence(): void
    {
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::never())->method('process');
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $this->expectException(UnauthorizedHttpException::class);
        $this->processor($persist, $security)->process(new SaleQuote(), new Post());
    }

    public function testCreateDraftKeepsExistingTotalsAndDoesNotRequireSendPermission(): void
    {
        $quote = new SaleQuote();
        $quote->addItem($this->item('10.00', '0.00'));
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::once())->method('process')->willReturnArgument(0);

        $saved = $this->processor($persist, $this->security(canSend: false))->process($quote, new Post());

        self::assertInstanceOf(SaleQuote::class, $saved);
        self::assertSame(SaleQuote::STATUS_DRAFT, $saved->getStatus());
        self::assertSame(9, $saved->getCreatedBy());
        self::assertSame(9, $saved->getSellerUserId());
        self::assertSame('10.00', $saved->getGrandTotal());
        self::assertSame('branch-a', $saved->getBranch()?->getId());
    }

    public function testCreateSentRequiresSendPermissionAndDoesNotPersistWithoutIt(): void
    {
        $quote = new SaleQuote()->setStatus(SaleQuote::STATUS_SENT);
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::never())->method('process');

        $this->expectException(AccessDeniedHttpException::class);
        try {
            $this->processor($persist, $this->security(canSend: false))->process($quote, new Post());
        } finally {
            self::assertSame(SaleQuote::STATUS_DRAFT, $quote->getStatus());
        }
    }

    public function testCreateSentWithSendPermissionPersistsTheSentStatus(): void
    {
        $quote = new SaleQuote()->setStatus(SaleQuote::STATUS_SENT);
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::once())->method('process')->willReturnArgument(0);

        $saved = $this->processor($persist, $this->security(canSend: true))->process($quote, new Post());

        self::assertInstanceOf(SaleQuote::class, $saved);
        self::assertSame(SaleQuote::STATUS_SENT, $saved->getStatus());
    }

    public function testCreateCannotStartConvertedAcceptedOrExpired(): void
    {
        foreach ([SaleQuote::STATUS_CONVERTED, SaleQuote::STATUS_ACCEPTED, SaleQuote::STATUS_EXPIRED] as $status) {
            $quote = new SaleQuote()->setStatus($status);
            $persist = $this->createMock(ProcessorInterface::class);
            $persist->expects(self::never())->method('process');

            try {
                $this->processor($persist, $this->security(canSend: true))->process($quote, new Post());
                self::fail(sprintf('Creating a quote as "%s" must be rejected.', $status));
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('status', $exception->getErrors());
            }
        }
    }

    public function testPatchToSentRequiresSendPermissionAfterTheTransitionIsValidated(): void
    {
        $quote = $this->existingQuote(SaleQuote::STATUS_SENT);
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::never())->method('process');

        $this->expectException(AccessDeniedHttpException::class);
        try {
            $this->processor($persist, $this->security(canSend: false), SaleQuote::STATUS_DRAFT)->process(
                $quote,
                new Patch(),
            );
        } finally {
            self::assertSame(SaleQuote::STATUS_DRAFT, $quote->getStatus());
        }
    }

    public function testInvalidTransitionIsStillRejectedWhenTheCallerCanSend(): void
    {
        $quote = $this->existingQuote(SaleQuote::STATUS_DRAFT);
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::never())->method('process');

        $this->expectException(ValidationException::class);
        $this->processor($persist, $this->security(canSend: true), SaleQuote::STATUS_CONVERTED)->process(
            $quote,
            new Patch(),
        );
    }

    public function testPatchToAcceptedDoesNotRequireSendPermission(): void
    {
        $quote = $this->existingQuote(SaleQuote::STATUS_ACCEPTED);
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::once())->method('process')->willReturnArgument(0);

        $saved = $this->processor($persist, $this->security(canSend: false), SaleQuote::STATUS_SENT)->process(
            $quote,
            new Patch(),
        );

        self::assertInstanceOf(SaleQuote::class, $saved);
        self::assertSame(SaleQuote::STATUS_ACCEPTED, $saved->getStatus());
    }

    public function testCreateRejectsAnotherBranch(): void
    {
        $quote = (new SaleQuote())->setBranch($this->branch('branch-b'));
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::never())->method('process');

        $this->expectException(ValidationException::class);
        $this->processor($persist, $this->security(canSend: false))->process($quote, new Post());
    }

    private function branch(string $id): Branch
    {
        $branch = new Branch();
        new \ReflectionProperty(Branch::class, 'id')->setValue($branch, $id);

        return $branch;
    }

    private function security(bool $canSend): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(9, 'vendedor', 4, ['ROLE_USER']));
        $security
            ->method('hasPermission')
            ->willReturnCallback(static fn(string $permission): bool => $canSend && $permission === 'sale_quotes.send');

        return $security;
    }

    /**
     * @param ProcessorInterface<mixed, mixed> $persist
     */
    private function processor(
        ProcessorInterface $persist,
        Security $security,
        string $originalStatus = SaleQuote::STATUS_DRAFT,
    ): SaleQuoteProcessor {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['status' => $originalStatus]);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $context = $this->createStub(BranchContextServiceInterface::class);
        $context->method('getCurrentBranchId')->willReturn('branch-a');
        $branches = $this->createStub(BranchRepository::class);
        $branches->method('find')->willReturn($this->branch('branch-a'));

        return new SaleQuoteProcessor(
            $persist,
            $this->createStub(ProcessorInterface::class),
            $entityManager,
            $security,
            new SaleQuoteService($this->createStub(EntityManagerInterface::class)),
            new OperationalBranchGuard($context, $branches),
        );
    }

    private function existingQuote(string $requestedStatus): SaleQuote
    {
        $quote = new SaleQuote()->setStatus($requestedStatus)->setBranch($this->branch('branch-a'));
        new \ReflectionProperty(SaleQuote::class, 'id')->setValue($quote, 'quote-1');

        return $quote;
    }

    private function item(string $gross, string $discount): \App\Sales\Entity\SaleQuoteItem
    {
        return new \App\Sales\Entity\SaleQuoteItem()
            ->setGrossAmount($gross)
            ->setDiscountAmount($discount)
            ->setTaxAmount('0.00')
            ->setNetAmount($gross);
    }
}
