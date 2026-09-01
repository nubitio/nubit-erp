<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Feature\Compiler;

use App\ControlPlane\Billing\Feature\FeatureCatalog;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

/**
 * Collects every feature-key reference in the codebase (attributes + source
 * literals) and reports the ones absent from {@see FeatureCatalog}.
 *
 * Split out of {@see RequireFeatureKeyPass} so each piece stays simple.
 */
final class FeatureKeyScanner
{
    private const string SRC_DIR = __DIR__ . '/../../../../../src';

    /** Regex per gate style; capture group 1 is the (raw) feature key. */
    private const array SOURCE_PATTERNS = [
        '/(?:->|::)(?:hasFeature|requireFeature)\(\s*[\'"]([a-z0-9_]+)[\'"]/',
        '/isFeatureEnabled\([^,()]+,\s*[\'"]([a-z0-9_]+)[\'"]/',
        '/IS_GRANTED\(\s*[\'"]FEATURE_([A-Z0-9_]+)[\'"]/',
        '/RequiresFeature\(\s*[\'"]([a-z0-9_]+)[\'"]/',
    ];

    /**
     * @return list<string> human-readable "key (where)" strings; empty when clean
     */
    public static function unknownKeys(ContainerBuilder $container): array
    {
        $violations = array_merge(
            self::fromAttributes($container),
            self::fromSource(),
        );

        return array_values(array_unique($violations));
    }

    /**
     * @return list<string>
     */
    private static function fromAttributes(ContainerBuilder $container): array
    {
        $violations = [];

        foreach (array_keys($container->getDefinitions()) as $id) {
            if (!str_starts_with($id, 'App\\') || !class_exists($id)) {
                continue;
            }

            foreach (self::requiresFeatureKeys($id) as $key) {
                if (FeatureCatalog::has($key)) {
                    continue;
                }

                $violations[] = sprintf('%s  (#[RequiresFeature] in %s)', $key, $id);
            }
        }

        return $violations;
    }

    /**
     * @param class-string $class  already confirmed to exist by the caller
     *
     * @return list<string>
     */
    private static function requiresFeatureKeys(string $class): array
    {
        $reflection = new \ReflectionClass($class);

        $attributes = $reflection->getAttributes(RequiresFeature::class);
        foreach ($reflection->getMethods() as $method) {
            $attributes = [...$attributes, ...$method->getAttributes(RequiresFeature::class)];
        }

        $keys = [];
        foreach ($attributes as $attribute) {
            $keys[] = $attribute->newInstance()->featureKey;
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private static function fromSource(): array
    {
        $dir = realpath(self::SRC_DIR);
        if ($dir === false) {
            return [];
        }

        $selfDir = realpath(__DIR__);
        $violations = [];

        foreach (Finder::create()->files()->in($dir)->name('*.php') as $file) {
            if ($selfDir !== false && str_starts_with((string) $file->getRealPath(), $selfDir)) {
                continue; // this pass's own directory
            }

            foreach (self::unknownInText($file->getContents()) as $key) {
                $violations[] = sprintf('%s  (in %s)', $key, $file->getRelativePathname());
            }
        }

        return $violations;
    }

    /**
     * @return list<string> lowercased keys not in the catalogue
     */
    private static function unknownInText(string $code): array
    {
        $found = [];

        foreach (self::SOURCE_PATTERNS as $pattern) {
            $matches = [];
            if (preg_match_all($pattern, $code, $matches) === 0) {
                continue;
            }

            /** @var list<string> $captured */
            $captured = $matches[1];
            foreach ($captured as $raw) {
                $key = strtolower($raw);
                if (!FeatureCatalog::has($key)) {
                    $found[] = $key;
                }
            }
        }

        return $found;
    }
}
