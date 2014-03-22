<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-client-react for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc\Client\React
 */

namespace Phergie\Irc\Client\React;

use Evenement\EventEmitter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Phergie\Irc\ConnectionInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use React\Stream\Stream;

/**
 * IRC client implementation based on the React component library.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class Client extends EventEmitter implements ClientInterface
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
     * Returns a socket configured for a specified remote.
     *
     * @param string $remote Address to connect the socket to (e.g. tcp://irc.freenode.net:6667)
     * @param array $context Context options for the socket
     * @return resource
     * @throws \Phergie\Irc\Client\React\Exception if unable to connect to the specified remote
     */
    protected function getSocket($remote, array $context)
    {
        $socket = @stream_socket_client(
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
     * Derives a remote for a given connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @return string Remote usable to establish a socket connection
     */
    protected function getRemote(ConnectionInterface $connection)
    {
        $transport = $connection->getOption('transport');
        if ($transport === null) {
            $transport = 'tcp';
        }
        $hostname = $connection->getServerHostname();
        $port = $connection->getServerPort();
        $remote = $transport . '://' . $hostname . ':' . $port;
        return $remote;
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
        if ($connection->getOption('force-ipv4')) {
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
     * @return \React\Stream\Stream
     */
    protected function getStream($socket)
    {
        return new Stream($socket, $this->getLoop());
    }

    /**
     * Adds an event listener to log data emitted by a stream.
     *
     * @param \Evenement\EventEmitter $emitter
     */
    protected function addLogging(EventEmitter $emitter)
    {
        $logger = $this->getLogger();
        $callback = function($msg) use ($logger) {
            $logger->debug($msg);
        };
        $emitter->on('data', $callback);
        $emitter->on('error', $callback);
    }

    /**
     * Returns a stream instance for parsing messages from the server and
     * emitting them as events.
     *
     * @return \Phergie\Irc\Client\React\ReadStream
     */
    protected function getReadStream()
    {
        $read = new ReadStream();
        $this->addLogging($read);
        return $read;
    }

    /**
     * Returns a stream instance for sending events to the server.
     *
     * @return \Phergie\Irc\Client\React\WriteStream
     */
    protected function getWriteStream()
    {
        $write = new WriteStream();
        $this->addLogging($write);
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
            if (!defined('STDERR')) {
                // See testGetLoggerRunFromStdin
                // @codeCoverageIgnoreStart
                define('STDERR', fopen('php://stderr', 'w'));
            }
            // @codeCoverageIgnoreEnd
            $handler = new StreamHandler(STDERR, Logger::DEBUG);
            $handler->setFormatter(new LineFormatter('%datetime% %level_name% %message%'));
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
        $client = $this;
        $logger = $this->getLogger();
        return function($message) use ($client, $write, $connection, $logger) {
            $client->emit('irc.received', array($message, $write, $connection, $logger));
        };
    }

    /**
     * Returns a callback for proxying events from the write stream to IRC
     * listeners of the client.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Connection on which
     *        the event occurred
     * @return callable
     */
    protected function getWriteCallback(ConnectionInterface $connection)
    {
        $client = $this;
        $logger = $this->getLogger();
        return function($message) use ($client, $connection, $logger) {
            $client->emit('irc.sent', array($message, $connection, $logger));
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
        $client = $this;
        $logger = $this->getLogger();
        return function($exception) use ($client, $connection, $logger) {
            $client->emit('connect.error', array($exception, $connection, $logger));
        };
    }

    /**
     * Returns a callback for when a connection is terminated, whether
     * explicitly by the bot or server or as a result of loss of connectivity.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Terminated connection
     * @return callable
     */
    protected function getEndCallback(ConnectionInterface $connection)
    {
        $client = $this;
        $logger = $this->getLogger();
        return function() use ($client, $connection, $logger) {
            $client->emit('connect.end', array($connection, $logger));
        };
    }

    /**
     * Configure streams to handle messages received from and sent to the
     * server.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Metadata for the
     *        connection over which messages are being exchanged
     * @param \React\Stream\Stream $stream Stream representing the connection
     *        to the server
     * @param \Phergie\Irc\Client\React\WriteStream $write Stream used to
     *        send events to the server
     */
    protected function configureStreams(ConnectionInterface $connection, Stream $stream, WriteStream $write)
    {
        $read = $this->getReadStream();
        $write->pipe($stream)->pipe($read);
        $read->on('irc.received', $this->getReadCallback($write, $connection));
        $write->on('data', $this->getWriteCallback($connection));
        $write->on('end', $this->getEndCallback($connection));
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

        // Terminate any preexisting connection
        $stream = $connection->getOption('stream');
        if ($stream !== null) {
            $this->getLoop()->removeStream($stream);
        }

        // Establish the socket connection
        $remote = $this->getRemote($connection);
        $context = $this->getContext($connection);
        try {
            $socket = $this->getSocket($remote, $context);
            $stream = $this->getStream($socket);
            $connection->setOption('stream', $stream);

            $write = $this->getWriteStream();
            $this->configureStreams($connection, $stream, $write);
            $this->identifyUser($connection, $write);
        } catch (Exception $e) {
            $this->emit(
                'connect.error',
                array(
                    $e->getMessage(),
                    $connection,
                    $this->getLogger()
                )
            );
        }

        $this->emit('connect.after.each', array($connection));
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

        $this->emit('connect.before.all', array($connections));
        foreach ($connections as $connection) {
            $this->addConnection($connection);
        }
        $this->emit('connect.after.all', array($connections));

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
