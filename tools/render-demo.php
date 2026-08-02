<?php

declare(strict_types=1);

/*
 * Records tools/demo.php as the README assets: an animated terminal
 * (GIF + MP4) and a still frame (PNG).
 *
 *   php tools/render-demo.php [output-dir=art]
 *
 * Not part of the test suite or the shipped runtime — it is the build script
 * for the images in the README, kept here so they can be regenerated.
 * Requires `rsvg-convert` (librsvg) and `ffmpeg` on PATH, plus the fonts
 * JetBrains Mono (code) and Noto Naskh Arabic (Arabic text) installed.
 */

const FONT_MONO = 'JetBrains Mono, DejaVu Sans Mono, monospace';

/* Arabic runs need the Naskh face ahead of any Latin monospace fallback,
 * otherwise librsvg shapes them with DejaVu Sans Mono and the letters come
 * out disconnected. */
const FONT_ARABIC = 'JetBrains Mono, Noto Naskh Arabic, DejaVu Sans';
const FONT_SIZE = 18.0;
const CHAR_WIDTH = FONT_SIZE * 0.6;
const LINE_HEIGHT = 28.0;
const PADDING_X = 26.0;
const PADDING_Y = 20.0;
const TITLE_BAR = 40.0;
const COLUMNS = 74;

const FPS = 16;
const CHARS_PER_FRAME = 4;
const HOLD_AFTER_TYPING = 3;
const HOLD_AFTER_OUTPUT = 7;
const HOLD_AT_START = 10;
const HOLD_AT_END = 44;

const BACKDROP = '#010409';
const WINDOW_BG = '#0d1117';
const WINDOW_BORDER = '#30363d';
const DEFAULT_INK = '#c9d1d9';
const CURSOR = '#58a6ff';

$outputDir = rtrim($argv[1] ?? __DIR__.'/../art', '/');
$workDir = sys_get_temp_dir().'/ummulqura-demo-frames';

foreach (['rsvg-convert', 'ffmpeg'] as $binary) {
    exec('command -v '.escapeshellarg($binary), $found, $status);

    if ($status !== 0) {
        fwrite(STDERR, "Missing required binary: {$binary}\n");
        exit(1);
    }
}

/**
 * @return list<list<array{text: string, color: string}>> one list of coloured runs per line
 */
function captureSession(): array
{
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/demo.php');
    $output = shell_exec($command);

    if ($output === null || $output === false || trim($output) === '') {
        fwrite(STDERR, "tools/demo.php produced no output.\n");
        exit(1);
    }

    $lines = [];

    foreach (explode("\n", rtrim($output, "\n")) as $line) {
        $runs = [];
        $color = DEFAULT_INK;

        foreach (preg_split('/(\e\[[\d;]*m)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $chunk) {
            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^\e\[38;2;(\d+);(\d+);(\d+)m$/', $chunk, $match) === 1) {
                $color = sprintf('#%02x%02x%02x', (int) $match[1], (int) $match[2], (int) $match[3]);

                continue;
            }

            if (str_starts_with($chunk, "\e[")) {
                $color = DEFAULT_INK;

                continue;
            }

            $runs[] = ['text' => $chunk, 'color' => $color];
        }

        $lines[] = $runs;
    }

    return $lines;
}

/**
 * Plain text of a line, for measuring and for spotting prompt lines.
 *
 * @param  list<array{text: string, color: string}>  $runs
 */
function plainText(array $runs): string
{
    return implode('', array_column($runs, 'text'));
}

/**
 * Keep only the first $visible characters of a line's runs.
 *
 * @param  list<array{text: string, color: string}>  $runs
 * @return list<array{text: string, color: string}>
 */
function truncateRuns(array $runs, int $visible): array
{
    $kept = [];
    $budget = $visible;

    foreach ($runs as $run) {
        if ($budget <= 0) {
            break;
        }

        $length = mb_strlen($run['text']);

        if ($length <= $budget) {
            $kept[] = $run;
            $budget -= $length;

            continue;
        }

        $kept[] = ['text' => mb_substr($run['text'], 0, $budget), 'color' => $run['color']];
        $budget = 0;
    }

    return $kept;
}

/**
 * Build every animation state: the visible lines so far, plus the cursor
 * column while a prompt line is being typed.
 *
 * @param  list<list<array{text: string, color: string}>>  $lines
 * @return list<array{lines: list<list<array{text: string, color: string}>>, cursor: int|null, repeat: int}>
 */
function buildStates(array $lines): array
{
    $states = [];
    $visible = [];
    $promptLength = mb_strlen('php > ');

    foreach ($lines as $index => $runs) {
        $text = plainText($runs);

        if (str_starts_with($text, 'php > ')) {
            $total = mb_strlen($text);

            for ($shown = $promptLength; $shown < $total; $shown += CHARS_PER_FRAME) {
                $states[] = [
                    'lines' => [...$visible, truncateRuns($runs, $shown)],
                    'cursor' => min($shown, $total),
                    'repeat' => 1,
                ];
            }

            $visible[] = $runs;
            $states[] = ['lines' => $visible, 'cursor' => $total, 'repeat' => HOLD_AFTER_TYPING];

            continue;
        }

        $visible[] = $runs;
        $isLast = $index === array_key_last($lines);
        $states[] = [
            'lines' => $visible,
            'cursor' => null,
            'repeat' => $isLast ? HOLD_AT_END : ($text === '' ? 1 : HOLD_AFTER_OUTPUT),
        ];
    }

    array_unshift($states, ['lines' => [], 'cursor' => null, 'repeat' => HOLD_AT_START]);

    return $states;
}

function escapeXml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function hasArabic(string $text): bool
{
    return preg_match('/\p{Arabic}/u', $text) === 1;
}

/**
 * @param  list<list<array{text: string, color: string}>>  $lines
 */
function renderSvg(array $lines, ?int $cursor, int $rows, float $width, float $height): string
{
    $svg = sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%1$.0f" height="%2$.0f" viewBox="0 0 %1$.0f %2$.0f">',
        $width,
        $height,
    );
    $svg .= sprintf('<rect width="%.0f" height="%.0f" fill="%s"/>', $width, $height, BACKDROP);
    $svg .= sprintf(
        '<rect x="8" y="8" width="%.0f" height="%.0f" rx="12" fill="%s" stroke="%s"/>',
        $width - 16,
        $height - 16,
        WINDOW_BG,
        WINDOW_BORDER,
    );
    $svg .= sprintf('<line x1="8" y1="%1$.0f" x2="%2$.0f" y2="%1$.0f" stroke="%3$s"/>', 8 + TITLE_BAR, $width - 8, WINDOW_BORDER);

    foreach ([['#ff5f57', 30], ['#febc2e', 52], ['#28c840', 74]] as [$dot, $cx]) {
        $svg .= sprintf('<circle cx="%d" cy="%.0f" r="6" fill="%s"/>', $cx, 8 + TITLE_BAR / 2, $dot);
    }

    $svg .= sprintf(
        '<text x="%.0f" y="%.0f" font-family="%s" font-size="13" fill="#6e7681" text-anchor="middle">%s</text>',
        $width / 2,
        8 + TITLE_BAR / 2 + 4.5,
        escapeXml(FONT_MONO),
        'ummulqura-calendar',
    );

    $top = 8 + TITLE_BAR + PADDING_Y;

    foreach ($lines as $row => $runs) {
        $y = $top + ($row + 1) * LINE_HEIGHT - 8;
        $column = 0;

        foreach ($runs as $run) {
            if (trim($run['text']) !== '') {
                $svg .= sprintf(
                    '<text x="%.2f" y="%.2f" font-family="%s" font-size="%.0f" fill="%s" xml:space="preserve">%s</text>',
                    PADDING_X + $column * CHAR_WIDTH,
                    $y,
                    escapeXml(hasArabic($run['text']) ? FONT_ARABIC : FONT_MONO),
                    FONT_SIZE,
                    $run['color'],
                    escapeXml($run['text']),
                );
            }

            $column += mb_strlen($run['text']);
        }
    }

    if ($cursor !== null) {
        $y = $top + count($lines) * LINE_HEIGHT - 8;
        $svg .= sprintf(
            '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s" opacity="0.85"/>',
            PADDING_X + $cursor * CHAR_WIDTH,
            $y - FONT_SIZE + 3.5,
            CHAR_WIDTH,
            FONT_SIZE + 2,
            CURSOR,
        );
    }

    return $svg.'</svg>';
}

