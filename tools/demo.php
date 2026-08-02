<?php

declare(strict_types=1);

/*
 * A short, self-contained tour of the package, printed as a REPL session.
 *
 *   php tools/demo.php            # ANSI colours (default when on a TTY)
 *   php tools/demo.php --no-color
 *
 * Every "php >" line below is really evaluated and every "=>" line is the
 * real return value — nothing here is transcribed by hand. Also used to
 * record the README demo (see tools/render-demo.php).
 */

require_once __DIR__.'/../tests/bootstrap.php';

$color = ! in_array('--no-color', $argv, true);

/**
 * Wrap text in a truecolor ANSI sequence.
 */
$paint = static function (string $text, string $hex) use ($color): string {
    if (! $color) {
        return $text;
    }

    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

    return sprintf("\e[38;2;%d;%d;%dm%s\e[0m", $r, $g, $b, $text);
};

const DIM = '#6e7681';
const COMMENT = '#8b949e';
const PUNCTUATION = '#c9d1d9';
const VARIABLE = '#79c0ff';
const KEYWORD = '#ff7b72';
const CALLABLE_NAME = '#d2a8ff';
const TEXT = '#a5d6ff';
const NUMBER = '#79c0ff';
const HEADING = '#e6edf3';
const GOOD = '#3fb950';

/**
 * Colour a PHP expression with the built-in tokenizer.
 */
$highlight = static function (string $code) use ($paint): string {
    $out = '';

    foreach (token_get_all('<?php '.$code) as $token) {
        if (is_string($token)) {
            $out .= $paint($token, PUNCTUATION);

            continue;
        }

        [$id, $text] = $token;

        if ($id === T_OPEN_TAG) {
            continue;
        }

        $out .= match ($id) {
            T_VARIABLE => $paint($text, VARIABLE),
            T_CONSTANT_ENCAPSED_STRING => $paint($text, TEXT),
            T_LNUMBER, T_DNUMBER => $paint($text, NUMBER),
            T_NEW, T_ARRAY, T_ECHO => $paint($text, KEYWORD),
            T_STRING => $paint($text, CALLABLE_NAME),
            T_COMMENT, T_DOC_COMMENT => $paint($text, COMMENT),
            default => $paint($text, PUNCTUATION),
        };
    }

    return $out;
};

/**
 * Render a returned value the way a REPL would print it.
 */
$inspect = static function (mixed $value) use ($paint): string {
    return match (true) {
        is_string($value) => $paint('"'.$value.'"', TEXT),
        is_bool($value) => $paint($value ? 'true' : 'false', KEYWORD),
        is_int($value) => $paint((string) $value, NUMBER),
        $value instanceof DateTimeInterface => $paint(get_debug_type($value), CALLABLE_NAME)
            .$paint('("', PUNCTUATION).$paint($value->format('Y-m-d'), TEXT).$paint('")', PUNCTUATION),
        default => $paint(get_debug_type($value), CALLABLE_NAME),
    };
};

/**
 * The session. `silent` statements (assignments) print no return value;
 * `note` is an aligned trailing comment.
 *
 * @var list<array{code: string, silent?: bool, note?: string}>
 */
$session = [
    ['code' => "\$eid = UmmAlQura::toHijri(new DateTimeImmutable('2026-03-20'));", 'silent' => true],
    ['code' => '$eid->toIsoString();'],
    ['code' => '$eid->toArabicString();'],
    ['code' => '$eid->monthNameEn();', 'note' => 'Eid al-Fitr 1447'],
    ['code' => "UmmAlQura::toGregorian(1447, 10, 1)->format('Y-m-d');", 'note' => 'and back again'],
    ['code' => 'UmmAlQura::monthLength(1447, 9);', 'note' => 'days in Ramadan 1447'],
    ['code' => "UmmAlQura::format(new DateTimeImmutable('2026-05-27'), 'l j F Y');"],
    ['code' => 'UmmAlQura::isReconstructed(1415, 10, 24);', 'note' => 'before the 1420 criterion'],
    ['code' => 'UmmAlQura::toGregorian(1415, 10, 24);', 'note' => 'the official service today'],
    ['code' => 'UmmAlQura::toGregorian(1415, 10, 24, Reckoning::AsPublished);', 'note' => 'the almanac of the time'],
    ['code' => "UmmAlQura::supports(new DateTimeImmutable('2080-01-01'));", 'note' => 'outside the tables'],
];

echo $paint('  ummulqura-calendar', HEADING).$paint('  ·  exact Gregorian ↔ Hijri', DIM)."\n";
echo $paint('  on the official Saudi Umm al-Qura tables', DIM)."\n\n";

foreach ($session as $statement) {
    echo $paint('php > ', DIM).$highlight($statement['code'])."\n";

    /*
     * eval()'d code does not inherit the file's imports, so the `use` is
     * re-stated; the statement itself is evaluated verbatim.
     */
    /** @var mixed $value */
    $value = eval(
        'use AsimNet\UmmulquraCalendar\UmmAlQura; use AsimNet\UmmulquraCalendar\Reckoning;'
        .' return '.rtrim($statement['code'], ';').';'
    );

    if ($statement['silent'] ?? false) {
        continue;
    }

    $line = $paint('  => ', DIM).$inspect($value);

    if (isset($statement['note'])) {
        $line .= str_repeat(' ', max(1, 40 - mb_strlen(preg_replace('/\e\[[\d;]*m/', '', $line) ?? '')));
        $line .= $paint('// '.$statement['note'], COMMENT);
    }

    echo $line."\n";
}

echo "\n".$paint('  ✓ 2,196 official months bundled, looked up — never calculated.', GOOD)."\n";
echo $paint('    From 1420 AH on, 312 of 312 month starts agree with an', GOOD)."\n";
echo $paint('    independently built table; earlier ones are flagged.', GOOD)."\n";
