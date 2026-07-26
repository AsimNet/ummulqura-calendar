# Ummulqura Calendar

**Exact Gregorian ↔ Hijri conversion on the official Saudi Umm al-Qura calendar, for PHP.**

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
![PHP 8.1+](https://img.shields.io/badge/php-%E2%89%A5%208.1-777bb4.svg)
![Zero dependencies](https://img.shields.io/badge/dependencies-none-brightgreen.svg)

Most Hijri libraries *calculate* the lunar calendar arithmetically and drift by
±1 day from the calendar Saudi Arabia actually uses — including on Eid al-Fitr
and Eid al-Adha. This library doesn't calculate anything: it looks the answer
up in the **official Umm al-Qura month tables**, scraped directly from
[ummulqura.org.sa](https://www.ummulqura.org.sa/ar), so every conversion
matches the official calendar exactly.

One caveat no library can remove: the official calendar itself only settled on
its present criterion in 1420 AH, so dates before April 1999 need care — see
[Eras and civil records](#eras-and-civil-records).

- 🎯 **Exact** — all 2,196 bundled months fetched from the official KACST
  conversion service and verified in both directions
- 🪶 **Zero dependencies** — pure PHP, works with any `DateTimeInterface`
  (including Carbon)
- 🗓️ **Wide range** — 1318–1500 AH (1900–2077 CE)
- 🧭 **Era-aware** — flags dates before 1420 AH, where the official calendar is
  a retroactive reconstruction ([why this matters](#eras-and-civil-records))
- 🧪 **Proven** — self-contained test suite pinning officially verified dates,
  round-trips, boundaries and month lengths

[اقرأ بالعربية](README.ar.md)

## Installation

```bash
composer require asimnet/ummulqura-calendar
```

Requires PHP 8.1 or newer. No extensions, no framework.

## Quick start

```php
use AsimNet\UmmulquraCalendar\UmmAlQura;

$hijri = UmmAlQura::toHijri(new DateTimeImmutable('2026-03-20'));

$hijri->year;             // 1447
$hijri->month;            // 10
$hijri->day;              // 1  — Eid al-Fitr
$hijri->monthNameAr();    // 'شوال'
$hijri->monthNameEn();    // 'Shawwal'
$hijri->toIsoString();    // '1447-10-01'
$hijri->toArabicString(); // '1 شوال 1447هـ'
```

```php
// Hijri → Gregorian (DateTimeImmutable, midnight UTC)
UmmAlQura::toGregorian(1447, 10, 1)->format('Y-m-d'); // '2026-03-20'
```

## Usage

### Converting dates

Any `DateTimeInterface` is accepted — `DateTime`, `DateTimeImmutable`, Carbon…
Only the calendar date (Y‑m‑d) is read; time of day and timezone are ignored.

```php
$hijri = UmmAlQura::toHijri(Carbon\Carbon::parse('2026-07-17'));
$greg  = UmmAlQura::toGregorian($hijri->year, $hijri->month, $hijri->day);
```

There are deliberately no `now()`/`today()` helpers and no global state:
`UmmAlQura::toHijri(new DateTimeImmutable)` is "today", and everything stays
trivially testable.

### Month lengths

```php
UmmAlQura::monthLength(1447, 9); // days in Ramadan 1447 (29 or 30)
```

### Range checking

The official tables cover a fixed range. Check before converting, or catch the
dedicated exception:

```php
UmmAlQura::supports(new DateTimeImmutable('2080-01-01')); // false
UmmAlQura::supports(1447, 9, 30);                         // true

try {
    UmmAlQura::toHijri(new DateTimeImmutable('1850-01-01'));
} catch (AsimNet\UmmulquraCalendar\OutOfSupportedRangeException $e) {
    // fall back to whatever suits your app
}
```

| Calendar  | From       | To         |
|-----------|------------|------------|
| Hijri     | 1318-01-01 | 1500-12-30 |
| Gregorian | 1900-04-30 | 2077-11-16 |

## Accuracy

Verified against the official KACST conversion service:

| Library | Result |
|---------|--------|
| **This package** | **Exact — 2,196/2,196 official months match, both directions** |
| `geniusts/hijri-dates` (arithmetic, no test suite) | ±1 day on many dates, including both Eids of 1447 |

A few examples of arithmetic drift:

| Gregorian | Official Umm al-Qura | `geniusts/hijri-dates` |
|-----------|----------------------|------------------------|
| 2026-02-17 | 29 Shaʿban 1447 | 1 Ramadan 1447 ✗ |
| 2026-03-20 | **1 Shawwal 1447 (Eid al-Fitr)** | 2 Shawwal ✗ |
| 2026-05-27 | **10 Dhu al-Hijjah 1447 (Eid al-Adha)** | 11 Dhu al-Hijjah ✗ |
| 2017-05-05 | 9 Shaʿban 1438 | 8 Shaʿban ✗ (its own README example) |

## Eras and civil records

The Umm al-Qura criterion has been revised several times, most recently for
**1420 AH**. The official service applies today's criterion across its entire
range, so months before 1420 are a *retroactive reconstruction* — not a
reproduction of the almanac that was actually published at the time.

This is measurable. Comparing the bundled table month start by month start
against the independent, table-based
[`hijridate`](https://github.com/dralshehri/hijridate) dataset (v2.5.0), over
the range both cover:

| Hijri era | Month starts that differ | |
|---|---|---|
| 1343–1419 — before the current criterion | 529 / 924 | 57% |
| **1420–1445 — under the current criterion** | **0 / 312** | **0%** |
| 1446–1500 — remainder of the table | 211 / 660 | 32% |

Where the criterion is current and the dates are not far-future, two
independently built tables agree **perfectly, 312 months out of 312**. That is
the real accuracy signal. A single headline "39% of month starts differ" figure
across the whole range obscures it, because the disagreement lives almost
entirely in the reconstructed past — and in a far-future band whose cause is
not investigated here.

### What this means in practice

A pre-1420 conversion can differ by a day from Saudi civil records holding the
same Hijri date. The official service pairs 24 Shawwal 1415 with 1995-03-26,
while national IDs issued from the almanac in force then pair it with
1995-03-25. Neither is a bug; they answer different questions.

So for a Hijri date of birth taken from a document issued before April 1999, do
not expect this library — or any library — to reproduce the Gregorian date
printed beside it. Store what the document says, and convert only for display.

```php
UmmAlQura::isReconstructed(1415, 10, 24);                        // true
UmmAlQura::isReconstructed(new DateTimeImmutable('1995-03-25')); // true
UmmAlQura::isReconstructed(1447, 10, 1);                         // false

UmmAlQura::CRITERION_EPOCH_HIJRI_YEAR; // 1420
UmmAlQura::CRITERION_EPOCH_GREGORIAN;  // '1999-04-17'
```

`isReconstructed()` accepts a Gregorian date or a Hijri triple, mirroring
`supports()`, and never throws — it is well defined outside the supported range
too.

## Data source

The bundled month-start table was scraped, one lookup per Hijri month, from the
official Umm al-Qura website — **<https://www.ummulqura.org.sa/ar>** — operated
by KACST (King Abdulaziz City for Science and Technology, Saudi Arabia), and
validated end-to-end: zero failed lookups, strictly monotonic month starts, all
month lengths 29/30, and weekdays consistent with the service's own output.

A re-validation script that cross-checks conversions against the live official
service ships in [`tools/validate-kacst.php`](tools/validate-kacst.php)
(network required; intentionally not part of the test suite).

The reduced-Julian-day table layout was inspired by
[hijridate](https://github.com/dralshehri/hijridate) by Mohammed Alshehri
(MIT); the data itself is scraped from the official source.

## Testing

The suite is self-contained — no `composer install` needed inside the package:

```bash
phpunit -c phpunit.xml
```

## License

[MIT](LICENSE)
