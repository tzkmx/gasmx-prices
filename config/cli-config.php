<?php
use Doctrine\DBAL\Tools\Console\ConsoleRunner;

require __DIR__ . '/../src/bootstrap.php';

// replace with the mechanism to retrieve DBAL connection in your app
$connection = $c['db'];
//
// // You can append new commands to $commands array, if needed
//
return ConsoleRunner::createHelperSet($connection);

