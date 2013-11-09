<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-parser for the canonical source repository
 * @copyright Copyright (c) 2008-2013 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc\Client\React
 */

namespace Phergie\Irc\Client\React;

/**
 * Tests for \Phergie\Irc\Client\React\Client.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests setLoop().
     */
    public function testSetLoop()
    {
        $client = new Client;
        $loop = $this->getMock('\React\EventLoop\LoopInterface');
        $client->setLoop($loop);
        $this->assertSame($loop, $client->getLoop());
    }

    /**
     * Tests getLoop().
     */
    public function testGetLoop()
    {
        $client = new Client;
        $this->assertInstanceOf('\React\EventLoop\LoopInterface', $client->getLoop());
    }

    /**
     * Returns a mock logger.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function getMockLogger()
    {
        return $this->getMock('\Psr\Log\LoggerInterface', array(), array(), '', false);
    }

    /**
     * Tests setLogger().
     */
    public function testSetLogger()
    {
        $client = new Client;
        $logger = $this->getMockLogger();
        $client->setLogger($logger);
        $this->assertSame($logger, $client->getLogger());
    }

    /**
     * Tests getLogger().
     */
    public function testGetLogger()
    {
        $client = new Client;
        $logger = $client->getLogger();
        $this->assertInstanceOf('\Psr\Log\LoggerInterface', $logger);
        $this->assertSame($logger, $client->getLogger());
    }

    /**
     * Returns a mock connection for testing getRemote().
     *
     * @param string $serverHostname
     * @param int $serverPort
     */
    protected function getMockConnectionForGetRemote($serverHostname, $serverPort)
    {
        $connection = $this->getMockConnection();

        $connection
            ->expects($this->any())
            ->method('getServerHostname')
            ->will($this->returnValue($serverHostname));

        $connection
            ->expects($this->any())
            ->method('getServerPort')
            ->will($this->returnValue($serverPort));

        return $connection;
    }

    /**
     * Returns a reflector for a non-public method of the class under test,
     * used to invoke that method directly to test it.
     *
     * @param string $method Name of the non-public method
     * @return \ReflectionMethod
     */
    protected function getMethod($method)
    {
        $method = new \ReflectionMethod('\Phergie\Irc\Client\React\Client', $method);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Tests getRemote().
     */
    public function testGetRemote()
    {
        $client = new Client;
        $method = $this->getMethod('getRemote');

        $connection = $this->getMockConnectionForGetRemote('irc.freenode.net', 6667);
        $this->assertSame('tcp://irc.freenode.net:6667', $method->invoke($client, $connection));

        $connection = $this->getMockConnectionForGetRemote('irc.rizon.net', 2323);
        $connection
            ->expects($this->any())
            ->method('getOption')
            ->with('transport')
            ->will($this->returnValue('ssl'));
        $this->assertSame('ssl://irc.rizon.net:2323', $method->invoke($client, $connection));
    }

    /**
     * Tests getContext().
     */
    public function testGetContext()
    {
        $client = new Client;
        $method = $this->getMethod('getContext');

        $connection = $this->getMockConnection();
        $this->assertSame(array('socket' => array()), $method->invoke($client, $connection));

        $connection = $this->getMockConnection();
        $connection
            ->expects($this->once())
            ->method('getOption')
            ->with('force-ipv4')
            ->will($this->returnValue(true));
        $this->assertSame(array('socket' => array('bindto' => '0.0.0.0:0')), $method->invoke($client, $connection));
    }

    /**
     * Tests getStream().
     */
    public function testGetStream()
    {
        $client = new Client;
        $method = $this->getMethod('getStream');
        $socket = fopen('php://memory', 'r+');
        $loop = $this->getMock('\React\EventLoop\LoopInterface');
        $client->setLoop($loop);
        $this->assertInstanceOf('\React\Stream\Stream', $method->invoke($client, $socket));
        fclose($socket);
    }

    /**
     * Tests getReadStream().
     */
    public function testGetReadStream()
    {
        $logger = $this->getMockLogger();
        $logger
            ->expects($this->at(0))
            ->method('debug')
            ->with('data-msg');
        $logger
            ->expects($this->at(1))
            ->method('debug')
            ->with('error-msg');

        $client = new Client;
        $client->setLogger($logger);

        $method = $this->getMethod('getReadStream');
        $read = $method->invoke($client);
        $this->assertInstanceOf('\Phergie\Irc\Client\React\ReadStream', $read);
        $read->emit('data', array('data-msg'));
        $read->emit('error', array('error-msg'));
    }

    /**
     * Tests getWriteStream().
     */
    public function testGetWriteStream()
    {
        $logger = $this->getMockLogger();
        $logger
            ->expects($this->at(0))
            ->method('debug')
            ->with('data-msg');
        $logger
            ->expects($this->at(1))
            ->method('debug')
            ->with('error-msg');

        $client = new Client;
        $client->setLogger($logger);

        $method = $this->getMethod('getWriteStream');
        $read = $method->invoke($client);
        $this->assertInstanceOf('\Phergie\Irc\Client\React\WriteStream', $read);
        $read->emit('data', array('data-msg'));
        $read->emit('error', array('error-msg'));
    }

    /**
     * Tests getSocket() with invalid socket metadata.
     */
    public function testGetSocketWithInvalidSocketMetadata()
    {
        $client = new Client;
        $method = $this->getMethod('getSocket');

        try {
            $method->invoke($client, 'tcp://0.0.0.0:0', array());
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertEquals(Exception::ERR_CONNECTION_ATTEMPT_FAILED, $e->getCode());
            $this->assertEquals(
                'Unable to connect to remote tcp://0.0.0.0:0: socket error 111 Connection refused',
                $e->getMessage()
            );
        }
    }

    /**
     * Tests getSocket() with valid socket metadata.
     */
    public function testGetSocketWithValidSocketMetadata()
    {
        $server = stream_socket_server('tcp://localhost:0');
        $name = stream_socket_get_name($server, false);
        $port = (int) substr(strrchr($name, ':'), 1);

        $client = new Client;
        $method = $this->getMethod('getSocket');

        $socket = $method->invoke($client, 'tcp://localhost:' . $port, array());
        $this->assertInternalType('resource', $socket);

        // Make sure the stream is non-blocking
        $this->assertFalse(fgets($socket));
    }

    /**
     * Returns a mock connection.
     *
     * @return \Phergie\Irc\ConnectionInterface
     */
    protected function getMockConnection()
    {
        return $this->getMock('\Phergie\Irc\ConnectionInterface', array(), array(), '', false);
    }

    /**
     * Returns a mock connection for testing addConnection().
     *
     * @return \Phergie\Irc\ConnectionInterface
     */
    protected function getMockConnectionForAddConnection()
    {
        $connection = $this->getMockConnection();
        foreach (array('username', 'hostname', 'servername', 'realname', 'nickname') as $property) {
            $connection
                ->expects($this->once())
                ->method('get' . ucfirst($property))
                ->will($this->returnValue($property));
        }
        return $connection;
    }

    /**
     * Returns a mock write stream.
     *
     * @return \Phergie\Irc\Client\React\WriteStream
     */
    protected function getMockWriteStream()
    {
        return $this->getMock('\Phergie\Irc\Client\React\WriteStream', array(), array(), '', false);
    }

    /**
     * Returns a mock write stream for testing addConnection().
     *
     * @return \Phergie\Irc\Client\React\WriteStream
     */
    protected function getMockWriteStreamForAddConnection()
    {
        $writeStream = $this->getMockWriteStream();
        $writeStream
            ->expects($this->once())
            ->method('ircUser')
            ->with('username', 'hostname', 'servername', 'realname');
        $writeStream
            ->expects($this->once())
            ->method('ircNick')
            ->with('nickname');
        return $writeStream;
    }

    /**
     * Returns a mock client for testing addConnection().
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @param \Phergie\Irc\Client\React\WriteStream $writeStream
     * @param \React\EventLoop\LoopInterface $loop
     * @param array $clientMethods
     * @return \Phergie\Irc\Client\React\Client
     */
    protected function getMockClientForAddConnection($connection, $writeStream, $loop, array $clientMethods = array())
    {
        $remote = 'tcp://irc.rizon.net:6667';
        $context = array('socket' => array());
        $socket = fopen('php://memory', 'r+');
        $readCallback = function() { };
        $writeCallback = function() { };
        $errorCallback = function() { };

        $readStream = $this->getMock('\Phergie\Irc\Client\React\ReadStream', array(), array(), '', false);
        $readStream
            ->expects($this->at(0))
            ->method('on')
            ->with('irc.received', $readCallback);
        $readStream
            ->expects($this->at(1))
            ->method('on')
            ->with('error', $errorCallback);

        $stream = $this->getMock('\React\Stream\Stream', array(), array(), '', false);
        $stream
            ->expects($this->once())
            ->method('pipe')
            ->with($readStream)
            ->will($this->returnValue($readStream));

        $writeStream
            ->expects($this->at(0))
            ->method('pipe')
            ->with($stream)
            ->will($this->returnValue($stream));
        $writeStream
            ->expects($this->at(1))
            ->method('on')
            ->with('data', $writeCallback);
        $writeStream
            ->expects($this->at(2))
            ->method('on')
            ->with('error', $errorCallback);

        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array_merge($clientMethods, array('getRemote', 'getContext', 'getSocket', 'getStream', 'getWriteStream', 'getReadStream', 'getReadCallback', 'getLoop', 'emit')));
        $client
            ->expects($this->at(0))
            ->method('emit')
            ->with('connect.before.each', array($connection));
        $client
            ->expects($this->at(8))
            ->method('emit')
            ->with('connect.after.each', array($connection));
        $client
            ->expects($this->any())
            ->method('getLoop')
            ->will($this->returnValue($loop));
        $client
            ->expects($this->once())
            ->method('getRemote')
            ->with($connection)
            ->will($this->returnValue($remote));
        $client
            ->expects($this->once())
            ->method('getContext')
            ->with($connection)
            ->will($this->returnValue($context));
        $client
            ->expects($this->once())
            ->method('getSocket')
            ->with($remote, $context)
            ->will($this->returnValue($socket));
        $client
            ->expects($this->once())
            ->method('getStream')
            ->with($socket)
            ->will($this->returnValue($stream));
        $client
            ->expects($this->once())
            ->method('getWriteStream')
            ->will($this->returnValue($writeStream));
        $client
            ->expects($this->once())
            ->method('getReadStream')
            ->will($this->returnValue($readStream));
        $client
            ->expects($this->once())
            ->method('getReadCallback')
            ->with($writeStream, $connection)
            ->will($this->returnValue($readCallback));
        $client
            ->expects($this->any())
            ->method('getWriteCallback')
            ->with($connection)
            ->will($this->returnValue($writeCallback));
        $client
            ->expects($this->any())
            ->method('getErrorCallback')
            ->with($connection)
            ->will($this->returnValue($errorCallback));

        return $client;
    }

    /**
     * Returns a mock loop.
     *
     * @return \React\EventLoop\LoopInterface
     */
    protected function getMockLoop()
    {
        return $this->getMock('\React\EventLoop\LoopInterface', array(), array(), '', false);
    }

    /**
     * Tests addConnection() when getSocket() throws an exception.
     */
    public function testAddConnnectionWithException()
    {
        $connection = $this->getMock('\Phergie\Irc\Connection', array(), array(), '', false);
        $writeStream = $this->getMock('\Phergie\Irc\Client\React\WriteStream', array(), array(), '', false);
        $logger = $this->getMockLogger();
        $remote = 'REMOTE';
        $context = array();
        $exception = new Exception(
            'Unable to connect to remote ' . $remote . ': socket error errno errstr',
            Exception::ERR_CONNECTION_ATTEMPT_FAILED
        );

        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array('getRemote', 'getContext', 'getSocket', 'getLogger', 'emit'));

        $client
            ->expects($this->once())
            ->method('getRemote')
            ->with($connection)
            ->will($this->returnValue($remote));
        $client
            ->expects($this->once())
            ->method('getContext')
            ->with($connection)
            ->will($this->returnValue($context));
        $client
            ->expects($this->once())
            ->method('getSocket')
            ->with($remote, $context)
            ->will($this->throwException($exception));
        $client
            ->expects($this->once())
            ->method('getLogger')
            ->will($this->returnValue($logger));
        $client
            ->expects($this->at(0))
            ->method('emit')
            ->with('connect.before.each', array($connection));
        $client
            ->expects($this->at(5))
            ->method('emit')
            ->with('connect.error', array($exception->getMessage(), $connection, $logger));
        $client
            ->expects($this->at(6))
            ->method('emit')
            ->with('connect.after.each', array($connection));

        $client->addConnection($connection);
    }

    /**
     * Tests addConnection() without a password.
     */
    public function testAddConnectionWithoutPassword()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $writeStream = $this->getMockWriteStreamForAddConnection();
        $loop = $this->getMockLoop();
        $client = $this->getMockClientForAddConnection($connection, $writeStream, $loop);
        $client->addConnection($connection);
    }

    /**
     * Tests addConnection() with a password.
     */
    public function testAddConnectionWithPassword()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $connection
            ->expects($this->once())
            ->method('getPassword')
            ->will($this->returnValue('password'));

        $writeStream = $this->getMockWriteStreamForAddConnection();
        $writeStream
            ->expects($this->once())
            ->method('ircPass')
            ->with('password');

        $loop = $this->getMockLoop();
        $client = $this->getMockClientForAddConnection($connection, $writeStream, $loop);
        $client->addConnection($connection);
    }

    /**
     * Tests run() with multiple connections.
     */
    public function testRunWithMultipleConnections()
    {
        $loop = $this->getMockLoop('\React\EventLoop\LoopInterface');
        $loop
            ->expects($this->once())
            ->method('run');

        $connection1 = $this->getMockConnection();
        $connection2 = $this->getMockConnection();
        $connections = array($connection1, $connection2);

        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array('addConnection', 'getLoop', 'emit'));
        $client
            ->expects($this->any())
            ->method('getLoop')
            ->will($this->returnValue($loop));
        $client
            ->expects($this->at(0))
            ->method('emit')
            ->with('connect.before.all', array($connections));
        $client
            ->expects($this->at(1))
            ->method('addConnection')
            ->with($connection1);
        $client
            ->expects($this->at(2))
            ->method('addConnection')
            ->with($connection2);
        $client
            ->expects($this->at(3))
            ->method('emit')
            ->with('connect.after.all', array($connections));

        $client->run($connections);
    }

    /**
     * Tests run() with a single connection.
     */
    public function testRunWithSingleConnection()
    {
        $loop = $this->getMockLoop('\React\EventLoop\LoopInterface');
        $loop
            ->expects($this->once())
            ->method('run');

        $connection = $this->getMockConnection();
        $connections = array($connection);

        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array('addConnection', 'getLoop', 'emit'));
        $client
            ->expects($this->any())
            ->method('getLoop')
            ->will($this->returnValue($loop));
        $client
            ->expects($this->at(0))
            ->method('emit')
            ->with('connect.before.all', array($connections));
        $client
            ->expects($this->at(1))
            ->method('addConnection')
            ->with($connection);
        $client
            ->expects($this->at(2))
            ->method('emit')
            ->with('connect.after.all', array($connections));

        $client->run($connection);
    }

    /**
     * Tests getReadCallback().
     */
    public function testGetReadCallback()
    {
        $logger = $this->getMockLogger();
        $write = $this->getMockWriteStream();
        $connection = $this->getMockConnection();
        $method = $this->getMethod('getReadCallback');
        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array('emit'));
        $client->setLogger($logger);
        $client
            ->expects($this->once())
            ->method('emit')
            ->with('irc.received', array('msg', $write, $connection, $logger));

        $callback = $method->invoke($client, $write, $connection);
        $callback('msg');
    }

    /**
     * Tests getWriteCallback().
     */
    public function testGetWriteCallback()
    {
        $logger = $this->getMockLogger();
        $write = $this->getMockWriteStream();
        $connection = $this->getMockConnection();
        $method = $this->getMethod('getWriteCallback');
        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array('emit'));
        $client->setLogger($logger);
        $client
            ->expects($this->once())
            ->method('emit')
            ->with('irc.sent', array('msg', $connection, $logger));

        $callback = $method->invoke($client, $connection);
        $callback('msg');
    }

    /**
     * Tests getErrorCallback().
     */
    public function testGetErrorCallback()
    {
        $logger = $this->getMockLogger();
        $write = $this->getMockWriteStream();
        $connection = $this->getMockConnection();
        $method = $this->getMethod('getErrorCallback');
        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array('emit'));
        $client->setLogger($logger);
        $client
            ->expects($this->once())
            ->method('emit')
            ->with('connect.error', array('msg', $connection, $logger));

        $callback = $method->invoke($client, $connection);
        $callback('msg');
    }

    /**
     * Returns a client stubbed for testing timer-related methods.
     *
     * @param \React\EventLoop\LoopInterface $loop
     * @return \Phergie\Irc\Client\React\Client
     */
    protected function getClientForTimerTest(\React\EventLoop\LoopInterface $loop)
    {
        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array('getLoop'));
        $client
            ->expects($this->any())
            ->method('getLoop')
            ->will($this->returnValue($loop));
        return $client;
    }

    /**
     * Data provider for testAddTimer().
     *
     * @return array
     */
    public function dataProviderTestAddTimer()
    {
        return array(
            array('addTimer'),
            array('addPeriodicTimer'),
        );
    }

    /**
     * Tests adding timers.
     *
     * @param string $method Method being tested
     * @dataProvider dataProviderTestAddTimer
     */
    public function testAddTimers($method)
    {
        $interval = 5;
        $callback = function() { };
        $timer = $this->getMock('\React\Event\Timer\TimerInterface');

        $loop = $this->getMockLoop();
        $loop->expects($this->once())
            ->method($method)
            ->with($interval, $callback)
            ->will($this->returnValue($timer));

        $client = $this->getClientForTimerTest($loop);
        $client->$method($interval, $callback);
    }

    /**
     * Tests cancelTimer().
     */
    public function testCancelTimer()
    {
        $timer = $this->getMock('\React\EventLoop\Timer\TimerInterface');

        $loop = $this->getMockLoop();
        $loop->expects($this->once())
            ->method('cancelTimer')
            ->with($timer);

        $client = $this->getClientForTimerTest($loop);
        $client->cancelTimer($timer);
    }

    /**
     * Tests isTimerActive().
     */
    public function testIsTimerActive()
    {
        $timer = $this->getMock('\React\EventLoop\Timer\TimerInterface');

        $loop = $this->getMockLoop();
        $loop->expects($this->once())
            ->method('isTimerActive')
            ->with($timer);

        $client = $this->getClientForTimerTest($loop);
        $client->isTimerActive($timer);
    }
}
