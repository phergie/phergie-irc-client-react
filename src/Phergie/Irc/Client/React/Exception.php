<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-client-react for the canonical source repository
 * @copyright Copyright (c) 2008-2013 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc
 */

namespace Phergie\Irc\Client\React;

/**
 * React client exception.
 *
 * @category Phergie
 * @package Phergie\Irc
 */
class Exception extends \Exception
{
    /**
     * An attempt to establish a connection to a server failed
     */
    const ERR_CONNECTION_ATTEMPT_FAILED = 1;
}
