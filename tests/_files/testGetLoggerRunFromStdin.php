<?php
date_default_timezone_set('GMT');
require __DIR__ . '/../../vendor/autoload.php';
$client = new \Phergie\Irc\Client\React\Client;
$logger = $client->getLogger();
$logger->debug("test");
