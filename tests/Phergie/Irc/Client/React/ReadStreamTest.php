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
 * Tests for \Phergie\Irc\Client\React\ReadStream.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class ReadStreamTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests setParser().
     */
    public function testSetParser()
    {
        $read = new ReadStream;
        $parser = $this->getMock('\Phergie\Irc\ParserInterface');
        $read->setParser($parser);
        $this->assertSame($parser, $read->getParser());
    }

    /**
     * Tests getParser().
     */
    public function testGetParser()
    {
        $read = new ReadStream;
        $this->assertInstanceOf('\Phergie\Irc\ParserInterface', $read->getParser());
    }

    /**
     * Tests write() with an incomplete event.
     */
    public function testWriteWithIncompleteEvent()
    {
        $data1 = 'PRIVMSG #channel';
        $data2 = ' :message';
        $parsed = array(
            'type' => 'PRIVMSG',
            'params' => array(
                'receivers' => '#channel',
                'text' => 'message',
                'all' => '#channel :message',
            ),
            'targets' => array('#channel'),
            'message' => "PRIVMSG #channel :message\r\n"
        );

        // Due to limitations in call_user_func*, specifically that it does not
        // pass parameters by reference and that call-time pass-by-reference is
        // deprecated in PHP 5.4, there's no supported way to simulate
        // modification of the $all parameter of consumeAll(), which is
        // received by reference, within the stub callback for consumeAll().
        // The most that can be tested here is that write() will retain the
        // remainder of the data stream (i.e.  all of it if consumeAll() does
        // not modify it, which it won't if the stream does not contain a
        // complete message) after its first invocation of consumeAll(), then
        // prepend that remainder to the $data parameter value passed to it
        // before it invokes consumeAll() again.
        $parser = $this->getMock('\Phergie\Irc\ParserInterface');
        $parser
            ->expects($this->at(0))
            ->method('consumeAll')
            ->with($data1)
            ->will($this->returnValue(array()));
        $parser
            ->expects($this->at(1))
            ->method('consumeAll')
            ->with($data1 . $data2 . "\r\n")
            ->will($this->returnCallback(function() use ($parsed) {
                return array($parsed);
            }));

        $read = $this->getMock('\Phergie\Irc\Client\React\ReadStream', array('emit'));
        $read
            ->expects($this->at(0))
            ->method('emit')
            ->with('data', array($parsed['message']));
        $read
            ->expects($this->at(1))
            ->method('emit')
            ->with('irc', array($parsed));
        $read->setParser($parser);
        $read->write($data1);
        $read->write($data2 . "\r\n");
    }

    /**
     * Tests write() with a complete event.
     */
    public function testWriteWithCompleteEvent()
    {
        $parsed = array(
            'prefix' => ':Angel',
            'nick' => 'Angel',
            'command' => 'PRIVMSG',
            'params' => array(
                'receivers' => 'Wiz',
                'text' => 'Hello are you receiving this message ?',
                'all' => 'Wiz :Hello are you receiving this message ?',
            ),
            'targets' => array('Wiz'),
            'message' => ":Angel PRIVMSG Wiz :Hello are you receiving this message ?\r\n",
        );

        $parser = $this->getMock('\Phergie\Irc\ParserInterface');
        $parser
            ->expects($this->once())
            ->method('consumeAll')
            ->with($parsed['message'])
            ->will($this->returnValue(array($parsed)));

        $read = $this->getMock('\Phergie\Irc\Client\React\ReadStream', array('emit'));
        $read
            ->expects($this->at(0))
            ->method('emit')
            ->with('data', array($parsed['message']));
        $read
            ->expects($this->at(1))
            ->method('emit')
            ->with('irc', array($parsed));
        $read->setParser($parser);
        $read->write($parsed['message']);
    }
}
