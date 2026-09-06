<?php

declare(strict_types=1);

/**
 * Architecture rules AR-001…AR-010 — see docs/adr/ADR-018 and
 * docs/architecture/nubit-erp-target-architecture.md §1.
 *
 * The layer -> namespace map is NOT defined here: it is read from
 * architecture/contexts.yaml (NB-2), the single source of truth that
 * tests/Architecture/ContextManifestTest.php keeps in sync with src/. Adding a
 * context to that file automatically places it in the right layer here.
 *
 * Layers, bottom to top (a layer may depend only on layers below it):
 *
 *   Infra          cross-cutting glue (ADR-001 amendment)
 *   Platform       identity, organization, tenant configuration
 *   ControlPlane   SaaS provisioning — peer of ErpCore, may not depend on it
 *   ErpCore        reusable enterprise capabilities, vertical-agnostic (AR-007)
 *   Assistant      AI assistant — reads across the core
 *   Orchestration  bootstrap/seed wiring — composes the core
 *   Vertical       industry verticals — one namespace each, never depend on each
 *                  other (AR-004); App\Pharmacy / App\Restaurant / App\Hospitality
 *                  do not exist yet (NB-10, NB-18, NB-19)
 *
 * Pre-existing violations are in phparkitect-baseline.json. That list may only
 * shrink — never add to it; route new coupling through a domain event, move the
 * shared type, or invert the dependency.
 */

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;
use Symfony\Component\Yaml\Yaml;

return static function (Config $config): void {
    /** @var array{contexts: array<string, array{namespace: string, layer: string}>} $manifest */
    $manifest = Yaml::parseFile(__DIR__ . '/architecture/contexts.yaml');

    /** @var array<string, list<string>> $byLayer */
    $byLayer = [];
    foreach ($manifest['contexts'] as $ctx) {
        $byLayer[$ctx['layer']][] = $ctx['namespace'];
    }

    $infra = $byLayer['Infra'] ?? [];
    $platform = $byLayer['Platform'] ?? [];
    $controlPlane = $byLayer['ControlPlane'] ?? [];
    $erpCore = $byLayer['ErpCore'] ?? [];
    $assistant = $byLayer['Assistant'] ?? [];
    $orchestration = $byLayer['Orchestration'] ?? [];

    // Verticals: one layer per namespace so AR-004 (no vertical -> vertical) is
    // enforced. The three below are declared ahead of the code existing.
    $verticalNamespaces = array_values(array_unique([
        ...($byLayer['Vertical'] ?? []),
        'App\\Pharmacy',
        'App\\Restaurant',
        'App\\Hospitality',
    ]));
    $allVerticals = $verticalNamespaces;

    $rules = [];

    // Infra depends on nothing else under src/.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$infra))
        ->should(new NotDependsOnTheseNamespaces(array_merge(
            $platform,
            $controlPlane,
            $erpCore,
            $assistant,
            $orchestration,
            $allVerticals,
        )))
        ->because('AR-001: infra glue must not depend on any bounded context');

    // AR-001: Platform depends on nothing above it.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$platform))
        ->should(new NotDependsOnTheseNamespaces(array_merge(
            $controlPlane,
            $erpCore,
            $assistant,
            $orchestration,
            $allVerticals,
        )))
        ->because('AR-001: Platform core must not depend on ControlPlane, ERP or verticals');

    // ControlPlane may use Platform + Infra only.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$controlPlane))
        ->should(new NotDependsOnTheseNamespaces(array_merge($erpCore, $assistant, $orchestration, $allVerticals)))
        ->because('AR-003: ControlPlane must not depend on ERP, Assistant or verticals');

    // AR-002 / AR-007: ERP core is vertical-agnostic.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$erpCore))
        ->should(new NotDependsOnTheseNamespaces(array_merge($assistant, $orchestration, $allVerticals)))
        ->because('AR-002/AR-007: ERP core must not depend on Assistant, Orchestration or any vertical');

    // Assistant may read the core but not orchestration or verticals.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$assistant))
        ->should(new NotDependsOnTheseNamespaces(array_merge($orchestration, $allVerticals)))
        ->because('AR-004: Assistant must not depend on Orchestration or verticals');

    // Orchestration composes the core; it may depend on any layer except verticals.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$orchestration))
        ->should(new NotDependsOnTheseNamespaces($allVerticals))
        ->because('AR-002: bootstrap/seed wiring must not depend on any vertical');

    // AR-004: no vertical depends on another vertical (or ControlPlane / Assistant / Orchestration).
    foreach ($verticalNamespaces as $vertical) {
        $others = array_values(array_diff($verticalNamespaces, [$vertical]));
        $rules[] = Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces($vertical))
            ->should(new NotDependsOnTheseNamespaces(array_merge($others, $controlPlane, $assistant, $orchestration)))
            ->because(sprintf(
                'AR-004: %s must not depend on another vertical, ControlPlane, Assistant or Orchestration',
                $vertical,
            ));
    }

    $config->add(ClassSet::fromDir(__DIR__ . '/src'), ...$rules);
};
