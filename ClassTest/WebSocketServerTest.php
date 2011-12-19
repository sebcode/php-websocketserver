<?php

require_once(dirname(__FILE__) . '/../WebSocketServer.php');

class WebSocketServerTest extends PHPUnit_Framework_TestCase
{
	
	public function testFrameDecoding()
	{
		// A single-frame unmasked text message

		$this->assertFrame(array(
			'isFin' => true,
			'opcode' => WebSocketServer::OP_TEXT,
			'isControl' => false,
			'isMasked' => false,
			'paylen' => 5,
			'data' => 'Hello'
		), '0x81 0x05 0x48 0x65 0x6c 0x6c 0x6f');

		// A single-frame masked text message

		$this->assertFrame(array(
			'isFin' => true,
			'opcode' => WebSocketServer::OP_TEXT,
			'isControl' => false,
			'isMasked' => true,
			'paylen' => 5,
			'data' => 'Hello'
		), '0x81 0x85 0x37 0xfa 0x21 0x3d 0x7f 0x9f 0x4d 0x51 0x58');

		// A fragmented unmasked text message

		$this->assertFrame(array(
			'isFin' => false,
			'opcode' => WebSocketServer::OP_TEXT,
			'isControl' => false,
			'isMasked' => false,
			'paylen' => 3,
			'data' => 'Hel'
		), '0x01 0x03 0x48 0x65 0x6c');

		$this->assertFrame(array(
			'isFin' => true,
			'opcode' => WebSocketServer::OP_CONT,
			'isControl' => false,
			'isMasked' => false,
			'paylen' => 2,
			'data' => 'lo'
		), '0x80 0x02 0x6c 0x6f');

		// Unmasked Ping request and masked Ping response

		$this->assertFrame(array(
			'isFin' => true,
			'opcode' => WebSocketServer::OP_PING,
			'isControl' => true,
			'isMasked' => false,
			'paylen' => 5,
			'data' => 'Hello'
		), '0x89 0x05 0x48 0x65 0x6c 0x6c 0x6f');

		$this->assertFrame(array(
			'isFin' => true,
			'opcode' => WebSocketServer::OP_PONG,
			'isControl' => true,
			'isMasked' => true,
			'paylen' => 5,
			'data' => 'Hello'
		), '0x8a 0x85 0x37 0xfa 0x21 0x3d 0x7f 0x9f 0x4d 0x51 0x58');
	}
	
	protected function assertFrame($exp, $data)
	{
		$raw = '';

		$data = str_replace('0x', '', $data);
		$matches = explode(' ', $data);

		foreach ($matches as $m) {
			$raw .= chr(hexdec($m));
		}

		$sock = fopen('php://memory', 'rw');
		fwrite($sock, $raw);
		rewind($sock);
		$s = new WebSocketServer($sock);
		$s->readFrame();
		$got = $s->getLastFrame();
		ksort($got);
		ksort($exp);

		$this->assertEquals($exp, $got);
	}

}
