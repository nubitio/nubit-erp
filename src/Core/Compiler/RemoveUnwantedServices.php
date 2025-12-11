<?php

namespace App\Core\Compiler;

use Override;
use ReflectionClass;
use Exception;
use App\Core\Annotation\IgnoreAutowire;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RemoveUnwantedServices implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $definitions = $container->getDefinitions();
        /** @var class-string $fqcn */
        foreach (array_keys($definitions) as $fqcn) {
            if (str_starts_with($fqcn, 'App\\')) {
                try {
                    $reflection = new ReflectionClass($fqcn);
                    $attribute = $reflection->getAttributes(IgnoreAutowire::class)[0] ?? null;
                    if (null !== $attribute) {
                        $container->removeDefinition($fqcn);
                    }
                } catch (Exception) {
                    // Ignore
                }
            }
        }
    }
}
