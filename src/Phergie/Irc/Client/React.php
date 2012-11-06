<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-client-reactphp for the canonical source repository
 * @copyright Copyright (c) 2008-2012 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc
 */

namespace Phergie\Irc\Client;

use Phergie\Irc\Client\React\Exception as ClientException;
use Phergie\Irc\Client\React\Stream;
use Phergie\Irc\ConnectionInterface;
use Phergie\Irc\GeneratorInterface;
use Phergie\Irc\ParserInterface;
use React\EventLoop\LoopInterface;
use React\Stream\Buffer;

/**
 * IRC client implementation based on the React component library.
 *
 * @category Phergie
 * @package Phergie\Irc
 */
class React
{
    /**
     * Event loop
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * IRC message parser
     *
     * @var \Phergie\Irc\ParserInterface
     */
    protected $parser;

    /**
     * IRC message generator
     *
     * @var \Phergie\Irc\GeneratorInterface
     */
    protected $generator;

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
     * Sets the IRC message parser dependency.
     *
     * @param \Phergie\Irc\ParserInterface $parser
     */
    public function setParser(ParserInterface $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Returns the IRC message parser dependency, initializing it if needed.
     *
     * @return \Phergie\Irc\ParserInterface
     */
    public function getParser()
    {
        if (!$this->parser) {
            $this->parser = new \Phergie\Irc\Parser();
        }
        return $this->parser;
    }

    /**
     * Sets the IRC message generator dependency.
     *
     * @param \Phergie\Irc\GeneratorInterface $generator
     */
    public function setGenerator(GeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Returns the IRC message generator dependency, initializing it if needed.
     *
     * @return \Phergie\Irc\GeneratorInterface
     */
    public function getGenerator()
    {
        if (!$this->generator) {
            $this->generator = new \Phergie\Irc\Generator();
        }
        return $this->generator;
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
            throw new ClientException(
                'Unable to connect: socket error ' . $errno . ' ' . $errstr,
                ClientException::ERR_CONNECTION_ATTEMPT_FAILED
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
     * Callback that handles data being received from the server.
     *
     * @param string $data Received data
     * @param \React\Stream\Stream $stream Stream on which data was received
     */
    public function handleRead($data, $stream)
    {
        $data = $this->buffer . $data;
        $messages = $this->getParser()->consumeAll($data);
        $this->buffer = $data;

        foreach ($messages as $message) {
            echo '<- ' . $message['message'];
            // @todo emit messages as events
        }
    }

    /**
     * Callback that handles data being sent by the client.
     *
     * @param string $data Sent data
     * @param \React\Stream\Stream $stream Stream on which data was sent
     */
    public function handleWrite($data, $stream)
    {
        echo '-> ' . $data;
    }

    /**
     * Initializes an IRC connection.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @throws \Phergie\Irc\Client\React\Exception if unable to establish the connection
     */
    protected function connect(ConnectionInterface $connection)
    {
        $loop = $this->getLoop();
        $remote = $this->getRemote($connection);
        $context = $this->getContext($connection);
        $socket = $this->getSocket($remote, $context);
        $client = $this;

        $stream = new Stream($socket, $loop);
        $stream->on('data', function($data, $stream) use ($client) {
            $client->handleRead($data, $stream);
        });
        $stream->on('write', function($data, $stream) use ($client) {
            $client->handleWrite($data, $stream);
        });

        $generator = $this->getGenerator();

        $password = $connection->getPassword();
        if ($password) {
            $message = $generator->ircPass($password);
            $stream->write($message);
        }

        $message = $generator->ircUser(
            $connection->getUsername(),
            $connection->getHostname(),
            $connection->getServername(),
            $connection->getRealname()
        );
        $stream->write($message);

        $message = $generator->ircNick($connection->getNickname());
        $stream->write($message);
    }

    /**
     * Executes the event loop, which continues running until no active
     * connections remain.
     */
    public function run()
    {
        foreach ($this->connections as $connection) {
            $this->connect($connection);
        }

        $this->getLoop()->run();
    }
}
