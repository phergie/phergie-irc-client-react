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

use \React\Stream\ReadableStreamInterface;
use \React\Stream\WritableStreamInterface;

/**
 * Connection implementation that stores stream objects for reading and
 * writing IRC messages over socket connections.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class Connection extends \Phergie\Irc\Connection
{
    /**
     * Stream used for reading messages
     *
     * @var \React\Stream\ReadableStreamInterface
     */
    protected $readStream;

    /**
     * Stream used for writing messages
     *
     * @var \React\Stream\WritableStreamInterface
     */
    protected $writeStream;

    /**
     * Sets the stream used for reading messages.
     *
     * @param \React\Stream\ReadableStreamInterface $stream
     */
    public function setReadStream(ReadableStreamInterface $stream)
    {
        $this->readStream = $stream;
    }

    /**
     * Returns the stream used for reading messages.
     *
     * @return \React\Stream\ReadableStreamInterface
     */
    public function getReadStream()
    {
        return $this->readStream;
    }

    /**
     * Sets the stream used for writing messages.
     *
     * @param \React\Stream\WritableStreamInterface $stream
     */
    public function setWriteStream(WritableStreamInterface $stream)
    {
        $this->writeStream = $stream;
    }

    /**
     * Returns the stream used for writing messages.
     *
     * @return \React\Stream\WritableStreamInterface 
     */
    public function getWriteStream()
    {
        return $this->writeStream;
    }
}
