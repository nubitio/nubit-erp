<?php

declare(strict_types=1);

namespace App\AI\Security;

/**
 * The permission each assistant tool needs. The tools read the same data the screens do, so the
 * assistant must not answer what the user could not open: a cashier without `accounts_payable.view`
 * gets no payables aging from the chat either.
 *
 * `null` means the tool touches no tenant data (the user manual).
 */
final class AssistantToolAccess
{
    /** @var array<string, string|null> */
    public const array PERMISSION_BY_TOOL = [
        'accounts_payable_aging' => 'accounts_payable.view',
        'cash_register_status' => 'cash.view',
        'customer_intelligence' => 'customers.view',
        'inventory_movements' => 'inventory.view',
        'product_stock' => 'inventory.view',
        'purchase_summary' => 'purchases.view',
        'recent_purchases' => 'purchases.view',
        'quote_search' => 'sale_quotes.view',
        'recent_sales' => 'sales.view',
        'sales_summary' => 'sales.view',
        'tax_document_status' => 'sales.view',
        'search_products' => 'catalog.view',
        'stock_alert' => 'inventory.view',
        'sunat_status' => 'settings.view',
        'propose_customer' => 'customers.create',
        'propose_product' => 'catalog.create',
        'propose_product_from_image' => 'catalog.create',
        'propose_sale' => 'sales.create',
        'propose_replace_rejected' => 'sales.create',
        'propose_credit_note' => 'sales.create',
        'propose_onboarding_fill' => 'settings.edit',
        'search_manual' => null,
    ];

    /** Whether the tool is known to the map at all; an unknown one is refused rather than let through. */
    public static function knows(string $tool): bool
    {
        return \array_key_exists($tool, self::PERMISSION_BY_TOOL);
    }

    public static function permissionFor(string $tool): ?string
    {
        return self::PERMISSION_BY_TOOL[$tool] ?? null;
    }
}
