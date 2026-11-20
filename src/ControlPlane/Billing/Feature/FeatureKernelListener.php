<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Feature;

use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Kernel listener that reads #[RequiresFeature] PHP attributes from the
 * matched controller (class and/or method) and delegates to
 * FeatureCheckerInterface::requireFeature(), which throws
 * FeatureNotAvailableException (HTTP 402) when the tenant's plan does not
 * include the requested feature.
 *
 * Handles all controller shapes supported by Symfony:
 *   - Invokable objects  (object with __invoke)
 *   - Array callables    ([$object, 'method'] or ['ClassName', 'method'])
 *   - String callables   ('ClassName::method')
 *   - Closures           (skipped — no attributes possible)
 */
final class FeatureKernelListener
{
    public function __construct(
        private readonly FeatureCheckerInterface $featureChecker,
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        [$class, $method] = $this->resolveClassAndMethod($controller);

        if ($class === null) {
            // Closure or unresolvable callable — nothing to inspect.
            return;
        }

        $reflectionClass = new \ReflectionClass($class);

        // 1. Class-level attributes apply to every action in the controller.
        foreach ($reflectionClass->getAttributes(RequiresFeature::class) as $attribute) {
            /** @var RequiresFeature $instance */
            $instance = $attribute->newInstance();
            $this->featureChecker->requireFeature($instance->featureKey);
        }

        // 2. Method-level attributes (only when a specific method is known).
        if ($method === null) {
            return;
        }

        try {
            $reflectionMethod = $reflectionClass->getMethod($method);
        } catch (\ReflectionException) {
            return;
        }

        foreach ($reflectionMethod->getAttributes(RequiresFeature::class) as $attribute) {
            /** @var RequiresFeature $instance */
            $instance = $attribute->newInstance();
            $this->featureChecker->requireFeature($instance->featureKey);
        }
    }

    /**
     * Resolves a callable to a [class-string|null, method-name|null] tuple.
     *
     * @param callable $controller
     *
     * @return array{class-string|null, string|null}
     */
    private function resolveClassAndMethod(callable $controller): array
    {
        // Array callable: [$object, 'method'] or ['ClassName', 'method']
        if (\is_array($controller)) {
            [$classOrObject, $method] = $controller;

            return [
                \is_object($classOrObject) ? $classOrObject::class : $classOrObject,
                $method,
            ];
        }

        // String callable: 'ClassName::method'
        if (\is_string($controller)) {
            if (str_contains($controller, '::')) {
                [$class, $method] = explode('::', $controller, 2);

                return class_exists($class) ? [$class, $method] : [null, null];
            }

            // Plain function name — no class attributes to read.
            return [null, null];
        }

        // Closure — no attributes possible.
        if ($controller instanceof \Closure) {
            return [null, null];
        }

        // Invokable object.
        if (\is_object($controller)) {
            return [$controller::class, '__invoke'];
        }

        return [null, null];
    }
}
