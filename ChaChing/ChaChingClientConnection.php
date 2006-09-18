<?php

/**
 * A client connection to the cha-ching server
 *
 * This class is intended to be used internally by the {@link ChaChingServer}
 * class.
 *
 * @package   ChaChing
 * @copyright 2006 silverorange
 */
class ChaChingClientConnection
{
	// {{{ private properties

	/**
	 * The socket this connection uses to communicate with the server
	 *
	 * @var resource
	 */
	private $socket;

	/**
	 * The IP address ths connection originated from
	 *
	 * @var string
	 */
	private $ip_address;

	/**
	 * A buffer containing data received by the server from this connection
	 *
	 * @var string
	 *
	 * @see ChaChingClientConnection::getMessage()
	 */
	private $buffer = '';

	/**
	 * The size of the data payload of this connection in characters
	 *
	 * If -1 this means the payload size is not yet know.
	 *
	 * @var integer
	 */
	private $size = -1;

	// }}}
	// {{{ public function __construct()

	/**
	 * Creates a new client connection object
	 *
	 * @param resource $socket the socket this connection uses to communicate
	 *                          with the server.
	 */
	public function __construct($socket)
	{
		$this->socket = $socket;
		socket_getpeername($socket, $this->ip_address);
	}

	// }}}
	// {{{ public function getSocket()

	/**
	 * Gets the socket this connection uses to communicate with the server
	 *
	 * @return resource the socket this connection uses to communicate with the
	 *                   server.
	 */
	public function getSocket()
	{
		return $this->socket;
	}

	// }}}
	// {{{ public function read()

	/**
	 * Reads data from this connection
	 *
	 * @return boolean true if this connection's data payload has finished
	 *                  being read and false if this connection still has data
	 *                  to send.
	 */
	public function read()
	{
		if (false === ($buffer = socket_read($this->socket,
			ChaChingServer::READ_BUFFER_LENGTH, PHP_BINARY_READ))) {
			echo "socket_read() failed: reason: ",
				socket_strerror(socket_last_error()), "\n";

			exit(1);
		}

		$this->buffer .= $buffer;

		if ($this->size == -1) {
			$multi_byte = (function_exists('mb_strlen') &&
				(ini_get('mbstring.func_overload') & 2) == 2 &&
				mb_internal_encoding() == 'UTF-8');

			$byte_length = ($multi_byte) ?
				mb_strlen($this->buffer, 'latin1') : strlen($this->buffer);

			if ($byte_length >= 2) {
				$binary_data = ($multi_byte) ?
					mb_substr($this->buffer, 0, 2, 'latin1') :
					substr($this->buffer, 0, 2);

				$message_data = ($multi_byte) ?
					mb_substr($this->buffer, 2, $byte_length, 'latin1') :
					substr($this->buffer, 2);

				$data = unpack('n', $binary_data);
				$this->size = $data[1];
				$this->buffer = $message_data;
			}
		}

		return (strlen($this->buffer) == $this->size) ||
			(strlen($buffer) == 0);
	}

	// }}}
	// {{{ public function getMessage()

	/**
	 * Gets the message received by the server from this connection
	 *
	 * @return string the message received by the server from this connection.
	 *                 If the full message has not yet been received, false is
	 *                 returned.
	 */
	public function getMessage()
	{
		$message = false;
		if (strlen($this->buffer) == $this->size)
			$message = $this->buffer;

		return $message;
	}

	// }}}
	// {{{ public function getIpAddress()

	/**
	 * Gets the IP address of this connection
	 *
	 * @return string the IP address of this connection.
	 */
	public function getIpAddress()
	{
		return $this->ip_address;
	}

	// }}}
}

?>
