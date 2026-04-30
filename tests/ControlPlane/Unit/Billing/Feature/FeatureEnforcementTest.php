<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Unit\Billing\Feature;

use App\ControlPlane\Billing\Feature\FeatureKernelListener;
use App\ControlPlane\Billing\Feature\FeatureVoter;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Nubit\Platform\Feature\Exception\FeatureNotAvailableException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;

/**
 * Unit tests for HTTP enforcement of feature gates.
 *
 * Covers SPEC-005 ACs:
 *   AC-10 FeatureKernelListener throws FeatureNotAvailableException (HTTP 402)
 *         when the controller has #[RequiresFeature] and the feature is disabled.
 *   AC-11 FeatureVoter returns false for IS_GRANTED('FEATURE_EXCEL_EXPORT')
 *         when the feature is disabled, and true when enabled.
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class FeatureEnforcementTest extends TestCase
{
    private FeatureCheckerInterface&MockObject $featureChecker;

    protected function setUp(): void
    {
        $this->featureChecker = $this->createMock(FeatureCheckerInterface::class);
    }

    // -------------------------------------------------------------------------
    // AC-10: FeatureKernelListener
    // -------------------------------------------------------------------------

    public function testKernelListenerThrowsExceptionForDisabledFeature(): void
    {
        $this->featureChecker
            ->method('requireFeature')
            ->with('excel_export')
            ->willThrowException(new FeatureNotAvailableException('excel_export'));

        $listener = new FeatureKernelListener($this->featureChecker);
        $event    = $this->makeControllerEvent(new ControllerWithFeatureAttribute());

        $this->expectException(FeatureNotAvailableException::class);
        $this->expectExceptionCode(402);

        $listener->onKernelController($event);
    }

    public function testKernelListenerAllowsRequestForEnabledFeature(): void
    {
        $this->featureChecker
            ->method('requireFeature')
            ->with('excel_export');
        // No exception thrown → feature is available.

        $listener = new FeatureKernelListener($this->featureChecker);
        $event    = $this->makeControllerEvent(new ControllerWithFeatureAttribute());

        $listener->onKernelController($event);

        // If we reach here without exception, the test passes.
        $this->addToAssertionCount(1);
    }

    public function testKernelListenerSkipsClosureControllers(): void
    {
        // Closures have no PHP attributes — listener must not call requireFeature.
        $this->featureChecker->expects(self::never())->method('requireFeature');

        $listener = new FeatureKernelListener($this->featureChecker);
        $event    = $this->makeControllerEvent(static fn () => null);

        $listener->onKernelController($event);
    }

    // -------------------------------------------------------------------------
    // AC-11: FeatureVoter
    // -------------------------------------------------------------------------

    public function testVoterReturnsFalseForDisabledFeature(): void
    {
        $this->featureChecker
            ->method('hasFeature')
            ->with('excel_export')
            ->willReturn(false);

        $voter  = new FeatureVoter($this->featureChecker);
        $token  = new NullToken();
        $result = $voter->vote($token, null, ['FEATURE_EXCEL_EXPORT']);

        // Voter::ACCESS_DENIED = -1
        self::assertSame(-1, $result);
    }

    public function testVoterReturnsTrueForEnabledFeature(): void
    {
        $this->featureChecker
            ->method('hasFeature')
            ->with('excel_export')
            ->willReturn(true);

        $voter  = new FeatureVoter($this->featureChecker);
        $token  = new NullToken();
        $result = $voter->vote($token, null, ['FEATURE_EXCEL_EXPORT']);

        // Voter::ACCESS_GRANTED = 1
        self::assertSame(1, $result);
    }

    public function testVoterAbstainsForNonFeatureAttribute(): void
    {
        $this->featureChecker->expects(self::never())->method('hasFeature');

        $voter  = new FeatureVoter($this->featureChecker);
        $token  = new NullToken();
        $result = $voter->vote($token, null, ['ROLE_ADMIN']);

        // Voter::ACCESS_ABSTAIN = 0
        self::assertSame(0, $result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeControllerEvent(callable $controller): ControllerEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');

        return new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}

/**
 * Minimal controller stub with a #[RequiresFeature] attribute on __invoke.
 * Defined outside the test class so PHP reflection can read its attributes.
 */
#[RequiresFeature('excel_export')]
final class ControllerWithFeatureAttribute
{
    public function __invoke(): void
    {
    }
}
