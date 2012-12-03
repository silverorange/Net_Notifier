<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Logger that displayes logged messages to STDOUT and STDERR
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
 * Base logger class.
 */
require_once 'Net/Notifier/Logger.php';

/**
 * Command-line outputter class.
 */
require_once 'Console/CommandLine/Outputter.php';

/**
 * Logger that displayes logged messages to STDOUT and STDERR
 *
 * Logged messages of priority {@link Net_Notifier_Logger:VERBOSITY_MESSAGES} are
 * displayed on STDERR. All other messages are displayed on STDOUT. Control over
 * how many messages are displayed is specified using the
 * {@link Net_Notifier_Logger::serVerbosity()} method.
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_Notifier_LoggerCLI extends Net_Notifier_Logger
{
    // {{{ protected properties

    /**
     * Outputter for displaying verbose output from this server
     *
     * @var Console_CommandLine_Outputter
     *
     * @see Net_Notifier_Server::setOutputter()
     */
    protected $outputer = null;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new CLI-output logger
     *
     * @param Console_CommandLine_Outputter $outputter the CLI outputter to use
     *                                                 for displaying logged
     *                                                 messages.
     */
    public function __construct(Console_CommandLine_Outputter $outputter)
    {
        $this->setOutputter($outputter);
    }

    // }}}
    // {{{ setOutputter()

    /**
     * Sets the outputter for displaying verbose oputput from this server
     *
     * @param Console_CommandLine_Outputter $outputter the CLI outputter to use
     *                                                 for displaying logged
     *                                                 messages.
     *
     * @return Net_Notifier_LoggerCLI the current object, for fluent interface.
     */
    public function setOutputter(Console_CommandLine_Outputter $outputter)
    {
        $this->outputter = $outputter;
        return $this;
    }

    // }}}
    // {{{ log()

    /**
     * Logs a message based on the verbosity level
     *
     * @param string  $message   the message to log.
     * @param integer $priority  an optional verbosity level to display at. If
     *                           not specified,
     *                           {@link Net_Notifier_Logger::VERBOSITY_MESSAGES}
     *                           is used.
     * @param boolean $timestamp optional. Whether or not to include a
     *                           timestamp with the output. True by default.
     *
     * @return Net_Notifier_Logger the current object, for fluent interface.
     */
    public function log(
        $message,
        $priority = Net_Notifier_Logger::VERBOSITY_MESSAGES,
        $timestamp = true
    ) {
        if ($priority <= $this->verbosity) {
            $message = ($timestamp)
                ? '[' . date('Y-m-d H:i:s') . '] ' . $message
                : $message;

            if ($priority === Net_Notifier_Logger::VERBOSITY_ERRORS) {
                $this->outputter->stderr($message);
            } else {
                $this->outputter->stdout($message);
            }
        }

        return $this;
    }

    // }}}
}

?>
