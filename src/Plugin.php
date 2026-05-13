<?php

declare(strict_types=1);

namespace Uniple\CheckoutWooCommerce;

use Uniple\CheckoutWooCommerce\Gateway\UnipleBlockSupport;
use Uniple\CheckoutWooCommerce\Gateway\UnipleGateway;
use Uniple\CheckoutWooCommerce\ReturnUrl\ReturnController;
use Uniple\CheckoutWooCommerce\Webhook\WebhookController;

final class Plugin
{
    public const VERSION = '0.1.0';
    public const PLUGIN_ID = 'uniple';

    public static function boot(): void
    {
        if (!class_exists(\WooCommerce::class)) {
            return;
        }

        add_filter('woocommerce_payment_gateways', [self::class, 'registerGateway']);
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            [self::class, 'registerBlockSupport']
        );
        add_action('rest_api_init', [WebhookController::class, 'registerRoutes']);
        add_action('woocommerce_api_uniple_return', [ReturnController::class, 'handle']);
    }

    /**
     * @param array<int, string> $gateways
     *
     * @return array<int, string>
     */
    public static function registerGateway(array $gateways): array
    {
        $gateways[] = UnipleGateway::class;

        return $gateways;
    }

    public static function registerBlockSupport(
        \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry
    ): void {
        $registry->register(new UnipleBlockSupport());
    }
}
