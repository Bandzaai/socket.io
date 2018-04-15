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


class Encoder extends Payload
{

    private $data;

    /** @var string */
    private $payload;

    /**
     *
     * @param string $data
     *            data to encode
     * @param integer $opCode
     *            OpCode to use (one of AbstractPayload's constant)
     * @param bool $mask
     *            Should we use a mask ?
     */
    public function __construct($data, $opCode, $mask)
    {
        $this->data = $data;
        $this->opCode = $opCode;
        $this->mask = (bool) $mask;
        
        if (true === $this->mask) {
            $this->maskKey = \openssl_random_pseudo_bytes(4);
        }
    }

    public function encode()
    {
        if (null !== $this->payload) {
            return;
        }
        
        $pack = '';
        $length = \strlen($this->data);
        
        if (0xFFFF < $length) {
            $pack = \pack('NN', ($length & 0xFFFFFFFF00000000) >> 0b100000, $length & 0x00000000FFFFFFFF);
            $length = 0x007F;
        } elseif (0x007D < $length) {
            $pack = \pack('n*', $length);
            $length = 0x007E;
        }
        
        $payload = ($this->fin << 0b001) | $this->rsv[0];
        $payload = ($payload << 0b001) | $this->rsv[1];
        $payload = ($payload << 0b001) | $this->rsv[2];
        $payload = ($payload << 0b100) | $this->opCode;
        $payload = ($payload << 0b001) | $this->mask;
        $payload = ($payload << 0b111) | $length;
        
        $data = $this->data;
        $payload = \pack('n', $payload) . $pack;
        
        if (true === $this->mask) {
            $payload .= $this->maskKey;
            $data = $this->maskData($data);
        }
        
        $this->payload = $payload . $data;
    }

    public function __toString()
    {
        $this->encode();
        
        return $this->payload;
    }
}
