<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Billing\Domain;

use App\EInvoicing\Billing\Domain\NoteConsultUseCase;
use App\EInvoicing\Billing\Domain\NoteCreateUseCase;
use App\EInvoicing\Billing\Domain\NoteRepositoryInterface;
use Greenter\Model\Response\CdrResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Sale\Note;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class NoteConsultUseCaseTest extends TestCase
{
    private NoteCreateUseCase&MockObject $noteCreateUseCase;
    private NoteRepositoryInterface&MockObject $noteRepository;
    private NoteConsultUseCase $useCase;

    protected function setUp(): void
    {
        $this->noteCreateUseCase = $this->createMock(NoteCreateUseCase::class);
        $this->noteRepository    = $this->createMock(NoteRepositoryInterface::class);

        $this->useCase = new NoteConsultUseCase(
            $this->noteCreateUseCase,
            $this->noteRepository,
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeNote(string $tipoDoc = '07', string $serie = 'B001', string $correlativo = '1'): Note
    {
        $note = new Note();
        $note->setTipoDoc($tipoDoc)
            ->setSerie($serie)
            ->setCorrelativo($correlativo);

        return $note;
    }

    private function makeStatusCdrResult(bool $success = true, string $code = '0'): StatusCdrResult
    {
        $cdrResponse = (new CdrResponse())
            ->setCode($code)
            ->setDescription('La nota de credito es valida');

        return (new StatusCdrResult())
            ->setSuccess($success)
            ->setCdrResponse($cdrResponse);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function testInvokeCallsNoteCreateUseCaseWithGivenTaxDocumentId(): void
    {
        $note = $this->makeNote();

        $this->noteCreateUseCase
            ->expects(self::once())
            ->method('__invoke')
            ->with('td-1')
            ->willReturn($note);

        $this->noteRepository
            ->method('getStatus')
            ->willReturn($this->makeStatusCdrResult());

        $this->useCase->__invoke('td-1');
    }

    public function testInvokeCallsRepositoryGetStatusWithNoteReturnedByCreateUseCase(): void
    {
        $note = $this->makeNote('07', 'B001', '5');

        $this->noteCreateUseCase
            ->method('__invoke')
            ->willReturn($note);

        $this->noteRepository
            ->expects(self::once())
            ->method('getStatus')
            ->with(self::identicalTo($note))
            ->willReturn($this->makeStatusCdrResult());

        $this->useCase->__invoke('td-1');
    }

    public function testInvokeReturnsStatusCdrResultFromRepository(): void
    {
        $note          = $this->makeNote();
        $expectedResult = $this->makeStatusCdrResult(true, '0');

        $this->noteCreateUseCase
            ->method('__invoke')
            ->willReturn($note);

        $this->noteRepository
            ->method('getStatus')
            ->willReturn($expectedResult);

        $result = $this->useCase->__invoke('td-1');

        self::assertSame($expectedResult, $result);
    }

    public function testInvokeReturnsFailedStatusCdrResultFromRepository(): void
    {
        $note          = $this->makeNote();
        $failedResult  = (new StatusCdrResult())->setSuccess(false);

        $this->noteCreateUseCase
            ->method('__invoke')
            ->willReturn($note);

        $this->noteRepository
            ->method('getStatus')
            ->willReturn($failedResult);

        $result = $this->useCase->__invoke('td-1');

        self::assertSame($failedResult, $result);
        self::assertFalse($result->isSuccess());
    }

    public function testNotePassedToRepositoryHasCorrectTipoDoc(): void
    {
        $note = $this->makeNote('08', 'F001', '10');

        $this->noteCreateUseCase
            ->method('__invoke')
            ->willReturn($note);

        $capturedNote = null;
        $this->noteRepository
            ->expects(self::once())
            ->method('getStatus')
            ->willReturnCallback(function (Note $n) use (&$capturedNote): StatusCdrResult {
                $capturedNote = $n;

                return $this->makeStatusCdrResult();
            });

        $this->useCase->__invoke('td-1');

        self::assertInstanceOf(Note::class, $capturedNote);
        /** @var Note $capturedNote */
        self::assertSame('08', $capturedNote->getTipoDoc());
        self::assertSame('F001', $capturedNote->getSerie());
        self::assertSame('10', $capturedNote->getCorrelativo());
    }

    public function testNotePassedToRepositoryHasCreditNoteType(): void
    {
        $note = $this->makeNote('07', 'B001', '3');

        $this->noteCreateUseCase
            ->method('__invoke')
            ->willReturn($note);

        $capturedNote = null;
        $this->noteRepository
            ->expects(self::once())
            ->method('getStatus')
            ->willReturnCallback(function (Note $n) use (&$capturedNote): StatusCdrResult {
                $capturedNote = $n;

                return $this->makeStatusCdrResult();
            });

        $this->useCase->__invoke('td-1');

        self::assertInstanceOf(Note::class, $capturedNote);
        /** @var Note $capturedNote */
        self::assertSame('07', $capturedNote->getTipoDoc());
        self::assertSame('B001', $capturedNote->getSerie());
        self::assertSame('3', $capturedNote->getCorrelativo());
    }
}
