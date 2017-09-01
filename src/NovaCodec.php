<?php

namespace ZanPHP\NovaCodec;

use ZanPHP\Contracts\Codec\Codec;
use ZanPHP\Contracts\Codec\PDU;
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
     * @param mixed $ctx
     * @return NovaPDU
     * @throws \ZanPHP\Exception\Codec\CodecException
     */
    public function decode($bytesBuffer, $ctx = null)
    {
        $pdu = new NovaPDU();
        $pdu->decode($bytesBuffer);
        return $pdu;
    }
}