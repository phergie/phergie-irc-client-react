# phergie/phergie-irc-client-react

A bare-bones PHP-based IRC client library built on React.

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
{
    "minimum-stability": "dev",
    "require": {
        "phergie/phergie-irc-client-react": "1.0.0"
    }
}
```

## Design goals

* Minimalistic and extensible design
* Informative logging of client-server interactions
* Simple easy-to-understand API

## Usage

```php
<?php
$connection = new \Phergie\Irc\Connection();
// ...

$client = new \Phergie\Irc\Client\React\Client();
$client->addConnection($connection);
$client->addListener(function($message, $write, $connection, $logger) {
    // ...
});
$client->run();
```

* `$message` is an associative array containing data for an event received from the server as obtained by `\Phergie\Irc\Parser`.
* `$write` is an instance of `\Phergie\Irc\Client\React\WriteStream` for sending new events from the client to the server.
* `$connection` is an instance of `\Phergie\Irc\Connection` containing metadata for the connection on which the event occurred.
* `$logger` is an instance of `\Monolog\Logger` for logging any relevant events from the listener.

## Tests

To run the unit test suite:

```
cd tests
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit Phergie/Irc/Client/React/TestSuite.php
```

## License

Released under the BSD License. See `LICENSE`.

## Community

Check out #phergie on irc.freenode.net.
