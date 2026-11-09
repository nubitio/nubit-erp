<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Identity module of nubit/admin-bundle: second factor (TOTP), invitations, password recovery.
 *
 * Why this is a PHP file and not nubit_admin.yaml: the bundle decides at container-compile time
 * whether to map its entities and attach the TOTP badge to the login passport, and it reads the
 * flag from the raw config tree WITHOUT resolving %env()% placeholders. In YAML,
 * `enabled: '%env(bool:IDENTITY_MODULE_ENABLED)%'` would be the non-empty string
 * "%env(bool:IDENTITY_MODULE_ENABLED)%", which casts to TRUE even when the variable is 0.
 *
 * Reading the variable here gives the intended off-by-default switch. It is baked into the
 * compiled container, so changing it needs a cache rebuild (a normal deploy / cache:clear).
 *
 * ROLLOUT ORDER (do not skip): 1) deploy with the flag off, 2) run `app:tenants:migrate` so every
 * tenant database has the nubit_* tables, 3) set IDENTITY_MODULE_ENABLED=1 and rebuild the cache.
 * With the module on and a table missing, EVERY login in that tenant fails.
 */
return static function (ContainerConfigurator $container): void {
    $enabled = filter_var(
        $_SERVER['IDENTITY_MODULE_ENABLED'] ?? $_ENV['IDENTITY_MODULE_ENABLED'] ?? false,
        FILTER_VALIDATE_BOOLEAN,
    );

    // Read by App\Identity\EventSubscriber\IdentityRoutesGuard.
    $container->parameters()->set('app.identity_enabled', $enabled);

    // Lets the SPA host page tell the frontend whether to offer recovery / invitations / 2FA.
    $container->extension('twig', ['globals' => ['identity_enabled' => $enabled]]);

    $container->extension('nubit_admin', [
        'identity' => [
            'enabled' => $enabled,
            'issuer' => 'Nubit ERP',
            // Not user_class: the default gateway assumes an e-mail identifier and string roles.
            // App\Identity\Auth\ErpIdentityUserGateway is aliased in services.yaml instead.
            // TOTP is opt-in per user (nobody is forced); mandatory roles stay empty on purpose.
            'totp' => ['required_for_all' => false, 'required_for_roles' => []],
        ],
    ]);
};
