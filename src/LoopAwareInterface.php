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

use React\EventLoop\LoopInterface;

/**
 * Interface for injection of an event loop.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
interface LoopAwareInterface
{
    /**
     * Sets the event loop for the implementing class to use.
     *
     * @param \React\EventLoop\LoopInterface $loop
     */
    public function setLoop(LoopInterface $loop);
}
