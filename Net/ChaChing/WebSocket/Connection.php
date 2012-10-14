<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Cha-ching WebSocket connection class
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
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * ChaChing WebSocket protocol definition.
 */
require_once 'Net/ChaChing/WebSocket.php';

/**
 * UTF-8 encoding exception class definition.
 */
require_once 'Net/ChaChing/WebSocket/UTF8EncodingException.php';

/**
 * Handshake failure exception class definition.
 */
require_once 'Net/ChaChing/WebSocket/HandshakeFailureException.php';

/**
 * Protocol exception class definition.
 */
require_once 'Net/ChaChing/WebSocket/ProtocolException.php';

/**
 * WebSocket handshake class definition.
 */
require_once 'Net/ChaChing/WebSocket/Handshake.php';

/**
 * WebSocket frame class definition.
 */
require_once 'Net/ChaChing/WebSocket/Frame.php';

/**
 * WebSocket frame-parser class definition.
 */
require_once 'Net/ChaChing/WebSocket/FrameParser.php';

/**
 * A WebSocket connection
 *
 * Handles hansdhaking, sending and receiving WebSocket messages and control
 * frames.
 *
 * @category  Net
 * @package   ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_ChaChing_WebSocket_Connection
{
    // {{{ class constants

    /**
     * The maximum size in bytes of WebSocket frames sent by this connection
     *
     * Larger messages are split into multiple frames.
     */
    const FRAME_SIZE = 2048;

    /**
     * Normal connection close code
     */
    const CLOSE_NORMAL = 1000;

    /**
     * Connection closed because endpoint is going away
     *
     * Use for server shutdown, for example.
     */
    const CLOSE_GOING_AWAY = 1001;

    /**
     * Connection closed due to protocol error.
     */
    const CLOSE_PROTOCOL_ERROR = 1002;

    /**
     * Connection closed because endpoint doesn't understand requested
     * data type.
     */
    const CLOSE_DATA_TYPE = 1003;

    /**
     * Connection closed because frame data payload was not encoded correctly
     * according to frame type
     */
    const CLOSE_ENCODING_ERROR = 1007;

    /**
     * Connection closed because the endpoint violated server policy.
     *
     * This is a generic close code that may be used when the policy violation
     * doesn't match another defined code or the specific policy violation
     * should remain hidden.
     */
    const CLOSE_POLICY_VIOLATION = 1008;

    /**
     * Connection closed because the message payload is too large to process.
     */
    const CLOSE_TOO_LARGE = 1009;

    /**
     * Connection closed because an unsupported WebSocket extension was
     * requested.
     */
    const CLOSE_UNSUPPORTED_EXTENSION = 1010;

    /**
     * Connection closed because of an unexpected error while processing
     * request
     */
    const CLOSE_UNEXPECTED_ERROR = 1011;

    /**
     * Connection state for TCP connection open but handshake not complete.
     */
    const STATE_CONNECTING = 0;

    /**
     * Connection state when handshake completes successfully.
     */
    const STATE_OPEN = 1;

    /**
     * Connection state when connection sent a close frame but is not yet
     * closed.
     */
    const STATE_CLOSING = 2;

    /**
     * Connection state for connections that are closed.
     */
    const STATE_CLOSED = 3;

    // }}}
    // {{{ protected properties

    /**
     * The socket this connection uses to communicate with the server
     *
     * @var resource
     */
    protected $socket;

    /**
     * The IP address from which this connection originated
     *
     * @var string
     */
    protected $ipAddress;

    /**
     * A buffer containing data received by the server from this connection
     * before the handshake is completed
     *
     * @var string
     */
    protected $handshakeBuffer = '';

    /**
     * A buffer containing binary data
     *
     * If binary data is sent in multiple frames, this will buffer the whole
     * message.
     *
     * @var string
     */
    protected $binaryBuffer = '';

    /**
     * Received complete binary messages
     *
     * @var array
     *
     * @see Net_ChaChing_WebSocket_Connection::getBinaryMessages()
     */
    protected $binaryMessages = array();

    /**
     * A buffer containing text data
     *
     * If text data is sent in multiple frames, this will buffer the whole
     * message.
     *
     * @var string
     */
    protected $textBuffer = '';

    /**
     * Received complete text messages
     *
     * @var array
     *
     * @see Net_ChaChing_WebSocket_Connection::getTextMessages()
     */
    protected $textMessages = array();

    /**
     * WebSocket frame parser for this connection
     *
     * @var Net_ChaChing_WebSocket_FrameParser
     */
    protected $parser = null;

    /**
     * The nonce value used during the connection handshake
     *
     * This is only used for clients connecting to servers.
     *
     * @var string
     */
    protected $handshakeNonce = '';

    /**
     * The current connection state
     *
     * One of:
     *
     * - {@link Net_ChaChing_WebSocket_Connection::STATE_CONNECTING}
     * - {@link Net_ChaChing_WebSocket_Connection::STATE_OPEN}
     * - {@link Net_ChaChing_WebSocket_Connection::STATE_CLOSING}
     * - {@link Net_ChaChing_WebSocket_Connection::STATE_CLOSED}
     *
     * @var integer
     */
    protected $state = self::STATE_CONNECTING;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new client connection object
     *
     * @param resource                           $socket the socket this
     *                                                   connection uses to
     *                                                   communicate with the
     *                                                   server.
     * @param Net_ChaChing_WebSocket_FrameParser $parser optional. The frame
     *                                                   parser to use for this
     *                                                   connection.
     */
    public function __construct(
        $socket,
        Net_ChaChing_WebSocket_FrameParser $parser = null
    ) {
        if ($parser === null) {
            $parser = new Net_ChaChing_WebSocket_FrameParser();
        }

        $this->setFrameParser($parser);
        $this->socket = $socket;
        socket_getpeername($socket, $this->ipAddress);
    }

    // }}}
    // {{{ read()

    /**
     * Reads data from this connection
     *
     * @param integer $length the number of bytes to read.
     *
     * @return boolean true if one or more messages were receved from this
     *                 connection and false if a partial message or another
     *                 frame type was received.
     */
    public function read($length)
    {
        $buffer = socket_read($this->socket, $length, PHP_BINARY_READ);

        if (false === $buffer) {
            echo "socket_read() failed: reason: ",
                socket_strerror(socket_last_error()), "\n";

            exit(1);
        }

        if ($this->state < self::STATE_OPEN) {
            $this->handshakeBuffer .= $buffer;

            $headerPos = mb_strpos(
                $this->handshakeBuffer,
                "\r\n\r\n",
                0,
                '8bit'
            );

            if ($headerPos !== false) {
                $data = mb_substr(
                    $this->handshakeBuffer,
                    0,
                    $headerPos,
                    '8bit'
                );

                try {
                    $this->handleHandshake($data);
                } catch (Net_ChaChing_WebSocket_ProtocolException $e) {
                    $this->state = self::STATE_CLOSED;
                    $this->shutdown();
                }

                $length = mb_strlen($this->handshakeBuffer, '8bit');
                $buffer = mb_substr(
                    $this->handshakeBuffer,
                    $headerPos + 4,
                    $length - $headerPos - 5,
                    '8bit'
                );
            }
        }

        if ($this->state > self::STATE_CONNECTING) {
            $frames = $this->parser->parse($buffer);

            foreach ($frames as $frame) {
                $this->handleFrame($frame);
            }
        }

        return (
               count($this->textMessages) > 0
            || count($this->binaryMessages) > 0
        );
    }

    // }}}
    // {{{ handleFrame()

    /**
     * Handles a completed received frame
     *
     * This handles saving completed text or binary messages, sending pongs
     * to pings and closing the connection if a close frame is received.
     *
     * @param Net_ChaChing_WebSocket_Frame $frame the frame to handle.
     *
     * @return void
     *
     * @throws Net_ChaChing_WebSocket_UTF8EncodingException if a complete
     *         text message is received and the text message has unpaired
     *         unpaired surrogates (invalid UTF-8 encoding).
     */
    protected function handleFrame(Net_ChaChing_WebSocket_Frame $frame)
    {
        switch ($frame->getOpcode()) {
        case Net_ChaChing_WebSocket_Frame::TYPE_BINARY:
            $this->binaryBuffer .= $frame->getUnmaskedData();
            if ($frame->isFinal()) {
                $this->binaryMessages[] = $this->binaryBuffer;
                $this->binaryBuffer = '';
            }
            break;

        case Net_ChaChing_WebSocket_Frame::TYPE_TEXT:
            $this->textBuffer .= $frame->getUnmaskedData();
            if ($frame->isFinal()) {
                if (!$this->isValidUTF8($this->textBuffer)) {
                    throw new Net_ChaChing_WebSocket_UTF8EncodingException(
                        'Received a text message that is invalid UTF-8.',
                        0,
                        $this->textBuffer
                    );
                }
                $this->textMessages[] = $this->textBuffer;
                $this->textBuffer = '';
            }
            break;

        case Net_ChaChing_WebSocket_Frame::TYPE_CLOSE:
            if ($this->state === self::STATE_CLOSING) {
                $this->close();
            } else {
                $this->startClose();
            }
            break;

        case Net_ChaChing_WebSocket_Frame::TYPE_PING:
            $this->pong($frame->getUnmaskedData());
            break;
        }
    }

    // }}}
    // {{{ writeBinary()

    /**
     * Writes a binary message to this connection's socket
     *
     * @param string $message the message to send.
     *
     * @return void
     */
    public function writeBinary($message)
    {
        $final = false;
        $pos = 0;
        $totalLength = mb_strlen($message, '8bit');
        while (!$final) {
            $data = mb_substr($message, $pos, self::FRAME_SIZE, '8bit');
            $dataLength = mb_strlen($data, '8bit');
            $pos += $dataLength;
            $final = ($pos === $totalLength);
            $frame = new Net_ChaChing_WebSocket_Frame(
                $data,
                Net_ChaChing_WebSocket_Frame::TYPE_BINARY,
                false,
                $final
            );
            $this->send($frame->__toString());
        }
    }

    // }}}
    // {{{ writeText()

    /**
     * Writes a text message to this connection's socket
     *
     * @param string $message the message to send encoded as UTF-8 text.
     *
     * @return void
     *
     * @throws Net_ChaChing_WebSocket_UTF8EncodingException if the specified
     *         text has unpaired surrogates (invalid UTF-8 encoding).
     */
    public function writeText($message)
    {
        if (!$this->isValidUTF8($message)) {
            throw new Net_ChaChing_WebSocket_UTF8EncodingException(
                'Can not write text message that is invalid UTF8.',
                0,
                $message
            );
        }

        $final = false;
        $pos = 0;
        $totalLength = mb_strlen($message, '8bit');
        while (!$final) {
            $data = mb_substr($message, $pos, self::FRAME_SIZE, '8bit');
            $dataLength = mb_strlen($data, '8bit');
            $pos += $dataLength;
            $final = ($pos === $totalLength);
            $frame = new Net_ChaChing_WebSocket_Frame(
                $data,
                Net_ChaChing_WebSocket_Frame::TYPE_TEXT,
                false,
                $final
            );
            $this->send($frame->__toString());
        }
    }

    // }}}
    // {{{ handleHandshake()

    /**
     * Performs a WebSocket handshake step for this client connection
     *
     * Depending on the handshake data received from the socket, this either
     * completes a server connection or completes a client connection.
     *
     * @param string $data the handshake data from the WebSocket client.
     *
     * @return void
     *
     * @throws Net_ChaChing_WebSocket_HandshakeFailureException if the
     *         handshake fails and the connection is closed.
     */
    protected function handleHandshake($data)
    {
        $handshake = new Net_ChaChing_WebSocket_Handshake();

        try {
            $response = $handshake->receive(
                $data,
                $this->handshakeNonce,
                array(Net_ChaChing_WebSocket::PROTOCOL)
            );

            // for client-connecting to server handshakes, we need to send the
            // Sec-WebSocket-Accept response.
            if ($response !== null) {
                $this->send($response);
            }

            $this->state = self::STATE_OPEN;
        } catch (Net_ChaChing_WebSocket_HandshakeFailureException $e) {
            // fail the WebSocket connection
            $this->close();

            // continue to throw exception so it may be logged/reported
            throw $e;
        }
    }

    // }}}
    // {{{ startHandshake()

    /**
     * Initiates a WebSocket handshake for this client connection
     *
     * @param string  $host      the server host name or IP address.
     * @param integer $port      the server connection port.
     * @param string  $resource  optional. The WebSocket resource name.
     * @param array   $protocols optional. An array of requested sub-protocols.
     *
     * @return void
     */
    public function startHandshake(
        $host,
        $port,
        $resource = '/',
        array $protocols = array()
    ) {
        $this->handshakeNonce = $this->getNonce();

        $handshake = new Net_ChaChing_WebSocket_Handshake();

        $request = $handshake->start(
            $host,
            $port,
            $this->handshakeNonce,
            $resource,
            $protocols
        );

        $this->send($request);

        $this->state = self::STATE_CONNECTING;
    }

    // }}}
    // {{{ shutdown()

    /**
     * Closes the WebSocket connection as per the IETF RFC 6455 section 7.1.1
     *
     * @return void
     */
    public function shutdown()
    {
        // close the socket for writing
        socket_shutdown($this->socket, 1);
    }

    // }}}
    // {{{ startClose()

    /**
     * Initiates the WebSocket closing handshake
     *
     * This sends a close frame with a reason code and message. Defined reason
     * codes include:
     *
     * - {@link Net_ChaChing_Connection::CLOSE_NORMAL},
     * - {@link Net_ChaChing_Connection::CLOSE_GOING_AWAY},
     * - {@link Net_ChaChing_Connection::CLOSE_PROTOCOL_ERROR},
     * - {@link Net_ChaChing_Connection::CLOSE_DATA_TYPE},
     * - {@link Net_ChaChing_Connection::CLOSE_ENCODING_ERROR},
     * - {@link Net_ChaChing_Connection::CLOSE_POLICY_VIOLATION},
     * - {@link Net_ChaChing_Connection::CLOSE_UNSUPPORTED_EXTENSION}, and
     * - {@link Net_ChaChing_Connection::CLOSE_UNEXPECTED_ERROR}.
     *
     * Other codes may be used depeding on the application.
     *
     * @param integer $code   the close reason code.
     * @param string  $reason optional. A text description of why the
     *                        connection is being closed. Encoded as UTF-8
     *                        text.
     *
     * @return void
     */
    public function startClose($code = self::CLOSE_NORMAL, $reason = '')
    {
        if ($this->state < self::STATE_CLOSING) {
            $code  = intval($code);
            $data  = pack('s', $code) . $reason;
            $frame = new Net_ChaChing_WebSocket_Frame(
                $data,
                Net_ChaChing_WebSocket_Frame::TYPE_CLOSE
            );

            $this->send($frame->__toString());

            $this->state = self::STATE_CLOSING;

            $this->shutdown();
        }
    }

    // }}}
    // {{{ close()

    /**
     * Closes this connection
     *
     * The underlying socket is closed.
     *
     * @return void
     */
    public function close()
    {
        socket_close($this->socket);
        $this->state = self::STATE_CLOSED;
    }

    // }}}
    // {{{ pong()

    /**
     * Sends a pong frame
     *
     * Pongs are sent automatically in response to pings. They can be sent
     * manually with this method.
     *
     * @param string $message the message to include with the pong frame.
     *
     * @return void
     */
    public function pong($message)
    {
        $frame = new Net_ChaChing_WebSocket_Frame(
            $message,
            Net_ChaChing_WebSocket_Frame::TYPE_PONG
        );

        $this->send($frame->__toString());
    }

    // }}}
    // {{{ getState()

    /**
     * Gets the current state of this connection
     *
     * One of:
     *
     * - {@link Net_ChaChing_WebSocket_Connection::STATE_CONNECTING}
     * - {@link Net_ChaChing_WebSocket_Connection::STATE_OPEN}
     * - {@link Net_ChaChing_WebSocket_Connection::STATE_CLOSING}
     * - {@link Net_ChaChing_WebSocket_Connection::STATE_CLOSED}
     *
     * @return integer the current state of this connection.
     */
    public function getState()
    {
        return $this->state;
    }

    // }}}
    // {{{ getBinaryMessages()

    /**
     * Gets the binary messages received by the server from this connection
     *
     * @return array the messages received by the server from this connection.
     *               If a full binary message has not yet been received, an
     *               empry array is returned.
     */
    public function getBinaryMessages()
    {
        $messages = $this->binaryMessages;
        $this->binaryMessages = array();
        return $messages;
    }

    // }}}
    // {{{ getTextMessages()

    /**
     * Gets the text messages received by the server from this connection
     *
     * @return array the messages received by the server from this connection.
     *               If a full text message has not yet been received, an empty
     *               array is returned.
     */
    public function getTextMessages()
    {
        $messages = $this->textMessages;
        $this->textMessages = array();
        return $messages;
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
    // {{{ setFrameParser()

    /**
     * Sets the frame parser to use for this connection
     *
     * @param Net_ChaChing_WebSocket_FrameParser $parser the parser to use for
     *                                                   this connection.
     *
     * @return Net_ChaChing_Connection the current object, for fluent
     *                                       interface.
     */
    public function setFrameParser(Net_ChaChing_WebSocket_FrameParser $parser)
    {
        $this->parser = $parser;
        return $this;
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
    // {{{ isValidUTF8()

    /**
     * Gets whether or not a string is valid UTF-8
     *
     * @param string $string the binary string to check.
     *
     * @return boolean true if the string is valud UTF-8 and false if it is not.
     */
    protected function isValidUTF8($string)
    {
        return (mb_detect_encoding($string, 'UTF-8', true) === 'UTF-8');
    }

    // }}}
    // {{{ getNonce()

    /**
     * Gets a random 16-byte base-64 encoded value
     *
     * The nonce is used during the WebSocket connection handshake. See
     * IETF RFC 6455 section 4.1 handshake requirements item 7.
     *
     * @return string a base-64 encoded randomly selected 16-byte value.
     */
    protected function getNonce()
    {
        $nonce = '';

        // get 16 random bytes
        for ($i = 0; $i < 16; $i++) {
            $nonce .= chr(mt_rand(0, 255));
        }

        // base-64 encode them
        $nonce = base64_encode($nonce);

        return $nonce;
    }

    // }}}
}

?>
