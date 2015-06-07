<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-client-react for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license Simplified BSD License
 * @package Phergie\Irc\Client\React
 */

namespace Phergie\Irc\Client\React;

use Evenement\EventEmitter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Phergie\Irc\ConnectionInterface;
use React\Dns\Resolver\Factory;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use React\Promise\Deferred;
use React\Stream\Stream;
use React\Stream\DuplexStreamInterface;

/**
 * IRC client implementation based on the React component library.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class Client extends EventEmitter implements
    ClientInterface,
    LoopAwareInterface,
    LoopAccessorInterface
{
    /**
     * Event loop
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * Logging stream
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Interval in seconds between irc.tick events
     *
     * @var float
     */
    protected $tickInterval = 0.2;

    /**
     * Connector used to establish SSL connections
     *
     * @var \React\SocketClient\SecureConnector
     */
    protected $secureConnector;

    /**
     * @var \React\Dns\Resolver\Resolver
     */
    protected $resolver;

    /**
     * @var string
     */
    protected $dnsServer = '8.8.8.8';

    /**
     * Contains all connection instances associated with an active socket stream
     *
     * @var \SplObjectStorage
     */
    protected $activeConnections;

    /**
     * Sets the event loop dependency.
     *
     * @param \React\EventLoop\LoopInterface $loop
     */
    public function setLoop(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Returns the event loop dependency, initializing it if needed.
     *
     * @return \React\EventLoop\LoopInterface
     */
    public function getLoop()
    {
        if (!$this->loop) {
            $this->loop = \React\EventLoop\Factory::create();
        }
        return $this->loop;
    }

    /**
     * Sets the DNS Resolver.
     *
     * @param Resolver $resolver
     */
    public function setResolver(Resolver $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * Get the DNS Resolver, if one isn't set in instance will be created.
     *
     * @return Resolver
     */
    public function getResolver()
    {
        if ($this->resolver instanceof Resolver) {
            return $this->resolver;
        }

        $factory = new Factory();
        $this->resolver = $factory->createCached($this->getDnsServer(), $this->getLoop());

        return $this->resolver;
    }

    /**
     * Set the DNS server to use when looking up IP's
     *
     * @param string $dnsServer
     */
    public function setDnsServer($dnsServer = '8.8.8.8')
    {
        $this->dnsServer = $dnsServer;
    }

    /**
     * Returns the configured DNS server
     *
     * @return string
     */
    public function getDnsServer()
    {
        return $this->dnsServer;
    }

    /**
     * Sets the interval in seconds between irc.tick events.
     *
     * @param float $tickInterval
     */
    public function setTickInterval($tickInterval)
    {
        $this->tickInterval = (float) $tickInterval;
    }

    /**
     * Returns the interval in seconds between irc.tick events.
     *
     * @return float
     */
    public function getTickInterval()
    {
        return $this->tickInterval;
    }

    /**
     * Add a connection instance to the active connection store
     *
     * @param \Phergie\Irc\ConnectionInterface
     */
    protected function addActiveConnection(ConnectionInterface $connection)
    {
        if (!$this->activeConnections) {
            $this->activeConnections = new \SplObjectStorage;
        }
        $this->activeConnections->attach($connection);
    }

    /**
     * Remove a connection instance from the active connections store
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     */
    protected function removeActiveConnection(ConnectionInterface $connection)
    {
        if ($this->activeConnections) {
            $this->activeConnections->detach($connection);
        }
    }

    /**
     * Gets an array of all connections associated with an active socket stream.
     *
     * @return \Phergie\Irc\ConnectionInterface[]
     */
    public function getActiveConnections()
    {
        return $this->activeConnections ? iterator_to_array($this->activeConnections, false) : array();
    }

    /**
     * Returns a socket configured for a specified remote.
     *
     * @param string $remote Address to connect the socket to (e.g. tcp://irc.freenode.net:6667)
     * @param array $context Context options for the socket
     * @return resource
     * @throws \Phergie\Irc\Client\React\Exception if unable to connect to the specified remote
     */
    protected function getSocket($remote, array $context)
    {
        $socket = stream_socket_client(
            $remote,
            $errno,
            $errstr,
            ini_get('default_socket_timeout'),
            STREAM_CLIENT_CONNECT,
            stream_context_create($context)
        );

        if (!$socket) {
            throw new Exception(
                'Unable to connect to remote ' . $remote .
                    ': socket error ' . $errno . ' ' . $errstr,
                Exception::ERR_CONNECTION_ATTEMPT_FAILED
            );
        }

        stream_set_blocking($socket, 0);
        return $socket;
    }

    /**
     * Derives the transport for a given connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @return string Transport usable in the URI for a stream
     */
    protected function getTransport(ConnectionInterface $connection)
    {
        return (string) $connection->getOption('transport') ?: 'tcp';
    }

    /**
     * Extracts the value of the force-ipv4 option from a given connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @return boolean TRUE to force use of IPv4, FALSE otherwise
     */
    protected function getForceIpv4Flag(ConnectionInterface $connection)
    {
        return (boolean) $connection->getOption('force-ipv4') ?: false;
    }

    /**
     * Derives a remote for a given connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @return string Remote usable to establish a socket connection
     */
    protected function getRemote(ConnectionInterface $connection)
    {
        $hostname = $connection->getServerHostname();
        $port = $connection->getServerPort();

        $deferred = new Deferred();
        $this->getResolver()
            ->resolve($hostname)
            ->then(
                function($ip) use($deferred, $port) {
                    $deferred->resolve('tcp://' . $ip . ':' . $port);
                },
                function($error) use ($deferred) {
                    $deferred->reject($error);
                }
            );

        return $deferred->promise();
    }

    /**
     * Derives a set of socket context options for a given connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @return array Associative array of context option key-value pairs
     */
    protected function getContext(ConnectionInterface $connection)
    {
        $context = array();
        if ($this->getForceIpv4Flag($connection)) {
            $context['bindto'] = '0.0.0.0:0';
        }
        $context = array('socket' => $context);
        return $context;
    }

    /**
     * Returns a stream for a socket connection.
     *
     * @param resource $socket Socket for the connection to the server
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @return \React\Stream\DuplexStreamInterface
     */
    protected function getStream($socket)
    {
        return new Stream($socket, $this->getLoop());
    }

    /**
     * Generates a closure for stream output logging.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @param string $level Evenement logging level to use
     * @param string $prefix Prefix string for log lines (optional)
     * @return callable
     * @throws \DomainException if $level is not a valid logging level
     */
    protected function getOutputLogCallback(ConnectionInterface $connection, $level, $prefix = null)
    {
        $logger = $this->getLogger();
        if (!method_exists($logger, $level)) {
            throw new \DomainException("Invalid log level '$level'");
        }
        return function($message) use ($connection, $level, $prefix, $logger) {
            $output = sprintf('%s %s%s', $connection->getMask(), $prefix, trim($message));
            call_user_func(array($logger, $level), $output);
        };
    }

    /**
     * Adds an event listener to log data emitted by a stream.
     *
     * @param \Evenement\EventEmitter $emitter
     * @param \Phergie\Irc\ConnectionInterface $connection Connection
     *        corresponding to the stream
     */
    protected function addLogging(EventEmitter $emitter, ConnectionInterface $connection)
    {
        $emitter->on('data', $this->getOutputLogCallback($connection, 'debug'));
        $emitter->on('error', $this->getOutputLogCallback($connection, 'notice'));
    }

    /**
     * Returns a stream instance for parsing messages from the server and
     * emitting them as events.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Connection
     *        corresponding to the read stream
     * @return \Phergie\Irc\Client\React\ReadStream
     */
    protected function getReadStream(ConnectionInterface $connection)
    {
        $read = new ReadStream();
        $this->addLogging($read, $connection);
        $read->on('invalid', $this->getOutputLogCallback($connection, 'notice', 'Parser unable to parse line: '));
        return $read;
    }

    /**
     * Returns a stream instance for sending events to the server.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Connection
     *        corresponding to the read stream
     * @return \Phergie\Irc\Client\React\WriteStream
     */
    protected function getWriteStream(ConnectionInterface $connection)
    {
        $write = new WriteStream();
        $this->addLogging($write, $connection);
        return $write;
    }

    /**
     * Returns a stream instance for logging data on the socket connection.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        if (!$this->logger) {
            // See testGetLoggerRunFromStdin
            // @codeCoverageIgnoreStart
            $stderr = defined('\STDERR') && !is_null(\STDERR)
                ? \STDERR : fopen('php://stderr', 'w');
            // @codeCoverageIgnoreEnd
            $handler = new StreamHandler($stderr, Logger::DEBUG);
            $handler->setFormatter(new LineFormatter("%datetime% %level_name% %message% %context%\n"));
            $this->logger = new Logger(get_class($this));
            $this->logger->pushHandler($handler);
        }
        return $this->logger;
    }

    /**
     * Sets a logger for logging data on the socket connection.
     *
     * @param \Psr\Log\LoggerInterface
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Returns a callback for proxying IRC events from the read stream to IRC
     * listeners of the client.
     *
     * @param \Phergie\Irc\Client\React\WriteStream $write Write stream
     *        corresponding to the read stream on which the event occurred
     * @param \Phergie\Irc\ConnectionInterface $connection Connection on
     *        which the event occurred
     * @return callable
     */
    protected function getReadCallback(WriteStream $write, ConnectionInterface $connection)
    {
        $logger = $this->getLogger();
        return function($message) use ($write, $connection, $logger) {
            $this->emit('irc.received', array($message, $write, $connection, $logger));
        };
    }

    /**
     * Returns a callback for proxying events from the write stream to IRC
     * listeners of the client.
     *
     * @param \Phergie\Irc\Client\React\WriteStream $write Write stream
     *        corresponding to the read stream on which the event occurred
     * @param \Phergie\Irc\ConnectionInterface $connection Connection on which
     *        the event occurred
     * @return callable
     */
    protected function getWriteCallback(WriteStream $write, ConnectionInterface $connection)
    {
        $logger = $this->getLogger();
        return function($message) use ($write, $connection, $logger) {
            $this->emit('irc.sent', array($message, $write, $connection, $logger));
        };
    }

    /**
     * Returns a callback for proxying connection error events to listeners of
     * the client.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Connection on which
     *        the error occurred
     * @return callable
     */
    protected function getErrorCallback(ConnectionInterface $connection)
    {
        $logger = $this->getLogger();
        return function($exception) use ($connection, $logger) {
            $this->emit('connect.error', array($exception, $connection, $logger));
        };
    }

    /**
     * Returns a callback for when a connection is terminated, whether
     * explicitly by the bot or server or as a result of loss of connectivity.
     *
     * @param \Phergie\Irc\Client\React\ReadStream $read Read stream for this
     *        connection
     * @param \Phergie\Irc\Client\React\WriteStream $write Write stream for this
     *        connection
     * @param \Phergie\Irc\ConnectionInterface $connection Terminated connection
     * @param \React\EventLoop\Timer\TimerInterface $timer Timer used to handle
     *        asynchronously queued events on the connection, which must be
     *        cancelled
     * @return callable
     */
    protected function getEndCallback(ReadStream $read, WriteStream $write, ConnectionInterface $connection, TimerInterface $timer)
    {
        $logger = $this->getLogger();
        return function() use ($read, $write, $connection, $timer, $logger) {
            $this->removeActiveConnection($connection);
            $this->emit('connect.end', array($connection, $logger));
            $this->cancelTimer($timer);
            $connection->clearData();
            $read->close();
            $write->close();
        };
    }

    /**
     * Returns a callback executed periodically to allow events to be sent
     * asynchronously versus in response to received or sent events.
     *
     * @param \Phergie\Irc\Client\React\WriteStream $write Stream used to
     *        send events to the server
     * @param \Phergie\Irc\ConnectionInterface $connection Connection to
     *        receive the event
     */
    protected function getTickCallback(WriteStream $write, ConnectionInterface $connection)
    {
        $logger = $this->getLogger();
        return function() use ($write, $connection, $logger) {
            $this->emit('irc.tick', array($write, $connection, $logger));
        };
    }

    /**
     * Configure streams to handle messages received from and sent to the
     * server.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Metadata for the
     *        connection over which messages are being exchanged
     * @param \React\Stream\DuplexStreamInterface $stream Stream representing the
     *        connection to the server
     * @param \Phergie\Irc\Client\React\WriteStream $write Stream used to
     *        send events to the server
     */
    protected function configureStreams(ConnectionInterface $connection, DuplexStreamInterface $stream, WriteStream $write)
    {
        $timer = $this->addPeriodicTimer($this->getTickInterval(), $this->getTickCallback($write, $connection));
        $read = $this->getReadStream($connection);
        $write->pipe($stream)->pipe($read);
        $read->on('irc.received', $this->getReadCallback($write, $connection));
        $write->on('data', $this->getWriteCallback($write, $connection));
        $end = array($stream, 'end');
        $read->on('end', $end);
        $write->on('end', $end);
        $stream->on('end', $this->getEndCallback($read, $write, $connection, $timer));
        $error = $this->getErrorCallback($connection);
        $read->on('error', $error);
        $write->on('error', $error);
    }

    /**
     * Identifies the user to a server.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Connection on which
     *        to identify the user
     * @param \Phergie\Irc\Client\React\WriteStream $writeStream Stream to
     *        receive commands identifying the user
     */
    protected function identifyUser(ConnectionInterface $connection, WriteStream $writeStream)
    {
        $password = $connection->getPassword();
        if ($password) {
            $writeStream->ircPass($password);
        }

        $writeStream->ircUser(
            $connection->getUsername(),
            $connection->getHostname(),
            $connection->getServername(),
            $connection->getRealname()
        );

        $writeStream->ircNick($connection->getNickname());
    }

    /**
     * Emits a connection error event.
     *
     * @param \Exception $exception
     * @param \Phergie\Irc\ConnectionInterface $connection
     */
    protected function emitConnectionError(\Exception $exception, ConnectionInterface $connection)
    {
        $this->emit(
            'connect.error',
            array(
                $exception->getMessage(),
                $connection,
                $this->getLogger()
            )
        );
    }

    /**
     * Initializes an unsecured connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     */
    protected function addUnsecuredConnection(ConnectionInterface $connection)
    {
        $this->getRemote($connection)->then(
            function($remote) use($connection) {
                $this->initializeConnection($remote, $connection);
            },
            function($error) use($connection) {
                $this->emitConnectionError($error, $connection);
            }
        );
    }

    /**
     * Returns a connector for establishing SSL connections.
     *
     * @return \React\SocketClient\SecureConnector
     */
    protected function getSecureConnector()
    {
        if (!$this->secureConnector) {
            $loop = $this->getLoop();
            $connector = new \React\SocketClient\Connector($loop, $this->getResolver());
            $this->secureConnector = new \React\SocketClient\SecureConnector($connector, $loop);
        }
        return $this->secureConnector;
    }

    /**
     * Initializes a secured connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @throws \Phergie\Irc\Client\React\Exception if the SSL transport and
     *         forcing IPv4 usage are both enabled
     */
    protected function addSecureConnection(ConnectionInterface $connection)
    {
        // @see https://github.com/reactphp/socket-client/issues/4
        if ($this->getForceIpv4Flag($connection)) {
            throw new Exception(
                'Using the SSL transport and IPv4 together is not currently supported',
                Exception::ERR_CONNECTION_STATE_UNSUPPORTED
            );
        }

        $hostname = $connection->getServerHostname();
        $port = $connection->getServerPort();

        $this->getSecureConnector()
            ->create($hostname, $port)
            ->then(
                function(DuplexStreamInterface $stream) use ($connection) {
                    $this->initializeStream($stream, $connection);
                    $this->emit('connect.after.each', array($connection, $connection->getOption('write')));
                }
            );
    }

    /**
     * Configures an established stream for a given connection.
     *
     * @param \React\Stream\DuplexStreamInterface $stream
     * @param \Phergie\Irc\ConnectionInterface $connection
     */
    protected function initializeStream(DuplexStreamInterface $stream, ConnectionInterface $connection)
    {
        try {
            $connection->setData('stream', $stream);
            $write = $this->getWriteStream($connection);
            $connection->setData('write', $write);
            $this->configureStreams($connection, $stream, $write);
            $this->identifyUser($connection, $write);
        } catch (\Exception $e) {
            $this->emitConnectionError($e, $connection);
        }
    }

    /**
     * Initializes an added connection.
     *
     * @param string $remote
     * @param \Phergie\Irc\ConnectionInterface $connection
     */
    protected function initializeConnection($remote, $connection)
    {
        try {
            $context = $this->getContext($connection);
            $socket = $this->getSocket($remote, $context);
            $stream = $this->getStream($socket);
            $this->initializeStream($stream, $connection);
            $this->addActiveConnection($connection);
        } catch (\Exception $e) {
            $this->emitConnectionError($e, $connection);
        }

        $this->emit('connect.after.each', array($connection, $connection->getOption('write')));
    }

    /**
     * Initializes an IRC connection.
     *
     * Emits connect.before.each and connect.after.each events before and
     * after connection attempts are established, respectively.
     *
     * Emits a connect.error event if a connection attempt fails.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Metadata for connection to establish
     * @throws \Phergie\Irc\Client\React\Exception if unable to establish the connection
     */
    public function addConnection(ConnectionInterface $connection)
    {
        $this->emit('connect.before.each', array($connection));

        if ($this->getTransport($connection) === 'ssl') {
            $this->addSecureConnection($connection);
        } else {
            $this->addUnsecuredConnection($connection);
        }
    }

    /**
     * Executes the event loop, which continues running until no active
     * connections remain.
     *
     * @param \Phergie\Irc\ConnectionInterface|\Phergie\Irc\ConnectionInterface[] $connections
     */
    public function run($connections)
    {
        if (!is_array($connections)) {
            $connections = array($connections);
        }

        $this->on('connect.error', function($message, $connection, $logger) {
            $logger->error($message);
        });

        $this->emit('connect.before.all', array($connections));

        foreach ($connections as $connection) {
            $this->addConnection($connection);
        }

        $writes = array_map(
            function($connection) {
                return $connection->getOption('write');
            },
            $connections
        );
        $this->emit('connect.after.all', array($connections, $writes));

        $this->getLoop()->run();
    }

    /**
     * Adds a one-time callback to execute after a specified amount of time has
     * passed. Proxies to addTimer() implementation of the event loop
     * implementation returned by getLoop().
     *
     * @param numeric $interval Number of seconds to wait before executing
     *        callback
     * @param callable $callback Callback to execute
     * @return \React\Event\Timer\TimerInterface Added timer
     */
    public function addTimer($interval, $callback)
    {
        return $this->getLoop()->addTimer($interval, $callback);
    }

    /**
     * Adds a recurring callback to execute on a specified interval. Proxies to
     * addPeriodTimer() implementation of the event loop implementation
     * returned by getLoop().
     *
     * @param numeric $interval Number of seconds to wait between executions of callback
     * @param callable $callback Callback to execute
     * @return \React\Event\Timer\TimerInterface Added timer
     */
    public function addPeriodicTimer($interval, $callback)
    {
        return $this->getLoop()->addPeriodicTimer($interval, $callback);
    }

    /**
     * Cancels a specified timer created using addTimer() or
     * addPeriodicTimer(). Proxies to the cancelTimer() implementation of the
     * event loop implementation returned by getLoop().
     *
     * @param \React\Event\Timer\TimerInterface $timer Timer returned by
     *        addTimer() or addPeriodicTimer()
     */
    public function cancelTimer(TimerInterface $timer)
    {
        $this->getLoop()->cancelTimer($timer);
    }

    /**
     * Checks if a timer created using addTimer() or addPeriodicTimer() is
     * active. Proxies to the isTimerActive() implementation of the event loop
     * implementation returned by getLoop().
     *
     * @param \React\Event\Timer\TimerInterface $timer Timer returned by
     *        addTimer() or addPeriodicTimer()
     * @return boolean TRUE if the specified timer is active, FALSE otherwise
     */
    public function isTimerActive(TimerInterface $timer)
    {
        return $this->getLoop()->isTimerActive($timer);
    }
}
