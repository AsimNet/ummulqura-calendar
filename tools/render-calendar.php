<?php

declare(strict_types=1);

/*
 * Renders the README calendar card: two Umm al-Qura months side by side,
 * every cell taken from the bundled official tables.
 *
 *   php tools/render-calendar.php [output-dir=art]
 *
 * Build script for the README image, not part of the shipped runtime.
 * Requires `rsvg-convert` (librsvg) plus the JetBrains Mono and
 * Noto Naskh Arabic fonts.
 */

require_once __DIR__.'/../tests/bootstrap.php';

use AsimNet\UmmulquraCalendar\HijriDate;
use AsimNet\UmmulquraCalendar\UmmAlQura;

const FONT = 'JetBrains Mono, DejaVu Sans Mono, monospace';
const FONT_AR = 'Noto Naskh Arabic, DejaVu Sans';

const WIDTH = 851.0;
const OUTER = 24.0;
const GUTTER = 18.0;
const PANEL_PADDING = 20.0;
const CELL_WIDTH = 49.0;
const CELL_HEIGHT = 46.0;
const HEADER_HEIGHT = 92.0;
const FOOTER_HEIGHT = 46.0;

const BACKDROP = '#010409';
const CARD_BG = '#0d1117';
const PANEL_BG = '#0f151d';
const BORDER = '#30363d';
const INK = '#e6edf3';
const DIM = '#8b949e';
const FAINT = '#6e7681';
const ACCENT = '#1f6feb';
const HIGHLIGHT_INK = '#ffffff';
const WEEKEND = '#f0883e';

/**
 * The two months on the card, with the day that gets the accent chip.
 *
 * @var list<array{year: int, month: int, highlight: int, caption: string}>
 */
$months = [
    ['year' => 1447, 'month' => 9, 'highlight' => 1, 'caption' => 'first day of Ramadan'],
    ['year' => 1447, 'month' => 10, 'highlight' => 1, 'caption' => 'Eid al-Fitr'],
];

function escapeXml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * @param  array{size?: float, fill?: string, family?: string, anchor?: string, weight?: string}  $options
 */
function text(string $value, float $x, float $y, array $options = []): string
{
    return sprintf(
        '<text x="%.2f" y="%.2f" font-family="%s" font-size="%.1f" fill="%s" text-anchor="%s" font-weight="%s">%s</text>',
        $x,
        $y,
        escapeXml($options['family'] ?? FONT),
        $options['size'] ?? 13.0,
        $options['fill'] ?? INK,
        $options['anchor'] ?? 'start',
        $options['weight'] ?? 'normal',
        escapeXml($value),
    );
}

/**
 * One month panel: header, weekday row, and a cell per day of the Hijri
 * month positioned by the real Gregorian weekday.
 *
 * @param  array{year: int, month: int, highlight: int, caption: string}  $spec
 */
