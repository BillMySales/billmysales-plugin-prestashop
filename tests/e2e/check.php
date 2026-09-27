<?php

declare(strict_types=1);

/**
 * BillMySales for PrestaShop.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the European Union Public Licence v. 1.2 (EUPL-1.2).
 * See LICENSE file for more details.
 */

/**
 * End-to-end tests: checks the deliveries a test case produced.
 *
 * Usage: php tests/e2e/check.php <webhooks dir> <from> '<expectations JSON>'
 *
 * <from> is how many requests the receiver had before the case; only the
 * later ones are checked. Expectations (all optional but "count"):
 * count, secret, platform, plugin_version, source, event, order_id, status,
 * total, meta ({key: value} of the checkout fields' values),
 * webservice (true when the payload is the "webservice" format: order
 * fields are read from payload.order instead of the payload's own root),
 * same_delivery (every request has the same X-BillMySales-Delivery),
 * schema (path of the JSON Schema the payload must match).
 *
 * Prints one line per check and exits with 1 when any fails.
 */

require __DIR__ . '/../../vendor/autoload.php';

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

[, $dir, $from, $json] = ($_SERVER['argv'] ?? []) + [null, '', '0', '{}'];
$expect = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
$files = glob($dir . '/*.json') ?: [];
sort($files);
$requests = array_slice($files, (int) $from);

$failed = false;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? '    ✔ ' : '    ✘ ') . $message . "\n";
    $failed = $failed || !$ok;
};

$check(count($requests) === $expect['count'], sprintf('%d request(s) received (expected %d)', count($requests), $expect['count']));

$deliveries = [];
foreach ($requests as $file) {
    $request = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $body = (string) file_get_contents(substr($file, 0, -5) . '.body');
    // Header names as sent (PHP's built-in server keeps their case).
    $headers = array_change_key_case($request['headers'], CASE_LOWER);
    $header = static fn (string $name): string => (string) ($headers[strtolower($name)] ?? '');
    $payload = json_decode($body, true);
    $deliveries[] = $header('X-BillMySales-Delivery');
    echo sprintf("  %s (answered %d)\n", basename($file, '.json'), $request['responded']);

    if (isset($expect['secret'])) {
        $signature = base64_encode(hash_hmac('sha256', $body, $expect['secret'], true));
        $check(hash_equals($signature, $header('X-BillMySales-Signature')), 'X-BillMySales-Signature is the HMAC-SHA256 of the body');
        $check(hash_equals($signature, $header('X-PRESTASHOPBMS-HMAC-SHA256')), 'X-PRESTASHOPBMS-HMAC-SHA256 (datasource) is the same signature');
        $leaks = array_filter($request['headers'], static fn ($value): bool => strpos((string) $value, $expect['secret']) !== false);
        $check($leaks === [], 'the secret is not in any header');
    }
    foreach (['platform' => 'X-BillMySales-Platform', 'plugin_version' => 'X-BillMySales-Plugin-Version', 'event' => 'X-BillMySales-Event'] as $key => $name) {
        if (isset($expect[$key])) {
            $check($header($name) === $expect[$key], sprintf('%s: %s', $name, $header($name)));
        }
    }
    if (isset($expect['source'])) {
        $check($header('X-BillMySales-Source') === $expect['source'], 'X-BillMySales-Source: ' . $header('X-BillMySales-Source'));
    }
    $check($header('X-BillMySales-Platform-Version') !== '', 'X-BillMySales-Platform-Version: ' . $header('X-BillMySales-Platform-Version'));
    $check((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $header('X-BillMySales-Delivery')), 'X-BillMySales-Delivery is a UUID');
    if (isset($expect['platform'], $expect['plugin_version'])) {
        $check($header('User-Agent') === 'BillMySales-' . $expect['platform'] . '/' . $expect['plugin_version'], 'User-Agent: ' . $header('User-Agent'));
    }

    $check(is_array($payload), 'the body is a JSON object');
    if (!is_array($payload)) {
        continue;
    }
    if (isset($expect['schema'])) {
        $validator = new Validator();
        $data = json_decode($body);
        $validator->validate($data, json_decode((string) file_get_contents($expect['schema'])), Constraint::CHECK_MODE_DISABLE_FORMAT);
        $errors = array_map(static fn (array $error): string => $error['property'] . ': ' . $error['message'], $validator->getErrors());
        $check($validator->isValid(), 'matches ' . basename($expect['schema']) . ($errors ? ' (' . implode('; ', $errors) . ')' : ''));
    }
    $order = !empty($expect['webservice']) ? ($payload['order'] ?? null) : $payload;
    foreach (['order_id' => 'id', 'status' => 'current_state', 'total' => 'total_paid'] as $key => $field) {
        if (isset($expect[$key]) && is_array($order)) {
            $check(($order[$field] ?? null) == $expect[$key], sprintf('%s: %s', $field, json_encode($order[$field] ?? null)));
        }
    }
    foreach ($expect['meta'] ?? [] as $key => $value) {
        $found = $payload['billmysales_custom_fields'][$key] ?? null;
        $check($found === $value, sprintf('billmysales_custom_fields %s: %s', $key, json_encode($found)));
    }
}

if (!empty($expect['same_delivery']) && $deliveries) {
    $check(count(array_unique($deliveries)) === 1, 'every request is the same delivery (retry)');
}

exit($failed ? 1 : 0);
