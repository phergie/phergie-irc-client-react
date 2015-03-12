# phergie/phergie-irc-client-react

A bare-bones PHP-based IRC client library built on React.

[![Build Status](https://secure.travis-ci.org/phergie/phergie-irc-client-react.png?branch=master)](http://travis-ci.org/phergie/phergie-irc-client-react)
[![Coverage Status](https://img.shields.io/coveralls/phergie/phergie-irc-client-react.svg)](https://coveralls.io/r/phergie/phergie-irc-client-react)
[![Code Climate](https://codeclimate.com/github/phergie/phergie-irc-client-react/badges/gpa.svg)](https://codeclimate.com/github/phergie/phergie-irc-client-react)

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "phergie/phergie-irc-client-react": "~2"
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
$client->on('irc.received', function($message, $write, $connection, $logger) {
    if ($message['command'] !== 'JOIN') {
        return;
    }
    $channel = $message['params']['channels'];
    $nick = $message['nick'];
    $write->ircPrivmsg($channel, 'Welcome ' . $nick . '!');
});
$client->run($connection);

// Also works:
// $client->run(array($connection1, ..., $connectionN));
```

1. Create and configure an instance of the connection class, `\Phergie\Irc\Connection`, for each server the bot will connect to. See [phergie-irc-connection documentation](https://github.com/phergie/phergie-irc-connection#usage) for more information on configuring connection objects.
2. Create an instance of the client class, `\Phergie\Irc\Client\React\Client`.
3. Call the client object's `on()` method any number of times, each time specifying an event to monitor and a callback that will be executed whenever that event is received from the server.
4. Call the client object's `run()` method with a connection object or array of multiple connection objects created in step #1.

## Client Events

Below are the events supported by the client. Its `on()` method can be used to add callbacks for them.

### connect.before.all

Emitted before any connections are established.

#### Parameters

* `\Phergie\Irc\ConnectionInterface[] $connections` - array of all connection objects

#### Example

```php
<?php
$client->on('connect.before.all', function(array $connections) {
    // ...
});
```

### connect.after.all

Emitted after all connections are established.

#### Parameters

* `\Phergie\Irc\ConnectionInterface[] $connections` - array of all connection objects
* `\Phergie\Irc\Client\React\WriteStream[] $writes` - corresponding array of connection write streams

Note that if a connection attempt failed, the value in `$writes` for that connection will be `null`.

#### Example

```php
<?php
$client->on('connect.after.all', function(array $connections, array $writes) {
    // ...
});
```

### connect.before.each

Emitted before each connection is established.

#### Parameters

* `\Phergie\Irc\ConnectionInterface $connection` - object for the connection to be established

#### Example

```php
<?php
$client->on('connect.before.each', function(\Phergie\Irc\ConnectionInterface $connection) {
    // ...
});
```

### connect.after.each

Emitted after each connection is established.

One potentially useful application of this is to institute a delay between connections in cases where the client is attempting to establish multiple connections to the same server and that server throttles connection attempts by origin to prevent abuse, DDoS attacks, etc.

#### Parameters

* `\Phergie\Irc\ConnectionInterface $connection` - object for the established connection
* `\Phergie\Irc\Client\React\WriteStream|null $write` - write stream for the connection

Note that if the connection attempt failed, `$write` will be `null`.

#### Example

```php
<?php
$client->on('connect.after.each', function(\Phergie\Irc\ConnectionInterface $connection, \Phergie\Irc\Client\React\WriteStream $write) {
    // ...
});
```

### connect.error

Emitted when an error is encountered on a connection.

#### Parameters

* `Exception $exception ` - exception describing the error that encountered
* `\Phergie\Irc\ConnectionInterface $connection` - container that stores metadata for the connection on which the event occurred and implements the interface `\Phergie\Irc\ConnectionInterface` (see [its source code](https://github.com/phergie/phergie-irc-connection/blob/master/src/Phergie/Irc/ConnectionInterface.php) for a list of available methods)
* `\Psr\Log\LoggerInterface $logger` - logger for logging any relevant events from the listener which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default (see [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information)

#### Example

```php
<?php
$client->on('connect.error', function(
    \Exception $message,
    \Phergie\Irc\ConnectionInterface $connection,
    \Psr\Log\LoggerInterface $logger
) use ($client) {
    $logger->debug('Connection to ' . $connection->getServerHostname() . ' lost: ' . $e->getMessage());
});
```

### connect.end

Emitted when a connection is terminated.

This can be useful for re-establishing a connection if it is unexpectedly terminated.

#### Parameters

* `\Phergie\Irc\ConnectionInterface $connection` - container that stores metadata for the connection that was terminated and implements the interface `\Phergie\Irc\ConnectionInterface` (see [its source code](https://github.com/phergie/phergie-irc-connection/blob/master/src/Phergie/Irc/ConnectionInterface.php) for a list of available methods)
* `\Psr\Log\LoggerInterface $logger` - logger for logging any relevant events from the listener which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default (see [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information)

#### Example

```php
<?php
$client->on('connect.end', function(\Phergie\Irc\ConnectionInterface $connection, \Psr\Log\LoggerInterface $logger) use ($client) {
    $logger->debug('Connection to ' . $connection->getServerHostname() . ' lost, attempting to reconnect');
    $client->addConnection($connection);
});
```

### irc.received

Emitted when an IRC event is received from the server.

#### Parameters

* `array $message` - associative array containing data for the event received from the server as obtained by `\Phergie\Irc\Parser` (see [its documentation](https://github.com/phergie/phergie-irc-parser#usage) for examples)
* `\Phergie\Irc\Client\React\WriteStream $write` - stream that will send new events from the client to the server when its methods are called and implements the interface `\Phergie\Irc\GeneratorInterface` (see [its source code](https://github.com/phergie/phergie-irc-generator/blob/master/src/Phergie/Irc/GeneratorInterface.php) for a list of available methods)
* `\Phergie\Irc\ConnectionInterface $connection` - container that stores metadata for the connection on which the event occurred and implements the interface `\Phergie\Irc\ConnectionInterface` (see [its source code](https://github.com/phergie/phergie-irc-connection/blob/master/src/Phergie/Irc/ConnectionInterface.php) for a list of available methods)
* `\Psr\Log\LoggerInterface $logger` - logger for logging any relevant events from the listener which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default (see [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information)

#### Example

```php
<?php
$client->on('irc.received', function(
    array $message,
    \Phergie\Irc\Client\React\WriteStream $write,
    \Phergie\Irc\ConnectionInterface $connection,
    \Psr\Log\LoggerInterface $logger
) {
    // ...
});
```

### irc.sent

Emitted when an IRC event is sent by the client to the server.

#### Parameters

* `string $message` - message being sent by the client
* `\Phergie\Irc\Client\React\WriteStream $write` - stream that will send new events from the client to the server when its methods are called and implements the interface `\Phergie\Irc\GeneratorInterface` (see [its source code](https://github.com/phergie/phergie-irc-generator/blob/master/src/Phergie/Irc/GeneratorInterface.php) for a list of available methods)
* `\Phergie\Irc\ConnectionInterface $connection` - container that stores metadata for the connection on which the event occurred and implements the interface `\Phergie\Irc\ConnectionInterface` (see [its source code](https://github.com/phergie/phergie-irc-connection/blob/master/src/Phergie/Irc/ConnectionInterface.php) for a list of available methods)
* `\Psr\Log\LoggerInterface $logger` - logger for logging any relevant events from the listener which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default (see [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information)

#### Example

```php
<?php
$client->on('irc.sent', function(
    $message,
    \Phergie\Irc\Client\React\WriteStream $write,
    \Phergie\Irc\ConnectionInterface $connection,
    \Psr\Log\LoggerInterface $logger
) {
    // ...
});
```

### irc.tick

Emitted periodically on each connection to allow events to be sent
asynchronously versus in response to received or sent events. The interval
between invocations is specified in seconds and set using the client's
`setTickInterval()` method.

#### Parameters

* `\Phergie\Irc\Client\React\WriteStream $write` - stream that will send new events from the client to the server when its methods are called and implements the interface `\Phergie\Irc\GeneratorInterface` (see [its source code](https://github.com/phergie/phergie-irc-generator/blob/master/src/Phergie/Irc/GeneratorInterface.php) for a list of available methods)
* `\Phergie\Irc\ConnectionInterface $connection` - container that stores metadata for the connection on which the event occurred and implements the interface `\Phergie\Irc\ConnectionInterface` (see [its source code](https://github.com/phergie/phergie-irc-connection/blob/master/src/Phergie/Irc/ConnectionInterface.php) for a list of available methods)
* `\Psr\Log\LoggerInterface $logger` - logger for logging any relevant events from the listener which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default (see [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information)

#### Example

```php
<?php
$client->on('irc.tick', function(
    \Phergie\Irc\Client\React\WriteStream $write,
    \Phergie\Irc\ConnectionInterface $connection,
    \Psr\Log\LoggerInterface $logger
) {
    // ...
});
```

## Timers

In some cases, it's desirable to execute a callback on a specified interval rather than in response to a specific event.

### One-Time Callbacks

To add one-time callbacks that execute after a specified amount of time (in seconds):

```php
<?php
$client->addTimer(5, function() {
    // ...
});
```

The above example will execute the specified callback at least 5 seconds after it's added.

### Recurring Callbacks

To add recurring callbacks that execute on a specified interval (in seconds):

```php
<?php
$client->addPeriodicTimer(5, function() {
    // ...
});
```

The above example will execute the specified callback at least every 5 seconds after it's added.

## Connection Options

### force-ip4

Connection sockets will use IPv6 by default where available. If you need to force usage of IPv4, set this option to `true`.

```php
<?php
$connection->setOption('force-ipv4', true);
```

### transport

By default, a standard TCP socket is used. For IRC servers that support TLS or SSL, specify an [appropriate transport](http://www.php.net/manual/en/transports.inet.php).

```php
<?php
$connection->setOption('transport', 'ssl');
```

## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.

## Community

Check out #phergie on irc.freenode.net.
