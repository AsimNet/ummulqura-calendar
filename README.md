# Ummulqura Calendar for PHP

> Composer package: `asimnet/ummulqura-calendar` — the PHP namespace remains
> `AsimNet\UmmulquraCalendar`.

Exact Gregorian ↔ Hijri date conversion on the **official Saudi Umm al-Qura
calendar**, backed by a month-start table **harvested directly from the
official KACST conversion service** (ummulqura.org.sa) — not an arithmetic
approximation. Pure PHP, **zero runtime dependencies**.

Common arithmetic Hijri algorithms drift by ±1 day from the official calendar
on many dates (including Eid al-Fitr and Eid al-Adha). This library looks the
answer up in the official tables instead, so it always matches the calendar
Saudi Arabia actually uses.

## Installation

```bash
composer require asimnet/ummulqura-calendar
```

Requires PHP 8.1+.

## Usage

```php
use AsimNet\UmmulquraCalendar\UmmAlQura;
use AsimNet\UmmulquraCalendar\HijriDate;

// Gregorian → Hijri
$hijri = UmmAlQura::toHijri(new DateTimeImmutable('2026-03-20'));
$hijri->year;            // 1447
$hijri->month;           // 10
$hijri->day;             // 1  (Eid al-Fitr)
$hijri->monthNameAr();   // 'شوال'
$hijri->monthNameEn();   // 'Shawwal'
$hijri->toIsoString();   // '1447-10-01'
$hijri->toArabicString();// '1 شوال 1447هـ'

// Hijri → Gregorian (returns DateTimeImmutable, midnight UTC)
UmmAlQura::toGregorian(1447, 10, 1)->format('Y-m-d'); // '2026-03-20'

// Month length (29 or 30)
UmmAlQura::monthLength(1447, 9); // days in Ramadan 1447

// Range checks
UmmAlQura::supports(new DateTimeImmutable('2080-01-01')); // false
UmmAlQura::supports(1447, 9, 30);                          // true
```

Any `DateTimeInterface` is accepted (including `Carbon`); only the calendar
date (Y-m-d) is read — time and timezone are ignored.

## Supported range

The official tables cover:

| Calendar  | From         | To           |
|-----------|--------------|--------------|
| Gregorian | 1900-04-30   | 2077-11-16   |
| Hijri     | 1318-01-01   | 1500-12-30   |

Dates outside this range throw `AsimNet\UmmulquraCalendar\OutOfSupportedRangeException`.

## Why not an arithmetic Hijri library?

Arithmetic implementations approximate the lunar calendar and drift from the
official Umm al-Qura table — and even table-based datasets can diverge from
the official converter. Verified against the official KACST service:

- `geniusts/hijri-dates` (arithmetic, ships **no test suite**): ±1 day on
  many dates, including both Eids of 1447.
- the `hijridate` (Python) dataset: **740 of 1,897 month starts (39%)**
  differ from the official conversion service.
- **this package: exact** — every one of the 2,196 bundled months was
  fetched from the official service itself (zero failures, strictly
  monotonic, all lengths 29/30, weekday-consistent).

Sample `geniusts/hijri-dates` divergences:

| Gregorian   | Official Umm al-Qura | geniusts/hijri-dates |
|-------------|----------------------|----------------------|
| 2026-02-17  | 1447-08-29           | 1447-09-01 ✗         |
| 2026-03-20 (عيد الفطر) | 1447-10-01 | off by ±1 ✗        |
| 2026-05-27 (نحو عيد الأضحى) | 1447-12-10 | off by ±1 ✗   |
| 2030-01-01  | 1451-08-26           | off by ±1 ✗          |

Even the example in its own README (`2017-05-05 ↔ 1438-08-08`) is one day off
the official table (2017-05-05 is actually **1438-08-09**). This library
matches the official table exactly for every date in 1343–1500 AH.

There are no `now()`/`today()` convenience helpers by design — the library is
stateless; `UmmAlQura::toHijri(new DateTimeImmutable)` covers "today" and
stays trivially testable.

## Data source & attribution

The bundled month-start table (`src/Data/MonthStarts.php`) was **scraped
directly from the official Umm al-Qura calendar website:
<https://www.ummulqura.org.sa/ar>** (its KACST conversion API — King
Abdulaziz City for Science and Technology, Saudi Arabia): one lookup per
Hijri month, all 2,196 months verified (strictly monotonic, 29/30-day
lengths, weekday-consistent) with zero failures.

The table layout (reduced-Julian-day month starts + binary search) was
inspired by the [hijridate](https://github.com/dralshehri/hijridate) Python
package by Mohammed Alshehri (MIT) — the data itself is KACST-harvested.

A validation script that cross-checks the conversion against the official
KACST web service lives in [`tools/validate-kacst.php`](tools/validate-kacst.php)
(network access required; not part of the test suite).

## Testing

The test suite is self-contained (no composer install needed inside the
package):

```bash
phpunit -c phpunit.xml
```

## License

MIT — see [LICENSE](LICENSE).
