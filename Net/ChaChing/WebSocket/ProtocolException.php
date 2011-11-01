<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Exception thrown when a WebSocket client requests an unsupported protocol
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
 * @copyright 2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Base exception interface
 */
require_once 'Net/ChaChing/Exception.php';

/**
 * Exception thrown when a WebSocket client requests an unsupported protocol
 *
 * @category  Net
 * @package   Net_ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_ChaChing_WebSocket_ProtocolException
    extends Exception
    implements Net_ChaChing_Exception
{
    // {{{ protected properties

    /**
     * The protocols supported by the server
     *
     * This is an array of strings.
     *
     * @var array
     *
     * @see Net_ChaChing_WebSocket_ProtocolException::getSupportedProtocols()
     */
    protected $supportedProtocols = array();

    /**
     * The protocols requested by the client
     *
     * This is an array of strings.
     *
     * @var array
     *
     * @see Net_ChaChing_WebSocket_ProtocolException::getRequestedProtocols()
     */
    protected $requestedProtocols = array();

    // }}}
    // {{{ __construct()

    /**
     * Creates a new unsupported protocol exception
     *
     * @param string  $message            the exception message.
     * @param integer $code               optional. The error code.
     * @param array   $supportedProtocols optional. An array of protocols
     *                                    supported by the server.
     * @param array   $requestedProtocols optional. An array of protocols
     *                                    requested by the client.
     */
    public function __construct(
        $message,
        $code = 0,
        array $supportedProtocols = array(),
        array $requestedProtocols = array()
    ) {
        parent::__construct($message, $code);
        $this->supportedProtocols = $supportedProtocols;
        $this->requestedProtocols = $requestedProtocols;
    }

    // }}}
    // {{{ getSupportedProtocols()

    /**
     * Gets an array of the protocols supported by the server
     *
     * @return array the protocols supported by the server.
     */
    public function getSupportedProtocols()
    {
        return $this->supportedProtocols;
    }

    // }}}
    // {{{ getRequestedProtocols()

    /**
     * Gets an array of the protocols requested by the client
     *
     * @return array the protocols requested by the client.
     */
    public function getRequestedProtocols()
    {
        return $this->getRequestedProtocols;
    }

    // }}}
}

?>
