<?php

declare(strict_types=1);

namespace App\Identity\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Who may use each API resource that does not declare `security` itself; the rules are data in
 * `config/packages/resource_access.yaml` (see there for the format and the reasons).
 */
final readonly class ResourceAccessPolicy
{
    /** Signed in is enough: the data is reference data every role works with. */
    public const string OPEN = "is_granted('ROLE_USER')";

    /**
     * @param array<class-string, array<string, string|list<string>>> $rules
     * @param list<class-string> $exempt resources outside the policy on purpose
     */
    public function __construct(
        #[Autowire(param: 'app.resource_access.rules')]
        private array $rules,
        #[Autowire(param: 'app.resource_access.exempt')]
        private array $exempt = [],
    ) {}

    /** @return array<class-string, array<string, string|list<string>>> */
    public function rules(): array
    {
        return $this->rules;
    }

    /** @return list<class-string> */
    public function exempt(): array
    {
        return $this->exempt;
    }

    /**
     * The `security` expression for one operation of `$resourceClass`, or null when the policy has
     * nothing to say about it.
     */
    public function expressionFor(string $resourceClass, string $httpMethod, ?string $uriTemplate): ?string
    {
        $rules = $this->rules[$resourceClass] ?? null;
        if ($rules === null) {
            return null;
        }

        $method = strtoupper($httpMethod);
        $keys = [];
        if ($uriTemplate !== null) {
            $keys[] = $method . ' ' . $uriTemplate;
        }
        $keys[] = $method;
        $keys[] = \in_array($method, ['GET', 'HEAD'], true) ? 'read' : 'write';

        foreach ($keys as $key) {
            if (isset($rules[$key])) {
                return self::expression($rules[$key]);
            }
        }

        return null;
    }

    /** @param string|list<string> $rule */
    public static function expression(string|array $rule): string
    {
        if ($rule === 'open') {
            return self::OPEN;
        }

        return implode(' or ', array_map(
            static fn(string $permission): string => sprintf("is_granted('perm:%s')", $permission),
            (array) $rule,
        ));
    }
}
