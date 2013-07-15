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

This example makes the bot greet other users as they join any of the channels in which the bot is present.

```php
<?php
$connection = new \Phergie\Irc\Connection();
// ...

$client = new \Phergie\Irc\Client\React\Client();
$client->addConnection($connection);
$client->addListener(function($message, $write, $connection, $logger) {
    if ($message['type'] !== 'JOIN') {
        return;
    }
    $channel = $message['params']['channel'];
    $nick = $message['nick'];
    $write->ircPrivmsg($channel, 'Welcome ' . $nick . '!');
});
$client->run();
```

1. Create and configure an instance of the connection class, `\Phergie\Irc\Connection`, for each server the bot will connect to. See [phergie-irc-connection documentation](https://github.com/phergie/phergie-irc-connection#usage) for more information on configuring connection objects.
2. Create an instance of the client class, `\Phergie\Irc\Client\React\Client`.
3. Call the client object's `addConnection()` method with each connection object you created in step #1.
4. Call the client object's `addListener()` method any number of times, each time specifying a callback that will be executed whenever an event is received from the server. These callbacks should take the following parameters:
   1. `$message` is an associative array containing data for the event received from the server as obtained by `\Phergie\Irc\Parser` (see [its documentation](https://github.com/phergie/phergie-irc-parser#usage) for examples).
   2. `$write` is an instance of `\Phergie\Irc\Client\React\WriteStream` (which implements the interface `\Phergie\Irc\GeneratorInterface` -- see [its source code](https://github.com/phergie/phergie-irc-generator/blob/master/src/Phergie/Irc/GeneratorInterface.php) for a list of available methods) that will send new events from the client to the server when its methods are called.
   3. `$connection` is an instance of `\Phergie\Irc\Connection` (which implements the interface `\Phergie\Irc\ConnectionInterface` -- see [its source code](https://github.com/phergie/phergie-irc-connection/blob/master/src/Phergie/Irc/ConnectionInterface.php) for a list of available methods) containing metadata for the connection on which the event occurred.
   4. `$logger` is an instance of `\Monolog\Logger` for logging any relevant events from the listener, which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default. See [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information.

## Tests

To run the unit test suite:

```
cd tests
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.

## Community

Check out #phergie on irc.freenode.net.
