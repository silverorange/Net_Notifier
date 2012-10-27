<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Socket wrapper class
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Copyright (c) 2008-2012 Alexey Borzov <avb@php.net>
 * Copyright (c) 2012 Michael Gauthier <mike@silverorange.com>
 *
 * This library is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation; either version 2.1 of the
 * License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * This file is adapted from HTTP_Request2_SocketWrapper, originally licensed
 * as follows:
 *
 *
 *     Copyright (c) 2008-2012, Alexey Borzov <avb@php.net>
 *
 *     All rights reserved.
 *
 *     Redistribution and use in source and binary forms, with or without
 *     modification, are permitted provided that the following conditions
 *     are met:
 *
 *        * Redistributions of source code must retain the above copyright
 *          notice, this list of conditions and the following disclaimer.
 *        * Redistributions in binary form must reproduce the above copyright
 *          notice, this list of conditions and the following disclaimer in the
 *          documentation and/or other materials provided with the distribution.
 *        * The names of the authors may not be used to endorse or promote products
 *          derived from this software without specific prior written permission.
 *
 *     THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
 *     IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
 *     THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *     PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 *     CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 *     EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 *     PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 *     PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 *     OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 *     NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 *     SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *
 * @category  Net
 * @package   ChaChing
 * @author    Alexey Borzov <avb@php.net>
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2008-2012 Alexy Borzov, 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Connection exception class definition.
 */
require_once 'Net/ChaChing/WebSocket/ConnectionException.php';

/**
 * Timeout exception class definition.
 */
require_once 'Net/ChaChing/WebSocket/TimeoutException.php';

/**
 * Socket wrapper class
 *
 * Needed to properly handle connection errors, global timeout support and
 * similar things. Loosely based on Net_Socket used by older HTTP_Request.
 *
 * @category  Net
 * @package   ChaChing
 * @author    Alexey Borzov <avb@php.net>
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2008-2012 Alexy Borzov, 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://tools.ietf.org/html/rfc1928
 */
abstract class Net_ChaChing_WebSocket_SocketAbstract
{
    /**
     * PHP warning messages raised during stream_socket_client() call
     * @var array
     */
    protected $connectionWarnings = array();

    /**
     * Connected socket
     *
     * @var resource
     */
    protected $socket;

    /**
     * Sum of start time and global timeout
     *
     * An exception will be thrown if request continues past this time.
     *
     * @var integer
     */
    protected $deadline;

    /**
     * Global timeout value, mostly for exception messages
     *
     * @var integer
     */
    protected $timeout;


    /**
     * Destructor, disconnects socket
     */
    public function __destruct()
    {
        fclose($this->socket);
    }

    /**
     * Wrapper around fread(), handles global request timeout
     *
     * @param integer $length the number of bytes to read.
     *
     * @return string the data read from the socket.
     *
     * @throws Net_ChaChing_WebSocket_TimeoutException In case of timeout.
     */
    public function read($length)
    {
        if ($this->deadline) {
            stream_set_timeout($this->socket, max($this->deadline - time(), 1));
        }
        $data = fread($this->socket, $length);
        $this->checkTimeout();
        return $data;
    }

    /**
     * Reads until either the end of the socket or a newline, whichever comes
     * first
     *
     * Strips the trailing newline from the returned data, handles global
     * request timeout. Method idea borrowed from Net_Socket PEAR package.
     *
     * @param integer $bufferSize buffer size to use for reading
     *
     * @return string Available data up to the newline (not including newline)
     *
     * @throws Net_ChaChing_WebSocket_TimeoutException In case of timeout.
     */
    public function readLine($bufferSize)
    {
        $line = '';
        while (!feof($this->socket)) {
            if ($this->deadline) {
                stream_set_timeout($this->socket, max($this->deadline - time(), 1));
            }
            $line .= @fgets($this->socket, $bufferSize);
            $this->checkTimeout();
            if (mb_substr($line, -1, mb_strlen($line, '8bit'), '8bit') == "\n") {
                return rtrim($line, "\r\n");
            }
        }
        return $line;
    }

    /**
     * Wrapper around fwrite(), handles global request timeout
     *
     * @param string $data String to be written
     *
     * @return integer the number of bytes written.
     *
     * @throws Net_ChaChing_WebSocket_ConnectionException
     */
    public function write($data)
    {
        if ($this->deadline) {
            stream_set_timeout($this->socket, max($this->deadline - time(), 1));
        }
        $written = fwrite($this->socket, $data);
        $this->checkTimeout();
        // http://www.php.net/manual/en/function.fwrite.php#96951
        if ($written < mb_strlen($data, '8bit')) {
            throw new Net_ChaChing_WebSocket_ConnectionException(
                'Error writing request'
            );
        }
        return $written;
    }

    /**
     * Tests for end-of-file on a socket
     *
     * @return boolean
     */
    public function eof()
    {
        return feof($this->socket);
    }

    /**
     * Sets request deadline
     *
     * @param integer $deadline Exception will be thrown if request continues
     *                          past this time
     * @param integer $timeout  Original request timeout value, to use in
     *                          Exception message
     */
    public function setDeadline($deadline, $timeout)
    {
        $this->deadline = $deadline;
        $this->timeout  = $timeout;
    }

    /**
     * Turns on encryption on a socket
     *
     * @throws Net_ChaChing_WebSocket_ConnectionException
     */
    public function enableCrypto()
    {
        $modes = array(
            STREAM_CRYPTO_METHOD_TLS_CLIENT,
            STREAM_CRYPTO_METHOD_SSLv3_CLIENT,
            STREAM_CRYPTO_METHOD_SSLv23_CLIENT,
            STREAM_CRYPTO_METHOD_SSLv2_CLIENT
        );

        foreach ($modes as $mode) {
            if (stream_socket_enable_crypto($this->socket, true, $mode)) {
                return;
            }
        }
        throw new Net_ChaChing_WebSocket_ConnectionException(
            'Failed to enable secure connection when connecting through proxy'
        );
    }

    /**
     * Throws an exception if stream timed out
     *
     * @throws Net_ChaChing_WebSocket_TimeoutException
     */
    protected function checkTimeout()
    {
        $info = stream_get_meta_data($this->socket);
        if ($info['timed_out'] || $this->deadline && time() > $this->deadline) {
            $reason = $this->deadline
                ? "after {$this->timeout} second(s)"
                : 'due to default_socket_timeout php.ini setting';

            throw new Net_ChaChing_WebSocket_TimeoutException(
                "Request timed out {$reason}"
            );
        }
    }

    /**
     * Error handler to use during stream_socket_client() call
     *
     * One stream_socket_client() call may produce *multiple* PHP warnings
     * (especially OpenSSL-related), we keep them in an array to later use for
     * the message of Net_ChaChing_WebSocket_ConnectionException
     *
     * @param integer $errno  error level
     * @param string  $errstr error message
     *
     * @return bool
     */
    protected function connectionWarningsHandler($errno, $errstr)
    {
        if ($errno & E_WARNING) {
            array_unshift($this->connectionWarnings, $errstr);
        }
        return true;
    }

    public function getPeerName()
    {
        return stream_socket_get_name($this->socket, true);
    }

    public function shutdown($how)
    {
        return stream_socket_shutdown($this->socket, $how);
    }

    public function getRawSocket()
    {
        return $this->socket;
    }

    public function peek($length)
    {
        return stream_socket_recvfrom($this->socket, $length, STREAM_PEEK);
    }
}

?>