$lines = captureSession();
$rows = count($lines);
$width = PADDING_X * 2 + COLUMNS * CHAR_WIDTH;
$height = 8 * 2 + TITLE_BAR + PADDING_Y * 2 + $rows * LINE_HEIGHT;

$states = buildStates($lines);

if (is_dir($workDir)) {
    array_map('unlink', glob($workDir.'/*') ?: []);
} else {
    mkdir($workDir, 0777, true);
}

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$frame = 0;

foreach ($states as $state) {
    $svgPath = sprintf('%s/state-%05d.svg', $workDir, $frame);
    file_put_contents($svgPath, renderSvg($state['lines'], $state['cursor'], $rows, $width, $height));

    $first = sprintf('%s/frame-%05d.png', $workDir, $frame);
    exec(sprintf('rsvg-convert -o %s %s', escapeshellarg($first), escapeshellarg($svgPath)), $ignored, $status);

    if ($status !== 0) {
        fwrite(STDERR, "rsvg-convert failed on frame {$frame}.\n");
        exit(1);
    }

    for ($copy = 1; $copy < $state['repeat']; $copy++) {
        copy($first, sprintf('%s/frame-%05d.png', $workDir, $frame + $copy));
    }

    $frame += $state['repeat'];
    printf("\rrendering frames: %d", $frame);
}

echo "\n";

$still = $outputDir.'/demo.png';
file_put_contents($workDir.'/still.svg', renderSvg($lines, null, $rows, $width, $height));
exec(sprintf('rsvg-convert -o %s %s', escapeshellarg($still), escapeshellarg($workDir.'/still.svg')));

$frames = escapeshellarg($workDir.'/frame-%05d.png');

exec(sprintf(
    'ffmpeg -y -loglevel error -framerate %d -i %s -filter_complex '.
    '%s -loop 0 %s',
    FPS,
    $frames,
    escapeshellarg('[0:v] split [a][b];[a] palettegen=max_colors=128:stats_mode=diff [p];[b][p] paletteuse=dither=bayer:bayer_scale=5:diff_mode=rectangle'),
    escapeshellarg($outputDir.'/demo.gif'),
), $ignored, $gifStatus);

exec(sprintf(
    'ffmpeg -y -loglevel error -framerate %d -i %s -vf %s -c:v libx264 -preset slow -crf 20 -movflags +faststart %s',
    FPS,
    $frames,
    escapeshellarg('scale=trunc(iw/2)*2:trunc(ih/2)*2,format=yuv420p'),
    escapeshellarg($outputDir.'/demo.mp4'),
), $ignored, $mp4Status);

foreach (['demo.png' => 0, 'demo.gif' => $gifStatus, 'demo.mp4' => $mp4Status] as $name => $status) {
    $path = $outputDir.'/'.$name;
    printf(
        "%-10s %s\n",
        $name,
        $status === 0 && is_file($path) ? sprintf('%.1f KB', filesize($path) / 1024) : 'FAILED',
    );
}
