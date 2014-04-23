<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-parser for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc\Client\React
 */

namespace Phergie\Irc\Client\React;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phake;
use React\EventLoop\LoopInterface;

/**
 * Tests for \Phergie\Irc\Client\React\Client.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Port on which to test client/server stream code
     * @var string
     */
    private $port = '6667';

    /**
     * Instance of the class under test
     * @var \Phergie\Irc\Client\React\Client
     */
    private $client;

    /**
     * Test IRC message
     * @var array
     */
    private $message = array(
        'command' => 'PRIVMSG',
        'params' => array(
            'receivers' => 'Wiz',
            'text' => 'Hello',
            'all' => 'Wiz :Hello',
        ),
        'message' => "PRIVMSG Wiz :Hello\r\n",
    );

    /**
     * Performs common setup used across most tests.
     */
    public function setUp()
    {
        // Set up a local mock server to listen on a particular port so that
        // code for establishing a client connection via PHP streams can be
        // tested
        $this->server = stream_socket_server('tcp://0.0.0.0:' . $this->port, $errno, $errstr);
        stream_set_blocking($this->server, 0);
        if (!$this->server) {
            $this->markTestSkipped('Cannot listen on port ' . $this->port);
        }

        // Instantiate the class under test
        $this->client = Phake::partialMock('\Phergie\Irc\Client\React\Client');
    }

    /**
     * Performs common cleanup used across most tests.
     */
    public function tearDown()
    {
        // Shut down the mock server connection
        fclose($this->server);
    }

    /**
     * Tests setLoop().
     */
    public function testSetLoop()
    {
        $loop = $this->getMockLoop();
        $this->client->setLoop($loop);
        $this->assertSame($loop, $this->client->getLoop());
    }

    /**
     * Tests getLoop().
     */
    public function testGetLoop()
    {
        $this->assertInstanceOf('\React\EventLoop\LoopInterface', $this->client->getLoop());
    }

    /**
     * Tests setLogger().
     */
    public function testSetLogger()
    {
        $logger = $this->getMockLogger();
        $this->client->setLogger($logger);
        $this->assertSame($logger, $this->client->getLogger());
    }

    /**
     * Tests getLogger().
     */
    public function testGetLogger()
    {
        $logger = $this->client->getLogger();
        $this->assertInstanceOf('\Psr\Log\LoggerInterface', $logger);
        $this->assertSame($logger, $this->client->getLogger());
    }

    /**
     * Tests setTickInterval().
     */
    public function testSetTickInterval()
    {
        $tickInterval = 0.5;
        $this->client->setTickInterval($tickInterval);
        $this->assertSame($tickInterval, $this->client->getTickInterval());
    }

    /**
     * Tests getTickInterval().
     */
    public function testGetTickInterval()
    {
        $this->assertSame(0.2, $this->client->getTickInterval());
    }

    /**
     * Tests getLogger() as part of code read from STDIN to verify that error
     * logging is properly directed to STDERR by default.
     */
    public function testGetLoggerRunFromStdin()
    {
        $dir = __DIR__;
        $port = $this->port;
        $code = <<<EOF
<?php
require '$dir/../../../../../vendor/autoload.php';
\$client = new \Phergie\Irc\Client\React\Client;
\$logger = \$client->getLogger();
\$logger->debug("test");
EOF;
        $script = tempnam(sys_get_temp_dir(), '');
        file_put_contents($script, $code);
        $null = strcasecmp(substr(PHP_OS, 0, 3), 'win') == 0 ? 'NUL' : '/dev/null';
        $php = defined('PHP_BINARY') ? PHP_BINARY : PHP_BINDIR . '/php';

        $command = $php . ' < ' . $script . ' 2>' . $null;
        $output = shell_exec($command);
        $this->assertNull($output);

        $command = $php . ' < ' . $script . ' 2>&1';
        $output = shell_exec($command);
        $this->assertRegExp('/^[0-9]{4}(-[0-9]{2}){2} [0-9]{2}(:[0-9]{2}){2} DEBUG test$/', $output);

        unlink($script);
    }

    /**
     * Tests addConnection() when a socket exception is thrown.
     */
    public function testAddConnectionWithException()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->getMockLogger();
        $exception = new Exception('message', Exception::ERR_CONNECTION_ATTEMPT_FAILED);

        $this->client->setLogger($logger);
        Phake::when($this->client)
            ->getSocket($this->isType('string'), $this->isType('array'))
            ->thenThrow($exception);

        $this->client->setLoop($this->getMockLoop());
        $this->client->run($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.each', array($connection)),
            Phake::verify($this->client)->emit('connect.error', array($exception->getMessage(), $connection, $logger)),
            Phake::verify($this->client)->emit('connect.after.each', array($connection))
        );

        Phake::verify($logger)->error($exception->getMessage());
    }

    /**
     * Tests addConnection() without a password.
     */
    public function testAddConnectionWithoutPassword()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $writeStream = $this->getMockWriteStream();
        Phake::when($connection)->getPassword()->thenReturn(null);
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);

        $this->client->setLogger($this->getMockLogger());
        $this->client->addConnection($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.each', array($connection)),
            Phake::verify($writeStream)->ircUser('username', 'hostname', 'servername', 'realname'),
            Phake::verify($writeStream)->ircNick('nickname'),
            Phake::verify($this->client)->emit('connect.after.each', array($connection))
        );

        Phake::verify($writeStream, Phake::never())->ircPass($this->anything());
    }

    /**
     * Tests addConnection() with a password.
     */
    public function testAddConnectionWithPassword()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);

        $this->client->setLogger($this->getMockLogger());
        $this->client->addConnection($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.each', array($connection)),
            Phake::verify($writeStream)->ircPass('password'),
            Phake::verify($writeStream)->ircUser('username', 'hostname', 'servername', 'realname'),
            Phake::verify($writeStream)->ircNick('nickname'),
            Phake::verify($this->client)->emit('connect.after.each', array($connection))
        );
    }

    /**
     * Tests addConnection() with a previously added connection.
     */
    public function testAddConnectionWithPreviouslyAddedConnection()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $writeStream = $this->getMockWriteStream();
        $loop = $this->getMockLoop();
        $oldStream = $this->getMockStream();
        Phake::when($this->client)->getLoop()->thenReturn($loop);
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($connection)->getOption('stream')->thenReturn($oldStream);

        $this->client->setLogger($this->getMockLogger());
        $this->client->addConnection($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.each', array($connection)),
            Phake::verify($loop)->removeStream($oldStream),
            Phake::verify($connection)->setOption('stream',
                $this->logicalAnd(
                    $this->isInstanceOf('\React\Stream\Stream'),
                    $this->logicalNot($this->identicalTo($oldStream))
                )
            ),
            Phake::verify($writeStream)->ircPass('password'),
            Phake::verify($writeStream)->ircUser('username', 'hostname', 'servername', 'realname'),
            Phake::verify($writeStream)->ircNick('nickname'),
            Phake::verify($this->client)->emit('connect.after.each', array($connection))
        );
    }

    /**
     * Tests addConnection() with a connection configured to force usage of
     * IPv4.
     */
    public function testAddConnectionWithForceIPv4Enabled()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($connection)->getOption('force-ipv4')->thenReturn(true);

        $this->client->setLogger($this->getMockLogger());
        $this->client->addConnection($connection);

        Phake::verify($this->client)->getStream(Phake::capture($socket));
        $expected = array('socket' => array('bindto' => '0.0.0.0:0'));
        $this->assertSame($expected, stream_context_get_options($socket));
    }

    /**
     * Tests addConnection() with a concrete write stream, where most other
     * tests have it mocked out.
     */
    public function testAddConnectionWithConcreteWriteStream()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->client->getLogger();
        $logger->popHandler();
        $stream = fopen('php://memory', 'w+');
        $handler = new StreamHandler($stream, Logger::DEBUG);
        $handler->setFormatter(new LineFormatter("%message%\r\n"));
        $logger->pushHandler($handler);

        $this->client->addConnection($connection);

        $mask = 'nickname!username@0.0.0.0';
        $expected = "$mask PASS :password\r\n$mask USER username hostname servername :realname\r\n$mask NICK :nickname\r\n";
        fseek($stream, 0);
        $actual = stream_get_contents($stream);
        $this->assertSame($expected, $actual);
    }

    /**
     * Tests that the client emits an event when it receives a message from the
     * server.
     */
    public function testReadCallback()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->getMockLogger();
        $readStream = $this->getMockReadStream();
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($this->client)->getReadStream($connection)->thenReturn($readStream);

        $this->client->setLogger($logger);
        $this->client->addConnection($connection);

        Phake::verify($readStream)->on('irc.received', Phake::capture($callback));
        $callback($this->message);
        Phake::verify($this->client)->emit('irc.received', Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertCount(4, $params);
        $this->assertSame($this->message, $params[0]);
        $this->assertInstanceOf('\Phergie\Irc\Client\React\WriteStream', $params[1]);
        $this->assertSame($connection, $params[2]);
        $this->assertSame($logger, $params[3]);
    }

    /**
     * Tests that the client emits an event when it sends a message to the
     * server.
     */
    public function testWriteCallback()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->getMockLogger();
        $readStream = $this->getMockReadStream();
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($this->client)->getReadStream($connection)->thenReturn($readStream);

        $this->client->setLogger($logger);
        $this->client->addConnection($connection);

        Phake::verify($writeStream)->on('data', Phake::capture($callback));
        $callback($this->message);
        Phake::verify($this->client)->emit('irc.sent', Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertCount(4, $params);
        $this->assertSame($this->message, $params[0]);
        $this->assertInstanceOf('\Phergie\Irc\Client\React\WriteStream', $params[1]);
        $this->assertSame($connection, $params[2]);
        $this->assertSame($logger, $params[3]);
    }

    /**
     * Tests that the client emits an event when a connection error occurs.
     */
    public function testErrorCallback()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->getMockLogger();
        $readStream = $this->getMockReadStream();
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($this->client)->getReadStream($connection)->thenReturn($readStream);

        $this->client->setLogger($logger);
        $this->client->addConnection($connection);

        Phake::verify($readStream)->on('error', Phake::capture($callback));
        $callback($this->message);
        Phake::verify($this->client)->emit('connect.error', Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertCount(3, $params);
        $this->assertSame($this->message, $params[0]);
        $this->assertSame($connection, $params[1]);
        $this->assertSame($logger, $params[2]);
    }

    /**
     * Tests that the client emits an event when a connection is terminated.
     */
    public function testEndCallback()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->getMockLogger();
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);

        $this->client->setLogger($logger);
        $this->client->addConnection($connection);

        Phake::verify($writeStream)->on('end', Phake::capture($callback));
        $callback();
        Phake::verify($this->client)->emit('connect.end', Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertCount(2, $params);
        $this->assertSame($connection, $params[0]);
        $this->assertSame($logger, $params[1]);
    }

    /**
     * Tests that the client emits a periodic event per connection.
     */
    public function testTickCallback()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->getMockLogger();
        $loop = $this->getMockLoop();
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);

        $this->client->setLoop($loop);
        $this->client->setLogger($logger);
        $this->client->addConnection($connection);

        Phake::verify($loop)->addPeriodicTimer(0.2, Phake::capture($callback));
        $callback();
        Phake::verify($this->client)->emit('irc.tick', Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertCount(3, $params);
        $this->assertSame($writeStream, $params[0]);
        $this->assertSame($connection, $params[1]);
        $this->assertSame($logger, $params[2]);
    }

    /**
     * Tests run() with multiple connections.
     */
    public function testRunWithMultipleConnections()
    {
        $loop = $this->getMockLoop();
        $connection1 = $this->getMockConnection();
        $connection2 = $this->getMockConnection();
        $connections = array($connection1, $connection2);
        Phake::when($this->client)->getLoop()->thenReturn($loop);

        $this->client->setLogger($this->getMockLogger());
        $this->client->run($connections);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.all', array($connections)),
            Phake::verify($this->client)->addConnection($connection1),
            Phake::verify($this->client)->addConnection($connection2),
            Phake::verify($this->client)->emit('connect.after.all', array($connections)),
            Phake::verify($loop, Phake::times(1))->run()
        );
    }

    /**
     * Tests run() with a single connection.
     */
    public function testRunWithSingleConnection()
    {
        $loop = $this->getMockLoop();
        $connection = $this->getMockConnection();
        $connections = array($connection);
        Phake::when($this->client)->getLoop()->thenReturn($loop);

        $this->client->setLogger($this->getMockLogger());
        $this->client->run($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.all', array($connections)),
            Phake::verify($this->client)->addConnection($connection),
            Phake::verify($this->client)->emit('connect.after.all', array($connections)),
            Phake::verify($loop, Phake::times(1))->run()
        );
    }

    /**
     * Tests addTimer().
     */
    public function testAddTimer()
    {
        $interval = 5;
        $callback = function() { };
        $timer = $this->getMockTimer();
        $loop = $this->getMockLoop();
        Phake::when($loop)->addTimer($interval, $callback)->thenReturn($timer);
        Phake::when($this->client)->getLoop()->thenReturn($loop);

        $this->assertSame($timer, $this->client->addTimer($interval, $callback));
    }

    /**
     * Tests addPeriodicTimer().
     */
    public function testAddPeriodicTimer()
    {
        $interval = 5;
        $callback = function() { };
        $timer = $this->getMockTimer();
        $loop = $this->getMockLoop();
        Phake::when($loop)->addPeriodicTimer($interval, $callback)->thenReturn($timer);
        Phake::when($this->client)->getLoop()->thenReturn($loop);

        $this->assertSame($timer, $this->client->addPeriodicTimer($interval, $callback));
    }

    /**
     * Tests cancelTimer().
     */
    public function testCancelTimer()
    {
        $timer = $this->getMockTimer();
        $loop = $this->getMockLoop();
        Phake::when($this->client)->getLoop()->thenReturn($loop);

        $this->client->cancelTimer($timer);

        Phake::verify($loop)->cancelTimer($timer);
    }

    /**
     * Tests isTimerActive().
     */
    public function testIsTimerActive()
    {
        $timer = $this->getMockTimer();
        $loop = $this->getMockLoop();
        Phake::when($this->client)->getLoop()->thenReturn($loop);

        $this->client->isTimerActive($timer);

        Phake::verify($loop)->isTimerActive($timer);
    }

    /**
     * Returns a mock logger.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function getMockLogger()
    {
        return Phake::mock('\Psr\Log\LoggerInterface');
    }

    /**
     * Returns a mock timer.
     *
     * @return \React\EventLoop\Timer\TimerInterface
     */
    protected function getMockTimer()
    {
        return Phake::mock('\React\EventLoop\Timer\TimerInterface');
    }

    /**
     * Returns a mock connection.
     *
     * @return \Phergie\Irc\ConnectionInterface
     */
    protected function getMockConnection()
    {
        return Phake::mock('\Phergie\Irc\ConnectionInterface');
    }

    /**
     * Returns a mock write stream.
     *
     * @return \Phergie\Irc\Client\React\WriteStream
     */
    protected function getMockWriteStream()
    {
        $write = Phake::mock('\Phergie\Irc\Client\React\WriteStream');
        Phake::when($write)
            ->pipe($this->isInstanceOf('\React\Stream\WritableStreamInterface'))
            ->thenGetReturnByLambda(function($stream) { return $stream; });
        return $write;
    }

    /**
     * Returns a mock stream.
     *
     * @return \React\Stream\Stream
     */
    protected function getMockStream()
    {
        return Phake::mock('\React\Stream\Stream');
    }

    /**
     * Returns a mock read stream.
     *
     * @return \Phergie\Irc\Client\React\ReadStream
     */
    protected function getMockReadStream()
    {
        return Phake::mock('\Phergie\Irc\Client\React\ReadStream');
    }

    /**
     * Returns a mock loop.
     *
     * @return \React\EventLoop\LoopInterface
     */
    protected function getMockLoop()
    {
        return Phake::mock('\React\EventLoop\LoopInterface');
    }

    /**
     * Returns a mock connection for testing addConnection().
     *
     * @return \Phergie\Irc\ConnectionInterface
     */
    protected function getMockConnectionForAddConnection()
    {
        $connection = $this->getMockConnection();
        Phake::when($connection)->getPassword()->thenReturn('password');
        Phake::when($connection)->getUsername()->thenReturn('username');
        Phake::when($connection)->getHostname()->thenReturn('hostname');
        Phake::when($connection)->getServername()->thenReturn('servername');
        Phake::when($connection)->getRealname()->thenReturn('realname');
        Phake::when($connection)->getNickname()->thenReturn('nickname');
        Phake::when($connection)->getServerHostname()->thenReturn('0.0.0.0');
        Phake::when($connection)->getServerPort()->thenReturn($this->port);
        return $connection;
    }
}
