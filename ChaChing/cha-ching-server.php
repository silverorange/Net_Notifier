#!/usr/bin/php
<?php

require_once 'ChaChingServer.php';

set_time_limit(0);

function error($message)
{
	echo "cha-ching-server: ", $message;
	exit(1);
}

$verbosity = ChaChingServer::VERBOSITY_ERRORS;
$port = 2000;

$args = $_SERVER['argv'];
$num_args = count($args);
for ($i = 1; $i < $num_args; $i++) {
	switch ($args[$i]) {
	case '-v':
	case '--verbose':
		$allowed_levels = array(
			(string)ChaChingServer::VERBOSITY_NONE,
			(string)ChaChingServer::VERBOSITY_ERRORS,
			(string)ChaChingServer::VERBOSITY_MESSAGES,
			(string)ChaChingServer::VERBOSITY_CLIENT,
			(string)ChaChingServer::VERBOSITY_ALL,
		);

		if (isset($args[$i + 1]) &&
			in_array($args[$i + 1], $allowed_levels)) {
			$verbosity = $args[$i + 1];
			$i++;
		} else {
			error("--verbose expects verbosity level between 0 and 4\n");
		}
		break;

	case '-p':
	case '--port':
		if (isset($args[$i + 1]) && is_numeric($args[$i + 1])) {
			$port = (integer)$args[$i + 1];
			$i++;
		} else {
			error("--port expects port number\n");
		}
		break;
	default:
		error("unrecognized argument: " . $args[$i] . "\n");
		break;
	}
}

$server = new ChaChingServer($port);
$server->setVerbosity($verbosity);
$server->run();

?>
