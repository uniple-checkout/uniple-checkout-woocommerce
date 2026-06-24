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

use WC_Product;
use WC_Product_Variation;

defined('ABSPATH') || exit;

final class ProductResolver
{
    public static function findBySku(string $productSku): ?WC_Product
    {
        if (preg_match('/^woocommerce-product-(\d+)-variation-(\d+)$/', $productSku, $m)) {
            $variation = wc_get_product((int) $m[2]);
            if (!$variation instanceof WC_Product_Variation || (int) $variation->get_parent_id() !== (int) $m[1]) {
                return null;
            }

            return $variation;
        }

        if (preg_match('/^woocommerce-product-(\d+)$/', $productSku, $m)) {
            $product = wc_get_product((int) $m[1]);

            return $product instanceof WC_Product ? $product : null;
        }

        return null;
    }

    public static function isPurchasable(WC_Product $product): bool
    {
        return $product->is_purchasable() && $product->is_in_stock() && (string) $product->get_price() !== '';
    }
}
