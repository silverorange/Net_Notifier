<?php

class ChaChingWebSocketMessage implements SplSubject
{
	protected $observers = array();

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
