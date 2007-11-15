<?php

require_once 'ChaChingClientConnection.php';

/**
 * A server process for sending and receiving cha-ching notifications
 *
 * The cha-ching server interacts with two types of clients. The first type
 * of client connects to the server, sends a message and disconnects. The
 * second type of client never sends data and remains connected to the server.
 * When a client of type one sends a message to the server, the server relays
 * the message to all connected clients of type two.
 *
 * @package   ChaChing
 * @copyright 2006 silverorange
 */
class ChaChingServer
{
	// {{{ class constants

	/**
	 * How many connections the server will queue for processing.
	 */
	const CONNECTION_QUEUE_LENGTH = 20;

	/**
	 * How long the read buffer for client connections is.
	 *
	 * If this is too short, multiple read calls will be made on client
	 * connections to receive messages.
	 */
	const READ_BUFFER_LENGTH = 2048;

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
	// {{{ private properties

	/**
	 * The socket at which this server accepts connections
	 *
	 * @var resource
	 */
	private $socket;

	/**
	 * The port this server runs on
	 *
	 * By default this is 2000. See {@link ChaChingServer::__construct()} to
	 * use a different port.
	 *
	 * @var integer
	 */
	private $port;

	/**
	 * The level of verbosity to use
	 *
	 * @var integer 
	 *
	 * @see ChaChingServer::setVerbosity(),
	 *      ChaChingServer::VERBOSITY_NONE, ChaChingServer::VERBOSITY_ERRORS,
	 *      ChaChingServer::VERBOSITY_MESSAGES,
	 *      ChaChingServer::VERBOSITY_CLIENT, ChaChingServer::VERBOSITY_ALL
	 */
	private $verbosity = 1;

	/**
	 * Clients connected to this server
	 *
	 * This is an array of {@link ChaChingClientConnection} objects.
	 *
	 * @var array
	 */
	private $clients = array();

	/**
	 * Whether or not this server is running
	 *
	 * @var boolean
	 */
	private $connected = false;

	// }}}
	// {{{ public function __construct()

	/**
	 * Creates a new chaching server
	 *
	 * @param integer $port the port on which this server should listen for
	 *                       incomming connections.
	 *
	 * @see ChaChingServer::run()
	 */
	public function __construct($port = 2000)
	{
		$this->port = (integer)$port;
	}

	// }}}
	// {{{ public function __destruct()

	/**
	 * Tries to ensure that all sockets are closed when this server is no
	 * longer referenced
	 *
	 * In reality, the destructor is never called.
	 */
	public function __destruct()
	{
		if ($this->connected)
			$this->disconnect();
	}

	// }}}
	// {{{ public function setVerbosity()

	/**
	 * Sets the level of verbosity to use
	 *
	 * @param integer $verbosity the level of verbosity to use.
	 *
	 * @see ChaChingServer::VERBOSITY_NONE, ChaChingServer::VERBOSITY_ERRORS,
	 *      ChaChingServer::VERBOSITY_MESSAGES,
	 *      ChaChingServer::VERBOSITY_CLIENT, ChaChingServer::VERBOSITY_ALL
	 */
	public function setVerbosity($verbosity)
	{
		$this->verbosity = (integer)$verbosity;
	}

	// }}}
	// {{{ public function run()

	/**
	 * Runs this cha-ching server
	 *
	 * The cha-ching server receives client connections and sends and receives
	 * data to and from connected clients.
	 */
	public function run()
	{
		$this->connect();
		while (true) {

			$read = $this->getReadArray();
			if (socket_select($read, $write = null, $except = null, null) < 1)
				continue;

			// check for new connections
			if (in_array($this->socket, $read)) {
				if (($new_socket = socket_accept($this->socket)) < 0) {
					$this->output("socket_accept() failed: reason: " .
						socket_strerror(socket_last_error()) . "\n",
						self::VERBOSITY_ERRORS);

					exit(1);
				}

				$client = new ChaChingClientConnection($new_socket);
				$this->clients[] = $client;
				$this->output("client connected from " .
					$client->getIpAddress() . "\n", self::VERBOSITY_CLIENT);
			}

			foreach ($this->getReadClients($read) as $client) {
				if ($client->read()) {
					$this->disconnectClient($client);
					$message = $client->getMessage();

					if ($message === 'shutdown')
						break 2;

					if (strlen($message) > 0) {
						$this->output("received message: '" . $message . "'\n",
							self::VERBOSITY_MESSAGES);

						$this->dispatchEvent($message);
					}
				} else {
					$this->output("got a message chunk\n",
						self::VERBOSITY_CLIENT);
				}
			}
		}
		$this->disconnect();
	}

