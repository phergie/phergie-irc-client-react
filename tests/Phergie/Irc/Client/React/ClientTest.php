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
     * @return \Monolog\Logger
     */
    protected function getMockLogger()
    {
        return $this->getMock('\Monolog\Logger', array(), array(), '', false);
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
        $this->assertInstanceOf('\Monolog\Logger', $logger);
        $this->assertSame($logger, $client->getLogger());
    }

    /**
     * Tests addListener() with an invalid callback.
     */
    public function testAddListenerWithInvalidCallback()
    {
        $client = new Client;
        try {
            $client->addListener(null);
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$callback must be of type callable', $e->getMessage());
        }
    }

    /**
     * Tests addListener() with a valid callback.
     */
    public function testAddListenerWithValidCallback()
    {
        $callback = function() { };
        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array('on'));
        $client
            ->expects($this->once())
            ->method('on')
            ->with('irc', $callback);
        $client->addListener($callback);
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
        $this->assertInstanceOf('\React\Stream\Stream', $method->invoke($client, $socket, $loop));
        fclose($socket);
    }

    /**
     * Tests getReadStream().
     */
    public function testGetReadStream()
    {
        $logger = $this->getMockLogger();
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('msg');

        $client = new Client;
        $client->setLogger($logger);

        $method = $this->getMethod('getReadStream');
        $read = $method->invoke($client);
        $this->assertInstanceOf('\Phergie\Irc\Client\React\ReadStream', $read);
        $read->emit('data', array('msg'));
    }

    /**
     * Tests getWriteStream().
     */
    public function testGetWriteStream()
    {
        $logger = $this->getMockLogger();
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('msg');

        $client = new Client;
        $client->setLogger($logger);

        $method = $this->getMethod('getWriteStream');
        $read = $method->invoke($client);
        $this->assertInstanceOf('\Phergie\Irc\Client\React\WriteStream', $read);
        $read->emit('data', array('msg'));
    }

    /**
     * Tests getSocket() with invalid socket metadata.
     */
    public function testGetSocketWithInvalidSocketMetadata()
    {
        $client = new Client;
        $method = $this->getMethod('getSocket');

        try {
            $method->invoke($client, '', array());
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertEquals(Exception::ERR_CONNECTION_ATTEMPT_FAILED, $e->getCode());
            $this->assertEquals('Unable to connect: socket error 0 Failed to parse address ""', $e->getMessage());
        }
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
     * Returns a mock connection for testing connect().
     *
     * @return \Phergie\Irc\ConnectionInterface
     */
    protected function getMockConnectionForConnect()
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
     * Returns a mock write stream for testing connect().
     *
     * @return \Phergie\Irc\Client\React\WriteStream
     */
    protected function getMockWriteStreamForConnect()
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
     * Returns a mock client for testing connect().
     *
     * @param \Phergie\Irc\ConnectionInterface $connection
     * @param \Phergie\Irc\Client\React\WriteStream $writeStream
     * @param \React\EventLoop\LoopInterface $loop
     * @param array $clientMethods
     * @return \Phergie\Irc\Client\React\Client
     */
    protected function getMockClientForConnect($connection, $writeStream, $loop, array $clientMethods = array())
    {
        $remote = 'tcp://irc.rizon.net:6667';
        $context = array('socket' => array());
        $socket = fopen('php://memory', 'r+');
        $callback = function() { };

        $readStream = $this->getMock('\Phergie\Irc\Client\React\ReadStream', array(), array(), '', false);
        $readStream
            ->expects($this->once())
            ->method('on')
            ->with('irc', $callback);

        $stream = $this->getMock('\React\Stream\Stream', array(), array(), '', false);
        $stream
            ->expects($this->once())
            ->method('pipe')
            ->with($readStream)
            ->will($this->returnValue($readStream));

        $writeStream
            ->expects($this->once())
            ->method('pipe')
            ->with($stream)
            ->will($this->returnValue($stream));

        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array_merge($clientMethods, array('getRemote', 'getContext', 'getSocket', 'getStream', 'getWriteStream', 'getReadStream', 'getReadCallback')));
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
            ->with($socket, $loop)
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
            ->will($this->returnValue($callback));

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
     * Tests connect() without a password.
     */
    public function testConnectWithoutPassword()
    {
        $connection = $this->getMockConnectionForConnect();
        $writeStream = $this->getMockWriteStreamForConnect();
        $loop = $this->getMockLoop();
        $client = $this->getMockClientForConnect($connection, $writeStream, $loop);
        $method = $this->getMethod('connect');
        $method->invoke($client, $connection, $loop);
    }

    /**
     * Tests connect() with a password.
     */
    public function testConnectWithPassword()
    {
        $connection = $this->getMockConnectionForConnect();
        $connection
            ->expects($this->once())
            ->method('getPassword')
            ->will($this->returnValue('password'));

        $writeStream = $this->getMockWriteStreamForConnect();
        $writeStream
            ->expects($this->once())
            ->method('ircPass')
            ->with('password');

        $loop = $this->getMockLoop();
        $client = $this->getMockClientForConnect($connection, $writeStream, $loop);
        $method = $this->getMethod('connect');
        $method->invoke($client, $connection, $loop);
    }

    /**
     * Tests run().
     */
    public function testRun()
    {
        $loop = $this->getMockLoop('\React\EventLoop\LoopInterface');
        $loop
            ->expects($this->once())
            ->method('run');

        $connection1 = $this->getMockConnection();
        $connection2 = $this->getMockConnection();

        $client = $this->getMock('\Phergie\Irc\Client\React\Client', array('connect', 'getLoop', 'emit'));
        $client
            ->expects($this->at(0))
            ->method('getLoop')
            ->will($this->returnValue($loop));
        $client
            ->expects($this->at(1))
            ->method('emit')
            ->with('connect.before.all', array(array($connection1, $connection2)));
        $client
            ->expects($this->at(2))
            ->method('emit')
            ->with('connect.before.each', array($connection1));
        $client
            ->expects($this->at(3))
            ->method('connect')
            ->with($connection1, $loop);
        $client
            ->expects($this->at(4))
            ->method('emit')
            ->with('connect.after.each', array($connection1));
        $client
            ->expects($this->at(5))
            ->method('emit')
            ->with('connect.before.each', array($connection2));
        $client
            ->expects($this->at(6))
            ->method('connect')
            ->with($connection2, $loop);
        $client
            ->expects($this->at(7))
            ->method('emit')
            ->with('connect.after.each', array($connection2));
        $client
            ->expects($this->at(8))
            ->method('emit')
            ->with('connect.after.all', array(array($connection1, $connection2)));

        $client->addConnection($connection1);
        $client->addConnection($connection2);
        $client->run();
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
            ->with('irc', array('msg', $write, $connection, $logger));

        $callback = $method->invoke($client, $write, $connection);
        $callback('msg');
    }
}
