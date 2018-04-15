<?php
/**
 * Copyright (c) 2018 Bandzaai
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
namespace Bandzaai\SocketIO\Engine;

class Session
{

    /** @var integer session's id */
    private $id;

    /** @var integer session's last heartbeat */
    private $heartbeat;

    /** @var integer[] session's and heartbeat's timeouts */
    private $timeouts;

    /** @var string[] supported upgrades */
    private $upgrades;

    public function __construct($id, $interval, $timeout, array $upgrades)
    {
        $this->id = $id;
        $this->upgrades = $upgrades;
        $this->heartbeat = \time();
        
        $this->timeouts = [
            'timeout' => $timeout,
            'interval' => $interval
        ];
    }

    /**
     * The property should not be modified, hence the private accessibility on them
     *
     * @param string $prop
     * @return mixed
     */
    public function __get($prop)
    {
        static $list = [
            'id',
            'upgrades'
        ];
        
        if (! \in_array($prop, $list)) {
            throw new \InvalidArgumentException(\sprintf('Unknown property "%s" for the Session object. Only the following are availables : ["%s"]', $prop, \implode('", "', $list)));
        }
        
        return $this->$prop;
    }

    /**
     * Checks whether a new heartbeat is necessary, and does a new heartbeat if it is the case
     *
     * @return Boolean true if there was a heartbeat, false otherwise
     */
    public function needsHeartbeat()
    {
        if (0 < $this->timeouts['interval'] && \time() > ($this->timeouts['interval'] + $this->heartbeat - 5)) {
            $this->heartbeat = \time();
            
            return true;
        }
        
        return false;
    }
}
