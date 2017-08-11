<?php


namespace
{

    use _\BinaryStream;

    /**
     * PHP_FUNCTION(nova_encode);
     * PHP_FUNCTION(nova_decode);
     * PHP_FUNCTION(is_nova_packet);
     * PHP_FUNCTION(nova_get_sequence);
     * PHP_FUNCTION(nova_get_time);
     * PHP_FUNCTION(nova_get_ip);
     */

    if (!function_exists("nova_encode")) {
        // define("NOVA_HEADER_COMMON_LEN", 37);
        // define("NOVA_MAGIC", 0xdabc);


        function nova_decode_new($buf)
        {
            $len = strlen($buf);
            if ($len <= 37) {
                return false;
            }

            $bs = new BinaryStream();
            $bs->write($buf);
            $msgSz = $bs->readInt32BE();
            if ($msgSz <= 37) {
                return false;
            }

            $magic = $bs->readUInt16BE();
            if ($magic !== 0xdabc) {
                return false;
            }

            $hdrSz = $bs->readInt16BE();
            if ($hdrSz > $msgSz) {
                return false;
            }

            $ver = $bs->readUInt8();
            $ip = $bs->readUInt32BE();
            $port = $bs->readUInt32BE();

            $servLen = $bs->readInt32BE();
            $serv = $bs->read($servLen);

            $mLen = $bs->readInt32BE();
            $method = $bs->read($mLen);


            $seq = $bs->readUInt64BE();

            $attLen = $bs->readInt32BE();
            $attach = $bs->read($attLen);

            $thriftBin = $bs->readFull();

            return [
                "sName" => $serv,
                "mName" => $method,
                "ip" => $ip,
                "port" => $port,
                "seqNo" => $seq,
                "attach" => $attach,
                "data" => $thriftBin
            ];
        }

        function nova_encode_new($serv, $method, $ip, $port, $seq, $attach, $thriftBin)
        {
            $hdrLen = 37 + strlen($serv) + strlen($method) + strlen($attach);
            if ($hdrLen > 0x7fff) {
                return false;
            }

            $bs = new BinaryStream();
            $bs->writeInt32BE($hdrLen + strlen($thriftBin));
            $bs->writeInt16BE(0xdabc);
            $bs->writeInt16BE($hdrLen);
            $bs->writeUInt8(1); // ver
            $bs->writeUInt32BE($ip);
            $bs->writeUInt32BE($port);
            $bs->writeInt32BE(strlen($serv));
            $bs->write($serv);
            $bs->writeInt32BE(strlen($method));
            $bs->write($method);
            $bs->writeUInt64BE($seq);
            $bs->writeInt32BE(strlen($attach));
            $bs->write($attach);
            $bs->write($thriftBin);
            return $bs->readFull();
        }


        /**
         * nova协议解包
         *
         * @since 2.0.0
         *
         * @param string $buf 二进制字符串
         * @param string &$serv 服务名
         * @param string &$method 方法名
         * @param string &$ip
         * @param int &$port
         * @param int &$seq
         * @param string &$attach 附加字段 通常为json编码字符串
         * @param string &$thriftBin nova body
         * @return bool
         */
        function nova_decode($buf, &$serv, &$method, &$ip, &$port, &$seq, &$attach, &$thriftBin)
        {
            $pkt = nova_decode_new($buf);
            if ($pkt === false) {
                return false;
            }

            $serv = $pkt["sName"];
            $method = $pkt["mName"];
            $ip = $pkt["ip"];
            $port = $pkt["port"];
            $seq = $pkt["seqNo"];
            $attach = $pkt["attach"];
            $thriftBin = $pkt["data"];
            return true;
        }

        /**
         * nova协议解包
         *
         * @since 2.0.0
         *
         * @param string $serv
         * @param string $method
         * @param int $ip
         * @param int $port
         * @param int $seq
         * @param string $attach 附加字段 通常为json编码字符串
         * @param string $thriftBin 协议body
         * @param string &$buf 打包结果
         * @return bool
         */
        function nova_encode($serv, $method, $ip, $port, $seq, $attach, $thriftBin, &$buf)
        {
            $bin = nova_encode_new($serv, $method, $ip, $port, $seq, $attach, $thriftBin);
            if ($bin === false) {
                return false;
            }
            $buf = $bin;
            return true;
        }


        function is_nova_packet($buf)
        {
            $len = strlen($buf);
            if ($len < 37) {
                return false;
            }

            $bs = new BinaryStream();
            $bs->write($buf);
            $bs->read(4);
            $magic = $bs->readInt16BE();
            return $magic === 0xdabc;
        }

        function nova_get_sequence()
        {
            static $seq = 0;
            return ++$seq;
        }

        function nova_get_time()
        {
            return time();
        }

        function nova_get_ip()
        {
            $ipList = \swoole_get_local_ip();
            // FIX remove lookback ip
            return $ipList[array_rand($ipList)];
        }
    }
}

