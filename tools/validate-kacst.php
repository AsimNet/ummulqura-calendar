<?php

declare(strict_types=1);

/*
 * One-off validation against the official KACST Umm al-Qura web service.
 * NOT part of the test suite (network access + the service's exact endpoint
 * are required). Usage:
 *
 *   php tools/validate-kacst.php "https://umqserv.kacst.gov.sa/<endpoint>?y=%d&m=%d&d=%d" [samples=40]
 *
 * The endpoint template receives the GREGORIAN year, month, day (sprintf
 * placeholders) and must return JSON containing the Hijri year/month/day
 * (keys matched case-insensitively: hy/hm/hd or year/month/day). TLS
 * verification is disabled (the service's chain is not always resolvable
 * from build environments) — equivalent to `curl -k`.
 */

require_once __DIR__.'/../tests/bootstrap.php';

use AsimNet\UmmAlQura\UmmAlQura;

$template = $argv[1] ?? null;
$samples = (int) ($argv[2] ?? 40);

if ($template === null) {
    fwrite(STDERR, "Usage: php tools/validate-kacst.php <endpoint-template> [samples]\n");
    exit(1);
}

mt_srand(2026);

$start = new DateTimeImmutable(UmmAlQura::GREGORIAN_MIN);
$end = new DateTimeImmutable(UmmAlQura::GREGORIAN_MAX);
$span = (int) $start->diff($end)->format('%a');

$matches = 0;
$failures = [];

for ($i = 0; $i < $samples; $i++) {
    $date = $start->modify('+'.mt_rand(0, $span).' days');
    $url = sprintf($template, (int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));

    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['timeout' => 15],
    ]);

    $body = @file_get_contents($url, false, $context);

    if ($body === false) {
        $failures[] = "{$date->format('Y-m-d')}: request failed";

        continue;
    }

    $payload = array_change_key_case((array) json_decode($body, true));
    $year = (int) ($payload['hy'] ?? $payload['year'] ?? 0);
    $month = (int) ($payload['hm'] ?? $payload['month'] ?? 0);
    $day = (int) ($payload['hd'] ?? $payload['day'] ?? 0);

    $local = UmmAlQura::toHijri($date);

    if ($local->year === $year && $local->month === $month && $local->day === $day) {
        $matches++;
    } else {
        $failures[] = "{$date->format('Y-m-d')}: local {$local->toIsoString()} vs official {$year}-{$month}-{$day}";
    }
}

echo "Matched {$matches}/{$samples}\n";

foreach ($failures as $failure) {
    echo "  {$failure}\n";
}

exit($failures === [] ? 0 : 1);
