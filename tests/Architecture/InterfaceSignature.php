<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

use function sort;
use function sprintf;

/**
 * Renders an interface's public method surface as a list of canonical,
 * order-independent signature strings. Used by PublicContractSnapshotTest
 * (NB-21) to pin the shape of every declared public contract.
 */
final class InterfaceSignature
{
    /**
     * @return list<string> one `name(params): return` string per public method, sorted
     */
    public static function describe(string $fqcn): array
    {
        assert(interface_exists($fqcn) || class_exists($fqcn), "{$fqcn} is not loadable");

        $signatures = [];
        foreach (new ReflectionClass($fqcn)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $params = array_map(self::param(...), $method->getParameters());
            $signatures[] = sprintf(
                '%s(%s): %s',
                $method->getName(),
                implode(', ', $params),
                self::type($method->getReturnType()),
            );
        }

        sort($signatures);

        return $signatures;
    }

    private static function param(ReflectionParameter $parameter): string
    {
        $marker = $parameter->isVariadic() ? ' ...' : '';
        $marker .= $parameter->isPassedByReference() ? ' &' : '';
        $optional = $parameter->isOptional() && !$parameter->isVariadic() ? '?' : '';

        return sprintf('%s%s $%s%s', self::type($parameter->getType()), $marker, $parameter->getName(), $optional);
    }

    private static function type(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if (!$type instanceof ReflectionNamedType) {
            // Union / intersection — ReflectionType::__toString is canonical enough.
            $rendered = (string) $type;

            return $rendered === '' ? 'mixed' : $rendered;
        }

        $nullable = $type->allowsNull() && $type->getName() !== 'null' && $type->getName() !== 'mixed';

        return ($nullable ? '?' : '') . $type->getName();
    }
}
