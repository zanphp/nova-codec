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

    private static $useNewCodec;

    public function __construct()
    {
        if (self::$useNewCodec === null) {
            self::$useNewCodec = function_exists("nova_encode_new");
        }
    }

    public function encode()
    {
        if (self::$useNewCodec) {
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
                sys_error("nova encode fail, novaPdu=" . print_r($this, true));
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
                sys_error("nova encode fail, novaPdu=" . print_r($this, true));
                throw new CodecException();
            }
            return $outBuf;
        }
    }

    public function decode($bytesBuffer)
    {
        if (self::$useNewCodec) {
            $arr = nova_decode_new($bytesBuffer);
            if (!$arr) {
                sys_error("nova decode fail, rawHex=" . bin2hex($bytesBuffer));
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
                sys_error("nova decode fail, rawHex=" . bin2hex($bytesBuffer));
                throw new CodecException();
            }
        }
    }
}