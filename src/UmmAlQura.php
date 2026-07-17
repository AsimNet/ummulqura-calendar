<?php

declare(strict_types=1);

namespace AsimNet\UmmAlQura;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Exact Gregorian <-> Hijri conversion on the official Saudi Umm al-Qura
 * calendar, backed by a month-start table harvested directly from the
 * official KACST conversion service (no arithmetic approximation).
 * Pure PHP, zero dependencies.
 *
 * Supported range: 1900-04-30 .. 2077-11-16 CE (1318-01-01 .. 1500-12-30 AH).
 */
final class UmmAlQura
{
    public const GREGORIAN_MIN = '1900-04-30';

    public const GREGORIAN_MAX = '2077-11-16';

    public const HIJRI_MIN_YEAR = 1318;

    public const HIJRI_MAX_YEAR = 1500;

    /**
     * Number of Hijri months elapsed before the first table entry (1318-01).
     */
    private const HIJRI_OFFSET = 1317 * 12;

    /**
     * Reduced Julian Day of the first and last supported Gregorian dates.
     */
    private const RJD_MIN = 15140;

    private const RJD_MAX = 79989;

    /**
     * @var list<int>|null
     */
    private static ?array $monthStarts = null;

    private function __construct() {}

    /**
     * Convert a Gregorian date to its Umm al-Qura Hijri date.
     *
     * @throws OutOfSupportedRangeException
     */
    public static function toHijri(DateTimeInterface $date): HijriDate
    {
        $rjd = self::gregorianToRjd($date);

        if ($rjd < self::RJD_MIN || $rjd > self::RJD_MAX) {
            throw new OutOfSupportedRangeException(sprintf(
                'Gregorian date %s is outside the supported Umm al-Qura range (%s to %s).',
                $date->format('Y-m-d'),
                self::GREGORIAN_MIN,
                self::GREGORIAN_MAX,
            ));
        }

        $monthStarts = self::monthStarts();
        $index = self::searchMonthIndex($monthStarts, $rjd);
        $months = $index + self::HIJRI_OFFSET;
        $years = intdiv($months, 12);

        return new HijriDate(
            year: $years + 1,
            month: $months - ($years * 12) + 1,
            day: $rjd - $monthStarts[$index] + 1,
        );
    }

    /**
     * Convert an Umm al-Qura Hijri date to the corresponding Gregorian date
     * (midnight UTC).
     *
     * @throws OutOfSupportedRangeException
     */
    public static function toGregorian(int $year, int $month, int $day): DateTimeImmutable
    {
        self::assertHijri($year, $month, $day);

        $monthStarts = self::monthStarts();
        $index = self::monthIndex($year, $month);
        $rjd = $monthStarts[$index] + $day - 1;

        return self::rjdToGregorian($rjd);
    }

    /**
     * Number of days (29 or 30 in the modern era) in the given Hijri month.
     *
     * @throws OutOfSupportedRangeException
     */
    public static function monthLength(int $year, int $month): int
    {
        self::assertHijri($year, $month, 1);

        $monthStarts = self::monthStarts();
        $index = self::monthIndex($year, $month);

        return $monthStarts[$index + 1] - $monthStarts[$index];
    }

    /**
     * Arabic weekday names, indexed by ISO day number (1 = Monday).
     */
    private const WEEKDAY_NAMES_AR = [
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
        7 => 'الأحد',
    ];

    /**
     * Same names without the definite article (short 'D' token form).
     */
    private const WEEKDAY_NAMES_AR_SHORT = [
        1 => 'اثنين',
        2 => 'ثلاثاء',
        3 => 'أربعاء',
        4 => 'خميس',
        5 => 'جمعة',
        6 => 'سبت',
        7 => 'أحد',
    ];

    /**
     * Format a Gregorian date as its Umm al-Qura Hijri date using the token
     * subset of {@see HijriDate::format()} plus 'l' (full Arabic weekday
     * name) and 'D' (short weekday name), which come from the Gregorian date
     * itself. Weekday substitution happens before Hijri formatting — Arabic
     * letters are never format tokens, so the substituted text is inert.
     *
     * @throws OutOfSupportedRangeException
     */
    public static function format(DateTimeInterface $date, string $pattern): string
    {
        $isoDay = (int) $date->format('N');

        return self::toHijri($date)->format(strtr($pattern, [
            'l' => self::WEEKDAY_NAMES_AR[$isoDay],
            'D' => self::WEEKDAY_NAMES_AR_SHORT[$isoDay],
        ]));
    }

