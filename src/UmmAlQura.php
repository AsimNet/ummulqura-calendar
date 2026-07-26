<?php

declare(strict_types=1);

namespace AsimNet\UmmulquraCalendar;

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
 *
 * Note on eras: the Umm al-Qura criterion was revised several times, most
 * recently for 1420 AH. The official service applies today's criterion over
 * its whole range, so months before 1420 AH are a retroactive reconstruction
 * rather than a reproduction of the almanac in force at the time, and may
 * differ by a day from Saudi civil records of that period. Use
 * {@see self::isReconstructed()} to detect those dates, and pass
 * {@see Reckoning::AsPublished} to convert them the way the almanacs of the
 * time did (available for 1356 AH onwards).
 */
final class UmmAlQura
{
    public const GREGORIAN_MIN = '1900-04-30';

    public const GREGORIAN_MAX = '2077-11-16';

    public const HIJRI_MIN_YEAR = 1318;

    public const HIJRI_MAX_YEAR = 1500;

    /**
     * First Hijri year governed by the current Umm al-Qura criterion
     * (geocentric conjunction before sunset and moonset after sunset at
     * Mecca). Months from this year onwards are the published calendar;
     * earlier months are reconstructed — see the class docblock.
     */
    public const CRITERION_EPOCH_HIJRI_YEAR = 1420;

    /**
     * Gregorian equivalent of 1420-01-01 AH, pinned by the bundled table.
     */
    public const CRITERION_EPOCH_GREGORIAN = '1999-04-17';

    /**
     * Earliest Hijri year covered by {@see Reckoning::AsPublished}. Below this
     * the surviving sources for the printed almanacs disagree with each other,
     * so no as-published answer is offered.
     */
    public const AS_PUBLISHED_MIN_HIJRI_YEAR = 1356;

    /**
     * Gregorian equivalent of 1356-01-01 AH as published, pinned by the table.
     */
    public const AS_PUBLISHED_MIN_GREGORIAN = '1937-03-14';

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

    /**
     * @var list<int>|null
     */
    private static ?array $asPublishedMonthStarts = null;

    private function __construct() {}

    /**
     * Convert a Gregorian date to its Umm al-Qura Hijri date.
     *
     * @throws OutOfSupportedRangeException
     */
    public static function toHijri(DateTimeInterface $date, Reckoning $reckoning = Reckoning::Official): HijriDate
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

        if ($reckoning === Reckoning::AsPublished && $rjd < self::asPublishedMinRjd()) {
            throw new OutOfSupportedRangeException(sprintf(
                'Gregorian date %s predates the as-published table, which begins at %s (%d-01-01 AH); '
                .'the surviving sources for earlier months disagree with each other.',
                $date->format('Y-m-d'),
                self::AS_PUBLISHED_MIN_GREGORIAN,
                self::AS_PUBLISHED_MIN_HIJRI_YEAR,
            ));
        }

        $monthStarts = self::monthStarts($reckoning);
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
    public static function toGregorian(
        int $year,
        int $month,
        int $day,
        Reckoning $reckoning = Reckoning::Official,
    ): DateTimeImmutable {
        self::assertHijri($year, $month, $day, $reckoning);

        $monthStarts = self::monthStarts($reckoning);
        $index = self::monthIndex($year, $month);
        $rjd = $monthStarts[$index] + $day - 1;

        return self::rjdToGregorian($rjd);
    }

