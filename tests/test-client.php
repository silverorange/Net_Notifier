<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Tests the pinging client of the ChaChing package by sending several test
 * pings
 *
 * First copy config.php.dist to config.php and modify to match your server.
 * Then run as 'php -f test-client.php'.
 *
 * @category  Net
 * @package   ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

require_once 'ChaChing/ChaChingClient.php';

if (   !file_exists(dirname(__FILE__) . '/config.php')
    || !is_readable(dirname(__FILE__) . '/config.php')
) {
    echo 'Test config file ' . dirname(__FILE__) . '/config.php is missing ' .
        'or not readable';

    exit(1);
}

include_once dirname(__FILE__) . '/config.php';

if (   !isset($config['server'])
    || !isset($config['port'])
    || !isset($config['sounds'])
) {
    echo 'Configuration file ' . dirname(__FILE__) . '/config.php must ' .
        'contain server, port and sounds.';

    exit(1);
}

for ($i = 0; $i < 10; $i++) {
    $client = new ChaChingClient($config['server'], $config['port']);
    $client->chaChing($config['sounds'][array_rand($config['sounds'])]);
    usleep(rand(300000, 1500000));
}

?>
