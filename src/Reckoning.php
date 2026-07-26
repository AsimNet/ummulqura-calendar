<?php

declare(strict_types=1);

namespace AsimNet\UmmulquraCalendar;

/**
 * Which reading of the Umm al-Qura calendar to convert against.
 *
 * The criterion behind the calendar was last revised for 1420 AH. The official
 * service applies today's criterion across its whole range, so for earlier
 * months it reconstructs the calendar rather than reproducing the almanac that
 * was printed at the time — and the two readings can differ by a day.
 */
enum Reckoning
{
    /**
     * The official KACST conversion service as it answers today, across the
     * full 1318–1500 AH range. The default, and the right choice for any
     * present-day or future date.
     */
    case Official;

    /**
     * The Umm al-Qura almanacs as they were published at the time, for
     * 1356–1419 AH. Use this to reproduce Hijri ↔ Gregorian pairings recorded
     * in documents of that period — Saudi national IDs and Ahwal Madaniyya
     * records among them.
     *
     * From 1420 AH onwards this is identical to {@see self::Official}. Before
     * 1356 AH it is unavailable and throws, because the surviving sources for
     * those months disagree with one another.
     */
    case AsPublished;
}
