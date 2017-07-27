<?php

namespace ZanPHP\NovaCodec;


use ZanPHP\Contracts\Codec\PDU;
use ZanPHP\Exception\Codec\CodecException;

class NovaPDU implements PDU
{
    public $serviceName;
    public $methodName;
    public $ip;
    public $port;
    public $seqNo;
    public $attach;
    public $body;

    public function encode()
    {
        $r = nova_encode(
            $this->serviceName,
            $this->methodName,
            $this->ip,
            $this->port,
            $this->seqNo,
            $this->attach,
            $this->body,
            $outBuf
            );
        if ($r === false) {
            throw new CodecException();
        }
        return $outBuf;
    }

    public function decode($bytesBuffer)
    {
        $r = nova_decode(
            $bytesBuffer,
            $this->serviceName,
            $this->methodName,
            $this->ip,
            $this->port,
            $this->seqNo,
            $this->attach,
            $this->body
        );
        if ($r === false) {
            throw new CodecException();
        }
    }
}