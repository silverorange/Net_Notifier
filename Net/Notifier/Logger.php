<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Abstract logger class
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
 * @copyright 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Abstract logger for receiving and displaying or storing messages
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class Net_Notifier_Logger
{
    // {{{ class constants

    /**
     * Verbosity level for showing nothing.
     */
    const VERBOSITY_NONE = 0;

    /**
     * Verbosity level for showing fatal errors.
     */
    const VERBOSITY_ERRORS = 1;

    /**
     * Verbosity level for showing relayed messages.
     */
    const VERBOSITY_MESSAGES = 2;

    /**
     * Verbosity level for showing all client activity.
     */
    const VERBOSITY_CLIENT = 3;

    /**
     * Verbosity level for showing all activity.
     */
    const VERBOSITY_ALL = 4;

    // }}}
    // {{{ protected properties

    /**
     * The level of verbosity to use
     *
     * @var integer
     *
     * @see Net_Notifier_Logger::setVerbosity()
     * @see Net_Notifier_Logger::VERBOSITY_NONE
     * @see Net_Notifier_Logger::VERBOSITY_ERRORS
     * @see Net_Notifier_Logger::VERBOSITY_MESSAGES
     * @see Net_Notifier_Logger::VERBOSITY_CLIENT
     * @see Net_Notifier_Logger::VERBOSITY_ALL
     */
    protected $verbosity = 1;

    // }}}
    // {{{ setVerbosity()

    /**
     * Sets the level of verbosity to use
     *
     * @param integer $verbosity the level of verbosity to use.
     *
     * @return Net_Notifier_Logger the current object, for fluent interface.
     *
     * @see Net_Notifier_Logger::VERBOSITY_NONE
     * @see Net_Notifier_Logger::VERBOSITY_ERRORS
     * @see Net_Notifier_Logger::VERBOSITY_MESSAGES
     * @see Net_Notifier_Logger::VERBOSITY_CLIENT
     * @see Net_Notifier_Logger::VERBOSITY_ALL
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = (integer)$verbosity;
        return $this;
    }

    // }}}
    // {{{ log()

    /**
     * Logs a message based on the verbosity level
     *
     * @param string  $message   the message to log.
     * @param integer $priority  an optional verbosity level to display at. By
     *                           default, this is
     *                           {@link Net_Notifier_Logger::VERBOSITY_MESSAGES}.
     * @param boolean $timestamp optional. Whether or not to include a
     *                           timestamp with the logged message. If not
     *                           specified, a timetamp is included.
     *
     * @return Net_Notifier_Logger the current object, for fluent interface.
     */
    abstract public function log(
        $message,
        $priority = self::VERBOSITY_MESSAGES,
        $timestamp = true
    );

    // }}}
}

?>