	// }}}
	// {{{ private function dispatchEvent()

	/**
	 * Notifies all connected clients of an event
	 *
	 * @param string $event_id the id of the event that occurred (and the
	 *                          notification that is sent).
	 */
	private function dispatchEvent($event_id)
	{
		foreach ($this->clients as $client) {
			$this->output("=> writing message '" . $event_id . "' to " .
				$client->getIpAddress() . " ... ", self::VERBOSITY_CLIENT);

			socket_write($client->getSocket(),
				pack('n', strlen($event_id)).$event_id);

			$this->output("done\n", self::VERBOSITY_CLIENT);
		}
	}

	// }}}
	// {{{ private function connect()

	/**
	 * Sets up this server's listen socket
	 */
	private function connect()
	{
		$this->output("creating socket ... ", self::VERBOSITY_ALL);
		if (false === ($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
			$this->output("socket_create() failed: reason: " .
				socket_strerror(socket_last_error()) . "\n",
				self::VERBOSITY_ERRORS);

			exit(1);
		}
		$this->output("done\n", self::VERBOSITY_ALL);

		$this->output("setting socket as reusable ... ", self::VERBOSITY_ALL);
		if (!socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1)) {
			$this->output("socket_set_option() failed: reason: " .
				socket_strerror(socket_last_error($sock)) . "\n",
				self::VERBOSITY_ERRORS);

			exit(1);
		} 
		$this->output("done\n", self::VERBOSITY_ALL);

		$this->output("binding socket on port " . $this->port . " ... ",
			self::VERBOSITY_ALL);

		if (!socket_bind($sock, 0, $this->port)) {
			$this->output("socket_bind() failed: reason: " .
				socket_strerror(socket_last_error($sock)) . "\n",
				self::VERBOSITY_ERRORS);

			exit(1);
		}
		$this->output("done\n", self::VERBOSITY_ALL);

		$this->output("setting socket to listen ... ", self::VERBOSITY_ALL);
		if (!socket_listen($sock, self::CONNECTION_QUEUE_LENGTH)) {
			$this->output("socket_listen() failed: reason: " .
				socket_strerror(socket_last_error($sock)) . "\n",
				self::VERBOSITY_ERRORS);

			exit(1);
		}
		$this->output("done\n", self::VERBOSITY_ALL);

		$this->socket = $sock;
		$this->connected = true;
	}

	// }}}
	// {{{ private function disconnect()

	/**
	 * Closes all client sockets and the server listen socket
	 */
	private function disconnect()
	{
		$this->output("closing sockets ... ", self::VERBOSITY_ALL);

		foreach ($this->clients as $client)
			socket_close($client->getSocket());

		$this->clients = array();
		socket_close($this->socket);

		$this->output("done\n", self::VERBOSITY_ALL);

		$this->connected = false;
	}

	// }}}
	// {{{ private function disconnectClient()

	/**
	 * Closes a client socket and removes the client from the list of clients
	 *
	 * @param ChaChingClientConnection $client the client to disconnect.
	 */
	private function disconnectClient(ChaChingClientConnection $client)
	{
		$this->output("disconnecting client from " . $client->getIpAddress() .
			" ... ", self::VERBOSITY_CLIENT);

		socket_close($client->getSocket());
		$key = array_search($client, $this->clients);
		unset($this->clients[$key]);

		$this->output("done\n", self::VERBOSITY_CLIENT);
	}

	// }}}
	// {{{ private function getReadClients()

	/**
	 * Gets an array of client connections whose sockets were read
	 *
	 * @param array $read an array of sockets that were read.
	 *
	 * @return array an array of ChaChingClientConnection objects having
	 *                sockets found in the given array of read sockets.
	 */
	private function &getReadClients(&$read)
	{
		$clients = array();

		foreach ($this->clients as $client)
			if (in_array($client->getSocket(), $read))
				$clients[] = $client;

		return $clients;
	}

	// }}}
	// {{{ private function getReadArray()

	/**
	 * Gets an array of sockets to check for reading
	 *
	 * @return array an aray of sockets to check for reading.
	 */
	private function &getReadArray()
	{
		$read_array = array();
		$read_array[] = $this->socket;
		foreach ($this->clients as $client)
			$read_array[] = $client->getSocket();

		return $read_array;
	}

	// }}}
	// {{{ private function output()

	/**
	 * Displays a debug string based on the verbosity level
	 *
	 * @param string $string the string to display.
	 * @param integer $verbosity an optional verbosity level to display at. By
	 *                            default, this is 1.
	 */
	private function output($string, $verbosity = 1)
	{
		if ($verbosity <= $this->verbosity)
			echo $string;
	}

	// }}}
}

?>
