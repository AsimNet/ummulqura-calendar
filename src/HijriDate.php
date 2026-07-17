<?php

declare(strict_types=1);

namespace AsimNet\UmmulquraCalendar;

/**
 * An immutable Hijri (Umm al-Qura) calendar date.
 */
final class HijriDate
{
    private const MONTH_NAMES_AR = [
        1 => 'محرم',
        2 => 'صفر',
        3 => 'ربيع الأول',
        4 => 'ربيع الآخر',
        5 => 'جمادى الأولى',
        6 => 'جمادى الآخرة',
        7 => 'رجب',
        8 => 'شعبان',
        9 => 'رمضان',
        10 => 'شوال',
        11 => 'ذو القعدة',
        12 => 'ذو الحجة',
    ];

    private const MONTH_NAMES_EN = [
        1 => 'Muharram',
        2 => 'Safar',
        3 => "Rabi' al-Awwal",
        4 => "Rabi' al-Thani",
        5 => 'Jumada al-Ula',
        6 => 'Jumada al-Akhirah',
        7 => 'Rajab',
        8 => "Sha'ban",
        9 => 'Ramadan',
        10 => 'Shawwal',
        11 => "Dhu al-Qi'dah",
        12 => 'Dhu al-Hijjah',
    ];

    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
    ) {}

    public function monthNameAr(): string
    {
        return self::MONTH_NAMES_AR[$this->month];
    }

    public function monthNameEn(): string
    {
        return self::MONTH_NAMES_EN[$this->month];
    }

    /**
     * ISO-like numeric format, e.g. "1447-09-01".
     */
    public function toIsoString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    /**
     * Arabic display format, e.g. "1 رمضان 1447هـ" — or with Eastern Arabic
     * (Indic) numerals, "١ رمضان ١٤٤٧هـ".
     */
    public function toArabicString(bool $indicDigits = false): string
    {
        $formatted = sprintf('%d %s %dهـ', $this->day, $this->monthNameAr(), $this->year);

        return $indicDigits ? self::toIndicDigits($formatted) : $formatted;
    }

    /**
     * Replace Western Arabic digits (0-9) with Eastern Arabic (Indic)
     * numerals (٠-٩).
     */
    public static function toIndicDigits(string $value): string
    {
        return strtr($value, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);
    }

    /**
     * Format using a subset of PHP date() tokens applied to the HIJRI
     * components: Y (4-digit year), y (2-digit year), m (zero-padded month),
     * n (month), d (zero-padded day), j (day), F (Arabic month name).
     * A backslash escapes the next character. Digits are Western; wrap with
     * {@see toIndicDigits()} for Eastern Arabic numerals.
     */
    public function format(string $pattern): string
    {
        $out = '';
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];

            if ($char === '\\' && $i + 1 < $length) {
                $out .= $pattern[++$i];

                continue;
            }

            $out .= match ($char) {
                'Y' => sprintf('%04d', $this->year),
                'y' => substr(sprintf('%04d', $this->year), -2),
                'm' => sprintf('%02d', $this->month),
                'n' => (string) $this->month,
                'd' => sprintf('%02d', $this->day),
                'j' => (string) $this->day,
                'F' => $this->monthNameAr(),
                default => $char,
            };
        }

        return $out;
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year
            && $this->month === $other->month
            && $this->day === $other->day;
    }

    public function __toString(): string
    {
        return $this->toIsoString();
    }
}
