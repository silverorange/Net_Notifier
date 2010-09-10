<?php

/**
 * Pinging client for the silverorange cha-ching server
 *
 * This client sends a message to the silverorange cha-ching server causing
 * various halarious sound effects.
 *
 * The cha-ching system works as follows:
 *
 * Server:
 *  Runs on a single system and receives requests. The server propegates
 *  cha-ching pings to connected playback clients.
 *
 * Playback Client:
 *  Runs on one or many machines. Clients connect to a server and play noises
 *  that are pushed from the server.
 *
 * Ping Client (this class):
 *  Notifies the cha-ching server to push a cha-ching to connected playback
 *  clients.
 *
 * Cha-ching pings are sent to the server in the following format:
 *
 * <pre>
 * Byte1      | Byte2      | Remaining Bytes
 * -----------+------------+----------------------------
 * Length MSB | Length LSB | Arbitrary string of Length characters identifying
 *            |            | the cha-ching sound to play.
 * </pre>
 *
 * @package   ChaChing
 * @copyright 2006 silverorange
 */
class ChaChingClient
{
	/**
	 * Connect socket timeout value
	 */
	const TIMEOUT = 5;

	/**
	 * The address of the cha-ching server
	 *
	 * @var string
	 */
	protected $address;

	/**
	 * The port of the cha-ching server
	 *
	 * By default, this is 2000.
	 *
	 * @var integer
	 */
	protected $port;

	/**
	 * The stream connection to the cha-ching server
	 *
	 * @var resource
	 */
	protected $stream;

	/**
	 * Whether or not this client is connected
	 *
	 * @var boolean
	 */
	protected $connected = false;

	/**
	 * Old error handler so some errors can be handed off instead of suppressed
	 *
	 * @var mixed
	 *
	 * @see ChachingClient::handleError()
	 */
	protected $old_error_handler = null;

	/**
	 * Creates a new cha-ching client
	 *
	 * @param string $address the address of the cha-ching server.
	 * @param integer $port an optional port number of the cha-ching server. If
	 *                       no port number is specified, 2000 is used.
	 */
	public function __construct($address, $port = 2000)
	{
		$this->address = $address;
		$this->port = $port;
	}

	/**
	 * Always disconnect if we're connected and the client is destroyed
	 */
	public function __destruct()
	{
		if ($this->connected)
			$this->disconnect();
	}

	/**
	 * Sends a cha-ching request
	 *
	 * @param string $id the id of the cha-ching sound to play.
	 */
	public function chaChing($id)
	{
		$this->old_error_handler =
			set_error_handler(array($this, 'handleError'));

		$this->connect();
		$this->send($id);
		$this->disconnect();

		restore_error_handler();
	}

	/**
	 * Handles PHP errors that may be raised by socket operations
	 *
	 * Since the cha-ching client is non-critical, we fail silently.
	 *
	 * @param integer $errno the level of the error raised.
	 * @param string $errstr the error message.
	 * @param string $errfile the filename that the error was raised in.
	 * @param integer $errline the line number the error was raised at.
	 *
	 * @return boolean false.
	 */
	public function handleError($errno, $errstr, $errfile, $errline)
	{
		// don't suppress user errors since they should cause the script to end
		if ($errno === E_USER_ERROR) {
			if ($this->old_error_handler !== null) {
				call_user_func($this->old_error_handler,
					$errno, $errstr, $errfile, $errline);
			} else {
				exit(1);
			}
		}

		return false;
	}

	/**
	 * Sends data to the cha-ching server
	 *
	 * Data is only sent if this client is connected.
	 *
	 * @param string $data the data to send.
	 */
	protected function send($data)
	{
		if ($this->connected) {
			$length_data = $this->getLengthData($data);
			fwrite($this->stream, $length_data.$data);
		}
	}

	/**
	 * Connects this client to the cha-ching server
	 */
	protected function connect()
	{
		$this->stream =
			fsockopen($this->address, $this->port, $errno, $errstr,
			self::TIMEOUT);

		if ($this->stream === false) {
			// handle error
		} else {
			stream_set_blocking($this->stream, false);
			$this->connected = true;
		}
	}

	/**
	 * Disconnects this client from the cha-ching server if it is connected
	 */
	protected function disconnect()
	{
		if ($this->connected) {
			fflush($this->stream);
			fclose($this->stream);
			$this->connected = false;
		}
	}

	/**
	 * Gets a two byte string containing encoding the integer character-length
	 * of the given data
	 *
	 * The high-order byte is displayed first (big endian).
	 *
	 * @param string $data the data from which to get the length data.
	 *
	 * @return string a two-byte big-endian binary string representing the
	 *                 integer character-length of the given data.
	 */
	protected function getLengthData($data)
	{
		// this gets character-length, not byte-length
		$byte_length = strlen($data);
		return pack('n', $byte_length);
	}
}

?>
