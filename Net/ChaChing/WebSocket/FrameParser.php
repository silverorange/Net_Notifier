<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/ChaChing/WebSocket/Frame.php';

class Net_ChaChing_WebSocket_FrameParser implements SplObserver
{
    protected $currentFrame = null;

    public function __construct()
    {
        $this->currentFrame = new Net_ChaChing_WebSocket_Frame();
        $this->currentFrame->attach($this);
    }

    public function parse($data)
    {
        $frames = array();

        while ($data != '') {
            $data = $this->currentFrame->parse($data);
            if ($data != '') {
                $frames[] = $this->currentFrame;
                $this->currentFrame = new Net_ChaChing_WebSocket_Frame();
                $this->currentFrame->attach($this);
            }
        }

        $state = $this->currentFrame->getReadyState();
        if ($state === Net_ChaChing_WebSocket_Frame::STATE_DONE) {
            $frames[] = $this->currentFrame;
        }

        return $frames;
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
    }
}

?>
