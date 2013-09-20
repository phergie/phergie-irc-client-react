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

use Phergie\Irc\GeneratorInterface;
use React\Stream\ReadableStream;

/**
 * Stream that sends IRC messages to a server.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class WriteStream extends ReadableStream implements GeneratorInterface
{
    /**
     * Generator composed to write IRC messages to the stream
     *
     * @var \Phergie\Irc\GeneratorInterface
     */
    protected $generator;

    /**
     * Returns the IRC message generator in use.
     *
     * @return \Phergie\Irc\GeneratorInterface
     */
    public function getGenerator()
    {
        if (!$this->generator) {
            $this->generator = new \Phergie\Irc\Generator;
        }
        return $this->generator;
    }

    /**
     * Sets the IRC message generator to use.
     *
     * @param \Phergie\Irc\GeneratorInterface $generator
     */
    public function setGenerator(GeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->getGenerator()->setPrefix($prefix);
    }

    /**
     * @parm string $password
     * @return string
     */
    public function ircPass($password)
    {
        $msg = $this->getGenerator()->ircPass($password);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param int $hopcount
     * @return string
     */
    public function ircNick($nickname, $hopcount = null)
    {
        $msg = $this->getGenerator()->ircNick($nickname, $hopcount);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $username
     * @param string $hostname
     * @param string $servername
     * @param string $realname
     * @return string
     */
    public function ircUser($username, $hostname, $servername, $realname)
    {
        $msg = $this->getGenerator()->ircUser($username, $hostname, $servername, $realname);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $servername
     * @param int $hopcount
     * @param string $info
     * @return string
     */
    public function ircServer($servername, $hopcount, $info)
    {
        $msg = $this->getGenerator()->ircServer($servername, $hopcount, $info);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $user
     * @param string $password
     * @return string
     */
    public function ircOper($user, $password)
    {
        $msg = $this->getGenerator()->ircOper($user, $password);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $message
     * @return string
     */
    public function ircQuit($message = null)
    {
        $msg = $this->getGenerator()->ircQuit($message);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $server
     * @param string $comment
     * @return string
     */
    public function ircSquit($server, $comment)
    {
        $msg = $this->getGenerator()->ircSquit($server, $comment);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $channels
     * @param string $keys
     * @return string
     */
    public function ircJoin($channels, $keys = null)
    {
        $msg = $this->getGenerator()->ircJoin($channels, $keys);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $channels
     * @return string
     */
    public function ircPart($channels)
    {
        $msg = $this->getGenerator()->ircPart($channels);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $target
     * @param string $mode
     * @param string $param
     * @return string
     */
    public function ircMode($target, $mode, $param = null)
    {
        $msg = $this->getGenerator()->ircMode($target, $mode, $param);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $channel
     * @param string $topic
     * @return string
     */
    public function ircTopic($channel, $topic = null)
    {
        $msg = $this->getGenerator()->ircTopic($channel, $topic);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $channels
     * @return string
     */
    public function ircNames($channels)
    {
        $msg = $this->getGenerator()->ircNames($channels);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $channels
     * @param string $server
     * @return string
     */
    public function ircList($channels = null, $server = null)
    {
        $msg = $this->getGenerator()->ircList($channels, $server);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $channel
     * @return string
     */
    public function ircInvite($nickname, $channel)
    {
        $msg = $this->getGenerator()->ircInvite($nickname, $channel);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $channel
     * @param string $user
     * @param string $comment
     * @return string
     */
    public function ircKick($channel, $user, $comment = null)
    {
        $msg = $this->getGenerator()->ircKick($channel, $user, $comment);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $server
     * @return string
     */
    public function ircVersion($server = null)
    {
        $msg = $this->getGenerator()->ircVersion($server);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $query
     * @param string $server
     * @return string
     */
    public function ircStats($query, $server = null)
    {
        $msg = $this->getGenerator()->ircStats($query, $server);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $servermask
     * @param string $remoteserver
     * @return string
     */
    public function ircLinks($servermask = null, $remoteserver = null)
    {
        $msg = $this->getGenerator()->ircLinks($servermask, $remoteserver);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $server
     * @return string
     */
    public function ircTime($server = null)
    {
        $msg = $this->getGenerator()->ircTime($server);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $targetserver
     * @param int $port
     * @param string $remoteserver
     * @return string
     */
    public function ircConnect($targetserver, $port = null, $remoteserver = null)
    {
        $msg = $this->getGenerator()->ircConnect($targetserver, $port, $remoteserver);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $server
     * @return string
     */
    public function ircTrace($server = null)
    {
        $msg = $this->getGenerator()->ircTrace($server);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $server
     * @return string
     */
    public function ircAdmin($server = null)
    {
        $msg = $this->getGenerator()->ircAdmin($server);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $server
     * @return string
     */
    public function ircInfo($server = null)
    {
        $msg = $this->getGenerator()->ircInfo($server);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $receivers
     * @param string $text
     * @return string
     */
    public function ircPrivmsg($receivers, $text)
    {
        $msg = $this->getGenerator()->ircPrivmsg($receivers, $text);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $text
     * @return string
     */
    public function ircNotice($nickname, $text)
    {
        $msg = $this->getGenerator()->ircNotice($nickname, $text);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $name
     * @param string $o
     * @return string
     */
    public function ircWho($name, $o = null)
    {
        $msg = $this->getGenerator()->ircWho($name, $o);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $server
     * @param string $nickmasks
     * @return string
     */
    public function ircWhois($server, $nickmasks)
    {
        $msg = $this->getGenerator()->ircWhois($server, $nickmasks);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param int $count
     * @param string $server
     * @return string
     */
    public function ircWhowas($nickname, $count = null, $server = null)
    {
        $msg = $this->getGenerator()->ircWhowas($nickname, $count, $server);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $comment
     * @return string
     */
    public function ircKill($nickname, $comment)
    {
        $msg = $this->getGenerator()->ircKill($nickname, $comment);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $server1
     * @param string $server2
     * @return string
     */
    public function ircPing($server1, $server2 = null)
    {
        $msg = $this->getGenerator()->ircPing($server1, $server2);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $daemon
     * @param string $daemon2
     * @return string
     */
    public function ircPong($daemon, $daemon2 = null)
    {
        $msg = $this->getGenerator()->ircPong($daemon, $daemon2);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $message
     * @return string
     */
    public function ircError($message)
    {
        $msg = $this->getGenerator()->ircError($message);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $message
     * @return string
     */
    public function ircAway($message = null)
    {
        $msg = $this->getGenerator()->ircAway($message);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @return string
     */
    public function ircRehash()
    {
        $msg = $this->getGenerator()->ircRehash();
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @return string
     */
    public function ircRestart()
    {
        $msg = $this->getGenerator()->ircRestart();
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $user
     * @param string $server
     * @return string
     */
    public function ircSummon($user, $server = null)
    {
        $msg = $this->getGenerator()->ircSummon($user, $server);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $server
     * @return string
     */
    public function ircUsers($server = null)
    {
        $msg = $this->getGenerator()->ircUsers($server);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $text
     * @return string
     */
    public function ircWallops($text)
    {
        $msg = $this->getGenerator()->ircWallops($text);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname1
     * @param string $nickname2
     * @param string $nickname3
     * @param string $nickname4
     * @param string $nickname5
     * @return string
     */
    public function ircUserhost($nickname1, $nickname2 = null, $nickname3 = null, $nickname4 = null, $nickname5 = null)
    {
        $msg = $this->getGenerator()->ircUserhost($nickname1, $nickname2, $nickname3, $nickname4, $nickname5);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nicknames
     * @return string
     */
    public function ircIson($nicknames)
    {
        $msg = $this->getGenerator()->ircIson($nicknames);
        $this->emit('data', array($msg));
        return $msg;
    }
    
    /**
     * @param string $receivers 
     * @param string $action
     * @return string
     */
    public function ctcpAction($receivers, $action)
    {
        $msg = $this->getGenerator()->ctcpAction($receivers, $action);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $action
     * @return string
     */
    public function ctcpActionResponse($nickname, $action)
    {
        $msg = $this->getGenerator()->ctcpActionResponse($nickname, $action);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $receivers
     * @return string
     */
    public function ctcpFinger($receivers)
    {
        $msg = $this->getGenerator()->ctcpFinger($receivers);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $text
     * @return string
     */
    public function ctcpFingerResponse($nickname, $text)
    {
        $msg = $this->getGenerator()->ctcpFingerResponse($nickname, $text);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $receivers
     * @return string
     */
    public function ctcpVersion($receivers)
    {
        $msg = $this->getGenerator()->ctcpVersion($receivers);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $name
     * @param string $version
     * @param string $environment
     * @return string
     */
    public function ctcpVersionResponse($nickname, $name, $version, $environment)
    {
        $msg = $this->getGenerator()->ctcpVersionResponse($nickname, $name, $version, $environment);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $receivers
     * @return string
     */
    public function ctcpSource($receivers)
    {
        $msg = $this->getGenerator()->ctcpSource($receivers);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $host
     * @param string $directories
     * @param string $files
     * @return string
     */
    public function ctcpSourceResponse($nickname, $host, $directories, $files)
    {
        $msg = $this->getGenerator()->ctcpSourceResponse($nickname, $host, $directories, $files);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $receivers
     * @return string
     */
    public function ctcpUserinfo($receivers)
    {
        $msg = $this->getGenerator()->ctcpUserinfo($receivers);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $text
     * @return string
     */
    public function ctcpUserinfoResponse($nickname, $text)
    {
        $msg = $this->getGenerator()->ctcpUserinfoResponse($nickname, $text);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $receivers
     * @return string
     */
    public function ctcpClientinfo($receivers)
    {
        $msg = $this->getGenerator()->ctcpClientInfo($receivers);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $client
     * @return string
     */
    public function ctcpClientinfoResponse($nickname, $client)
    {
        $msg = $this->getGenerator()->ctcpClientinfoResponse($nickname, $client);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $receivers
     * @param string $query
     * @return string
     */
    public function ctcpErrmsg($receivers, $query)
    {
        $msg = $this->getGenerator()->ctcpErrmsg($receivers, $query);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $query
     * @param string $message
     * @return string
     */
    public function ctcpErrmsgResponse($nickname, $query, $message)
    {
        $msg = $this->getGenerator()->ctcpErrmsgResponse($nickname, $query, $message);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $receivers
     * @param int $timestamp
     * @return string
     */
    public function ctcpPing($receivers, $timestamp)
    {
        $msg = $this->getGenerator()->ctcpPing($receivers, $timestamp);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param int $timestamp
     * @return string
     */
    public function ctcpPingResponse($nickname, $timestamp)
    {
        $msg = $this->getGenerator()->ctcpPingResponse($nickname, $timestamp);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $receivers
     * @return string
     */
    public function ctcpTime($receivers)
    {
        $msg = $this->getGenerator()->ctcpTime($receivers);
        $this->emit('data', array($msg));
        return $msg;
    }

    /**
     * @param string $nickname
     * @param string $time
     * @return string
     */
    public function ctcpTimeResponse($nickname, $time)
    {
        $msg = $this->getGenerator()->ctcpTimeResponse($nickname, $time);
        $this->emit('data', array($msg));
        return $msg;
    }
}
