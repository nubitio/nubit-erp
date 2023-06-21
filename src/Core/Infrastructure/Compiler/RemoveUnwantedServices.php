<?php

namespace App\Core\Infrastructure\Compiler;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RemoveUnwantedServices implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definitions = $container->getDefinitions();
        /** @var class-string $fqcn */
        foreach ($definitions as $fqcn => $definition) {
            if (str_starts_with($fqcn, 'App\\')) {
                try {
                    $reflection = new \ReflectionClass($fqcn);
                    $attribute = $reflection->getAttributes(IgnoreAutowire::class)[0] ?? null;
                    if (null !== $attribute) {
                        $container->removeDefinition($fqcn);
                    }
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        }
    }
}
