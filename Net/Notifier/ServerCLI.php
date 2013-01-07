<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Server command line interface
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
 */

/**
 * Command line interface parser class.
 */
require_once 'Console/CommandLine.php';

/**
 * Base exception class.
 */
require_once 'Net/Notifier/Exception.php';

/**
 * Logger implementation class.
 */
require_once 'Net/Notifier/LoggerCLI.php';

/**
 * Notification server class.
 */
require_once 'Net/Notifier/Server.php';

/**
 * Server command line interface
 *
 * This provides a comamnd-line interface for the notification server. It
 * bootstraps the actual server and sets up the command line and logging
 * interfaces.
 *
 * The server CLI is designed to be subclassed if a custom server is desired.
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2012-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_Notifier_ServerCLI
{
    // {{{ __invoke()

    /**
     * Runs this CLI server
     *
     * @return void
     */
    public function __invoke()
    {
        $parser = $this->getParser();
        $logger = $this->getLogger($parser);

        try {

            $result = $parser->parse();

            $logger->setVerbosity($result->options['verbose']);

            try {
                $server = $this->getServer($result->options);
                $server->setLogger($logger);
                $server->run();
            } catch (Net_Notifier_Exception $e) {
                $logger->log(
                    $e->getMessage() . PHP_EOL,
                    Net_Notifier_Logger::VERBOSITY_ERRORS
                );

                exit(1);
            }

        } catch (Console_CommandLine_Exception $e) {
            $logger->log(
                $e->getMessage() . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ERRORS
            );

            exit(1);
        } catch (Exception $e) {
            $logger->log(
                $e->getMessage() . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ERRORS
            );

            $logger->log(
                $e->getTraceAsString() . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ERRORS
            );

            exit(1);
        }
    }

    // }}}
    // {{{ getParser()

    /**
     * Gets the command line parser for this server
     *
     * @return Console_CommandLine the command line parser for this server.
     */
    protected function getParser()
    {
        return Console_CommandLine::fromXmlFile($this->getUiXml());
    }

    // }}}
    // {{{ getLogger()

    /**
     * Gets the logger for this server
     *
     * @param Console_CommandLine $parser the command line parser for this
     *                                    CLI.
     *
     * @return Net_Notifier_Logger the logger for this server.
     */
    protected function getLogger(Console_CommandLine $parser)
    {
        return new Net_Notifier_LoggerCLI($parser->outputter);
    }

    // }}}
    // {{{ getServer()

    /**
     * Gets the server used by this CLI
     *
     * @param array $options   an indexed array of command line options.
     * @param array $arguments an indexed array of command line arguments.
     *
     * @return Net_Notifier_Server the server used by this CLI.
     */
    protected function getServer(array $options, array $arguments)
    {
        return new Net_Notifier_Server($options['port']);
    }

    // }}}
    // {{{ getUiXml()

    /**
     * Gets the XML command line interface definition for this server
     *
     * @return string the XML command line interface definition for this
     *                server.
     */
    protected function getUiXml()
    {
        $dir = '@data-dir@' . DIRECTORY_SEPARATOR
            . '@package-name@' . DIRECTORY_SEPARATOR . 'data';

        if ($dir[0] == '@') {
            $dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..'
                . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data';
        }

        return $dir . DIRECTORY_SEPARATOR . 'server-cli.xml';
    }

    // }}}
}

?>
