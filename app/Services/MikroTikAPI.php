<?php

namespace App\Services;

use Exception;

class MikroTikAPI
{
    private $socket;
    private $host;
    private $port;
    private $username;
    private $password;
    private $connected = false;
    private $timeout = 3;
    private $debug = false;
    private $maxRetries = 1;
    private $retryDelay = 1;

    public function __construct(string $host, int $port = 8728, string $username = 'admin', string $password = '')
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Connect to MikroTik router with retry logic
     */
    public function connect(): bool
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                if ($attempt > 1) {
                    sleep($this->retryDelay * ($attempt - 1)); // Exponential backoff
                }
                
                $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
                
                if (!$this->socket) {
                    throw new Exception("Connection failed: {$errstr} ({$errno})");
                }

                stream_set_timeout($this->socket, $this->timeout);
                stream_set_blocking($this->socket, true);
                
                // Login
                $response = $this->comm(['/login', '=name=' . $this->username, '=password=' . $this->password]);
                
                if (isset($response[0][0]) && $response[0][0] === '!done') {
                    $this->connected = true;
                    return true;
                }

                // Try old login method (RouterOS < 6.43)
                if (isset($response[0]['ret'])) {
                    $challenge = $response[0]['ret'];
                    $response = $this->comm([
                        '/login',
                        '=name=' . $this->username,
                        '=response=00' . md5(chr(0) . $this->password . pack('H*', $challenge))
                    ]);
                    
                    if (isset($response[0][0]) && $response[0][0] === '!done') {
                        $this->connected = true;
                        return true;
                    }
                }

