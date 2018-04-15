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

use Bandzaai\SocketIO\Exception\SocketException;
use Bandzaai\SocketIO\Exception\UnsupportedActionException;
use Bandzaai\SocketIO\Exception\MalformedUrlException;
use Bandzaai\SocketIO\Exception\ServerConnectionFailureException;
use Bandzaai\SocketIO\Exception\UnsupportedTransportException;

class Engine
{

    const OPEN = 0;

    const CLOSE = 1;

    const PING = 2;

    const PONG = 3;

    const MESSAGE = 4;

    const UPGRADE = 5;

    const NOOP = 6;

    const CONNECT = 0;

    const DISCONNECT = 1;

    const EVENT = 2;

    const ACK = 3;

    const ERROR = 4;

    const BINARY_EVENT = 5;

    const BINARY_ACK = 6;

    const TRANSPORT_POLLING = 'polling';

    const TRANSPORT_WEBSOCKET = 'websocket';

    /** @var string[] Parse url result */
    protected $url;

    /** @var array cookies received during handshake */
    protected $cookies = [];

    /** @var Session Session information */
    protected $session;

    /** @var mixed[] Array of options for the engine */
    protected $options;

    /** @var resource Resource to the connected stream */
    protected $stream;

    /** @var string the namespace of the next message */
    protected $namespace = '';

    /** @var mixed[] Array of php stream context options */
    protected $context = [];

    public function __construct($url, array $options = [])
    {
        $this->url = $this->parseUrl($url);
        if (isset($options['context'])) {
            $this->context = $options['context'];
            unset($options['context']);
        }
        $this->options = \array_replace($this->getDefaultOptions(), $options);
    }

    public function connect()
    {
        if (\is_resource($this->stream)) {
            return;
        }
        $this->handshake();
        $protocol = 'http';
        $errors = [
            null,
            null
        ];
        $host = \sprintf('%s:%d', $this->url['host'], $this->url['port']);
        if (true === $this->url['secured']) {
            $protocol = 'ssl';
            $host = 'ssl://' . $host;
        }
        // add custom headers
        if (isset($this->options['headers'])) {
            $headers = isset($this->context[$protocol]['header']) ? $this->context[$protocol]['header'] : [];
            $this->context[$protocol]['header'] = \array_merge($headers, $this->options['headers']);
        }
        $this->stream = \stream_socket_client($host, $errors[0], $errors[1], $this->options['timeout'], STREAM_CLIENT_CONNECT, \stream_context_create($this->context));
        if (! \is_resource($this->stream)) {
            throw new SocketException($errors[0], $errors[1]);
        }
        \stream_set_timeout($this->stream, $this->options['timeout']);
        $this->upgradeTransport();
    }

    /**
     *
     * {@inheritdoc}
     */
    public function keepAlive()
    {
        throw new UnsupportedActionException($this, 'keepAlive');
    }

    /**
     *
     * {@inheritdoc}
     */
    public function close()
    {
        if (! \is_resource($this->stream)) {
            return;
        }
        $this->write(self::CLOSE);
        \fclose($this->stream);
        $this->stream = null;
        $this->session = null;
        $this->cookies = [];
    }

    /**
     *
     * {@inheritdoc}
     */
    public function of($namespace)
    {
        $this->namespace = $namespace;
        $this->write(self::MESSAGE, self::CONNECT . $namespace);
    }

    /**
     * Write the message to the socket
     *
     * @param integer $code
     *            type of message (one of EngineInterface constants)
     * @param string $message
     *            Message to send, correctly formatted
     */
    public function write($code, $message = null)
    {
        if (! is_resource($this->stream)) {
            return;
        }
        if (! is_int($code) || 0 > $code || 6 < $code) {
            throw new \InvalidArgumentException('Wrong message type when trying to write on the socket');
        }
        $payload = new Encoder($code . $message, Encoder::OPCODE_TEXT, true);
        $bytes = fwrite($this->stream, (string) $payload);
        
        usleep((int) $this->options['wait']);
        return $bytes;
    }

