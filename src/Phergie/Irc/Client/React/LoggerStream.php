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

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

/**
 * Stream that logs data read from or written to it using Monolog if it's
 * available.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class LoggerStream extends EventEmitter implements ReadableStreamInterface, WritableStreamInterface
{
    /**
     * Stream for which data read from it is logged
     *
     * @var \React\Stream\ReadableStreamInterface
     */
    protected $read;

    /**
     * Stream for which data written to it is logged
     *
     * @var \React\Stream\WritableStreamInterface
     */
    protected $write;

    /**
     * Logger for data read from or written to the stream
     *
     * @var \Monolog\Logger
     */
    protected $logger;

    /**
     * Accepts an optional writable stream to proxy to.
     */
    public function __construct(ReadableStreamInterface $read, WritableStreamInterface $write)
    {
        $this->read = $read;
        $this->write = $write;
        $this->logger = $this->getLogger();

        $read->on('irc', array($this, 'handleRead'));
    }

    /**
     * Returns the logger in use.
     *
     * @return \Monolog\Logger|NULL Logger or NULL if Monolog isn't available
     */
    public function getLogger()
    {
        if (class_exists('\\Monolog\\Logger') && !$this->logger) {
            $handler = new \Monolog\Handler\StreamHandler(STDERR, \Monolog\Logger::DEBUG);
            $handler->setFormatter(new \Monolog\Formatter\LineFormatter("[%datetime%] %level_name%: %message%"));
            $this->logger = new \Monolog\Logger(get_class($this));
            $this->logger->pushHandler($handler);
        }
        return $this->logger;
    }

    /**
     * Sets the logger in use.
     *
     * @param \Monolog\Logger $logger
     */
    public function setLogger($logger)
    {
        $class = get_class($logger);
        if ($class != '\\Monolog\\Logger' && !in_array('\\Monolog\\Logger', class_parents($class))) {
            trigger_error(__METHOD__ . ' was called with an invalid value for $logger', E_USER_WARNING);
        }
        $this->logger = $logger;
    }

    /**
     * Handles IRC messages being read from the read stream.
     *
     * @param array $message
     */
    public function handleRead(array $message)
    {
        if ($this->logger) {
            $this->logger->debug('S ' . $message['message']);
        }
    }

    /**
     * Implements WritableStreamInterface::isWritable().
     *
     * @return boolean
     */
    public function isWritable()
    {
        return $this->write->isWritable();
    }

    /**
     * Implements WritableStreamInterface::write().
     *
     * @param string $data
     */
    public function write($data)
    {
        if ($this->logger) {
            $this->logger->debug('C ' . $data);
        }

        return $this->write->write($data);
    }

    /**
     * Implements WritableStreamInterface::end().
     *
     * @param string $data
     */
    public function end($data = null)
    {
        return $this->write->end($data);
    }

    /**
     * Implements StreamInterface::close().
     */
    public function close()
    {
        return $this->write->close();
    }

    /**
     * Implements ReadableStreamInterface::isReadable().
     *
     * @return boolean
     */
    public function isReadable()
    {
        return $this->read->isReadable();
    }

    /**
     * Implements ReadableStreamInterface::pause().
     */
    public function pause()
    {
        return $this->read->pause();
    }

    /**
     * Implements ReadableStreamInterface::resume().
     */
    public function resume()
    {
        return $this->read->resume();
    }

    /**
     * Implements ReadableStreamInterface::pipe().
     */
    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        return $this->read->pipe($dest, $options);
    }
}
