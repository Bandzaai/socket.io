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

use Bandzaai\SocketIO\Payload;

class Decoder extends Payload implements \Countable
{

    private $payload;

    private $data;

    private $length;

    /**
     *
     * @param string $payload
     *            Payload to decode
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    public function decode()
    {
        if (null !== $this->data) {
            return;
        }
        
        $length = \count($this);
        
        // if ($payload !== null) and ($payload packet error)?
        // invalid websocket packet data or not (text, binary opCode)
        if (3 > $length) {
            return;
        }
        
        $payload = \array_map('ord', \str_split($this->payload));
        
        $this->fin = ($payload[0] >> 0b111);
        
        $this->rsv = [
            ($payload[0] >> 0b110) & 0b1, // rsv1
            ($payload[0] >> 0b101) & 0b1, // rsv2
            ($payload[0] >> 0b100) & 0b1
        ]; // rsv3
        
        $this->opCode = $payload[0] & 0xF;
        $this->mask = (bool) ($payload[1] >> 0b111);
        
        $payloadOffset = 2;
        
        if ($length > 125) {
            $payloadOffset = (0xFFFF < $length && 0xFFFFFFFF >= $length) ? 6 : 4;
        }
        
        $payload = \implode('', \array_map('chr', $payload));
        
        if (true === $this->mask) {
            $this->maskKey = \substr($payload, $payloadOffset, 4);
            $payloadOffset += 4;
        }
        
        $data = \substr($payload, $payloadOffset, $length);
        
        if (true === $this->mask) {
            $data = $this->maskData($data);
        }
        
        $this->data = $data;
    }

    public function count()
    {
        if (null === $this->payload) {
            return 0;
        }
        
        if (null !== $this->length) {
            return $this->length;
        }
        
        $length = \ord($this->payload[1]) & 0x7F;
        
        if ($length == 126 || $length == 127) {
            $length = \unpack('H*', \substr($this->payload, 2, ($length == 126 ? 2 : 4)));
            $length = \hexdec($length[1]);
        }
        
        return $this->length = $length;
    }

    public function __toString()
    {
        $this->decode();
        
        return $this->data ?: '';
    }
}
