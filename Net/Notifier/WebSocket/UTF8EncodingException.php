<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Exception thrown when invalid UTF-8 encoding is detected
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
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Base exception interface
 */
require_once 'Net/Notifier/Exception.php';

/**
 * Base WebSocket exception interface
 */
require_once 'Net/Notifier/WebSocket/Exception.php';

/**
 * Exception thrown when invalid UTF-8 encoding is detected
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_Notifier_WebSocket_UTF8EncodingException
    extends Exception
    implements Net_Notifier_Exception, Net_Notifier_WebSocket_Exception
{
    // {{{ protected properties

    /**
     * The binary data that is invalid UTF-8
     *
     * @var string
     *
     * @see Net_Notifier_WebSocket_UTF8EncodingException::getBinaryData()
     */
    protected $binaryData = '';

    // }}}
    // {{{ __construct()

    /**
     * Creates a new UTF-8 encoding exception
     *
     * @param string  $message    the exception message.
     * @param integer $code       optional. The error code.
     * @param string  $binaryData optional. The binary data that is invalid.
     */
    public function __construct($message, $code = 0, $binaryData = '')
    {
        parent::__construct($message, $code);
        $this->binaryData = $binaryData;
    }

    // }}}
    // {{{ getBinaryData()

    /**
     * Gets the binary data that is invalid UTF-8
     *
     * @return string the binary data that is invalid UTF-8.
     */
    public function getBinaryData()
    {
        return $this->binaryData;
    }

    // }}}
}

?>
