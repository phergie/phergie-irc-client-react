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

/**
 * Interface for accesing an event loop.
 *
 * @category Phergie
 * @package Phergie\Irc\Client\React
 */
interface LoopAccessorInterface
{
    /**
     * Returns the event loop in use by the implementing class.
     *
     * @return \React\EventLoop\LoopInterface
     */
    public function getLoop();
}
