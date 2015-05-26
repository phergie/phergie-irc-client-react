<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-client-react for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license Simplified BSD License
 * @package Phergie\Irc\Client\React
 */

namespace Phergie\Irc\Client\React;

use Phergie\Irc\ParserInterface;
use React\Stream\WritableStream;

/**
 * Stream that extracts IRC messages from data piped to it and emits them as
 * 'irc' events.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class ReadStream extends WritableStream
{
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
     * @return \Phergie\Irc\ParserInterface
     */
    public function getParser()
    {
        if (!$this->parser) {
            $this->parser = new \Phergie\Irc\Parser();
        }
        return $this->parser;
    }

    /**
     * Parses messages from data piped to this stream and emits them as
     * events.
     *
     * @param string $data
     */
    public function write($data)
    {
        $all = $this->tail . $data;
        $messages = $this->getParser()->consumeAll($all);
        $this->tail = $all;

        foreach ($messages as $message) {
            if (isset($message['message'])) {
                $this->emit('data', array($message['message']));
                $this->emit('irc.received', array($message));
            } elseif (isset($message['invalid'])) {
                $this->emit('invalid', array($message['invalid']));
            }
        }
    }
}
