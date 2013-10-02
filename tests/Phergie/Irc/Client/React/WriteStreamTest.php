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
 * Tests for \Phergie\Irc\Client\React\WriteStream.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class WriteStreamTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests that WriteStream implements GeneratorInterface.
     */
    public function testImplementsGeneratorInterface()
    {
        $this->assertInstanceOf('\Phergie\Irc\GeneratorInterface', new WriteStream());
    }

    /**
     * Tests getGenerator().
     */
    public function testGetGenerator()
    {
        $write = new WriteStream();
        $this->assertInstanceOf('\Phergie\Irc\Generator', $write->getGenerator());
    }

    /**
     * Tests setGenerator().
     */
    public function testSetGenerator()
    {
        $write = new WriteStream();
        $generator = $this->getMock('\Phergie\Irc\GeneratorInterface');
        $write->setGenerator($generator);
        $this->assertSame($generator, $write->getGenerator());
    }

    /**
     * Tests setPrefix().
     */
    public function testSetPrefix()
    {
        $prefix = 'prefix-string';
        $generator = $this->getMock('\Phergie\Irc\GeneratorInterface');
        $generator
            ->expects($this->once())
            ->method('setPrefix')
            ->with($prefix);
        $write = new WriteStream;
        $write->setGenerator($generator);
        $write->setPrefix($prefix);
    }

    /**
     * Tests methods that proxy to the generator and emit data events.
     *
     * @param string $method
     * @param array $arguments
     * @dataProvider getProxyingMethods
     */
    public function testProxyingToGenerator($method, array $arguments = array())
    {
        $msg = $method . '_msg';

        $generator = $this->getMock('\Phergie\Irc\GeneratorInterface');
        $mocker = $generator
            ->expects($this->once())
            ->method($method);
        if ($arguments) {
            call_user_func_array(array($mocker, 'with'), $arguments);
        }
        $mocker->will($this->returnValue($msg));

        $write = $this->getMock('\Phergie\Irc\Client\React\WriteStream', array('emit'));
        $write
            ->expects($this->once())
            ->method('emit')
            ->with('data', array($msg));
        $write->setGenerator($generator);

        $this->assertSame($msg, call_user_func_array(array($write, $method), $arguments));
    }

    /**
     * Data provider for testProxyingToGenerator().
     */
    public function getProxyingMethods()
    {
        return array(
            array('ircPass', array('password')),
            array('ircNick', array('nickname', 'hopcount')),
            array('ircUser', array('username', 'hostname', 'servername', 'realname')),
            array('ircServer', array('servername', 'hopcount', 'info')),
            array('ircOper', array('user', 'password')),
            array('ircQuit', array('message')),
            array('ircSquit', array('server', 'comment')),
            array('ircJoin', array('channels', 'keys')),
            array('ircPart', array('channels')),
            array('ircMode', array('target', 'mode', 'param')),
            array('ircTopic', array('channel', 'topic')),
            array('ircNames', array('channels')),
            array('ircList', array('channels', 'server')),
            array('ircInvite', array('nickname', 'channel')),
            array('ircKick', array('channel', 'user', 'comment')),
            array('ircVersion', array('server')),
            array('ircStats', array('query', 'server')),
            array('ircLinks', array('servermask', 'remoteserver')),
            array('ircTime', array('server')),
            array('ircConnect', array('targetserver', 'port', 'remoteserver')),
            array('ircTrace', array('server')),
            array('ircAdmin', array('server')),
            array('ircInfo', array('server')),
            array('ircPrivmsg', array('receivers', 'text')),
            array('ircNotice', array('nickname', 'text')),
            array('ircWho', array('name', 'o')),
            array('ircWhois', array('server', 'nickmasks')),
            array('ircWhowas', array('nickname', 'count', 'server')),
            array('ircKill', array('nickname', 'comment')),
            array('ircPing', array('server1', 'server2')),
            array('ircPong', array('daemon', 'daemon2')),
            array('ircError', array('message')),
            array('ircAway', array('message')),
            array('ircRehash'),
            array('ircRestart'),
            array('ircSummon', array('user', 'server')),
            array('ircUsers', array('server')),
            array('ircWallops', array('text')),
            array('ircUserhost', array('nickname1', 'nickname2', 'nickname3', 'nickname4', 'nickname5')),
            array('ircIson', array('nicknames')),
            array('ctcpAction', array('receivers', 'action')),
            array('ctcpActionResponse', array('nickname', 'action')),
            array('ctcpFinger', array('receivers')),
            array('ctcpFingerResponse', array('nickname', 'text')),
            array('ctcpVersion', array('receivers')),
            array('ctcpVersionResponse', array('nickname', 'name', 'version', 'environment')),
            array('ctcpSource', array('receivers')),
            array('ctcpSourceResponse', array('nickname', 'host', 'directories', 'files')),
            array('ctcpUserinfo', array('receivers')),
            array('ctcpUserinfoResponse', array('nickname', 'text')),
            array('ctcpClientinfo', array('receivers')),
            array('ctcpClientinfoResponse', array('nickname', 'client')),
            array('ctcpErrmsg', array('receivers', 'query')),
            array('ctcpErrmsgResponse', array('nickname', 'query', 'message')),
            array('ctcpPing', array('receivers', 'timestamp')),
            array('ctcpPingResponse', array('nickname', 'timestamp')),
            array('ctcpTime', array('receivers')),
            array('ctcpTimeResponse', array('nickname', 'time')),
        );
    }
}