    /**
     * Whether a Gregorian date — or a (year, month, day) Hijri triple — is
     * inside the supported table range.
     */
    public static function supports(DateTimeInterface|int $date, ?int $month = null, ?int $day = null): bool
    {
        if ($date instanceof DateTimeInterface) {
            $rjd = self::gregorianToRjd($date);

            return $rjd >= self::RJD_MIN && $rjd <= self::RJD_MAX;
        }

        try {
            self::assertHijri($date, $month ?? 1, $day ?? 1);
        } catch (OutOfSupportedRangeException) {
            return false;
        }

        return true;
    }

    /**
     * @throws OutOfSupportedRangeException
     */
    private static function assertHijri(int $year, int $month, int $day): void
    {
        if ($year < self::HIJRI_MIN_YEAR || $year > self::HIJRI_MAX_YEAR) {
            throw new OutOfSupportedRangeException(sprintf(
                'Hijri year must be between %d and %d, got %d.',
                self::HIJRI_MIN_YEAR,
                self::HIJRI_MAX_YEAR,
                $year,
            ));
        }

        if ($month < 1 || $month > 12) {
            throw new OutOfSupportedRangeException(sprintf('Hijri month must be between 1 and 12, got %d.', $month));
        }

        if ($day < 1) {
            throw new OutOfSupportedRangeException(sprintf('Hijri day must be positive, got %d.', $day));
        }

        $monthStarts = self::monthStarts();
        $index = self::monthIndex($year, $month);
        $length = $monthStarts[$index + 1] - $monthStarts[$index];

        if ($day > $length) {
            throw new OutOfSupportedRangeException(sprintf(
                'Hijri day must be between 1 and %d for %d-%02d, got %d.',
                $length,
                $year,
                $month,
                $day,
            ));
        }
    }

    private static function monthIndex(int $year, int $month): int
    {
        return (($year - 1) * 12) + $month - 1 - self::HIJRI_OFFSET;
    }

    /**
     * Index of the month containing $rjd: the last entry <= $rjd
     * (binary search, upper bound minus one).
     *
     * @param  list<int>  $monthStarts
     */
    private static function searchMonthIndex(array $monthStarts, int $rjd): int
    {
        $low = 0;
        $high = count($monthStarts);

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);

            if ($monthStarts[$mid] <= $rjd) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $low - 1;
    }

    /**
     * Reduced Julian Day (JDN - 2400000) of a Gregorian calendar date,
     * via the Fliegel–Van Flandern algorithm (C-style truncating division,
     * matching PHP's intdiv semantics).
     */
    private static function gregorianToRjd(DateTimeInterface $date): int
    {
        $y = (int) $date->format('Y');
        $m = (int) $date->format('n');
        $d = (int) $date->format('j');

        $a = intdiv($m - 14, 12);

        $jdn = intdiv(1461 * ($y + 4800 + $a), 4)
            + intdiv(367 * ($m - 2 - 12 * $a), 12)
            - intdiv(3 * intdiv($y + 4900 + $a, 100), 4)
            + $d - 32075;

        return $jdn - 2400000;
    }

    private static function rjdToGregorian(int $rjd): DateTimeImmutable
    {
        $jdn = $rjd + 2400000;

        $l = $jdn + 68569;
        $n = intdiv(4 * $l, 146097);
        $l -= intdiv(146097 * $n + 3, 4);
        $i = intdiv(4000 * ($l + 1), 1461001);
        $l -= intdiv(1461 * $i, 4) - 31;
        $j = intdiv(80 * $l, 2447);
        $d = $l - intdiv(2447 * $j, 80);
        $l = intdiv($j, 11);
        $m = $j + 2 - 12 * $l;
        $y = 100 * ($n - 49) + $i + $l;

        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d),
            new DateTimeZone('UTC'),
        );
    }

    /**
     * @return list<int>
     */
    private static function monthStarts(): array
    {
        return self::$monthStarts ??= require __DIR__.'/Data/MonthStarts.php';
    }
}