function renderPanel(array $spec, float $originX, float $originY, float $panelWidth, float $panelHeight): string
{
    $length = UmmAlQura::monthLength($spec['year'], $spec['month']);
    $first = UmmAlQura::toGregorian($spec['year'], $spec['month'], 1);
    $last = UmmAlQura::toGregorian($spec['year'], $spec['month'], $length);
    $hijri = new HijriDate($spec['year'], $spec['month'], 1);

    $svg = sprintf(
        '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="10" fill="%s" stroke="%s"/>',
        $originX,
        $originY,
        $panelWidth,
        $panelHeight,
        PANEL_BG,
        BORDER,
    );

    $left = $originX + PANEL_PADDING;
    $right = $originX + $panelWidth - PANEL_PADDING;

    $svg .= text(
        sprintf('%s %d', $hijri->monthNameEn(), $spec['year']),
        $left,
        $originY + 32,
        ['size' => 16, 'weight' => 'bold'],
    );
    $svg .= text(
        HijriDate::toIndicDigits(sprintf('%s %d', $hijri->monthNameAr(), $spec['year'])),
        $right,
        $originY + 32,
        ['size' => 16, 'family' => FONT_AR, 'anchor' => 'end', 'fill' => INK],
    );
    $svg .= text(
        sprintf('%s – %s', $first->format('j M Y'), $last->format('j M Y')),
        $left,
        $originY + 54,
        ['size' => 12, 'fill' => DIM],
    );
    $svg .= text(
        sprintf('%d days', $length),
        $right,
        $originY + 54,
        ['size' => 12, 'fill' => DIM, 'anchor' => 'end'],
    );

    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $gridTop = $originY + HEADER_HEIGHT - 18;

    foreach ($weekdays as $column => $name) {
        $svg .= text(
            $name,
            $left + ($column + 0.5) * CELL_WIDTH,
            $gridTop,
            ['size' => 11, 'fill' => in_array($name, ['Fri', 'Sat'], true) ? WEEKEND : FAINT, 'anchor' => 'middle'],
        );
    }

    /* Sunday-first columns, matching the printed Umm al-Qura calendar. */
    $column = (int) $first->format('w');
    $row = 0;

    for ($day = 1; $day <= $length; $day++) {
        $date = UmmAlQura::toGregorian($spec['year'], $spec['month'], $day);
        $cellX = $left + $column * CELL_WIDTH;
        $cellY = $gridTop + 12 + $row * CELL_HEIGHT;
        $isHighlight = $day === $spec['highlight'];

        if ($isHighlight) {
            $svg .= sprintf(
                '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="8" fill="%s"/>',
                $cellX + 2,
                $cellY + 2,
                CELL_WIDTH - 4,
                CELL_HEIGHT - 6,
                ACCENT,
            );
        }

        $svg .= text(
            (string) $day,
            $cellX + CELL_WIDTH / 2,
            $cellY + 22,
            ['size' => 15, 'anchor' => 'middle', 'fill' => $isHighlight ? HIGHLIGHT_INK : INK],
        );
        $svg .= text(
            $date->format('j') === '1' ? $date->format('j M') : $date->format('j'),
            $cellX + CELL_WIDTH / 2,
            $cellY + 35,
            ['size' => 9.5, 'anchor' => 'middle', 'fill' => $isHighlight ? '#c9defa' : FAINT],
        );

        $column++;

        if ($column === 7) {
            $column = 0;
            $row++;
        }
    }

    $highlightDate = UmmAlQura::toGregorian($spec['year'], $spec['month'], $spec['highlight']);
    $footerY = $originY + $panelHeight - 18;

    $svg .= sprintf('<circle cx="%.2f" cy="%.2f" r="4" fill="%s"/>', $left + 4, $footerY - 4, ACCENT);
    $svg .= text(
        sprintf(
            '%d %s = %s  ·  %s',
            $spec['highlight'],
            $hijri->monthNameEn(),
            $highlightDate->format('D j M Y'),
            $spec['caption'],
        ),
        $left + 16,
        $footerY,
        ['size' => 11.5, 'fill' => DIM],
    );

    return $svg;
}

/**
 * Week rows the month occupies once its first day is placed in the correct
 * Sunday-first column.
 *
 * @param  array{year: int, month: int, highlight: int, caption: string}  $spec
 */
function weekRows(array $spec): int
{
    $first = (int) UmmAlQura::toGregorian($spec['year'], $spec['month'], 1)->format('w');

    return (int) ceil(($first + UmmAlQura::monthLength($spec['year'], $spec['month'])) / 7);
}

$panelWidth = (WIDTH - OUTER * 2 - GUTTER) / 2;
$panelHeight = HEADER_HEIGHT + max(array_map('weekRows', $months)) * CELL_HEIGHT + FOOTER_HEIGHT;
$height = OUTER * 2 + $panelHeight + 34;

$svg = sprintf(
    '<svg xmlns="http://www.w3.org/2000/svg" width="%1$.0f" height="%2$.0f" viewBox="0 0 %1$.0f %2$.0f">',
    WIDTH,
    $height,
);
$svg .= sprintf('<rect width="%.0f" height="%.0f" fill="%s"/>', WIDTH, $height, BACKDROP);
$svg .= sprintf(
    '<rect x="8" y="8" width="%.0f" height="%.0f" rx="14" fill="%s" stroke="%s"/>',
    WIDTH - 16,
    $height - 16,
    CARD_BG,
    BORDER,
);

foreach ($months as $index => $spec) {
    $svg .= renderPanel(
        $spec,
        OUTER + $index * ($panelWidth + GUTTER),
        OUTER,
        $panelWidth,
        $panelHeight,
    );
}

$svg .= text(
    'Umm al-Qura months as published by KACST — day 1 of each month is a table lookup, not an arithmetic estimate.',
    WIDTH / 2,
    $height - OUTER + 2,
    ['size' => 11, 'fill' => FAINT, 'anchor' => 'middle'],
);
$svg .= '</svg>';

$outputDir = rtrim($argv[1] ?? __DIR__.'/../art', '/');

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$svgPath = sys_get_temp_dir().'/ummulqura-calendar-card.svg';
file_put_contents($svgPath, $svg);

exec(sprintf(
    'rsvg-convert -o %s %s',
    escapeshellarg($outputDir.'/calendar.png'),
    escapeshellarg($svgPath),
), $ignored, $status);

if ($status !== 0) {
    fwrite(STDERR, "rsvg-convert failed.\n");
    exit(1);
}

printf("calendar.png %.1f KB\n", filesize($outputDir.'/calendar.png') / 1024);
