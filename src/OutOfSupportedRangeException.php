<?php

declare(strict_types=1);

namespace AsimNet\UmmulquraCalendar;

use RuntimeException;

/**
 * Thrown when a date falls outside the range covered by the official
 * Umm al-Qura tables bundled with this library.
 */
final class OutOfSupportedRangeException extends RuntimeException {}
