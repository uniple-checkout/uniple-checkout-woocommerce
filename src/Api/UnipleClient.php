<?php

declare(strict_types=1);

namespace Uniple\CheckoutWooCommerce\Api;

use InvalidArgumentException;
use RuntimeException;

/**
 * uniple Merchant API thin client for WooCommerce.
 *
 * EC-CUBE 4 (Guzzle) / EC-CUBE 2 (curl) を WP HTTP API (wp_remote_post/get) に
 * 移植。 routing は uniple SSR で完結 (= r22 thin client 方針)、 plugin 側は
 * POST sessions → checkoutUrl redirect + GET sessions (option C fallback) のみ。
 */
final class UnipleClient
{
    public const TIMEOUT_SECONDS = 5;

    /**
     * @param array{api_key:string, webhook_secret:string, merchant_label:string, api_base_url:string, mode:string} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param array{amountJpyc:int|string, merchantOrderId:string, itemName:string, successUrl:string, cancelUrl:string, webhookUrl:string} $params
     *
     * @return array{ok:bool, sessionId:string, checkoutUrl:string, payId:string, status:string, expiresAt:string}
     */
    public function createSession(array $params): array
    {
        if ($this->config['api_key'] === '') {
            throw new RuntimeException('uniple_api_key_not_configured');
        }

        $endpoint = $this->endpoint('/api/merchant/checkout/sessions');
        $amountInt = $this->toIntegerJpyc($params['amountJpyc']);
        $itemName = (string) ($params['itemName'] ?? 'WooCommerce order');

        $body = [
            'amountJpyc' => (string) $amountInt,
            'successUrl' => $params['successUrl'],
            'cancelUrl' => $params['cancelUrl'],
            'clientReferenceId' => (string) $params['merchantOrderId'],
            'merchantLabel' => $this->config['merchant_label'],
            'description' => $itemName,
            'lineItems' => [[
                'name' => $itemName,
                'quantity' => 1,
                'amountJpyc' => $amountInt,
            ]],
            'splitEngine' => 'v3',
            'webhookUrl' => $params['webhookUrl'],
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => $this->commonHeaders(['Content-Type' => 'application/json']),
            'body' => (string) wp_json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'timeout' => self::TIMEOUT_SECONDS,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('uniple_session_unreachable: '.$response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $payload = json_decode($raw, true);

        if ($status !== 200 || !is_array($payload) || ($payload['ok'] ?? false) !== true) {
            throw new RuntimeException(
                'uniple_session_failed: status='.$status.' body='.substr($raw, 0, 300)
            );
        }

        $session = is_array($payload['session'] ?? null) ? $payload['session']
            : (is_array($payload['data'] ?? null) ? $payload['data'] : $payload);
        $sessionId = (string) ($session['sessionId'] ?? '');
        $checkoutUrl = (string) ($session['checkoutUrl'] ?? '');

        if ($sessionId === '' || $checkoutUrl === '') {
            throw new RuntimeException('uniple_session_missing_url');
        }

        return [
            'ok' => true,
            'sessionId' => $sessionId,
            'checkoutUrl' => $checkoutUrl,
            'payId' => (string) ($session['payId'] ?? ''),
            'status' => (string) ($session['status'] ?? ''),
            'expiresAt' => (string) ($session['expiresAt'] ?? ''),
        ];
    }

    /**
     * @return array{ok?:bool, item?:array<string,mixed>, error?:string, httpStatus:int}
     */
    public function getCheckoutSession(string $sessionId): array
    {
        if ($sessionId === '') {
            throw new InvalidArgumentException('sessionId empty');
        }
        if ($this->config['api_key'] === '') {
            throw new RuntimeException('uniple_api_key_not_configured');
        }

        $endpoint = $this->endpoint('/api/merchant/checkout/sessions/'.rawurlencode($sessionId));
        $response = wp_remote_get($endpoint, [
            'headers' => $this->commonHeaders(),
            'timeout' => self::TIMEOUT_SECONDS,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('uniple_session_lookup_network_error: '.$response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new RuntimeException('uniple_session_lookup_non_json: httpStatus='.$status);
        }

        $data['httpStatus'] = $status;

        return $data;
    }

    public function verifySignature(string $rawBody, string $sigHeader, ?string $secret = null): bool
    {
        $secret = $secret ?? $this->config['webhook_secret'];
        if ($sigHeader === '' || $secret === '') {
            return false;
        }
        $provided = preg_replace('/^sha256=/', '', trim($sigHeader)) ?? '';
        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (strlen($provided) !== strlen($expected)) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    public function toIntegerJpyc(mixed $value): int
    {
        if ($value === null || $value === '' || $value === false) {
            throw new InvalidArgumentException('amountJpyc empty');
        }
        $s = trim((string) $value);
        if ($s === '') {
            throw new InvalidArgumentException('amountJpyc empty');
        }
        if (preg_match('/^(\d+)$/', $s, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^(\d+)\.0+$/', $s, $m)) {
            return (int) $m[1];
        }
        throw new InvalidArgumentException('amountJpyc not integer-compatible: '.$s);
    }

    /**
     * @param array<string,string> $extra
     *
     * @return array<string,string>
     */
    private function commonHeaders(array $extra = []): array
    {
        return array_merge([
            'Authorization' => 'Bearer '.$this->config['api_key'],
            'Accept' => 'application/json',
            'User-Agent' => UserAgent::build(),
        ], $extra);
    }

    private function endpoint(string $path): string
    {
        $base = rtrim($this->config['api_base_url'] !== '' ? $this->config['api_base_url'] : 'https://uniple.io', '/');

        return $base.$path;
    }
}
