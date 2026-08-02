# Ummulqura Calendar

**Exact Gregorian ↔ Hijri conversion on the official Saudi Umm al-Qura calendar, for PHP.**

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
![PHP 8.1+](https://img.shields.io/badge/php-%E2%89%A5%208.1-777bb4.svg)
![Zero dependencies](https://img.shields.io/badge/dependencies-none-brightgreen.svg)

<p align="center">
  <img
    src="https://raw.githubusercontent.com/AsimNet/ummulqura-calendar/main/art/demo.gif"
    alt="Terminal session converting 2026-03-20 to 1 Shawwal 1447 and back, printing Arabic month names, Ramadan's length, and the two readings of a pre-1420 date"
    width="851">
</p>

<p align="center">
  <sub>
    Recorded from <a href="tools/demo.php"><code>tools/demo.php</code></a> —
    <a href="https://raw.githubusercontent.com/AsimNet/ummulqura-calendar/main/art/demo.mp4">MP4 version</a>
  </sub>
</p>

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
  a retroactive reconstruction, and converts them the way the almanacs of the
  time did, so pairings on old IDs come back intact
  ([why this matters](#eras-and-civil-records))
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

Run the same tour locally — no install required:

```bash
php tools/demo.php
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

<p align="center">
  <img
    src="https://raw.githubusercontent.com/AsimNet/ummulqura-calendar/main/art/calendar.png"
    alt="Ramadan 1447 and Shawwal 1447 rendered as month grids with their Gregorian dates, 1 Shawwal marked as Eid al-Fitr on Friday 20 March 2026"
    width="851">
</p>

<p align="center">
  <sub>
    Every cell above is a lookup in the bundled tables
    (<a href="tools/render-calendar.php"><code>tools/render-calendar.php</code></a>)
  </sub>
</p>

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

A pre-1420 conversion can differ from Saudi civil records holding the same Hijri
date — usually by a day, and for 15 of the 768 covered months by **two**. The
official service pairs 24 Shawwal 1415 with 1995-03-26, while national IDs
issued from the almanac in force then pair it with 1995-03-25. Neither is a bug;
they answer different questions.

Pick the question you actually want answered:

```php
use AsimNet\UmmulquraCalendar\Reckoning;

// The official service as it answers today — the default, unchanged.
UmmAlQura::toGregorian(1415, 10, 24);                    // 1995-03-26
UmmAlQura::toHijri(new DateTimeImmutable('1995-03-25')); // 1415-10-23

// The almanac as published at the time — what the ID carries.
UmmAlQura::toGregorian(1415, 10, 24, Reckoning::AsPublished);                    // 1995-03-25
UmmAlQura::toHijri(new DateTimeImmutable('1995-03-25'), Reckoning::AsPublished); // 1415-10-24
```

`Reckoning` is an optional trailing argument on `toHijri()`, `toGregorian()`,
`monthLength()`, `format()` and `supports()`, defaulting to
`Reckoning::Official` everywhere — no existing behaviour changes.

To spot affected dates without converting twice:

```php
UmmAlQura::isReconstructed(1415, 10, 24);                        // true
UmmAlQura::isReconstructed(new DateTimeImmutable('1995-03-25')); // true
UmmAlQura::isReconstructed(1447, 10, 1);                         // false

UmmAlQura::CRITERION_EPOCH_HIJRI_YEAR;  // 1420
UmmAlQura::CRITERION_EPOCH_GREGORIAN;   // '1999-04-17'
UmmAlQura::AS_PUBLISHED_MIN_HIJRI_YEAR; // 1356
UmmAlQura::AS_PUBLISHED_MIN_GREGORIAN;  // '1937-03-14'
```

`isReconstructed()` accepts a Gregorian date or a Hijri triple, mirroring
`supports()`, and never throws — it is well defined outside the supported range
too.

Storing a date of birth off an ID? Keep both fields exactly as the document
prints them, and reach for `Reckoning::AsPublished` when you must derive one
from the other.

### The as-published table

| | |
|---|---|
| Range | 1356-01 … 1419-12 AH (1937-03-14 … 1999-04-16 CE) — 768 months |
| Source | the Umm al-Qura dataset of [`hijridate`](https://github.com/dralshehri/hijridate) 2.5.0 by Mohammed Alshehri (MIT), compiled from the printed almanacs |
| Validation | every one of the 768 months cross-checked against an independent implementation of the same historical calendar ([AlAdhan](https://aladhan.com/), `calendarMethod=HJCoSA`) — **zero disagreements** |
| Divergence | 484 of 768 months (63%) start on a different Gregorian day than the official table: 452 by one day earlier, 15 by two days earlier, 17 by one day later |
| 1420 AH onwards | identical to `Reckoning::Official`, verified month by month |

Two deliberate limits:

- **It starts at 1356, not at 1343** (`hijridate`'s own lower bound). Across
  1343–1355 the two sources disagree on 74 of 156 months, so the record there
  is not settled enough to ship. Asking for an earlier date with
  `Reckoning::AsPublished` throws `OutOfSupportedRangeException` instead of
  guessing.
- **Shaʿban 1364 is 28 days long.** Both sources carry it, so it is preserved
  rather than smoothed to 29 — the early almanacs really did record months like
  this. `monthLength()` can therefore return 28 under this reckoning.

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
(MIT). The official table's data is scraped from KACST as described above; the
separate [as-published table](#the-as-published-table) used by
`Reckoning::AsPublished` comes from hijridate's own Umm al-Qura dataset, with
thanks — see that section for its range and validation.

## Testing

The suite is self-contained — no `composer install` needed inside the package:

```bash
phpunit -c phpunit.xml
```

The README images are generated from the library itself and can be rebuilt
with `php tools/render-demo.php` and `php tools/render-calendar.php` (they
need `rsvg-convert`, `ffmpeg`, and the JetBrains Mono / Noto Naskh Arabic
fonts).

## License

[MIT](LICENSE)
