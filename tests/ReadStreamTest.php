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

use Phake;
use Phergie\Irc\Client\React\ReadStream;

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
        $parser = $this->getMockParser();
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

        $parser = $this->getMockParser();
        Phake::when($parser)
            ->consumeAll($data1)
            ->thenReturn(array());
        $all = $data1 . $data2 . "\r\n";
        Phake::when($parser)
            ->consumeAll(Phake::setReference('')->when($all))
            ->thenReturnCallback(function() use ($parsed) {
                return array($parsed);
            });

        $read = $this->getMockReadStream();
        $read->setParser($parser);
        $read->write($data1);
        $read->write($data2 . "\r\n");

        Phake::inOrder(
            Phake::verify($read)->emit('data', array($parsed['message'])),
            Phake::verify($read)->emit('irc.received', array($parsed))
        );
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

        $parser = $this->getMockParser();
        Phake::when($parser)
            ->consumeAll($parsed['message'])
            ->thenReturn(array($parsed));

        $read = $this->getMockReadStream();
        $read->setParser($parser);
        $read->write($parsed['message']);

        Phake::inOrder(
            Phake::verify($read)->emit('data', array($parsed['message'])),
            Phake::verify($read)->emit('irc.received', array($parsed))
        );
    }

    /**
     * Tests write() with an invalid event.
     */
    public function testWriteWithInvalidEvent()
    {
        $parsed = array(
            'invalid' => ":invalid:message\r\n",
        );

        $parser = $this->getMockParser();
        Phake::when($parser)
            ->consumeAll($parsed['invalid'])
            ->thenReturn(array($parsed));

        $read = $this->getMockReadStream();
        $read->setParser($parser);
        $read->write($parsed['invalid']);

        Phake::verify($read)->emit('invalid', array($parsed['invalid']));
        Phake::verify($read, Phake::never())->emit(
            $this->logicalOr($this->equalTo('data'), $this->equalTo('irc.received')),
            $this->anything()
        );
    }

    /**
     * Returns a mock parser.
     *
     * @return \Phergie\Irc\ParserInterface
     */
    protected function getMockParser()
    {
        return Phake::mock('\Phergie\Irc\ParserInterface');
    }

    /**
     * Returns a partial mock read stream.
     *
     * @return \Phergie\Irc\Client\React\ReadStream
     */
    protected function getMockReadStream()
    {
        return Phake::partialMock('\Phergie\Irc\Client\React\ReadStream');
    }
}
