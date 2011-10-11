<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'Net/ChaChing/WebSocket/Frame.php';

class Net_ChaChing_WebSocket_FrameParser
{
    protected $currentFrame = null;

    public function __construct()
    {
        $this->currentFrame = new Net_ChaChing_WebSocket_Frame();
    }

    public function parse($data)
    {
        $frames = array();

        while ($data != '') {
            $data = $this->currentFrame->parse($data);
            if ($data != '') {
                $frames[] = $this->currentFrame;
                $this->currentFrame = new Net_ChaChing_WebSocket_Frame();
            }
        }

        // if we received exactly enough data, the last frame is also complete
        $state = $this->currentFrame->getState();
        if ($state === Net_ChaChing_WebSocket_Frame::STATE_DONE) {
            $frames[] = $this->currentFrame;
        }

        return $frames;
    }
}

?>
