<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-client-react for the canonical source repository
 * @copyright Copyright (c) 2008-2012 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc\Client\React
 */

namespace Phergie\Irc\Client\React;

use Evenement\EventEmitter;
use Evenement\EventEmitterInterface;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

/**
 * IRC client implementation based on the React component library.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class Client extends EventEmitter
{
    /**
     * Event loop
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * List of connections
     *
     * @var \Phergie\Irc\ConnectionInterface[]
     */
    protected $connections = array();
    
    /**
     * Buffer for partial messages received
     *
     * @var string
     */
    protected $buffer = '';

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
     * Adds a connection to establish and listen on for events once the event
     * loop is executed.
     *
     * @param \Phergie\Irc\Client\React\Connection $connection
     */
    public function addConnection(Connection $connection)
    {
        $this->connections[] = $connection;
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
                'Unable to connect: socket error ' . $errno . ' ' . $errstr,
                Exception::ERR_CONNECTION_ATTEMPT_FAILED
            );
        }

        stream_set_blocking($socket, 0);

        return $socket;
    }

    /**
     * Derives a remote for a given connection.
     *
     * @param \Phergie\Irc\Client\React\Connection $connection
     * @return string Remote usable to establish a socket connection
     */
    protected function getRemote(Connection $connection)
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
     * @param \Phergie\Irc\Client\React\Connection $connection
     * @return array Associative array of context option key-value pairs
     */
    protected function getContext(Connection $connection)
    {
        $context = array();
        if ($connection->getOption('force-ipv4')) {
            $context['bindto'] = '0.0.0.0:0';
        }
        $encoding = $connection->getOption('encoding');
        if ($encoding) {
            $context['encoding'] = $encoding;
        }
        $context = array('socket' => $context);
        return $context;
    }

    /**
     * Returns a stream instance for receiving events from the server.
     *
     * @param resource $socket Socket for the connection to the server
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @return \Phergie\Irc\Client\React\ReadStream
     */
    protected function getReadStream($socket, LoopInterface $loop)
    {
        return new ReadStream($socket, $loop);
    }

    /**
     * Returns a stream instance for sending events to the server.
     *
     * @param resource $socket Socket for the connection to the server
     * @param \React\EventLoop\LoopInterface $loop Event loop
     * @return \Phergie\Irc\Client\React\WriteStream
     */
    protected function getWriteStream($socket, LoopInterface $loop)
    {
        return new WriteStream($socket, $loop);
    }

    /**
     * Returns a stream instance for receiving events from and sending events
     * to the server and logging them as they occur.
     *
     * @param \React\Stream\ReadableStreamInterface $read
     * @param \React\Stream\WritableStreamInterface $write
     * @return \Phergie\Irc\Client\React\LoggerStream
     */
    protected function getLoggerStream(ReadableStreamInterface $read, WritableStreamInterface $write)
    {
        return new LoggerStream($read, $write);
    }

    /**
     * Sets one event emitter to listen and transmit events from another event emitter.
     *
     * @param \Evenement\EventEmitterInterface $sender Original sender of the events
     * @param \Evenement\EventEmitterInterface $recipient Recipient to transmit the events
     * @param string $event Name of the event
     * @param array $args Optional additional arguments to include when transmitting
     */
    protected function transmit(EventEmitterInterface $sender, EventEmitterInterface $recipient, $event, array $args = array())
    {
        $sender->on($event, function() use ($recipient, $event, $args) {
            $recipient->emit($event, array_merge(func_get_args(), $args));
        });
    }

    /**
     * Initializes an IRC connection.
     *
     * @param \React\EventLoop\LoopInterface $loop Event loop to listen for events on the connection
     * @param \Phergie\Irc\Client\React\Connection $connection Metadata for connection to establish
     * @throws \Phergie\Irc\Client\React\Exception if unable to establish the connection
     */
    protected function connect(Connection $connection, LoopInterface $loop)
    {
        // Establish the socket connection
        $remote = $this->getRemote($connection);
        $context = $this->getContext($connection);
        $socket = $this->getSocket($remote, $context);

        // Get streams to receive messages from and send messages to the server
        $read = $this->getReadStream($socket, $loop);
        $write = $this->getWriteStream($socket, $loop);
        $logger = $this->getLoggerStream($read, $write);

        // Stores the streams in the connection for later use
        $connection->setReadStream($logger);
        $connection->setWriteStream($logger);

        // Transmit events on both streams to listeners of the client
        $args = array($connection);
        foreach (array('data', 'end', 'error', 'close', 'irc') as $event) {
            $this->transmit($read, $this, $event, $args);
        }
        foreach (array('drain', 'error', 'close', 'pipe') as $event) {
            $this->transmit($write, $this, $event, $args);
        }

        // Establish the user's identity
        $password = $connection->getPassword();
        if ($password) {
            $write->ircPass($password);
        }

        $write->ircUser(
            $connection->getUsername(),
            $connection->getHostname(),
            $connection->getServername(),
            $connection->getRealname()
        );

        $write->ircNick($connection->getNickname());
    }

    /**
     * Executes the event loop, which continues running until no active
     * connections remain.
     */
    public function run()
    {
        $loop = $this->getLoop();
        foreach ($this->connections as $connection) {
            $this->connect($connection, $loop);
        }
        $loop->run();
    }
}
