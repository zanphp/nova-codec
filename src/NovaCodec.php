<?php

namespace ZanPHP\NovaCodec;

use ZanPHP\Codec\Codec;
use ZanPHP\Codec\PDU;
use ZanPHP\Exception\Codec\CodecException;

class NovaCodec implements Codec
{

    /**
     * @param PDU $pdu
     * @return string
     * @throws \ZanPHP\Exception\Codec\CodecException
     */
    public function encode(PDU $pdu)
    {
        if ($pdu instanceof NovaPDU) {
            return $pdu->encode();
        } else {
            throw new CodecException("unexpected pdu");
        }
    }

    /**
     * @param string $bytesBuffer
     * @return NovaPDU
     * @throws \ZanPHP\Exception\Codec\CodecException
     */
    public function decode($bytesBuffer)
    {
        $pdu = new NovaPDU();
        $pdu->decode($bytesBuffer);
        return $pdu;
    }
}