<?php

declare(strict_types=1);

namespace AsimNet\UmmAlQura\Tests;

use AsimNet\UmmAlQura\HijriDate;
use AsimNet\UmmAlQura\OutOfSupportedRangeException;
use AsimNet\UmmAlQura\UmmAlQura;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UmmAlQuraTest extends TestCase
{
    /**
     * Gregorian → Hijri pairs verified against the official KACST
     * Umm al-Qura service (umqserv.kacst.gov.sa) — includes dates where the
     * common arithmetic approximations disagree by ±1 day (Eid al-Fitr and
     * Eid al-Adha 1447 among them).
     *
     * @return array<string, array{string, int, int, int}>
     */
    public static function officialPairs(): array
    {
        return [
            '2026-07-17' => ['2026-07-17', 1448, 2, 3],
            'ramadan boundary 2026-02-17' => ['2026-02-17', 1447, 8, 29],
            'eid al-fitr 1447' => ['2026-03-20', 1447, 10, 1],
            'eid al-adha 1447 window' => ['2026-05-27', 1447, 12, 10],
            '2026-12-31' => ['2026-12-31', 1448, 7, 22],
            '2027-06-01' => ['2027-06-01', 1448, 12, 26],
            '2030-01-01' => ['2030-01-01', 1451, 8, 26],
        ];
    }

    #[DataProvider('officialPairs')]
    public function test_to_hijri_matches_official_kacst_values(string $gregorian, int $y, int $m, int $d): void
    {
        $hijri = UmmAlQura::toHijri(new DateTimeImmutable($gregorian));

        $this->assertSame(
            sprintf('%04d-%02d-%02d', $y, $m, $d),
            $hijri->toIsoString(),
            "Gregorian {$gregorian} must convert to the official Umm al-Qura date",
        );
    }

    #[DataProvider('officialPairs')]
    public function test_to_gregorian_matches_official_kacst_values(string $gregorian, int $y, int $m, int $d): void
    {
        $this->assertSame(
            $gregorian,
            UmmAlQura::toGregorian($y, $m, $d)->format('Y-m-d'),
        );
    }

    /**
     * Month starts harvested directly from the official KACST conversion
     * service (ummulqura.org.sa), spread across the whole supported range.
     *
     * @return array<int, array{int, int, string}>
     */
    public static function officialMonthStarts(): array
    {
        return [
            [1318, 1, '1900-04-30'],
            [1336, 5, '1918-02-13'],
            [1354, 9, '1935-11-28'],
            [1373, 1, '1953-09-10'],
            [1391, 5, '1971-06-24'],
            [1409, 9, '1989-04-07'],
            [1428, 1, '2007-01-20'],
            [1446, 5, '2024-11-03'],
            [1464, 9, '2042-08-17'],
            [1483, 1, '2060-05-31'],
        ];
    }

    #[DataProvider('officialMonthStarts')]
    public function test_month_starts_match_the_official_service_across_eras(int $y, int $m, string $gregorian): void
    {
        $this->assertSame($gregorian, UmmAlQura::toGregorian($y, $m, 1)->format('Y-m-d'));
        $this->assertSame(
            sprintf('%04d-%02d-01', $y, $m),
            UmmAlQura::toHijri(new DateTimeImmutable($gregorian))->toIsoString(),
        );
    }

    public function test_round_trip_over_broad_sample_of_month_starts(): void
    {
        // Every 100th month across the whole supported range: convert the
        // month start (and mid-month) Hijri date to Gregorian and back.
        for ($index = 0; $index < 2196; $index += 100) {
            $months = $index + 1317 * 12;
            $year = intdiv($months, 12) + 1;
            $month = $months % 12 + 1;

            foreach ([1, 15] as $day) {
                $gregorian = UmmAlQura::toGregorian($year, $month, $day);
                $back = UmmAlQura::toHijri($gregorian);

                $this->assertTrue(
                    $back->equals(new HijriDate($year, $month, $day)),
                    "Round trip failed for {$year}-{$month}-{$day} (got {$back})",
                );
            }
        }
    }

    public function test_month_lengths_are_29_or_30_across_the_whole_range(): void
    {
        // The officially harvested table is strictly 29/30 for every month.
        // Also sanity-check the total span (183 lunar years, ~354.36 days each).
        $total = 0;

        for ($year = 1318; $year <= 1500; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $length = UmmAlQura::monthLength($year, $month);
                $this->assertContains($length, [29, 30], "Length of {$year}-{$month}");
                $total += $length;
            }
        }

        $this->assertGreaterThan(183 * 353, $total);
        $this->assertLessThan(183 * 356, $total);
    }

    public function test_boundary_dates(): void
    {
        $first = UmmAlQura::toHijri(new DateTimeImmutable(UmmAlQura::GREGORIAN_MIN));
        $this->assertSame('1318-01-01', $first->toIsoString());

        $last = UmmAlQura::toHijri(new DateTimeImmutable(UmmAlQura::GREGORIAN_MAX));
        $this->assertSame('1500-12-30', $last->toIsoString());

        $this->assertSame(UmmAlQura::GREGORIAN_MIN, UmmAlQura::toGregorian(1318, 1, 1)->format('Y-m-d'));
        $this->assertSame(UmmAlQura::GREGORIAN_MAX, UmmAlQura::toGregorian(1500, 12, 30)->format('Y-m-d'));
    }

    public function test_out_of_range_gregorian_throws(): void
    {
        $this->expectException(OutOfSupportedRangeException::class);

        UmmAlQura::toHijri(new DateTimeImmutable('2078-01-01'));
    }

    public function test_out_of_range_hijri_year_throws(): void
    {
        $this->expectException(OutOfSupportedRangeException::class);

        UmmAlQura::toGregorian(1501, 1, 1);
    }

    public function test_invalid_hijri_day_for_month_throws(): void
    {
        $length = UmmAlQura::monthLength(1447, 9);

        $this->expectException(OutOfSupportedRangeException::class);

        UmmAlQura::toGregorian(1447, 9, $length + 1);
    }

    public function test_supports(): void
    {
        $this->assertTrue(UmmAlQura::supports(new DateTimeImmutable('2026-07-17')));
        $this->assertFalse(UmmAlQura::supports(new DateTimeImmutable('2080-01-01')));
        $this->assertFalse(UmmAlQura::supports(new DateTimeImmutable('1900-01-01')));

        $this->assertTrue(UmmAlQura::supports(1447, 9, 30));
        $this->assertFalse(UmmAlQura::supports(1501, 1, 1));
        $this->assertFalse(UmmAlQura::supports(1447, 13, 1));
    }

    public function test_hijri_date_value_object(): void
    {
        $date = new HijriDate(1447, 9, 1);

        $this->assertSame('رمضان', $date->monthNameAr());
        $this->assertSame('Ramadan', $date->monthNameEn());
        $this->assertSame('1447-09-01', $date->toIsoString());
        $this->assertSame('1 رمضان 1447هـ', $date->toArabicString());
        $this->assertSame('1447-09-01', (string) $date);
    }

    /**
     * The geniusts/hijri-dates readme documents 2017-05-05 <-> 1438-08-08 as
     * its own example — the OFFICIAL Umm al-Qura table disagrees by one day
     * in both directions. These are the correct official values.
     */
    public function test_geniusts_readme_example_with_correct_official_values(): void
    {
        $this->assertSame(
            '1438-08-09',
            UmmAlQura::toHijri(new DateTimeImmutable('2017-05-05'))->toIsoString(),
        );

        $this->assertSame(
            '2017-05-04',
            UmmAlQura::toGregorian(1438, 8, 8)->format('Y-m-d'),
        );
    }

    public function test_accepts_any_datetime_interface_and_ignores_time_of_day(): void
    {
        $mutable = new \DateTime('2026-03-20 23:59:59');
        $immutable = new DateTimeImmutable('2026-03-20 00:00:00');

        $this->assertTrue(UmmAlQura::toHijri($mutable)->equals(UmmAlQura::toHijri($immutable)));
    }

    public function test_all_twelve_month_names_in_both_languages(): void
    {
        $arabic = [
            1 => 'محرم', 2 => 'صفر', 3 => 'ربيع الأول', 4 => 'ربيع الآخر',
            5 => 'جمادى الأولى', 6 => 'جمادى الآخرة', 7 => 'رجب', 8 => 'شعبان',
            9 => 'رمضان', 10 => 'شوال', 11 => 'ذو القعدة', 12 => 'ذو الحجة',
        ];
        $english = [
            1 => 'Muharram', 2 => 'Safar', 3 => "Rabi' al-Awwal", 4 => "Rabi' al-Thani",
            5 => 'Jumada al-Ula', 6 => 'Jumada al-Akhirah', 7 => 'Rajab', 8 => "Sha'ban",
            9 => 'Ramadan', 10 => 'Shawwal', 11 => "Dhu al-Qi'dah", 12 => 'Dhu al-Hijjah',
        ];

        foreach (range(1, 12) as $month) {
            $date = new HijriDate(1447, $month, 1);
            $this->assertSame($arabic[$month], $date->monthNameAr(), "Arabic name of month {$month}");
            $this->assertSame($english[$month], $date->monthNameEn(), "English name of month {$month}");
        }
    }

    public function test_format_tokens(): void
    {
        $date = new HijriDate(1447, 9, 5);

        $this->assertSame('1447/09/05', $date->format('Y/m/d'));
        $this->assertSame('1447/9/5', $date->format('Y/n/j'));
        $this->assertSame('5 رمضان 1447', $date->format('j F Y'));
        $this->assertSame('47', $date->format('y'));
        $this->assertSame('Y 1447', $date->format('\Y Y'));
    }

    public function test_gregorian_format_with_weekday_tokens(): void
    {
        // 2026-03-20 (Friday) = 1447-10-01 official.
        $date = new DateTimeImmutable('2026-03-20');

        $this->assertSame('1447/10/1', UmmAlQura::format($date, 'Y/n/j'));
        $this->assertSame('الجمعة 1 شوال 1447', UmmAlQura::format($date, 'l j F Y'));
        $this->assertSame('جمعة 1 شوال 1447', UmmAlQura::format($date, 'D j F Y'));
    }

    public function test_indic_digit_formatting(): void
    {
        $this->assertSame('٠١٢٣٤٥٦٧٨٩', HijriDate::toIndicDigits('0123456789'));

        $date = new HijriDate(1447, 9, 1);
        $this->assertSame('١ رمضان ١٤٤٧هـ', $date->toArabicString(indicDigits: true));
    }

    #[DataProvider('officialPairs')]
    public function test_weekday_is_preserved_across_conversion(string $gregorian, int $y, int $m, int $d): void
    {
        $this->assertSame(
            (new DateTimeImmutable($gregorian))->format('N'),
            UmmAlQura::toGregorian($y, $m, $d)->format('N'),
        );
    }

    public function test_known_weekdays_match_the_official_calendar(): void
    {
        // Eid al-Fitr 1447 (1447-10-01) falls on a Friday; 1448-02-03 too.
        $this->assertSame('Friday', UmmAlQura::toGregorian(1447, 10, 1)->format('l'));
        $this->assertSame('Friday', UmmAlQura::toGregorian(1448, 2, 3)->format('l'));
        $this->assertSame('Wednesday', UmmAlQura::toGregorian(1447, 12, 10)->format('l'));
    }

    public function test_to_gregorian_returns_immutable_utc_midnight(): void
    {
        $date = UmmAlQura::toGregorian(1448, 2, 3);

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('00:00:00', $date->format('H:i:s'));
        $this->assertSame('UTC', $date->getTimezone()->getName());
    }
}
