<?php

// This is just a temporary sandbox to help me test things.
// It will eventually be deleted.

require_once 'vendor/autoload.php';

use Phergie\Irc\Connection;
use Phergie\Irc\Client\React;

$connection = new Connection;
$connection->setServerHostname('irc.freenode.net');
$connection->setUsername('Elazar');
$connection->setHostname('irc.freenode.net');
$connection->setServername('irc.freenode.net');
$connection->setRealname('Matthew Turland');
$connection->setNickname('Phergie3');

$client = new React;
$client->addConnection($connection);
$client->run();