    /**
     * Number of days in the given Hijri month — 29 or 30 throughout the
     * official table; the as-published table also carries a single 28-day
     * month (Sha'ban 1364), which the early almanacs really did record.
     *
     * @throws OutOfSupportedRangeException
     */
    public static function monthLength(int $year, int $month, Reckoning $reckoning = Reckoning::Official): int
    {
        self::assertHijri($year, $month, 1, $reckoning);

        $monthStarts = self::monthStarts($reckoning);
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
    public static function format(
        DateTimeInterface $date,
        string $pattern,
        Reckoning $reckoning = Reckoning::Official,
    ): string {
        $isoDay = (int) $date->format('N');

        return self::toHijri($date, $reckoning)->format(strtr($pattern, [
            'l' => self::WEEKDAY_NAMES_AR[$isoDay],
            'D' => self::WEEKDAY_NAMES_AR_SHORT[$isoDay],
        ]));
    }

    /**
     * Whether a Gregorian date — or a (year, month, day) Hijri triple — is
     * inside the supported table range.
     */
    public static function supports(
        DateTimeInterface|int $date,
        ?int $month = null,
        ?int $day = null,
        Reckoning $reckoning = Reckoning::Official,
    ): bool {
        try {
            if ($date instanceof DateTimeInterface) {
                self::toHijri($date, $reckoning);
            } else {
                self::assertHijri($date, $month ?? 1, $day ?? 1, $reckoning);
            }
        } catch (OutOfSupportedRangeException) {
            return false;
        }

        return true;
    }

    /**
     * Whether a date predates the current Umm al-Qura criterion (1420 AH) and
     * is therefore a retroactive reconstruction rather than the calendar that
     * was published at the time.
     *
     * Conversions for such dates remain faithful to the official service, but
     * may differ by a day from what Saudi civil records (national ID, Ahwal
     * Madaniyya) hold for the same Hijri date. To match those records, convert
     * with {@see Reckoning::AsPublished} instead.
     *
     * Accepts either a Gregorian date or a Hijri (year, month, day) triple,
     * mirroring {@see self::supports()}; for a triple only the year is
     * consulted, since the era boundary falls on 1420-01-01. Never throws —
     * the answer is well defined for dates outside the supported range too.
     */
    public static function isReconstructed(DateTimeInterface|int $date, ?int $month = null, ?int $day = null): bool
    {
        if ($date instanceof DateTimeInterface) {
            return self::gregorianToRjd($date) < self::criterionEpochRjd();
        }

        return $date < self::CRITERION_EPOCH_HIJRI_YEAR;
    }

    /**
     * Reduced Julian Day of 1420-01-01 AH, read off the bundled table so the
     * epoch can never drift away from the data.
     */
    private static function criterionEpochRjd(): int
    {
        return self::monthStarts()[self::monthIndex(self::CRITERION_EPOCH_HIJRI_YEAR, 1)];
    }

    /**
     * @throws OutOfSupportedRangeException
     */
    private static function assertHijri(
        int $year,
        int $month,
        int $day,
        Reckoning $reckoning = Reckoning::Official,
    ): void {
        if ($year < self::HIJRI_MIN_YEAR || $year > self::HIJRI_MAX_YEAR) {
            throw new OutOfSupportedRangeException(sprintf(
                'Hijri year must be between %d and %d, got %d.',
                self::HIJRI_MIN_YEAR,
                self::HIJRI_MAX_YEAR,
                $year,
            ));
        }

        if ($reckoning === Reckoning::AsPublished && $year < self::AS_PUBLISHED_MIN_HIJRI_YEAR) {
            throw new OutOfSupportedRangeException(sprintf(
                'Hijri year %d predates the as-published table, which begins at %d; '
                .'the surviving sources for earlier months disagree with each other.',
                $year,
                self::AS_PUBLISHED_MIN_HIJRI_YEAR,
            ));
        }

        if ($month < 1 || $month > 12) {
            throw new OutOfSupportedRangeException(sprintf('Hijri month must be between 1 and 12, got %d.', $month));
        }

        if ($day < 1) {
            throw new OutOfSupportedRangeException(sprintf('Hijri day must be positive, got %d.', $day));
        }

        $monthStarts = self::monthStarts($reckoning);
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
    private static function monthStarts(Reckoning $reckoning = Reckoning::Official): array
    {
        if ($reckoning === Reckoning::AsPublished) {
            return self::$asPublishedMonthStarts ??= self::buildAsPublishedMonthStarts();
        }

        return self::$monthStarts ??= require __DIR__.'/Data/MonthStarts.php';
    }

    /**
     * The official table with the 1356-01 .. 1419-12 span replaced by the
     * month starts the almanacs of that era actually carried. Entries below
     * 1356 are left untouched and are never reachable — assertHijri() and
     * toHijri() reject them for this reckoning — and 1420 onwards is identical
     * in both readings, so only the middle span is overridden.
     *
     * @return list<int>
     */
    private static function buildAsPublishedMonthStarts(): array
    {
        $table = self::monthStarts();
        $overrides = require __DIR__.'/Data/MonthStartsAsPublished.php';

        array_splice(
            $table,
            self::monthIndex(self::AS_PUBLISHED_MIN_HIJRI_YEAR, 1),
            count($overrides),
            $overrides,
        );

        return $table;
    }

    /**
     * Reduced Julian Day of 1356-01-01 AH as published — the earliest date the
     * as-published reckoning will answer for.
     */
    private static function asPublishedMinRjd(): int
    {
        return self::monthStarts(Reckoning::AsPublished)[
            self::monthIndex(self::AS_PUBLISHED_MIN_HIJRI_YEAR, 1)
        ];
    }
}
