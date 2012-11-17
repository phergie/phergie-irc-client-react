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

use Phergie\Irc\ParserInterface;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

/**
 * Stream that reads IRC messages and emits them as 'irc' events.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class ReadStream extends Stream
{
    /**
     * Capacity of the message buffer, 512 bytes per RFC 1459 Section 2.3
     *
     * @var int
     * @link http://irchelp.org/irchelp/rfc/chapter2.html#c2_3
     */
    public $bufferSize = 512;

    /**
     * IRC message parser
     *
     * @var \Phergie\Irc\ParserInterface
     */
    protected $parser;

    /**
     * Partial message from the last read
     *
     * @var string
     */
    protected $tail = '';

    /**
     * Sets up an event handler for when data is read.
     *
     * @param resource $stream
     * @param \React\EventLoop\LoopInterface $loop
     * @param 
     */
    public function __construct($stream, LoopInterface $loop)
    {
        parent::__construct($stream, $loop);

        $this->on('data', function($data, $stream) {
            $stream->readMessages($data);
        });
    }

    /**
     * Sets the IRC message parser in use.
     *
     * @param \Phergie\Irc\ParserInterface $parser
     */
    public function setParser(ParserInterface $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Returns the IRC message parser in use.
     *
     * @return \Phergie\Irc\ParserInterface $parser
     */
    public function getParser()
    {
        if (!$this->parser) {
            $this->parser = new \Phergie\Irc\Parser();
        }
        return $this->parser;
    }

    /**
     * Callback for when data is read. Parses messages from the data stream
     * and emits them as events.
     *
     * @param string $data
     */
    public function readMessages($data)
    {
        $data = $this->tail . $data;
        $messages = $this->getParser()->consumeAll($data);
        $this->tail = $data;

        foreach ($messages as $message) {
            $this->emit('irc', array($message));
        }
    }
}