    public function emit($event, array $args)
    {
        $namespace = $this->namespace;
        if ('' !== $namespace) {
            $namespace .= ',';
        }
        return $this->write(self::MESSAGE, self::EVENT . $namespace . \json_encode([
            $event,
            $args
        ]));
    }

    /**
     *
     * {@inheritdoc} Be careful, this method may hang your script, as we're not in a non
     *               blocking mode.
     */
    public function read()
    {
        if (! is_resource($this->stream)) {
            return;
        }
        /*
         * The first byte contains the FIN bit, the reserved bits, and the
         * opcode... We're not interested in them. Yet.
         * the second byte contains the mask bit and the payload's length
         */
        $data = fread($this->stream, 2);
        $bytes = unpack('C*', $data);
        $mask = ($bytes[2] & 0b10000000) >> 7;
        $length = $bytes[2] & 0b01111111;
        /*
         * Here is where it is getting tricky :
         *
         * - If the length <= 125, then we do not need to do anything ;
         * - if the length is 126, it means that it is coded over the next 2 bytes ;
         * - if the length is 127, it means that it is coded over the next 8 bytes.
         *
         * But,here's the trick : we cannot interpret a length over 127 if the
         * system does not support 64bits integers (such as Windows, or 32bits
         * processors architectures).
         */
        switch ($length) {
            case 0x7D: // 125
                break;
            case 0x7E: // 126
                $data .= $bytes = fread($this->stream, 2);
                $bytes = unpack('n', $bytes);
                if (empty($bytes[1])) {
                    throw new \RuntimeException('Invalid extended packet len');
                }
                $length = $bytes[1];
                break;
            case 0x7F: // 127
                       // are (at least) 64 bits not supported by the architecture ?
                if (8 > PHP_INT_SIZE) {
                    throw new \DomainException('64 bits unsigned integer are not supported on this architecture');
                }
                /*
                 * As (un)pack does not support unpacking 64bits unsigned
                 * integer, we need to split the data
                 *
                 * {@link http://stackoverflow.com/questions/14405751/pack-and-unpack-64-bit-integer}
                 */
                $data .= $bytes = fread($this->stream, 8);
                list ($left, $right) = array_values(unpack('N2', $bytes));
                $length = $left << 32 | $right;
                break;
        }
        // incorporate the mask key if the mask bit is 1
        if (true === $mask) {
            $data .= \fread($this->stream, 4);
        }
        // Split the packet in case of the length > 16kb
        while ($length > 0 && $buffer = \fread($this->stream, $length)) {
            $data .= $buffer;
            $length -= \strlen($buffer);
        }
        // decode the payload
        return (string) new Decoder($data);
    }

