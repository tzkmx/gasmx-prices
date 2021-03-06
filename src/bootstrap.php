<?php
require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use GasmxPricesUpdate\Command\GasmxPricesUpdate\GasmxPlacesUpdateCommand;
use GasmxPricesUpdate\Command\GasmxPricesUpdate\GasmxPricesUpdateCommand;
use Pimple\Container;
use GasmxPricesUpdate\Provider\HttpClientProvider;
use GasmxPricesUpdate\Provider\DatabaseProvider;
use GasmxPricesUpdate\Command\GasmxPricesUpdate\PlacesTableGeneratorCommand;

$c = new Container([
    'maps_api_key' => getenv('MAPS_API_KEY'),
]);

$c['cache.path'] = realpath(__DIR__ . '/../var/cache');

$c->register(new HttpClientProvider());
$c->register(new DatabaseProvider());

$c['console.app'] = function($c) {
    $app = new Application('Gasmx Prices Update', '1.0');
    $app->add($c['gasmx.prices.update']);
    $app->add($c['gasmx.prices.createdb']);
    $app->add($c['gasmx.places.update']);
    return $app;
};
$c['gasmx.places.update'] = function($c) {
    return new GasmxPlacesUpdateCommand($c);
};
$c['gasmx.prices.update'] = function($c) {
    return new GasmxPricesUpdateCommand($c);
};
$c['gasmx.prices.createdb'] = function($c) {
    return new PlacesTableGeneratorCommand($c);
};
