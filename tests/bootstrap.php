<?php

declare(strict_types=1);

/*
 * Self-contained bootstrap: the package has zero runtime dependencies, so the
 * suite runs without a composer install inside the package — any PHPUnit
 * binary (e.g. the host app's vendor/bin/phpunit) can execute it.
 */
require_once __DIR__.'/../src/OutOfSupportedRangeException.php';
require_once __DIR__.'/../src/HijriDate.php';
require_once __DIR__.'/../src/UmmAlQura.php';
