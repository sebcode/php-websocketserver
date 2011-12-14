<?php

class WebSocketServer
{
	protected $rsock;
	protected $wsock;

	protected $lastFrame = array();

	protected $bufType = false;
	protected $buf = '';

	const MAX_PAYLOAD_LEN = 1048576;
	const MAX_BUFFER_SIZE = 1048576;

	const OP_CONT = 0x0;
	const OP_TEXT = 0x1;
	const OP_BIN = 0x2;
	const OP_CLOSE = 0x8;
	const OP_PING = 0x9;
	const OP_PONG = 0xa;
	
	public function __construct($readSocket, $writeSocket = false)
	{
		$this->rsock = $readSocket;
		$this->wsock = $writeSocket;
	}

	public function process()
	{
		$this->processHandshake();
		$this->processFrames();
	}

	public function processHandshake()
	{
		$requestHeaders = array();

		$i = 0;

		while ($data = $this->readLine()) {
			$data = rtrim($data);

			if (empty($data)) {
				break;
			}

			@list($k, $v) = explode(':', $data, 2);
			$requestHeaders[$k] = ltrim($v);

			if ($i++ > 30) {
				break;
			}
		}

		if (empty($requestHeaders['Sec-WebSocket-Key'])) {
			throw new Exception('invalid_handshake_request');
		}
		
		$key = base64_encode(sha1($requestHeaders['Sec-WebSocket-Key'] . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

		$response = "HTTP/1.1 101 Switching Protocols\r\n";
		$response .= "Upgrade: WebSocket\r\n";
		$response .= "Connection: Upgrade\r\n";
		$response .= "Sec-WebSocket-Accept: ". $key ."\r\n";
		$response .= "\r\n";

		$this->write($response);
	}

	public function processFrames()
	{
		while ($this->readFrame()) {
			$f = $this->lastFrame;

			/* unfragmented message */
			if ($f['isFin'] && $f['opcode'] != 0) {
				$this->gotData($f['opcode'], $f['data']);
			}
			/* start fragmented message */
			else if (!$f['isFin'] && $f['opcode'] != 0) {
				$this->bufType = $f['opcode'];
				$this->buf = $f['data'];
				$this->checkBufSize();
			}
			/* continue fragmented message */
			else if (!$f['isFin'] && $f['opcode'] == 0) {
				$this->buf .= $f['data'];
				$this->checkBufSize();
			}
			/* finalize fragmented message */
			else if ($f['isFin'] && $f['opcode'] == 0) {
				$this->buf .= $f['data'];
				$this->checkBufSize();

				$this->gotData($this->bufType, $this->buf);

				$this->bufType = false;
				$this->buf = '';
			}
		}
	}

	protected function gotData($type, $data)
	{
		if ($type == self::OP_TEXT) {
			$this->gotText($data);
		} else if ($type == self::OP_BIN) {
			$this->gotBin($data);
		}
	}

	protected function checkBufSize()
	{
		if (strlen($this->buf) >= self::MAX_BUFFER_SIZE) {
			throw new Exception('limit_violation');
		}
	}

	public function readFrame()
	{
		/* read first 2 bytes */

		$data = $this->read(2);
		$b1 = ord($data[0]);
		$b2 = ord($data[1]);

		$isFin = ($b1 & (1 << 7)) != 0;
		$opcode = $b1 & 0x0f;
		$isMasked = ($b2 & (1 << 7)) != 0;
		$paylen = $b2 & 0x7f;

		/* read extended payload length, if applicable */

		if ($paylen == 126) {
			/* the following 2 bytes are the actual payload len */
			$data = $this->read(2);
			$unpacked = unpack('n', $data);
			$paylen = $unpacked[1];
		} else if ($paylen == 127) {
			/* the following 8 bytes are the actual payload len */
			$data = $this->read(8);
			throw new Exception('not_implemented');
		}

		if ($paylen >= self::MAX_PAYLOAD_LEN) {
			throw new Exception('limit_violation');
		}
		
		//error_log($b1 . ' ' . decbin($b1) . ' ' . $b2 . ' ' . decbin($b2) . ' paylen: ' . $paylen . ' ismasked:' . ($isMasked ? 1 : 0));

		/* read masking key and decode payload data */

		$mask = false;
		$data = '';

		if ($isMasked) {
			$mask = $this->read(4);

			if ($paylen) {
				$data = $this->read($paylen);

				for ($i = 0, $j = 0, $l = strlen($data); $i < $l; $i++) {
					$data[$i] = chr(ord($data[$i]) ^ ord($mask[$j]));
					
					if ($j++ >= 3) {
						$j = 0;
					}
				}
			}
		} else if ($paylen) {
			$data = $this->read($paylen);
		}

		$this->lastFrame['isFin'] = $isFin;
		$this->lastFrame['opcode'] = $opcode;
		$this->lastFrame['isMasked'] = $isMasked;
		$this->lastFrame['paylen'] = $paylen;
		$this->lastFrame['data'] = $data;
		
		return true;
	}

	public function readLine()
	{
		$ret = fgets($this->rsock, 8192);

		if ($ret === false) {
			throw new Exception('read_failed');
		}

		return $ret;
	}

	public function read($len)
	{
		$ret = fread($this->rsock, $len);

		if ($ret === false || $ret === '') {
			throw new Exception('read_failed');
		}

		if (strlen($ret) !== $len) {
			throw new Exception('read_failed');
		}

		return $ret;
	}

	public function write($data)
	{
		$ret = fwrite($this->wsock, $data);

		if ($ret === false) {
			throw new Exception('write_failed');
		}

		if ($ret !== strlen($data)) {
			throw new Exception('write_failed');
		}

		return $ret;
	}

	public function getLastFrame()
	{
		return $this->lastFrame;
	}

	public function sendText($text)
	{
		$len = strlen($text);

		/* extended 64bit payload not implemented yet */
		if ($len > 0xffff) {
			throw new Exception('not_implemented');
		}

		/* 0x81 = first and last bit set (fin, opcode=text) */
		$header = chr(0x81);

		/* extended 32bit payload */
		if ($len >= 125) {
			$header .= chr(126) . pack('n', $len);
		} else {
			$header .= chr($len);
		}

		$this->write($header . $text);
	}

	protected function gotText($text)
	{
		/* to be implemented by child class */
	}

	protected function gotBin($data)
	{
		/* to be implemented by child class */
	}

}

