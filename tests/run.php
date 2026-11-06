#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Runs every test file with no dependency behind it.
 */

require __DIR__ . '/../autoload.php';
require __DIR__ . '/Runner.php';

use Raips\Tests\Runner;

$files = glob(__DIR__ . '/*.test.php');
sort($files);

exit(Runner::main($files));
