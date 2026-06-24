<?php
/*
 * uniple checkout for WooCommerce
 * Copyright (C) 2026 uniple inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2,
 * as published by the Free Software Foundation.
 */

declare(strict_types=1);

namespace Uniple\CheckoutWooCommerce\X402;

defined('ABSPATH') || exit;

final class QuoteStore
{
    public const TTL_SECONDS = 900;
    private const OPTION_PREFIX = 'uniple_x402_quote_';

    /**
     * @param array<string,mixed> $quote
     */
    public static function save(array $quote): void
    {
        $key = self::optionKey((string) $quote['quoteId']);
        if (!add_option($key, $quote, '', false)) {
            update_option($key, $quote, false);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function find(string $quoteId): ?array
    {
        $quote = get_option(self::optionKey($quoteId), null);

        return is_array($quote) ? $quote : null;
    }

    public static function markUsed(string $quoteId): void
    {
        $quote = self::find($quoteId);
        if ($quote === null) {
            return;
        }
        $quote['usedAt'] = gmdate(DATE_ATOM);
        update_option(self::optionKey($quoteId), $quote, false);
    }

    private static function optionKey(string $quoteId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $quoteId) ?? '';
        if (strlen($safe) > 80) {
            $safe = hash('sha256', $safe);
        }

        return self::OPTION_PREFIX.$safe;
    }
}
