<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/ChaChing/WebSocket/ProtocolException.php';

abstract class Net_ChaChing_WebSocket_AbstractHandshake
{
    // {{{ class constants

    /**
     * Magic number used to identify WebSocket handshake requests
     *
     * Taken from the IETF RFC 6455.
     *
     * @link http://datatracker.ietf.org/doc/rfc6455/
     */
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /**
     * Protocol version
     *
     * See IETF RFC 6455 Section 4.1 item 9.
     *
     * @link http://datatracker.ietf.org/doc/rfc6455/
     */
    const VERSION = 13;

    // }}}
    // {{{ protected properties

    /**
     * Supported application-specific sub-protocols
     *
     * @var array
     */
    protected $protocols = array();

    // }}}
    // {{{ setProtocols()

    /**
     * Sets the  list of supported application-specific sub-protocols
     *
     * If set, only handshake requests for the specified protocols will succeed.
     *
     * @param array $protocols the supported protocols for this handshake.
     *
     * @return Net_ChaChing_WebSocket_AbstractHandshake the current object,
     *                                                  for fluent interface.
     */
    public function setProtocols(array $protocols)
    {
        $this->protocols = $protocols;
        return $this;
    }

    // }}}
    // {{{ getSupportedProtocol()

    protected function getSupportedProtocol(array $protocols)
    {
        $supportedProtocol = null;

        foreach ($protocols as $protocol) {
            if (in_array($protocol, $this->protocols)) {
                $supportedProtocol = $protocol;
                break;
            }
        }

        return $supportedProtocol;
    }

    // }}}
    // {{{ parseHeaders()

    /**
     * Parses the raw handshake header into an array of headers
     *
     * @param string $header the raw handshake header.
     *
     * @return array a structured array containing the following:
     *               - <kbd>status</kbd>  - the handshake status line, and
     *               - <kbd>headers</kbd> - an array of headers. Array keys
     *                                      are the header names and array
     *                                      values are the values.
     */
    protected function parseHeaders($header)
    {
        $parsedHeaders = array(
            'status'  => '',
            'headers' => array()
        );

        $headers = explode("\r\n", $header);
        $parsedHeaders['status'] = array_shift($headers);

        foreach ($headers as $header) {
            list($name, $value) = explode(':', $header, 2);
            $parsedHeaders['headers'][$name] = ltrim($value);
        }

        return $parsedHeaders;
    }

    // }}}
    // {{{ getAccept()

    /**
     * Gets the accept header value for this handshake
     *
     * The 20-character string is derived through the following:
     *
     *  1. start with the key string
     *  2. append the string 258EAFA5-E914-47DA-95CA-C5AB0DC85B11
     *  3. take the sha1() of the resulting string
     *  4. base-64 encode the sha1 result
     *
     * See section 5.2.2 of {@link http://www.whatwg.org/specs/web-socket-protocol/ The WebSocket Protocol}
     * for further details.
     *
     * @param string $key the key from which to generate the accept hash.
     *
     * @return string the accept hash.
     */
    protected function getAccept($key)
    {
        $accept = $key . self::GUID;
        $accept = sha1($accept, true);
        $accept = base64_encode($accept);
        return $accept;
    }

    // }}}
}

?>
