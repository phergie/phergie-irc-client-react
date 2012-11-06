<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-client-reactphp for the canonical source repository
 * @copyright Copyright (c) 2008-2012 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc
 */

namespace Phergie\Irc\Client\React;

/**
 * Specialization of the React Stream implementation that emits an additional
 * even when data is written successfully to the stream.
 */
class Stream extends \React\Stream\Stream
{
    /**
     * Writes data to the stream and, if successful, emits a 'write' event.
     *
     * @param string $data Data to write to the stream
     * @return boolean TRUE if the operation succeeded, FALSE otherwise
     */
    public function write($data)
    {
        $result = parent::write($data);
        if ($result) {
            $this->emit('write', array($data, $this));
        }
        return $result;
    }
}
