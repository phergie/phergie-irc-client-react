<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-parser for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license Simplified BSD License
 * @package Phergie\Irc\Client\React
 */

namespace Phergie\Irc\Tests\Client\React;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phake;
use Phergie\Irc\Client\React\Exception;
use Phergie\Irc\Client\React\ReadStream;
use Phergie\Irc\Client\React\WriteStream;
use React\EventLoop\LoopInterface;
use React\SocketClient\SecureConnector;
use React\Stream\StreamInterface;

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
     * Tests setResolver().
     */
    public function testSetResolver()
    {
        $this->client->setLoop($this->getMockLoop());
        $resolver = $this->getMockResolver();
        $this->client->setResolver($resolver);
        $this->assertSame($resolver, $this->client->getResolver());
    }

    /**
     * Tests getResolver().
     */
    public function testGetResolver()
    {
        $this->client->setLoop($this->getMockLoop());
        $this->assertInstanceOf('\React\Dns\Resolver\Resolver', $this->client->getResolver());
    }

    /**
     * Tests setDnsServer().
     */
    public function testSetDnsServer()
    {
        $ipAddress = '1.2.3.4';
        $this->client->setDnsServer($ipAddress);
        $this->assertSame($ipAddress, $this->client->getDnsServer());
    }

    /**
     * Tests getDnsServer().
     */
    public function testGetDnsServer()
    {
        $this->assertSame('8.8.8.8', $this->client->getDnsServer());
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
        $script = __DIR__ . '/_files/testGetLoggerRunFromStdin.php';
        $null = strcasecmp(substr(PHP_OS, 0, 3), 'win') == 0 ? 'NUL' : '/dev/null';
        $php = defined('PHP_BINARY') ? PHP_BINARY : PHP_BINDIR . '/php';

        $command = $php . ' ' . $script . ' 2>' . $null;
        $output = shell_exec($command);
        $this->assertEmpty($output);

        $command = $php . ' ' . $script . ' 2>&1';
        $output = shell_exec($command);
        $this->assertRegExp('/^[0-9]{4}(-[0-9]{2}){2} [0-9]{2}(:[0-9]{2}){2} DEBUG test \\[\\]$/', $output);
    }

    /**
     * Tests addConnection() when a socket exception is thrown.
     */
    public function testAddConnectionWithException()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        $logger = $this->getMockLogger();
        $exception = new Exception('message', Exception::ERR_CONNECTION_ATTEMPT_FAILED);

        $this->client->setLogger($logger);
        Phake::when($this->client)
            ->getSocket($this->isType('string'), $this->isType('array'))
            ->thenThrow($exception);

        $this->client->setLoop($this->getMockLoop());
        $this->client->setResolver($this->getMockResolver());
        $this->client->run($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.each', array($connection)),
            Phake::verify($this->client)->emit('connect.error', array($exception->getMessage(), $connection, $logger)),
            Phake::verify($this->client)->emit('connect.after.each', array($connection, null))
        );

        Phake::verify($logger)->error($exception->getMessage());

        Phake::verify($this->client, Phake::never())->addActiveConnection($connection);
        $this->assertSame(array(), $this->client->getActiveConnections());
    }

    /**
     * Tests addConnection() without a password.
     */
    public function testAddConnectionWithoutPassword()
    {
        $connection = $this->getMockConnectionForAddConnection();
        Phake::when($connection)->getPassword()->thenReturn(null);
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($connection)->getOption('write')->thenReturn($writeStream);

        $this->client->setLogger($this->getMockLogger());
        $this->client->setResolver($this->getMockResolver());
        $this->client->addConnection($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.each', array($connection)),
            Phake::verify($connection)->setData('write', $writeStream),
            Phake::verify($writeStream)->ircUser('username', 'hostname', 'servername', 'realname'),
            Phake::verify($writeStream)->ircNick('nickname'),
            Phake::verify($this->client)->emit('connect.after.each', array($connection, $writeStream))
        );

        Phake::verify($writeStream, Phake::never())->ircPass($this->anything());

        Phake::verify($this->client)->addActiveConnection($connection);
        $this->assertSame(array($connection), $this->client->getActiveConnections());
    }

    /**
     * Tests addConnection() with a password.
     */
    public function testAddConnectionWithPassword()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($connection)->getOption('write')->thenReturn($writeStream);

        $this->client->setLogger($this->getMockLogger());
        $this->client->setResolver($this->getMockResolver());
        $this->client->addConnection($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.each', array($connection)),
            Phake::verify($connection)->setData('write', $writeStream),
            Phake::verify($writeStream)->ircPass('password'),
            Phake::verify($writeStream)->ircUser('username', 'hostname', 'servername', 'realname'),
            Phake::verify($writeStream)->ircNick('nickname'),
            Phake::verify($this->client)->emit('connect.after.each', array($connection, $writeStream))
        );

        Phake::verify($this->client)->addActiveConnection($connection);
        $this->assertSame(array($connection), $this->client->getActiveConnections());
    }

    /**
     * Tests addConnection() with a connection configured to force usage of
     * IPv4.
     */
    public function testAddConnectionWithForceIPv4Enabled()
    {
        $connection = $this->getMockConnectionForAddConnection();
        Phake::when($connection)->getOption('force-ipv4')->thenReturn(true);
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);

        $this->client->setLogger($this->getMockLogger());
        $this->client->setResolver($this->getMockResolver());
        $this->client->addConnection($connection);

        Phake::verify($this->client)->getSocket($this->isType('string'), Phake::capture($actual));
        $expected = array('socket' => array('bindto' => '0.0.0.0:0'));
        $this->assertSame($expected, $actual);
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

        $this->client->setResolver($this->getMockResolver());
        $this->client->addConnection($connection);

        $mask = 'nickname!username@0.0.0.0';
        $expected = "$mask PASS :password\r\n$mask USER username hostname servername :realname\r\n$mask NICK :nickname\r\n";
        fseek($stream, 0);
        $actual = stream_get_contents($stream);
        $this->assertSame($expected, $actual);
    }

    /**
     * Tests that adding a connection configured to use the SSL transport and
     * force use of IPv4 throws an exception.
     *
     * @see https://github.com/reactphp/socket-client/issues/4
     */
    public function testAddConnectionWithSslTransportAndForceIpv4ThrowsException()
    {
        $connection = $this->getMockConnectionForAddConnection();
        Phake::when($connection)->getOption('transport')->thenReturn('ssl');
        Phake::when($connection)->getOption('force-ipv4')->thenReturn(true);
        $this->client->setResolver($this->getMockResolver());

        try {
            $this->client->addConnection($connection);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertSame(Exception::ERR_CONNECTION_STATE_UNSUPPORTED, $e->getCode());
        }
    }

    /**
     * Tests that a connect.error event is emitted if a stream initialized
     * error is encountered.
     */
    public function testAddConnectionWithStreamInitializationError()
    {
        $connection = $this->getMockConnectionForAddConnection();
        Phake::when($connection)->getOption('transport')->thenReturn('ssl');

        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);

        $exception = new Exception('message');
        Phake::when($this->client)->identifyUser($connection, $writeStream)->thenThrow($exception);

        Phake::when($this->client)->getSecureConnector()->thenReturn($this->getMockSecureConnector());

        $logger = $this->getMockLogger();

        $this->client->setLogger($logger);
        $this->client->setLoop($this->getMockLoop());
        $this->client->addConnection($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.each', array($connection)),
            Phake::verify($this->client)->emit('connect.error', array($exception->getMessage(), $connection, $logger)),
            Phake::verify($this->client)->emit('connect.after.each', array($connection, null))
        );

        Phake::verify($this->client, Phake::never())->addActiveConnection($connection);
        $this->assertSame(array(), $this->client->getActiveConnections());
    }

    /**
     * Helper for testing read and write callbacks.
     *
     * @param string $onEvent
     * @param string $emitEvent
     * @param \Phergie\Irc\Client\React\ReadStream|\Phergie\Irc\Client\React\WriteStream $onStream
     * @param \Phergie\Irc\Client\React\ReadStream $readStream
     * @param \Phergie\Irc\Client\React\WriteStream $writeStream
     */
    protected function doCallbackTest($onEvent, $emitEvent, $onStream, ReadStream $readStream, WriteStream $writeStream)
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->getMockLogger();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($this->client)->getReadStream($connection)->thenReturn($readStream);

        $this->client->setLogger($logger);
        $this->client->setResolver($this->getMockResolver());
        $this->client->addConnection($connection);

        Phake::verify($onStream)->on($onEvent, Phake::capture($callback));
        $callback($this->message);
        Phake::verify($this->client)->emit($emitEvent, Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertCount(4, $params);
        $this->assertSame($this->message, $params[0]);
        $this->assertInstanceOf('\Phergie\Irc\Client\React\WriteStream', $params[1]);
        $this->assertSame($connection, $params[2]);
        $this->assertSame($logger, $params[3]);
    }

    /**
     * Tests that the client emits an event when it receives a message from the
     * server.
     */
    public function testReadCallback()
    {
        $readStream = $this->getMockReadStream();
        $writeStream = $this->getMockWriteStream();
        $this->doCallbackTest(
            'irc.received',
            'irc.received',
            $readStream,
            $readStream,
            $writeStream
        );
    }

    /**
     * Tests that the client emits an event when it sends a message to the
     * server.
     */
    public function testWriteCallback()
    {
        $readStream = $this->getMockReadStream();
        $writeStream = $this->getMockWriteStream();
        $this->doCallbackTest(
            'data',
            'irc.sent',
            $writeStream,
            $readStream,
            $writeStream
        );
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
        $this->client->setResolver($this->getMockResolver());
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
     * Tests that the client emits an event when a dns lookup error occurs.
     */
    public function testErrorCallbackResolverReject()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->getMockLogger();

        $this->client->setLogger($logger);
        $this->client->setResolver($this->getMockResolver('string', true));
        $this->client->addConnection($connection);

        Phake::verify($this->client)->emit('connect.error', Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertCount(3, $params);
        $this->assertSame('Something went wrong', $params[0]);
        $this->assertSame($connection, $params[1]);
        $this->assertSame($logger, $params[2]);
    }

    /**
     * Tests that the client emits an event when a connection is terminated.
     */
    public function testWriteTriggeredEndCallback()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->getMockLogger();
        $loop = $this->getMockLoop();
        $writeStream = $this->getMockWriteStream();
        $stream = $this->getMockStream();
        $timer = $this->getMockTimer();
        Phake::when($this->client)->getStream(Phake::anyParameters())->thenReturn($stream);
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($loop)->addPeriodicTimer(0.2, $this->isType('callable'))->thenReturn($timer);

        $this->client->setLoop($loop);
        $this->client->setLogger($logger);
        $this->client->setResolver($this->getMockResolver());
        $this->client->addConnection($connection);

        Phake::verify($writeStream)->on('end', Phake::capture($streamCloser));
        call_user_func($streamCloser);
        Phake::verify($stream)->end();

        Phake::verify($stream)->on('end', Phake::capture($callback));
        call_user_func($callback);
        Phake::verify($this->client)->emit('connect.end', Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertCount(2, $params);
        $this->assertSame($connection, $params[0]);
        $this->assertSame($logger, $params[1]);
        Phake::verify($loop)->cancelTimer($timer);
        Phake::verify($connection)->clearData();
        Phake::verify($writeStream)->close();
    }

    /**
     * Tests that the client emits an event when a connection is terminated.
     */
    public function testReadTriggeredEndCallback()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $logger = $this->getMockLogger();
        $loop = $this->getMockLoop();
        $readStream = $this->getMockReadStream();
        $stream = $this->getMockStream();
        $timer = $this->getMockTimer();
        Phake::when($this->client)->getStream(Phake::anyParameters())->thenReturn($stream);
        Phake::when($this->client)->getReadStream($connection)->thenReturn($readStream);
        Phake::when($loop)->addPeriodicTimer(0.2, $this->isType('callable'))->thenReturn($timer);

        $this->client->setLoop($loop);
        $this->client->setLogger($logger);
        $this->client->setResolver($this->getMockResolver());
        $this->client->addConnection($connection);

        Phake::verify($readStream)->on('end', Phake::capture($streamCloser));
        call_user_func($streamCloser);
        Phake::verify($stream)->end();

        Phake::verify($stream)->on('end', Phake::capture($callback));
        call_user_func($callback);
        Phake::verify($this->client)->emit('connect.end', Phake::capture($params));
        $this->assertInternalType('array', $params);
        $this->assertCount(2, $params);
        $this->assertSame($connection, $params[0]);
        $this->assertSame($logger, $params[1]);
        Phake::verify($loop)->cancelTimer($timer);
        Phake::verify($connection)->clearData();
        Phake::verify($readStream)->close();

        Phake::verify($this->client)->removeActiveConnection($connection);
        $this->assertSame(array(), $this->client->getActiveConnections());
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
        $timer = $this->getMockTimer();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($loop)->addPeriodicTimer(0.2, $this->isType('callable'))->thenReturn($timer);

        $this->client->setLoop($loop);
        $this->client->setLogger($logger);
        $this->client->setResolver($this->getMockResolver());
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
        $writeStream1 = $this->getMockWriteStream();
        $writeStream2 = $this->getMockWriteStream();
        $writeStreams = array($writeStream1, $writeStream2);
        Phake::when($this->client)->getWriteStream($connection1)->thenReturn($writeStream1);
        Phake::when($this->client)->getWriteStream($connection2)->thenReturn($writeStream2);
        Phake::when($connection1)->getOption('write')->thenReturn($writeStream1);
        Phake::when($connection2)->getOption('write')->thenReturn($writeStream2);

        $this->client->setLogger($this->getMockLogger());
        $this->client->setResolver($this->getMockResolver('null'));
        $this->client->run($connections);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.all', array($connections)),
            Phake::verify($this->client)->addConnection($connection1),
            Phake::verify($this->client)->addConnection($connection2),
            Phake::verify($this->client)->emit('connect.after.all', array($connections, $writeStreams)),
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
        $writeStream = $this->getMockWriteStream();
        $writeStreams = array($writeStream);
        Phake::when($connection)->getOption('write')->thenReturn($writeStream);
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($this->client)->getLoop()->thenReturn($loop);

        $this->client->setLogger($this->getMockLogger());
        $this->client->setResolver($this->getMockResolver('null'));
        $this->client->run($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.all', array($connections)),
            Phake::verify($this->client)->addConnection($connection),
            Phake::verify($this->client)->emit('connect.after.all', array($connections, $writeStreams)),
            Phake::verify($loop, Phake::times(1))->run()
        );
    }

    /**
     * Tests run() with a connection configured to use the SSL transport.
     */
    public function testRunWithConnectionUsingSslTransport()
    {
        $loop = $this->getMockLoop();
        $connection = $this->getMockConnectionForAddConnection();
        $connections = array($connection);
        $writeStream = $this->getMockWriteStream();
        $writeStreams = array($writeStream);
        Phake::when($connection)->getOption('write')->thenReturn($writeStream);
        Phake::when($connection)->getOption('transport')->thenReturn('ssl');
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        Phake::when($this->client)->getLoop()->thenReturn($loop);

        $this->client->setLogger($this->getMockLogger());
        $this->client->setResolver($this->getMockResolver('null'));
        $this->client->run($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.all', array($connections)),
            Phake::verify($this->client)->addConnection($connection),
            Phake::verify($this->client)->emit('connect.after.all', array($connections, $writeStreams)),
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
            ->thenReturnCallback(function($stream) { return $stream; });
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
        $loop = Phake::mock('\React\EventLoop\LoopInterface');
        Phake::when($loop)->addPeriodicTimer(Phake::anyParameters())->thenReturn($this->getMockTimer());
        return $loop;
    }

    /**
     * Returns a mock loop.
     *
     * @return \React\Dns\Resolver\Resolver
     */
    protected function getMockResolver($type = 'string', $reject = false)
    {
        $resolver = Phake::mock('\React\Dns\Resolver\Resolver');

        if ($reject) {
            $promise = new FakePromiseReject(new Exception('Something went wrong'));
        } else {
            $promise = new FakePromiseResolve('0.0.0.0');
        }

        Phake::when($resolver)->resolve($this->isType($type))->thenReturn($promise);

        return $resolver;
    }

    /**
     * Returns a mock secure connector.
     *
     * @return \React\SocketClient\SecureConnector
     */
    protected function getMockSecureConnector()
    {
        $connector = Phake::mock('\React\SocketClient\SecureConnector');
        $promise = new FakePromiseResolve($this->getMockStream());
        Phake::when($connector)->create('0.0.0.0', $this->port)->thenReturn($promise);
        return $connector;
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
        Phake::when($connection)->getMask()->thenReturn('nickname!username@0.0.0.0');
        return $connection;
    }
}

class FakePromise
{
    protected $args;

    public function __construct()
    {
        $this->args = func_get_args();
    }
}

class FakePromiseResolve extends FakePromise
{
    public function then($callback)
    {
        call_user_func_array($callback, $this->args);
    }
}

class FakePromiseReject extends FakePromise
{
    public function then($unUsedCallback, $callback)
    {
        call_user_func_array($callback, $this->args);
    }
}
