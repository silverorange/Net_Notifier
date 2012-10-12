<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * WebSocket frame class
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
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

/**
 * A WebSocket frame
 *
 * See IETF 6455 Section 5 for a description of the WebSocket frame format.
 *
 * @category  Net
 * @package   ChaChing
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Net_ChaChing_WebSocket_Frame
{
    // {{{ class constants

    /**
     * WebSocket continuation frame type.
     */
    const TYPE_CONT = 0x00;

    /**
     * WebSocket UTF-8 text frame type.
     */
    const TYPE_TEXT = 0x01;

    /**
     * WebSocket binary data frame type.
     */
    const TYPE_BINARY = 0x02;

    /**
     * WebSocket undefined reserved non-control frame type.
     */
    const TYPE_FRAME1 = 0x03;

    /**
     * WebSocket undefined reserved non-control frame type.
     */
    const TYPE_FRAME2 = 0x04;

    /**
     * WebSocket undefined reserved non-control frame type.
     */
    const TYPE_FRAME3 = 0x05;

    /**
     * WebSocket undefined reserved non-control frame type.
     */
    const TYPE_FRAME4 = 0x06;

    /**
     * WebSocket undefined reserved non-control frame type.
     */
    const TYPE_FRAME5 = 0x07;

    /**
     * WebSocket connection close control frame type.
     */
    const TYPE_CLOSE = 0x08;

    /**
     * WebSocket ping control frame type.
     */
    const TYPE_PING = 0x09;

    /**
     * WebSocket pong control frame type.
     */
    const TYPE_PONG = 0x0a;

    /**
     * WebSocket undefined reserved control frame type.
     */
    const TYPE_CONTROL1 = 0x0b;

    /**
     * WebSocket undefined reserved control frame type.
     */
    const TYPE_CONTROL2 = 0x0c;

    /**
     * WebSocket undefined reserved control frame type.
     */
    const TYPE_CONTROL3 = 0x0d;

    /**
     * WebSocket undefined reserved control frame type.
     */
    const TYPE_CONTROL4 = 0x0e;

    /**
     * WebSocket undefined reserved control frame type.
     */
    const TYPE_CONTROL5 = 0x0f;

    /**
     * Frame parse state for unsent frames.
     *
     * Used for sending frames.
     */
    const STATE_UNSENT = 0;

    /**
     * Frame parse state for frame that has not finished parsing header.
     */
    const STATE_OPENED = 1;

    /**
     * Frame parse state for frame with complete header but incomplete payload.
     */
    const STATE_HEADERS_RECEIVED = 2;

    /**
     * Frame parse state for completely parsed frames (headers and payload).
     */
    const STATE_DONE = 4;

    // }}}

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

    public function __construct(
        $data = '',
        $opcode = self::TYPE_TEXT,
        $isMasked = false,
        $fin = true
    ) {
        if ($data !='') {
            $this->unmaskedData = $data;
            $this->isMasked = $isMasked;
            if ($this->isMasked) {
                $this->mask = $this->generateMask();
            }
            $this->opcode = $opcode;
            $this->fin = $fin;
            $length = mb_strlen($data);
            if ($length < 0x7e) {
                $this->length = $length;
            } elseif ($length <= 0xffff) {
                $this->length = 0x7e;
                $this->length16 = $length;
                $this->length64 = 0;
            } else {
                $this->length = 0x7f;
                $this->length16 = 0;
                $this->length64 = $length;
            }
        }
    }

    // {{{ __toString()

    /**
     * Gets a binary string of this frame ready to send over the wire
     *
     * The string is formatted according to IETF RFC 6455.
     *
     * @return string a binary string of this frame ready to send over
     *                the wire.
     */
    public function __toString()
    {
        return $this->getHeader().$this->getData();
    }

    // }}}

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

        if ($this->cursor == $this->getLength() + $this->headerLength) {
            $this->state = self::STATE_DONE;
        }

        return $data;
    }

    // {{{ isFinal()

    /**
     * Gets whether or not this frame is the final frame in a multi-frame
     * message
     *
     * @return boolean true if this frame is the final frame in a multi-frame
     *                 message. Otherwise false.
     */
    public function isFinal()
    {
        return $this->fin;
    }

    // }}}
    // {{{ isMasked()

    /**
     * Gets whether or not this frame is masked
     *
     * @return boolean true if this frame is masked. Otherwise false.
     */
    public function isMasked()
    {
        return $this->isMasked;
    }

    // }}}

    public function getRawData()
    {
        return $this->data;
    }

    public function getUnmaskedData()
    {
        return $this->unmaskedData;
    }

    public function getOpcode()
    {
        return $this->opcode;
    }

    // {{{ getLength()

    /**
     * Gets the length of this frame's payload data in bytes
     *
     * @return integer the length of this frame's payload data in bytes.
     *
     * @todo Support long lengths (length values larger than a 32-bit signed
     *       integer.)
     */
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

    // }}}

    public function getState()
    {
        return $this->state;
    }

    // {{{ getHeader()

    /**
     * Gets this frame's header as a binary string
     *
     * @return string this frame's header as a binary string.
     *
     * @todo Support long lengths (length values larger than a 32-bit signed
     *       integer.)
     */
    protected function getHeader()
    {
        $header = '';

        $fin  = $this->fin  ? 0x80 : 0x00;

        $rsv1 = $this->rsv1 ? 0x40 : 0x00;
        $rsv2 = $this->rsv2 ? 0x20 : 0x00;
        $rsv3 = $this->rsv3 ? 0x10 : 0x00;

        $byte1 = $fin | $rsv1 | $rsv2 | $rsv3 | $this->opcode;

        $mask = $this->isMasked ? 0x80 : 0x00;

        $byte2 = $mask | $this->length;

        $header .= pack('CC', $byte1, $byte2);

        if ($this->length === 0x7e) {
            $header .= pack('s', $this->length16);
        }

        if ($this->length === 0x7f) {
            // TODO
        }

        if ($this->isMasked) {
            $header .= $this->mask;
        }

        return $header;
    }

    // }}}
    // {{{ getData()

    /**
     * Gets the data portion of this frame as a binary string
     *
     * If this frame is masked, the displayed data is masked.
     *
     * @return string the data portion of this frame as a binary string.
     */
    protected function getData()
    {
        $data = $this->unmaskedData;

        if ($this->isMasked) {
            $data = $this->mask($data);
        }

        return $data;
    }

    // }}}

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
            $this->unmaskedData .= $this->mask($data);
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

    // {{{ mask()

    /**
     * Masks data according the the algorithm described in RFC 6455 Section 5.3
     *
     * @param string $data a binary string containing the data to mask.
     *
     * @return string a binary string containing the the masked data.
     */
    protected function mask($data)
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

    // }}}
    // {{{ generateMask()

    /**
     * Gets a masking key for masked WebSocket frames
     *
     * @return string a binary stirng containing a 32-bit random value.
     */
    protected function generateMask()
    {
        // get two random 16-bit integers
        $short1 = mt_rand(0, 65536);
        $short2 = mt_rand(0, 65536);

        // pack them in a string
        return pack('ss', $short1, $short2);
    }

    // }}}
    // {{{ getChar()

    /**
     * Gets an integer representing the specified 8-bit unsigned char in a
     * binary string
     *
     * This is used for data masking and parsing values from WebSocket frames.
     * Since PHP has no unsigned char type the integer type (at least 32-bit)
     * is used.
     *
     * @param string  $data the string.
     * @param integer $char the character position for which to get the integer
     *                      value.
     *
     * @return integer the integer representing the specified character.
     */
    protected function getChar($data, $char)
    {
        return reset(unpack('C', mb_substr($data, $char, 1, '8bit')));
    }

    // }}}
    // {{{ getShort()

    /**
     * Gets an integer representing the 16-bit unsigned short of the first
     * two bytes of a binary string
     *
     * This is used for parsing values from WebSocket frames. Since PHP has no
     * unsigned short type the integer type (at least 32-bit) is used.
     *
     * @param string $data the binary string.
     *
     * @return integer the integer representing the 16-bit unsigned short of
     *                 first two bytes of the binary string.
     */
    protected function getShort($data)
    {
        return reset(unpack('n', mb_substr($data, 0, 2, '8bit')));
    }

    // }}}
    // {{{ getLong()

    /**
     * Gets a 64-bit unsigned integer value from the first 4 bytes of the
     * specified binary string
     *
     * This is used for parsing values from WebSocket frames. Because PHP has
     * no native support for 64-bit unsigned integers, the
     * {@link http://www.php.net/bcmath} module is used.
     *
     * @param string $data the binary string.
     *
     * @return string a string representing the decimal value of the 64-bit
     *                unsigned integer.
     *
     * @todo This method is currently unimplemented.
     */
    protected function getLong($data)
    {
        return '0';
    }

    // }}}
}

?>
