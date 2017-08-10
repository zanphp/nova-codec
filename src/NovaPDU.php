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

    private $useNewCodec;

    public function __construct()
    {
        $this->useNewCodec = function_exists("nova_encode_new");
    }

    public function encode()
    {
        if ($this->useNewCodec) {
            $outBuf = nova_encode_new(
                $this->serviceName,
                $this->methodName,
                $this->ip,
                $this->port,
                $this->seqNo,
                $this->attach,
                $this->body
            );
            if (!$outBuf) {
                throw new CodecException();
            }
            return $outBuf;
        } else {
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
    }

    public function decode($bytesBuffer)
    {
        if ($this->useNewCodec) {
            $arr = nova_decode_new($bytesBuffer);
            if (!$arr) {
                throw new CodecException();
            }

            $this->serviceName = $arr["sName"];
            $this->methodName = $arr["mName"];
            $this->ip = $arr["ip"];
            $this->port = $arr["port"];
            $this->seqNo = $arr["seqNo"];
            $this->attach = $arr["attach"];
            $this->body = $arr["data"];
        } else {
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
}