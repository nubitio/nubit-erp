<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withParallel()
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withComposerBased(
        doctrine: true,
        phpunit: true,
        symfony: true,
    )
    ->withImportNames(
        removeUnusedImports: true,
    )
    ->withSets([
        LevelSetList::UP_TO_PHP_85,
    ])
    ->withRules([
        DeclareStrictTypesRector::class,
    ])
    ->withAttributesSets(
        symfony: true,
        doctrine: true,
    )
    ->withSkip([
        __DIR__ . '/src/Command/ImportUbigeoCommand.php',
        __DIR__ . '/src/Command/FillTimeDimensionCommand.php',
    ]);
