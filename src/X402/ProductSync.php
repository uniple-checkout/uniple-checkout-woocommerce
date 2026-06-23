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

use RuntimeException;
use Uniple\CheckoutWooCommerce\Api\UnipleClient;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

defined('ABSPATH') || exit;

final class ProductSync
{
    private const MAX_PRODUCTS_PER_SYNC = 200;

    /**
     * @return array{synced:int,active:int,inactive:int,skipped:int,response:array<string,mixed>}
     */
    public function syncAll(UnipleClient $client): array
    {
        if (!function_exists('wc_get_products')) {
            throw new RuntimeException('woocommerce_unavailable');
        }

        $products = [];
        $activeCount = 0;
        $inactiveCount = 0;
        $skippedCount = 0;
        $sortOrder = 0;

        $ids = wc_get_products([
            'type' => ['simple', 'variable'],
            'status' => ['publish', 'draft', 'pending', 'private'],
            'limit' => self::MAX_PRODUCTS_PER_SYNC,
            'orderby' => 'ID',
            'order' => 'ASC',
            'return' => 'ids',
        ]);

        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product instanceof WC_Product) {
                ++$skippedCount;
                continue;
            }

            if ($product instanceof WC_Product_Variable) {
                foreach ($product->get_children() as $variationId) {
                    if (count($products) >= self::MAX_PRODUCTS_PER_SYNC) {
                        ++$skippedCount;
                        continue;
                    }
                    $variation = wc_get_product($variationId);
                    if (!$variation instanceof WC_Product_Variation) {
                        ++$skippedCount;
                        continue;
                    }
                    $item = $this->productPayload($variation, $product, $sortOrder++);
                    if ($item === null) {
                        ++$skippedCount;
                        continue;
                    }
                    $products[] = $item;
                    $item['active'] ? ++$activeCount : ++$inactiveCount;
                }
                continue;
            }

            if (count($products) >= self::MAX_PRODUCTS_PER_SYNC) {
                ++$skippedCount;
                continue;
            }
            $item = $this->productPayload($product, null, $sortOrder++);
            if ($item === null) {
                ++$skippedCount;
                continue;
            }
            $products[] = $item;
            $item['active'] ? ++$activeCount : ++$inactiveCount;
        }

        $response = $client->syncProducts($products);

        return [
            'synced' => count($products),
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'skipped' => $skippedCount,
            'response' => $response,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function productPayload(WC_Product $product, ?WC_Product $parent, int $sortOrder): ?array
    {
        $priceJpyc = $this->normalizePriceJpyc($this->taxIncludedPrice($product));
        if ($priceJpyc === null) {
            return null;
        }

        $parentId = $parent ? $parent->get_id() : $product->get_id();
        $externalId = $parent
            ? sprintf('woocommerce-product-%d-variation-%d', $parentId, $product->get_id())
            : sprintf('woocommerce-product-%d', $product->get_id());
        $active = $this->isActive($product, $parent);

        return [
            'externalId' => $externalId,
            'name' => $this->name($product, $parent),
            'priceJpyc' => $priceJpyc,
            'active' => $active,
            'description' => $this->description($product, $parent),
            'imageUrl' => $this->imageUrl($product, $parent),
            'pageUrl' => get_permalink($parentId) ?: '',
            'taxLabel' => '税込',
            'sortOrder' => $sortOrder,
        ];
    }

    private function taxIncludedPrice(WC_Product $product): string
    {
        $price = (string) $product->get_price();
        if ($price === '') {
            return '';
        }
        if (function_exists('wc_get_price_including_tax')) {
            return (string) wc_get_price_including_tax($product, ['qty' => 1, 'price' => (float) $price]);
        }

        return $price;
    }

    private function isActive(WC_Product $product, ?WC_Product $parent): bool
    {
        $statusOk = $product->get_status() === 'publish'
            && (!$parent || $parent->get_status() === 'publish');

        return $statusOk
            && $product->is_purchasable()
            && $product->is_in_stock()
            && $this->normalizePriceJpyc($product->get_price()) !== null;
    }

    private function name(WC_Product $product, ?WC_Product $parent): string
    {
        $name = $parent ? trim($parent->get_name().' / '.$product->get_name()) : $product->get_name();
        $name = trim($name);

        return mb_substr($name !== '' ? $name : 'WooCommerce product', 0, 255);
    }

    private function description(WC_Product $product, ?WC_Product $parent): string
    {
        $source = $product->get_description() ?: $product->get_short_description();
        if ($source === '' && $parent) {
            $source = $parent->get_description() ?: $parent->get_short_description();
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $source)));

        return mb_substr($text, 0, 1000);
    }

    private function imageUrl(WC_Product $product, ?WC_Product $parent): string
    {
        $imageId = $product->get_image_id();
        if (!$imageId && $parent) {
            $imageId = $parent->get_image_id();
        }
        if (!$imageId) {
            return '';
        }

        return (string) (wp_get_attachment_image_url($imageId, 'full') ?: '');
    }

    private function normalizePriceJpyc(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $s = trim((string) $value);
        if (!preg_match('/^(\d+)(?:\.(\d{1,6}))?$/', $s, $m)) {
            return null;
        }
        $integer = ltrim($m[1], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = isset($m[2]) ? rtrim($m[2], '0') : '';
        if ($integer === '0' && $fraction === '') {
            return null;
        }

        return $fraction === '' ? $integer : $integer.'.'.$fraction;
    }
}
