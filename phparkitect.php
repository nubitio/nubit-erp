<?php

declare(strict_types=1);

/**
 * Architecture rules AR-001…AR-010 — see docs/adr/ADR-018 and
 * docs/architecture/nubit-erp-target-architecture.md §1.
 *
 * Layers, bottom to top (a layer may depend only on layers below it):
 *
 *   Infra          App\Core, App\Efact         cross-cutting glue (ADR-001 amendment)
 *   Platform       Identity, Organization, Settings
 *   ControlPlane   App\ControlPlane            SaaS provisioning
 *   ErpCore        Catalog, Contacts, Inventory, Sales, Billing, CashRegister,
 *                  Purchasing, Reporting, AccountsPayable, BankReconciliation,
 *                  FixedAssets, Payroll, ExpenseReports, Accounting, Taxes, Integration
 *   Assistant      App\AI                     reads across the core
 *   Orchestration  App\Setup, App\Onboarding  bootstrap/seed wiring — composes the
 *                                             core, so it may depend on any layer
 *                                             except the verticals
 *   Vertical_*     App\Pharmacy / App\Restaurant / App\Hospitality (not yet created)
 *
 * ControlPlane sits beside ErpCore: both depend on Platform + Infra, neither on
 * the other. Verticals depend on ErpCore + Platform + Infra, never on each other
 * (AR-004) and never on ControlPlane or Assistant.
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

return static function (Config $config): void {
    $infra = ['App\\Core', 'App\\Efact'];
    $platform = ['App\\Identity', 'App\\Organization', 'App\\Settings'];
    $orchestration = ['App\\Setup', 'App\\Onboarding'];
    $controlPlane = ['App\\ControlPlane'];
    $erpCore = [
        'App\\Catalog', 'App\\Contacts', 'App\\Inventory', 'App\\Sales', 'App\\Billing',
        'App\\CashRegister', 'App\\Purchasing', 'App\\Reporting', 'App\\AccountsPayable',
        'App\\BankReconciliation', 'App\\FixedAssets', 'App\\Payroll', 'App\\ExpenseReports',
        'App\\Accounting', 'App\\Taxes', 'App\\Integration',
    ];
    $assistant = ['App\\AI'];
    $verticals = [
        'Pharmacy' => ['App\\Pharmacy'],
        'Restaurant' => ['App\\Restaurant'],
        'Hospitality' => ['App\\Hospitality'],
    ];
    $allVerticals = array_merge(...array_values($verticals));

    $rules = [];

    // Infra depends on nothing else under src/.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$infra))
        ->should(new NotDependsOnTheseNamespaces(array_merge($platform, $controlPlane, $erpCore, $assistant, $allVerticals)))
        ->because('AR-001: infra glue must not depend on any bounded context');

    // AR-001: Platform depends on nothing above it.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$platform))
        ->should(new NotDependsOnTheseNamespaces(array_merge($controlPlane, $erpCore, $assistant, $allVerticals)))
        ->because('AR-001: Platform core must not depend on ControlPlane, ERP or verticals');

    // ControlPlane may use Platform + Infra only.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$controlPlane))
        ->should(new NotDependsOnTheseNamespaces(array_merge($erpCore, $assistant, $allVerticals)))
        ->because('AR-003: ControlPlane must not depend on ERP, Assistant or verticals');

    // AR-002 / AR-007: ERP core is vertical-agnostic.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$erpCore))
        ->should(new NotDependsOnTheseNamespaces(array_merge($assistant, $allVerticals)))
        ->because('AR-002/AR-007: ERP core must not depend on Assistant or any vertical');

    // Assistant may read the core but not verticals.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$assistant))
        ->should(new NotDependsOnTheseNamespaces($allVerticals))
        ->because('AR-004: Assistant must not depend on verticals');

    // Orchestration composes the core; it may depend on any layer except verticals.
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(...$orchestration))
        ->should(new NotDependsOnTheseNamespaces($allVerticals))
        ->because('AR-002: bootstrap/seed wiring must not depend on any vertical');

    // AR-004: no vertical depends on another vertical (or ControlPlane / Assistant).
    foreach ($verticals as $name => $ns) {
        $others = array_values(array_diff($allVerticals, $ns));
        $rules[] = Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces(...$ns))
            ->should(new NotDependsOnTheseNamespaces(array_merge($others, $controlPlane, $assistant)))
            ->because("AR-004: vertical {$name} must not depend on another vertical, ControlPlane or Assistant");
    }

    $config->add(ClassSet::fromDir(__DIR__ . '/src'), ...$rules);
};
