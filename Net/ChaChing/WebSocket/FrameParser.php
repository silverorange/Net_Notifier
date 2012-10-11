<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * WebSocket frame parser class
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * This library is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation; either version 2.1 of the
 * License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @category  Net
 * @package   ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * Frame class
 */
require_once 'Net/ChaChing/WebSocket/Frame.php';

/**
 * Parseri for extracting WebSocket frames from a raw data-stream
 *
 * @category  Net
 * @package   ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_ChaChing_WebSocket_FrameParser
{
    // {{{ protected properties

    /**
     * The current frame being parsed
     *
     * @var Net_ChaChing_WebSocket_Frame
     */
    protected $currentFrame = null;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new frame parser
     */
    public function __construct()
    {
        $this->currentFrame = new Net_ChaChing_WebSocket_Frame();
    }

    // }}}
    // {{{ parse()

    /**
     * Parses WebSocket frames out of a raw data stream
     *
     * Any numbe of frames may be parsed. If the data chunk contains no
     * complete frames, no frames may be returned. The frame parses maintains
     * its state so subsequent data reads can be passed to this method to
     * complete partial frames.
     *
     * @param string $data the raw data.
     *
     * @return array an array of parsed {@link Net_ChaChing_WebSocket_Frame}
     *               objects.
     */
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

    // }}}
}

?>
