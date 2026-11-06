<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use Raips\Showroom\Server;

$site = getenv('RAIPS_SITE');
if ($site === false || trim($site) === '') {
    $site = __DIR__;
}

$data = getenv('RAIPS_DATA');

(new Server(trim($site), $data === false || trim($data) === '' ? null : trim($data)))->serve();
