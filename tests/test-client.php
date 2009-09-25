<?php

require_once 'PackageConfig.php';

PackageConfig::addPackage('cha-ching', 'work-gauthierm');

require_once 'ChaChing/ChaChingClient.php';

$client = new ChaChingClient('192.168.0.26');
$client->chaChing('awesome');

?>