                throw new Exception("Login failed");
                
            } catch (Exception $e) {
                $lastException = $e;
                if ($this->socket) {
                    @fclose($this->socket);
                    $this->socket = null;
                }
                
                if ($attempt < $this->maxRetries) {
                    continue;
                }
            }
        }
        
        throw new Exception("Connection failed after {$this->maxRetries} attempts: " . $lastException->getMessage());
    }

    /**
     * Disconnect from router
     */
    public function disconnect(): void
    {
        if ($this->socket && is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
        $this->connected = false;
    }

    /**
     * Execute command on router with auto-reconnect
     */
    public function comm(array $command, bool $parse = true): array
    {
        // Skip reconnect check for login command
        $isLoginCommand = isset($command[0]) && $command[0] === '/login';
        
        if (!$isLoginCommand) {
            $this->ensureConnected();
        }
        
        foreach ($command as $word) {
            $this->write($word);
        }
        $this->write('', true);
        
        $response = $this->read();
        
        if ($parse) {
            return $this->parseResponse($response);
        }
        
        return $response;
    }

    /**
     * Check if socket is still connected
     */
    private function isSocketAlive(): bool
    {
        if (!$this->socket || !is_resource($this->socket)) {
            return false;
        }
        
        $meta = stream_get_meta_data($this->socket);
        return !$meta['eof'] && !$meta['timed_out'];
    }

    /**
     * Reconnect if needed
     */
    private function ensureConnected(): void
    {
        if (!$this->isSocketAlive()) {
            $this->disconnect();
            $this->connect();
        }
    }

    /**
     * Write word to socket
     */
    private function write(string $word, bool $end = false): void
    {
        // Check socket is alive
        if (!$this->socket || !is_resource($this->socket)) {
            throw new Exception("Socket is not connected");
        }
        
        $length = strlen($word);
        
        if ($length < 0x80) {
            fwrite($this->socket, chr($length));
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            fwrite($this->socket, chr(($length >> 8) & 0xFF));
            fwrite($this->socket, chr($length & 0xFF));
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            fwrite($this->socket, chr(($length >> 16) & 0xFF));
            fwrite($this->socket, chr(($length >> 8) & 0xFF));
            fwrite($this->socket, chr($length & 0xFF));
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            fwrite($this->socket, chr(($length >> 24) & 0xFF));
            fwrite($this->socket, chr(($length >> 16) & 0xFF));
            fwrite($this->socket, chr(($length >> 8) & 0xFF));
            fwrite($this->socket, chr($length & 0xFF));
        } else {
            fwrite($this->socket, chr(0xF0));
            fwrite($this->socket, chr(($length >> 24) & 0xFF));
            fwrite($this->socket, chr(($length >> 16) & 0xFF));
            fwrite($this->socket, chr(($length >> 8) & 0xFF));
            fwrite($this->socket, chr($length & 0xFF));
        }
        
        fwrite($this->socket, $word);
    }

    /**
     * Read response from socket
     */
    private function read(): array
    {
        $response = [];
        $reply = [];
        
        while (true) {
            $word = $this->readWord();
            
            if ($word === false) {
                break;
            }
            
            if ($word === '') {
                if (!empty($reply)) {
                    $response[] = $reply;
                    
                    if (isset($reply[0]) && ($reply[0] === '!done' || $reply[0] === '!fatal')) {
                        break;
                    }
                    
                    $reply = [];
                }
            } else {
                $reply[] = $word;
            }
        }
        
        return $response;
    }

    /**
     * Read single word from socket
     */
    private function readWord(): string|false
    {
        if (!$this->socket || !is_resource($this->socket)) {
            return false;
        }
        
        $byte = @fread($this->socket, 1);
        
        if ($byte === false || strlen($byte) === 0) {
            // Check if connection is still alive
            $meta = stream_get_meta_data($this->socket);
            if ($meta['timed_out'] || $meta['eof']) {
                $this->connected = false;
            }
            return false;
        }
        
        $length = ord($byte);
        
        if (($length & 0x80) === 0x00) {
            // length < 0x80
        } elseif (($length & 0xC0) === 0x80) {
            $length &= ~0x80;
            $length <<= 8;
            $length += ord(fread($this->socket, 1));
        } elseif (($length & 0xE0) === 0xC0) {
            $length &= ~0xC0;
            $length <<= 8;
            $length += ord(fread($this->socket, 1));
            $length <<= 8;
            $length += ord(fread($this->socket, 1));
        } elseif (($length & 0xF0) === 0xE0) {
            $length &= ~0xE0;
            $length <<= 8;
            $length += ord(fread($this->socket, 1));
            $length <<= 8;
            $length += ord(fread($this->socket, 1));
            $length <<= 8;
            $length += ord(fread($this->socket, 1));
        } elseif ($length === 0xF0) {
            $length = ord(fread($this->socket, 1));
            $length <<= 8;
            $length += ord(fread($this->socket, 1));
            $length <<= 8;
            $length += ord(fread($this->socket, 1));
            $length <<= 8;
            $length += ord(fread($this->socket, 1));
        }
        
        if ($length === 0) {
            return '';
        }
        
        $word = '';
        while (strlen($word) < $length) {
            $chunk = fread($this->socket, $length - strlen($word));
            if ($chunk === false) {
                break;
            }
            $word .= $chunk;
        }
        
        return $word;
    }

    /**
     * Parse response into associative array
     */
    private function parseResponse(array $response): array
    {
        $parsed = [];
        
        foreach ($response as $reply) {
            $item = [];
            foreach ($reply as $word) {
                if (str_starts_with($word, '=')) {
                    $parts = explode('=', substr($word, 1), 2);
                    if (count($parts) === 2) {
                        $item[$parts[0]] = $parts[1];
                    }
                } else {
                    $item[] = $word;
                }
            }
            $parsed[] = $item;
        }
        
        return $parsed;
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Set timeout
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
    
    /**
     * Set max retries
     */
    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = max(1, $retries);
        return $this;
    }
    
    /**
     * Set retry delay
     */
    public function setRetryDelay(int $seconds): self
    {
        $this->retryDelay = max(1, $seconds);
        return $this;
    }
    
    /**
     * Ping router to check if connection is alive
     */
    public function ping(): bool
    {
        if (!$this->connected || !$this->socket || !is_resource($this->socket)) {
            return false;
        }
        
        try {
            $response = $this->comm(['/system/resource/print'], true);
            // Check for valid response (either !re or !done)
            return !empty($response) && isset($response[0]) && 
                   (isset($response[0][0]) && in_array($response[0][0], ['!re', '!done']));
        } catch (Exception $e) {
            $this->connected = false;
            return false;
        }
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
