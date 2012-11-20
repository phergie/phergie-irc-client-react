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
use Phergie\Irc\ConnectionInterface;
use React\EventLoop\LoopInterface;

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
     * Logging stream
     *
     * @var \Phergie\Irc\Client\React\LoggerStream
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
     * Adds a connection to establish and listen on for events once the event
     * loop is executed.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     */
    public function addConnection(ConnectionInterface $connection)
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
        $encoding = $connection->getOption('encoding');
        if ($encoding) {
            $context['encoding'] = $encoding;
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
    protected function getStream($socket, LoopInterface $loop)
    {
        return new \React\Stream\Stream($socket, $loop);
    }

    /**
     * Returns a stream instance for parsing messages from the server and
     * emitting them as events.
     *
     * @return \Phergie\Irc\Client\React\ReadStream
     */
    protected function getReadStream()
    {
        return new ReadStream();
    }

    /**
     * Returns a stream instance for sending events to the server.
     *
     * @return \Phergie\Irc\Client\React\WriteStream
     */
    protected function getWriteStream()
    {
        return new WriteStream();
    }

    /**
     * Returns a stream instance for logging data on the socket connection.
     *
     * @return \Phergie\Irc\Client\React\LoggerStream
     */
    protected function getLoggerStream()
    {
        if (!$this->logger) {
            $this->logger = new LoggerStream();
        }
        return $this->logger;
    }

    /**
     * Initializes an IRC connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Metadata for connection to establish
     * @param \React\EventLoop\LoopInterface $loop Event loop to listen for events on the connection
     * @throws \Phergie\Irc\Client\React\Exception if unable to establish the connection
     */
    protected function connect(ConnectionInterface $connection, LoopInterface $loop)
    {
        // Establish the socket connection
        $remote = $this->getRemote($connection);
        $context = $this->getContext($connection);
        $socket = $this->getSocket($remote, $context);
        $stream = $this->getStream($socket, $loop);

        // Configure streams to handle messages received from and sent to the server
        $read = $this->getReadStream();
        $write = $this->getWriteStream();
        $logger = $this->getLoggerStream();

        $stream->pipe($logger);
        $stream->pipe($read);
        $write->pipe($logger);
        $write->pipe($stream);
        $client = $this;
        $read->on('irc', function($message) use ($client, $write, $connection, $logger) {
            $client->emit('irc', array($message, $write, $connection));
        });

        // Establish the user's identity to the server
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
