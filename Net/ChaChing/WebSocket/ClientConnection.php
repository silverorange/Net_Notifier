<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/ChaChing/WebSocket/ProtocolException.php';

/**
 * WebSocket handshake class.
 */
require_once 'Net/ChaChing/WebSocket/Handshake.php';

require_once 'Net/ChaChing/WebSocket/Frame.php';

require_once 'Net/ChaChing/WebSocket/FrameParser.php';

class Net_ChaChing_WebSocket_ClientConnection
{
    const FRAME_SIZE = 2048;

    const CLOSE_NORMAL = 1000;

    const CLOSE_SHUTDOWN = 1001;

    const CLOSE_ERROR = 1002;

    const CLOSE_DATA_TYPE = 1003;

    const CLOSE_FRAME_SIZE = 1004;

    const CLOSE_ENCODING_ERROR = 1007;

    // {{{ protected properties

    /**
     * Whether or not this connection is closed
     *
     * @var boolean
     */
    protected $isClosed = false;

    /**
     * @var boolean
     */
    protected $isClosing = false;

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
     * before the handshake is completed
     *
     * @var string
     */
    protected $handshakeBuffer = '';

    /**
     * A buffer containing data frame data
     *
     * If data is sent in multiple frames, this will buffer the whole message.
     *
     * @var string
     */
    protected $dataBuffer = '';

    /**
     * Received complete messages
     *
     * @var array
     *
     * @see Net_ChaChing_WebSocket_ClientConnection::getMessages()
     */
    protected $messages = array();

    /**
     * Whether or not the connection handshake has been performed
     *
     * @var boolean
     */
    protected $hasHandshaken = false;

    /**
     * WebSocket frame parser for this connection
     *
     * @var Net_ChaChing_WebSocket_FrameParser
     */
    protected $parser = null;

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
        $this->parser = new Net_ChaChing_WebSocket_FrameParser();
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

        if (!$this->hasHandshaken) {
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
                    $this->handshake($data);
                } catch (Net_ChaChing_WebSocket_ProtocolException $e) {
                    $this->hasHandshaken = true;
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

        if ($this->hasHandshaken) {
            $frames = $this->parser->parse($buffer);

            foreach ($frames as $frame) {
                $this->handleFrame($frame);
            }
        }

        return (count($this->messages) > 0);
    }

    // }}}

    protected function handleFrame(Net_ChaChing_WebSocket_Frame $frame)
    {
        switch ($frame->getOpCode()) {
        case Net_ChaChing_WebSocket_Frame::TYPE_TEXT:
        case Net_ChaChing_WebSocket_Frame::TYPE_BINARY:
            $this->dataBuffer .= $frame->getUnmaskedData();
            if ($frame->isFinal()) {
                $this->messages[] = $this->dataBuffer;
                $this->dataBuffer = '';
            }
            break;

        case Net_ChaChing_WebSocket_Frame::TYPE_CLOSE:
            $this->startClose();
            break;

        case Net_ChaChing_WebSocket_Frame::TYPE_PING:
            $this->pong($frame->getUnmaskedData());
            break;
        }
    }

    // {{{ write()

    /**
     * Writes a message to this connection's socket
     *
     * @param string $message the message to send.
     *
     * @return void
     */
    public function write($message)
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
                Net_ChaChing_WebSocket_Frame::TYPE_TEXT,
                false,
                $final
            );
            $this->send($frame->__toString());
        }
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
        $handshake = new Net_ChaChing_WebSocket_Handshake(
            array(
                Net_ChaChing_WebSocket_Server::PROTOCOL
            )
        );

        $response  = $handshake->handshake($data);

        $this->send($response);

        $this->hasHandshaken = true;
    }

    // }}}

    /**
     * Closes the WebSocket connection as per the IETF draft specification
     * section 7.1.1
     */
    public function shutdown()
    {
        socket_shutdown($this->socket, 1);
    }

    public function startClose($code = self::CLOSE_NORMAL, $reason = '')
    {
        if (!$this->isClosing() && !$this->isClosed()) {
            $code  = intval($code);
            $data  = pack('S', $code) . $reason;
            $frame = new Net_ChaChing_WebSocket_Frame(
                $data,
                Net_ChaChing_WebSocket_Frame::TYPE_CLOSE
            );

            $this->send($frame->__toString());

            $this->isClosing = true;

            $this->shutdown();
        }
    }

    public function close()
    {
        socket_close($this->socket);
    }

    public function pong($message)
    {
        $frame = new Net_ChaChing_WebSocket_Frame(
            $message,
            Net_ChaChing_WebSocket_Frame::TYPE_PONG
        );

        $this->send($frame->__toString());
    }

    public function isClosed()
    {
        return $this->isClosed;
    }

    public function isClosing()
    {
        return $this->isClosing;
    }

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
    // {{{ getMessages()

    /**
     * Gets the messages received by the server from this connection
     *
     * @return array the messages received by the server from this connection.
     *               If a full message has not yet been received, an empty
     *               array is returned.
     */
    public function getMessages()
    {
        $messages = $this->messages;
        $this->messages = array();
        return $messages;
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
}

?>
