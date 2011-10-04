<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Client connection to the cha-ching server class
 *
 * PHP version 5
 *
 * LICENSE:
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
 * @category  Net
 * @package   ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * WebSocket handshake class.
 */
require_once 'ChaChing/ChaChingWebSocketHandshake.php';

/**
 * A client connection to the cha-ching server
 *
 * This class is intended to be used internally by the {@link ChaChingServer}
 * class. It handles both regular socket connections and WebSocket
 * connections.
 *
 * @category  Net
 * @package   ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class ChaChingClientConnection
{
    // {{{ protected properties

    /**
     * The socket this connection uses to communicate with the server
     *
     * @var resource
     */
    protected $socket;

    /**
     * The IP address ths connection originated from
     *
     * @var string
     */
    protected $ipAddress;

    /**
     * A buffer containing data received by the server from this connection
     *
     * @var string
     *
     * @see ChaChingClientConnection::getMessage()
     */
    protected $buffer = '';

    /**
     * The size of the data payload of this connection in characters
     *
     * If -1 this means the payload size is not yet know.
     *
     * @var integer
     */
    protected $size = -1;

    /**
     * Whether or not this connection is a WebSocket connection
     *
     * @var boolean
     *
     * @see ChaChingClientConnection::isWebSocket()
     */
    protected $isWebSocket = false;

    /**
     * If this connection is a WebSocket connection, whether or not the
     * connection handshake has been performed
     *
     * @var boolean
     */
    protected $hasHandshaken = false;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new client connection object
     *
     * @param resource $socket the socket this connection uses to communicate
     *                          with the server.
     */
    public function __construct($socket)
    {
        $this->socket = $socket;
        socket_getpeername($socket, $this->ipAddress);
    }

    // }}}
    // {{{ getSocket()

    /**
     * Gets the socket this connection uses to communicate with the server
     *
     * @return resource the socket this connection uses to communicate with the
     *                   server.
     */
    public function getSocket()
    {
        return $this->socket;
    }

    // }}}
    // {{{ read()

    /**
     * Reads data from this connection
     *
     * @return boolean true if this connection's data payload has finished
     *                  being read and false if this connection still has data
     *                  to send.
     */
    public function read()
    {
        if (false === ($buffer = socket_read($this->socket,
            ChaChingServer::READ_BUFFER_LENGTH, PHP_BINARY_READ))) {
            echo "socket_read() failed: reason: ",
                socket_strerror(socket_last_error()), "\n";

            exit(1);
        }

        $this->buffer .= $buffer;
        $byteLength    = mb_strlen($this->buffer, '8bit');

        if ($this->size == -1 && $byteLength >= 3) {
            $data = mb_substr($this->buffer, 0, 3, '8bit');

            if ($data === 'GET') {

                $this->isWebSocket = true;

                // treat as WebSocket client
                if (!$this->hasHandshaken) {

                    $headerPos = mb_strpos(
                        $this->buffer,
                        "\r\n\r\n",
                        0,
                        '8bit'
                    );

                    if ($headerPos !== false) {
                        $data = mb_substr(
                            $this->buffer,
                            0,
                            $headerPos,
                            '8bit'
                        );

                        $this->handshake($data);
                    }
                }

            } else {

                // treat as a regular socket client
                $binaryData = mb_substr($this->buffer, 0, 2, '8bit');

                $messageData = mb_substr(
                    $this->buffer,
                    2,
                    $byteLength,
                    '8bit'
                );

                $data         = unpack('n', $binaryData);
                $this->size   = $data[1];
                $this->buffer = $messageData;

            }
        }

        return
               mb_strlen($this->buffer, '8bit') === $this->size
            || mb_strlen($buffer, '8bit') === 0;
    }

    // }}}
    // {{{ getMessage()

    /**
     * Gets the message received by the server from this connection
     *
     * @return string the message received by the server from this connection.
     *                 If the full message has not yet been received, false is
     *                 returned.
     */
    public function getMessage()
    {
        $message = false;
        if (strlen($this->buffer) == $this->size)
            $message = $this->buffer;

        return $message;
    }

    // }}}
    // {{{ getIpAddress()

    /**
     * Gets the IP address of this connection
     *
     * @return string the IP address of this connection.
     */
    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    // }}}
    // {{{ write()

    /**
     * Writes a message to this connection's socket
     *
     * The message is sent in an application-specific wrapper so it is
     * understood by the client.
     *
     * @param string $message the message to send.
     *
     * @return void
     */
    public function write($message)
    {
        $this->send($this->wrap($message));
    }

    // }}}
    // {{{ isWebSocket()

    /**
     * Gets whether or not this connection is a WebSocket connection
     *
     * @return boolean true if this connection is a WebSocket connection;
     *                 otherwise, false.
     */
    public function isWebSocket()
    {
        return $this->isWebSocket;
    }

    // }}}
    // {{{ send()

    /**
     * Sends raw data over this connection's socket
     *
     * @param string $message the data to send.
     *
     * @return void
     */
    protected function send($message)
    {
        $length = mb_strlen($message, '8bit');
        socket_write($this->socket, $message, $length);
    }

    // }}}
    // {{{ wrap()

    /**
     * Wraps a message in an application-specific wrapper
     *
     * The type of message wrapper depends on whether or not this connection
     * is a WebSocket connection.
     *
     * @param string $message the message data to wrap.
     *
     * @return string the wrapped message. After wrapping, the message is
     *                suitable for sending over this client's socket.
     */
    protected function wrap($message)
    {
        if ($this->isWebSocket()) {
            $message = "\x00" . $message . "\xff";
        } else {
            $length  = mb_strlen($message, '8bit');
            $message = pack('n', $length) . $message;
        }

        return $message;
    }

    // }}}
    // {{{ handshake()

    /**
     * Perform a WebSocket handshake for this client connection
     *
     * @param string $data the handshake data from the WebSocket client.
     *
     * @return void
     */
    protected function handshake($data)
    {
        $handshake = new ChaChingWebSocketHandshake();
        $response  = $handshake->handshake($data);

        $this->send($response);

        $this->hasHandshaken = true;
    }

    // }}}
}

?>
