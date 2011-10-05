<?php

class ChaChingWebsocketFrame implements SplSubject
{
	const TYPE_CONT      = 0x00;
	const TYPE_TEXT      = 0x01;
	const TYPE_BINARY    = 0x02;
	const TYPE_CONTROL1  = 0x03;
	const TYPE_CONTROL2  = 0x04;
	const TYPE_CONTROL3  = 0x05;
	const TYPE_CONTROL4  = 0x06;
	const TYPE_CONTROL5  = 0x07;
	const TYPE_CLOSE     = 0x08;
	const TYPE_PING      = 0x09;
	const TYPE_PONG      = 0x0a;
	const TYPE_CONTROL6  = 0x0b;
	const TYPE_CONTROL7  = 0x0c;
	const TYPE_CONTROL8  = 0x0d;
	const TYPE_CONTROL9  = 0x0e;
	const TYPE_CONTROL10 = 0x0f;

	const STATE_UNSENT           = 0;
	const STATE_OPENED           = 1;
	const STATE_HEADERS_RECEIVED = 2;
	const STATE_LOADING          = 3;
	const STATE_DONE             = 4;

	protected $opcode = 0;

	protected $isMasked = false;

	protected $mask = '';

	protected $fin = false;

	protected $rsv1 = 0;

	protected $rsv2 = 0;

	protected $rsv3 = 0;

	protected $length = 0;

	protected $length16 = 0;

	protected $length64 = 0;

	protected $lengthData = '';

	protected $data = '';

	protected $unmaskedData = '';

	protected $cursor = 0;

	protected $isHeaderComplete = false;

	protected $observers = array();

	protected $headerLength = 2;

	protected $state = self::STATE_UNSENT;

	public function __construct($data = '', $isMasked = false, $fin = true)
	{
		if ($data !='') {
			$this->unmaskedData = $data;
			$this->isMasked = $isMasked;
			$this->fin = $fin;
			// TODO
		}
	}

	public function __toString()
	{
		// TODO
	}

	public function parse($data)
	{
		if ($this->state === self::STATE_UNSENT) {
			$this->state = self::STATE_OPENED;
		}

		if ($this->isHeaderComplete) {
			$data = $this->parseData($data);
		} else {
			$data = $this->parseHeader($data);
		}

		// notify if we've received all data for this frame
		if ($this->cursor == $this->getLength() + $this->headerLength) {
			$this->state = self::STATE_DONE;
			$this->notify();
		}

		return $data;
	}

	public function isFinal()
	{
		return $this->fin;
	}

	public function isMasked()
	{
		return $this->isMasked;
	}

	public function getRawData()
	{
		return $this->data;
	}

	public function getUnmaskedData()
	{
		return $this->unmaskedData;
	}

	public function getOpCode()
	{
		return $this->opcode;
	}

	public function getLength()
	{
		$length = $this->length64;

		if ($length == 0) {
			$length = $this->length16;
		}

		if ($length == 0) {
			$length = $this->length;
		}

		return $length;
	}

	public function attach(SplObserver $observer)
	{
		if (!in_array($observer, $this->observers)) {
			$this->observers[] = $observer;
		}
	}

	public function detach(SplObserver $observer)
	{
		if (!in_array($observer, $this->observers)) {
			$this->observers[] = $observer;
		}
	}

	public function notify()
	{
		foreach ($this->observers as $observer) {
			$observer->update($this);
		}
	}

	public function getReadyState()
	{
		return $this->state;
	}

	protected function parseData($data)
	{
		$length = mb_strlen($data, '8bit');

		if ($this->cursor + $length > $this->getLength() + $this->headerLength) {
			$dataLength = $this->getLength()
				- $this->cursor
				+ $this->headerLength;
		} else {
			$dataLength = $length;
		}

		$data     = mb_substr($data, 0, $dataLength, '8bit');
		$leftover = mb_substr($data, $length, $length - $dataLength, '8bit');

		$this->data .= $data;
		if ($this->isMasked()) {
			$this->unmaskedData .= $this->unmask($data);
		} else {
			$this->unmaskedData .= $data;
		}
		$this->cursor += mb_strlen($data, '8bit');

		return $leftover;
	}

	protected function parseHeader($data)
	{
		$leftover = '';

		$length = mb_strlen($data, '8bit');

		for ($i = 0; $i < $length; $i++) {
			switch ($this->cursor) {
			case 0x00:
				$char = $this->getChar($data, $i);

				$this->fin    = (($char & 0x80) === 0x80);
				$this->rsv1   = (($char & 0x40) === 0x40);
				$this->rsv2   = (($char & 0x20) === 0x20);
				$this->rsv3   = (($char & 0x10) === 0x10);
				$this->opcode = $char & 0x0f;

				break;

			case 0x01:
				$char = $this->getChar($data, $i);

				$this->isMasked = (($char & 0x80) === 0x80);
				$this->length    = $char & 0x7f;

				if ($this->isMasked) {
					$this->headerLength += 4;
				}

				// extended length
				if ($this->length === 0x7e) {
					$this->headerLength += 2;
				} elseif ($this->length === 0x7f) {
					$this->headerLength += 6;
				}

				break;

			case 0x02:
			case 0x03:
				if ($this->length === 0x7e) {
					$this->lengthData .= mb_substr($data, $i, 1, '8bit');
					if ($this->cursor === 0x03) {
						$this->length16 = $this->getShort($this->lengthData);
					}
				} elseif ($this->length === 0x7f) {
					$this->lengthData .= mb_substr($data, $i, 1, '8bit');
				} else {
					$this->mask .= mb_substr($data, $i, 1, '8bit');
				}
				break;

			case 0x04:
			case 0x05:
			case 0x06:
			case 0x07:
				if ($this->length === 0x7f) {
					$this->lengthData .= mb_substr($data, $i, 1, '8bit');
				} else {
					$this->mask .= mb_substr($data, $i, 1, '8bit');
				}
				break;

			case 0x08:
			case 0x09:
				$this->lengthData .= mb_substr($data, $i, 1, '8bit');
				if ($this->cursor === 0x09) {
					$this->length64 = $this->getLong($this->lengthData);
				}
				break;

			case 0x0a:
			case 0x0b:
				$this->mask .= mb_substr($data, $i, 1, '8bit');
				break;

			default:
				throw new Exception('Parsed frame header length incorrectly.');
			}

			$this->cursor++;

			// check if finished parsing header
			if ($this->cursor === $this->headerLength) {
				$this->isHeaderComplete = true;
				$this->state = self::STATE_HEADERS_RECEIVED;
				$this->notify();
				break;
			}
		}

		// we have more data
		if ($i < $length) {
			$data = mb_substr($data, $i + 1, $length - $i - 1, '8bit');
			$leftover = $this->parseData($data);
		}

		return $leftover;
	}

	protected function unmask($data)
	{
		$out    = '';
		$length = mb_strlen($data, '8bit');
		$j      = ($this->cursor - $this->headerLength) % 4;

		for ($i = 0; $i < $length; $i++) {
			$data_octet = $this->getChar($data, $i);
			$mask_octet = $this->getChar($this->mask, $j);
			$out .= pack('C', ($data_octet ^ $mask_octet));
			$j++;
			if ($j >= 4) {
				$j = 0;
			}
		}

		return $out;
	}

	protected function getChar($data, $char)
	{
		return reset(unpack('C', mb_substr($data, $char, 1, '8bit')));
	}

	protected function getShort($data)
	{
		return reset(unpack('n', mb_substr($data, 0, 2, '8bit')));
	}

	protected function getLong($data)
	{
		// TODO
		return 0;
	}
}
