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
namespace Bandzaai\SocketIO;

use Bandzaai\SocketIO\Engine\Engine;
use Bandzaai\SocketIO\Exception\SocketException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Client
{

    /**
     * Engine interface.
     *
     * @var
     */
    private $engine;

    /**
     * Logger interface.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Is a connection opnened.
     *
     * @var boolean
     */
    private $isConnected = false;

    /**
     * Construct.
     *
     * @param Engine $engine
     * @param LoggerInterface $logger
     */
    public function __construct(string $url, array $options = [], LoggerInterface $logger = null)
    {
        $this->engine = new Engine($url, $options);
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Desctruct.
     */
    public function __destruct()
    {
        if (! $this->isConnected) {
            return;
        }
        
        $this->close();
    }

    /**
     * Connects to the websocket
     *
     * @param boolean $keepAlive
     *            keep alive the connection (not supported yet) ?
     * @return $this
     */
    public function initialize($keepAlive = false): Client
    {
        try {
            $this->logger->debug('Connecting to the websocket');
            $this->engine->connect();
            $this->logger->debug('Connected to the server');
            
            $this->isConnected = true;
            
            if (true === $keepAlive) {
                $this->logger->debug('Keeping alive the connection to the websocket');
                $this->engine->keepAlive();
            }
        } catch (SocketException $e) {
            $this->logger->error('Could not connect to the server', [
                'exception' => $e
            ]);
            
            throw $e;
        }
        
        return $this;
    }

    /**
     * Reads a message from the socket
     *
     * @return string Message read from the socket
     */
    public function read(): string
    {
        $this->logger->debug('Reading a new message from the socket');
        return $this->engine->read();
    }

    /**
     * Emits a message through the engine
     *
     * @param string $event
     * @param array $args
     *
     * @return $this
     */
    public function emit($event, array $args): Client
    {
        $this->logger->debug('Sending a new message', [
            'event' => $event,
            'args' => $args
        ]);
        $this->engine->emit($event, $args);
        
        return $this;
    }

    /**
     * Sets the namespace for the next messages
     *
     * @param
     *            string namespace the name of the namespace
     * @return $this
     */
    public function of($namespace): Client
    {
        $this->logger->debug('Setting the namespace', [
            'namespace' => $namespace
        ]);
        $this->engine->of($namespace);
        
        return $this;
    }

    /**
     * Closes the connection
     *
     * @return $this
     */
    public function close(): Client
    {
        $this->logger->debug('Closing the connection to the websocket');
        $this->engine->close();
        
        $this->isConnected = false;
        
        return $this;
    }

    /**
     * Gets the engine used, for more advanced functions
     *
     * @return Engine
     */
    public function getEngine(): Engine
    {
        return $this->engine;
    }
}
