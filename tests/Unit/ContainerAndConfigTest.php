<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use VMP\Core\Container;
use VMP\Support\Config;

$container = new Container();
$container->bind('sample', static function () {
    return 'ok';
});
if ($container->make('sample') !== 'ok') {
    fwrite(STDERR, "Container bind/make failed\n");
    exit(1);
}

$container->singleton('shared', static function () {
    return new stdClass();
});
$first = $container->make('shared');
$second = $container->make('shared');
if ($first !== $second) {
    fwrite(STDERR, "Container singleton failed\n");
    exit(1);
}

$config = new Config(__DIR__ . '/../../app/Config');
if ($config->get('app.name') !== 'Vendor Marketplace') {
    fwrite(STDERR, "Config load failed\n");
    exit(1);
}

if (!function_exists('config')) {
    fwrite(STDERR, "config helper missing\n");
    exit(1);
}

if (config('commission.default_rate') != 10.0) {
    fwrite(STDERR, "config helper failed\n");
    exit(1);
}

echo "Container and config tests passed\n";
