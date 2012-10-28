<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Tests the send client of the Net_Notifier package by sending several test
 * messages
 *
 * First copy config.php.dist to config.php and modify to match your server.
 * Then run as 'php -f test-client.php'.
 *
 * @category  Net
 * @package   Net_Notifier
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

require_once 'Net/Notifier/Client.php';

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

error_reporting(E_ALL);
ini_set('display_errors', '1');

$address = 'ws://' . $config['server'] . ':' . $config['port'];

for ($i = 0; $i < 10; $i++) {
    $id = $config['sounds'][array_rand($config['sounds'])];
    $value = getValue() / 100;
    $client = new Net_Notifier_Client($address);
    $client->sendText(json_encode(array('id' => $id, 'value' => $value)));
    usleep(rand(300000, 1500000));
}

/**
 * Gets a random value from a probability density function using
 * rejection-sampling
 *
 * It's not very efficient but it doesn't have to be since this is only a
 * small simulation.
 *
 * @return float a value between 1 and 100000.
 */
function getValue()
{
    while (true) {
        $value = mt_rand(1, 100000);
        $threshold = exp(-($value - 1) / 10000) * 15;
        if (mt_rand(0, 100) < $threshold) {
            break;
        }
    }

    return $value;
}

?>
