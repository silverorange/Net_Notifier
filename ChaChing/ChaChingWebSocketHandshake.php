<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * WebSocket handshake parser used by cha-ching client connection
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
 * @copyright 2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * A client connection to the cha-ching server
 *
 * This class is intended to be used internally by the
 * {@link ChaChingServerClientConnection} class.
 *
 * @category  Net
 * @package   ChaChing
 * @copyright 2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class ChaChingWebSocketHandshake
{
    // {{{ protected properties

    /**
     * Supported application-specific sub-protocols
     *
     * @var array
     */
    protected $protocols = array();

    // }}}
    // {{{ __construct()

    /**
     * Creates a new handshake
     *
     * @param array $protocols optional. A list of supported application-
     *                         specific sub-protocols. If this array is
     *                         specified, only handshake requests for the
     *                         specified protocols will succeed.
     */
    public function __construct(array $protocols = array())
    {
        $this->protocols = $protocols;
    }

    // }}}
    // {{{ handshake()

    /**
     * Does the actual WebSocket handshake
     *
     * See sections 1.3 and 6.2 of
     * {@link http://www.whatwg.org/specs/web-socket-protocol/ The WebSocket Protocol}
     * for further details.
     *
     * @param string $handshake the handshake request.
     *
     * @return string the handshake response.
     */
    public function handshake($handshake)
    {
        $handshake = $this->parseHandshake($handshake);
        $headers   = $handshake['headers']['headers'];

        $resource = explode(' ', $handshake['headers']['status'], 3);
        $resource = $resource[1];
        $location = 'ws://' . $headers['Host'] . $resource;

        $response =
            "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: WebSocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Origin: " . $headers['Origin'] . "\r\n" .
            "Sec-WebSocket-Location: " . $location . "\r\n";

        if (isset($headers['Sec-WebSocket-Protocol'])) {
            // TODO: check against supported protocols
            $response .= 'Sec-WebSocket-Protocol: ' .
                $headers['Sec-WebSocket-Protocol'] . "\r\n";
        }

        $response .= "\r\n";

        if (   isset($headers['Sec-WebSocket-Key1'])
            && isset($headers['Sec-WebSocket-Key2'])
        ) {
            $key1 = $this->parseKey($headers['Sec-WebSocket-Key1']);
            $key2 = $this->parseKey($headers['Sec-WebSocket-Key2']);
            $key  = $this->buildKey($key1, $key2, $handshake['data']);

            $response .= $key . "\r\n";
        }

        $response .= "\xff";

        return $response;
    }

    // }}}
    // {{{ parseHandshake()

    /**
     * Parses the raw handshake request into an array of headers and data
     *
     * @param string $handshake the raw handshake request.
     *
     * @return array a structured array containing the following:
     *               - <kbd>headers</kbd> - a structured array of headers, and
     *               - <kbd>data</kbd>    - a string containing additional
     *                                      data like authentication keys.
     */
    protected function parseHandshake($handshake)
    {
        $handshake = explode("\r\n\r\n", $handshake, 2);
        $headers   = $this->parseHeaders($handshake[0]);

        // last 8 bytes are key data
        $data = $handshake[1];

        return array(
            'headers' => $headers,
            'data'    => $data,
        );
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
    // {{{ parseKey()

    /**
     * Parses a 32-bit integer out of a handshake key
     *
     * The 32-bit integer is derived through the following:
     *
     *  1. make a number from all the numeric characters in the key
     *  2. divide the number by the number of space characters in the key
     *
     * See section 6.2 of {@link http://www.whatwg.org/specs/web-socket-protocol/ The WebSocket Protocol}
     * for further details.
     *
     * @param string $key the key to parse.
     *
     * @return integer the 32-bit integer.
     */
    protected function parseKey($key)
    {
        $number = '';
        $spaces = 0;

        $length = mb_strlen($key, '8bit');
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($key, $i, 1, '8bit');
            if (ctype_digit($char)) {
                $number .= $char;
            }
            if ($char === ' ') {
                $spaces++;
            }
        }

        return intval($number / $spaces);
    }

    // }}}
    // {{{ buildKey()

    /**
     * Builds the response key used in the handshake response
     *
     * See section 6.2 of {@link http://www.whatwg.org/specs/web-socket-protocol/ The WebSocket Protocol}
     * for further details on how the key is generated.
     *
     * @param integer $key1 the first request key as parsed into a 32-bit
     *                      integer.
     * @param integer $key2 the second request key as parsed into a 32-bit
     *                      integer.
     * @param string  $data the third request key as 8 bytes (64 bits) of
     *                      binary data.
     *
     * @return string the response key as 16 bytes (128 bits) of binary data.
     */
    protected function buildKey($key1, $key2, $data)
    {
        $key = pack('N', $key1) . pack('N', $key2) . $data;
        $key = md5($key, true);
        return $key;
    }

    // }}}
}

?>
