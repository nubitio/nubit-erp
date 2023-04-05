<?php

namespace App\Core\Infrastructure\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RemoveUnwantedControllers implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $definitions = $container->getDefinitions();
        /** @var class-string $fqcn */
        foreach ($definitions as $fqcn => $definition) {
            if (str_starts_with($fqcn, 'App\\')) {
                try {
                    $reflection = new \ReflectionClass($fqcn);
                    if ($reflection->isSubclassOf('Symfony\Bundle\FrameworkBundle\Controller\AbstractController')) {
                        $container->removeDefinition($fqcn);
                    }
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        }
    }
}