namespace _
{

    class MemoryBuffer
    {
        const kCheapPrepend = 8;
        const kInitialSize = 1024;

        protected $buffer;

        protected $readerIndex;

        protected $writerIndex;

        protected $evMap;

        public function __construct($size = self::kInitialSize)
        {
            $this->buffer = new \swoole_buffer($size + static::kCheapPrepend);
            $this->readerIndex = static::kCheapPrepend;
            $this->writerIndex = static::kCheapPrepend;
            $this->evMap = [];
        }

        public function readableBytes()
        {
            return $this->writerIndex - $this->readerIndex;
        }

        public function writableBytes()
        {
            return $this->buffer->capacity - $this->writerIndex;
        }

        public function prependableBytes()
        {
            return $this->readerIndex;
        }

        public function capacity()
        {
            return $this->buffer->capacity;
        }

        public function get($len)
        {
            if ($len <= 0) {
                return "";
            }

            $len = min($len, $this->readableBytes());
            return $this->rawRead($this->readerIndex, $len);
        }

        public function read($len)
        {
            if ($len <= 0) {
                return "";
            }

            $len = min($len, $this->readableBytes());
            $read = $this->rawRead($this->readerIndex, $len);
            $this->readerIndex += $len;
            if ($this->readerIndex === $this->writerIndex) {
                $this->reset();
            }
            return $read;
        }

        public function readFull()
        {
            return $this->read($this->readableBytes());
        }

        public function write($bytes)
        {
            if ($bytes === "") {
                return false;
            }

            $len = strlen($bytes);

            if ($len <= $this->writableBytes()) {
                $this->rawWrite($this->writerIndex, $bytes);
                $this->writerIndex += $len;
                $this->trigger("write", $bytes);
                return true;
            }

            // expand
            if ($len > ($this->prependableBytes() + $this->writableBytes() - static::kCheapPrepend)) {
                $this->expand(($this->writerIndex + $len) * 2);
            }

            // copy-move 内部腾挪
            if ($this->readerIndex !== static::kCheapPrepend) {
                $this->rawWrite(static::kCheapPrepend, $this->rawRead($this->readerIndex, $this->writerIndex - $this->readerIndex));
                $this->writerIndex = $this->writerIndex - $this->readerIndex + static::kCheapPrepend;
                $this->readerIndex = static::kCheapPrepend;
            }

            $this->rawWrite($this->writerIndex, $bytes);
            $this->writerIndex += $len;
            $this->trigger("write", $bytes);
            return true;
        }

        public function prepend($bytes)
        {
            if ($bytes === "") {
                return false;
            }

            $size = $this->prependableBytes();
            $len = strlen($bytes);
            if ($len > $size) {
                throw new \InvalidArgumentException("no space to prepend [len=$len, size=$size]");
            }
            $this->rawWrite($size - $len, $bytes);
            $this->readerIndex -= $len;
            return true;
        }

        public function peek($offset, $len = 1)
        {
            $offset = $this->readerIndex + max(0, $offset);
            $len = min($len, $this->writerIndex - $offset);
            return $this->rawRead($offset, $len);
        }

        public function reset()
        {
            $this->readerIndex = static::kCheapPrepend;
            $this->writerIndex = static::kCheapPrepend;
        }

        public function on($ev, callable $cb)
        {
            $this->evMap[$ev] = $cb;
        }

        protected function trigger($ev, ...$args)
        {
            if (isset($this->evMap[$ev])) {
                $cb = $this->evMap[$ev];
                $cb(...$args);
            }
        }

        private function rawRead($offset, $len)
        {
            if ($len === 0) {
                return "";
            }
            if ($offset < 0 || $offset + $len > $this->buffer->capacity) {
                throw new \InvalidArgumentException(__METHOD__ . ": offset=$offset, len=$len, capacity={$this->buffer->capacity}");
            }
            return $this->buffer->read($offset, $len);
        }

        private function rawWrite($offset, $bytes)
        {
            if ($bytes === "") {
                return 0;
            }
            $len = strlen($bytes);
            if ($offset < 0 || $offset + $len > $this->buffer->capacity) {
                throw new \InvalidArgumentException(__METHOD__ . ": offset=$offset, len=$len, capacity={$this->buffer->capacity}");
            }
            return $this->buffer->write($offset, $bytes);
        }

        private function expand($size)
        {
            if ($size <= $this->buffer->capacity) {
                throw new \InvalidArgumentException(__METHOD__ . ": size=$size, capacity={$this->buffer->capacity}");
            }
            return $this->buffer->expand($size);
        }

        public function __toString()
        {
            return $this->rawRead($this->readerIndex, $this->writerIndex - $this->readerIndex);
        }
    }

    class BinaryStream extends MemoryBuffer
    {
        public function prependInt16BE($i)
        {
            return $this->prepend(pack('n', $i));
        }

