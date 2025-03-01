<?php

namespace App\Core\Infrastructure\Compiler;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RemoveUnwantedControllers implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        $definitions = $container->getDefinitions();
        /** @var class-string $fqcn */
        foreach (array_keys($definitions) as $fqcn) {
            if (str_starts_with($fqcn, 'App\\')) {
                try {
                    $reflection = new \ReflectionClass($fqcn);
                    if ($reflection->isSubclassOf(AbstractController::class)) {
                        $container->removeDefinition($fqcn);
                    }
                } catch (\Exception) {
                    // Ignore
                }
            }
        }
    }
}
