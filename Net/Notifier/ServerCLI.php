<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Console/CommandLine.php';
require_once 'Net/Notifier/Exception.php';
require_once 'Net/Notifier/LoggerCLI.php';
require_once 'Net/Notifier/Server.php';

class Net_Notifier_ServerCLI
{
    public function __invoke()
    {
        $parser = $this->getParser();
        $logger = $this->getLogger($parser);

        try {

            $result = $parser->parse();

            $logger->setVerbosity($result->options['verbose']);

            try {
                $server = $this->getServer($result->options);
                $server->addLogger($logger);
                $server->run();
            } catch (Net_Notifier_Exception $e) {
                $logger->log(
                    $e->getMessage() . PHP_EOL,
                    Net_Notifier_Logger::VERBOSITY_ERRORS
                );

                exit(1);
            }

        } catch (Console_CommandLine_Exception $e) {
            $this->logger->log(
                $e->getMessage() . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ERRORS
            );

            exit(1);
        } catch (Exception $e) {
            $this->logger->log(
                $e->getMessage() . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ERRORS
            );

            $this->logger->log(
                $e->getTraceAsString() . PHP_EOL,
                Net_Notifier_Logger::VERBOSITY_ERRORS
            );

            exit(1);
        }
    }

    protected function getParser()
    {
        return Console_CommandLine::fromXmlFile($this->getUiXml());
    }

    protected function getLogger(Console_CommandLine $parser)
    {
        return new Net_Notifier_LoggerCLI($parser->outputter);
    }

    protected function getServer(array $options)
    {
        return new Net_Notifier_Server($options['port']);
    }

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
}

?>