        public function prependInt32BE($i)
        {
            return $this->prepend(pack('N', $i));
        }

        public function writeUInt8($i)
        {
            return $this->write(pack('C', $i));
        }

        public function writeInt16BE($i)
        {
            return $this->write(pack('n', $i));
        }

        public function writeInt16LE($i)
        {
            return $this->write(pack('v', $i));
        }

        public function writeUInt16BE($i)
        {
            return $this->write(pack('n', $i));
        }

        public function writeUInt16LE($i)
        {
            return $this->write(pack('v', $i));
        }

        public function writeUInt32BE($i)
        {
            return $this->write(pack('N', $i));
        }

        public function writeUInt32LE($i)
        {
            return $this->write(pack('V', $i));
        }

        public function writeUInt64BE($uint64Str)
        {
            $low = bcmod($uint64Str, "4294967296");
            $hi = bcdiv($uint64Str, "4294967296", 0);
            return $this->write(pack("NN", $hi, $low));
        }

        public function writeUInt64LE($uint64Str)
        {
            $low = bcmod($uint64Str, "4294967296");
            $hi = bcdiv($uint64Str, "4294967296", 0);
            return $this->write(pack('VV', $low, $hi));
        }

        public function writeInt32BE($i)
        {
            return $this->write(pack('N', $i));
        }

        public function writeInt32LE($i)
        {
            return $this->write(pack('V', $i));
        }

        public function writeFloat($f)
        {
            return $this->write(pack('f', $f));
        }

        public function writeDouble($d)
        {
            return $this->write(pack('d', $d));
        }

        public function readUInt8()
        {
            $ret = unpack("Cr", $this->read(1));
            return $ret == false ? null : $ret["r"];
        }

        public function readInt16BE()
        {
            $ret = unpack("nn", $this->read(2));
            $v = $ret["n"];
            if ($v >= 0x8000) {
                $v -= 0x10000;
            }
            return $v;
        }

        public function readInt16LE()
        {

        }

        public function readUInt16BE()
        {
            $ret = unpack("nr", $this->read(2));
            return $ret === false ? null : $ret["r"];
        }

        public function readUInt16LE()
        {
            $ret = unpack("vr", $this->read(2));
            return $ret === false ? null : $ret["r"];
        }

        public function readUInt32BE()
        {
            $ret = unpack("nhi/nlo", $this->read(4));
            return $ret === false ? null : (($ret["hi"] << 16) | $ret["lo"]);
        }

        public function readUInt32LE()
        {
            $ret = unpack("vlo/vhi", $this->read(4));
            return $ret === false ? null : (($ret["hi"] << 16) | $ret["lo"]);
        }

        public function readUInt64BE()
        {
            $param = unpack("Nhi/Nlow", $this->read(8));
            return bcadd(bcmul($param["hi"], "4294967296", 0), $param["low"]);
        }

        public function readUInt64LE()
        {
            $param = unpack("Vlow/Vhi", $this->read(8));
            return bcadd(bcmul($param["hi"], "4294967296", 0), $param["low"]);
        }

        public function readInt32BE()
        {
            $ret = unpack("Nr", $this->read(4));
            return $ret === false ? null : $ret["r"];
        }

        public function readInt32LE()
        {
            $ret = unpack("Vr", $this->read(4));
            return $ret === false ? null : $ret["r"];
        }

        public function readFloat()
        {
            $ret = unpack("fr", $this->read(4));
            return $ret === false ? null : $ret["r"];
        }

        public function readDouble()
        {
            $ret = unpack("dr", $this->read(8));
            return $ret === false ? null : $ret["r"];
        }
    }
}



namespace
{
    if (false) {

        class swoole_buffer
        {

            public $capacity = 0;
            public $length = 0;

            /**
             * __construct
             *
             * @param int $size [optional]
             */
            public function __construct($size = null) {}

            /**
             * __destruct
             */
            public function __destruct() {}

            /**
             * __toString
             *
             * @return string
             */
            public function __toString() {}

            /**
             * substr
             *
             * @param int $offset
             * @param int $length [optional]
             * @param int $seek [optional]
             * @return string
             */
            public function substr($offset, $length = null, $seek = null) {}

            /**
             * write
             *
             * @param int $offset
             * @param string $data
             * @return bool
             */
            public function write($offset, $data) {}

            /**
             * read
             *
             * @param $offset
             * @param $length
             * @return string
             */
            public function read($offset, $length) {}

            /**
             * append
             *
             * @param $data
             * @return bool
             */
            public function append($data) {}

            /**
             * expand
             *
             * @param int $size
             * @return bool
             */
            public function expand($size) {}

            /**
             * recycle
             *
             * @return void
             */
            public function recycle() {}

            /**
             * clear
             *
             * @return void
             */
            public function clear() {}

        }
    }
}