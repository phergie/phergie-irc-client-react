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

use React\Stream\ThroughStream;

/**
 * Stream that logs data piped to it using Monolog if it's available.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
class LoggerStream extends ThroughStream
{
    /**
     * Logger for data read from or written to the stream
     *
     * @var \Monolog\Logger
     */
    protected $logger;

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
     * Logs data received on the stream.
     *
     * @param string $data
     * @return string
     */
    public function filter($data)
    {
        $logger = $this->getLogger();
        if ($logger) {
            $logger->debug($data);
        }
        return $data;
    }
}
