<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Connection exception class definition
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
 * @copyright 2012-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */

/**
 * Exception thrown when there is an error connecting a socket
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      https://github.com/silverorange/Net_Notifier
 */
class Net_Notifier_Socket_ConnectionException
    extends Exception
    implements Net_Notifier_Exception, Net_Notifier_Socket_Exception
{
    // {{{ protected properties

    /**
     * The error code returned by the PHP stream extension if available
     *
     * @var integer
     *
     * @see Net_Notifier_Socket_ConnectionException::getStreamCode()
     */
    protected $streamCode = 0;

    // }}}
    // {{{ public function __construct()

    /**
     * Creates a new connection exception
     *
     * @param string  $message    the exception message.
     * @param integer $code       optional. The error code.
     * @param integer $streamCode optional. The error code returned by the PHP
     *                            stream extension if available.
     */
    public function __construct($message, $code = 0, $streamCode = 0)
    {
        parent::__construct($message, $code);
        $this->streamCode = $streamCode;
    }

    // }}}
    // {{{ public function getStreamCode()

    /**
     * Gets the error code from the PHP stream extension for this exception
     *
     * @return integer
     */
    public function getStreamCode()
    {
        return $this->streamCode;
    }

    // }}}
}

?>
