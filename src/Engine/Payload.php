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

abstract class Payload
{

    const OPCODE_NON_CONTROL_RESERVED_1 = 0x3;

    const OPCODE_NON_CONTROL_RESERVED_2 = 0x4;

    const OPCODE_NON_CONTROL_RESERVED_3 = 0x5;

    const OPCODE_NON_CONTROL_RESERVED_4 = 0x6;

    const OPCODE_NON_CONTROL_RESERVED_5 = 0x7;

    const OPCODE_CONTINUE = 0x0;

    const OPCODE_TEXT = 0x1;

    const OPCODE_BINARY = 0x2;

    const OPCODE_CLOSE = 0x8;

    const OPCODE_PING = 0x9;

    const OPCODE_PONG = 0xA;

    const OPCODE_CONTROL_RESERVED_1 = 0xB;

    const OPCODE_CONTROL_RESERVED_2 = 0xC;

    const OPCODE_CONTROL_RESERVED_3 = 0xD;

    const OPCODE_CONTROL_RESERVED_4 = 0xE;

    const OPCODE_CONTROL_RESERVED_5 = 0xF;

    protected $fin = 0b1;

    // only one frame is necessary
    protected $rsv = [
        0b0,
        0b0,
        0b0
    ];

    // rsv1, rsv2, rsv3
    protected $mask = false;

    protected $maskKey = "\x00\x00\x00\x00";

    protected $opCode;

    /**
     * Mask a data according to the current mask key
     *
     * @param string $data
     *            Data to mask
     * @return string Masked data
     */
    protected function maskData($data)
    {
        $masked = '';
        $data = str_split($data);
        $key = str_split($this->maskKey);
        
        foreach ($data as $i => $letter) {
            $masked .= $letter ^ $key[$i % 4];
        }
        
        return $masked;
    }
}