    /**
     *
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'SocketIO';
    }

    protected function handshake()
    {
        if (null !== $this->session) {
            return;
        }
        $query = [
            'use_b64' => $this->options['use_b64'],
            'EIO' => $this->options['version'],
            'transport' => $this->options['transport']
        ];
        if (isset($this->url['query'])) {
            $query = \array_replace($query, $this->url['query']);
        }
        $context = $this->context;
        $protocol = true === $this->url['secured'] ? 'ssl' : 'http';
        if (! isset($context[$protocol])) {
            $context[$protocol] = [];
        }
        // add customer headers
        if (isset($this->options['headers'])) {
            $headers = isset($context[$protocol]['header']) ? $context[$protocol]['header'] : [];
            $context[$protocol]['header'] = \array_merge($headers, $this->options['headers']);
        }
        $url = \sprintf('%s://%s:%d/%s/?%s', $this->url['scheme'], $this->url['host'], $this->url['port'], \trim($this->url['path'], '/'), \http_build_query($query));
        $result = @\file_get_contents($url, false, \stream_context_create($context));
        if (false === $result) {
            $message = null;
            $error = \error_get_last();
            if (null !== $error && false !== \strpos($error['message'], 'file_get_contents()')) {
                $message = $error['message'];
            }
            throw new ServerConnectionFailureException($message);
        }
        $open_curly_at = \strpos($result, '{');
        $todecode = \substr($result, $open_curly_at, \strrpos($result, '}') - $open_curly_at + 1);
        $decoded = \json_decode($todecode, true);
        if (! \in_array('websocket', $decoded['upgrades'])) {
            throw new UnsupportedTransportException('websocket');
        }
        $cookies = [];
        foreach ($http_response_header as $header) {
            if (\preg_match('/^Set-Cookie:\s*([^;]*)/i', $header, $matches)) {
                $cookies[] = $matches[1];
            }
        }
        $this->cookies = $cookies;
        $this->session = new Session($decoded['sid'], $decoded['pingInterval'], $decoded['pingTimeout'], $decoded['upgrades']);
    }
    
    /**
     * Upgrades the transport to WebSocket
     *
     * FYI:
     * Version "2" is used for the EIO param by socket.io v1
     * Version "3" is used by socket.io v2
     */
    protected function upgradeTransport()
    {
        $query = ['sid'       => $this->session->id,
            'EIO'       => $this->options['version'],
            'transport' => static::TRANSPORT_WEBSOCKET];
        if ($this->options['version'] === 2) {
            $query['use_b64'] = $this->options['use_b64'];
        }
        $url = \sprintf('/%s/?%s', \trim($this->url['path'], '/'), \http_build_query($query));
        $hash = \sha1(\uniqid(\mt_rand(), true), true);
        if ($this->options['version'] !== 2) {
            $hash = \substr($hash, 0, 16);
        }
        $key = \base64_encode($hash);
        $origin = '*';
        $headers = isset($this->context['headers']) ? (array) $this->context['headers'] : [] ;
        foreach ($headers as $header) {
            $matches = [];
            if (\preg_match('`^Origin:\s*(.+?)$`', $header, $matches)) {
                $origin = $matches[1];
                break;
            }
        }
        $request = "GET {$url} HTTP/1.1\r\n"
        . "Host: {$this->url['host']}:{$this->url['port']}\r\n"
        . "Upgrade: WebSocket\r\n"
            . "Connection: Upgrade\r\n"
                . "Sec-WebSocket-Key: {$key}\r\n"
                . "Sec-WebSocket-Version: 13\r\n"
                    . "Origin: {$origin}\r\n";
                    if (!empty($this->cookies)) {
                        $request .= "Cookie: " . \implode('; ', $this->cookies) . "\r\n";
                    }
                    $request .= "\r\n";
                    \fwrite($this->stream, $request);
                    $result = \fread($this->stream, 12);
                    if ('HTTP/1.1 101' !== $result) {
                        throw new \UnexpectedValueException(
                            \sprintf('The server returned an unexpected value. Expected "HTTP/1.1 101", had "%s"', $result)
                            );
                    }
                    // cleaning up the stream
                    while ('' !== \trim(\fgets($this->stream)));
                    $this->write(self::UPGRADE);
                    //remove message '40' from buffer, emmiting by socket.io after receiving EngineInterface::UPGRADE
                    if ($this->options['version'] === 2) {
                        $this->read();
                    }
    }

    /**
     * Parse an url into parts we may expect
     *
     * @param string $url
     *
     * @return string[] information on the given URL
     */
    protected function parseUrl($url)
    {
        $parsed = \parse_url($url);
        if (false === $parsed) {
            throw new MalformedUrlException($url);
        }
        $server = \array_replace([
            'scheme' => 'http',
            'host' => 'localhost',
            'query' => []
        ], $parsed);
        if (! isset($server['port'])) {
            $server['port'] = 'https' === $server['scheme'] ? 443 : 80;
        }
        if (! isset($server['path']) || $server['path'] == '/') {
            $server['path'] = 'socket.io';
        }
        if (! \is_array($server['query'])) {
            \parse_str($server['query'], $query);
            $server['query'] = $query;
        }
        $server['secured'] = 'https' === $server['scheme'];
        return $server;
    }

    /**
     * Get the defaults options
     *
     * @return array mixed[] Defaults options for this engine
     */
    protected function getDefaultOptions()
    {
        return [
            'version' => 3,
            'use_b64' => false,
            'transport' => self::TRANSPORT_POLLING,
            'debug' => false,
            'wait' => 100 * 1000,
            'timeout' => \ini_get("default_socket_timeout")
        ];
    }
}
