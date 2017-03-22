<?php
/**
 * Created by PhpStorm.
 * User: GP
 * Date: 22/03/2017
 * Time: 09:49 AM
 */

namespace GasmxPricesUpdate\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Logging\EchoSQLLogger;

class DatabaseProvider implements ServiceProviderInterface
{
    public function register(Container $c)
    {
        $c['db.config'] = function($c) {
            $config = new Configuration();
            if(isset($c['db.logging']) && true === $c['db.logging']) {
                $logger = new EchoSQLLogger();
                $config->setSQLLogger($logger);
            }
            return $config;
        };
        $c['db.params'] = [
            'url' => 'sqlite:///gasmx-prices.db',
        ];
        $c['db'] = function($c) {
            $conn = DriverManager::getConnection($c['db.params'], $c['db.config']);
            return $conn;
        };
    }
}
