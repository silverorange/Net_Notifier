<?php

require_once 'ChaChing/ChaChingWebSocketFrame.php';

class ChaChingWebSocketMessage implements SplSubject, SplObserver
{
	const STATE_UNSENT           = 0;
	const STATE_OPENED           = 1;
	const STATE_HEADERS_RECEIVED = 2;
	const STATE_LOADING          = 3;
	const STATE_DONE             = 4;

	protected $observers = array();

	protected $data = '';

	protected $state = self::STATE_UNSENT;

	protected $currentFrame = null;

	public function __construct()
	{
		$this->currentFrame = new ChaChingWebSocketFrame();
		$this->currentFrame->attach($this);
	}

	public function parse($data)
	{
		if ($this->state === self::STATE_UNSENT) {
			$this->state = self::STATE_OPENED;
		}

		while ($data != '') {
			$data = $this->currentFrame->parse($data);
			if ($data != '') {
				$this->currentFrame = new ChaChingWebSocketFrame();
				$this->currentFrame->attach($this);
			}
		}
	}

	public function getReadyState()
	{
		return $this->state;
	}

	public function getData()
	{
		return $this->data;
	}

	public function update(SplSubject $frame)
	{
		if ($frame->getReadyState() === ChaChingWebSocketFrame::STATE_DONE) {
			$this->data .= $frame->getUnmaskedData();
			if ($frame->isFinal()) {
				$this->state = self::STATE_DONE;
				$this->notify();
			}
		}
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
}

?>
