#!/usr/bin/env php
<?php

// TODO: use phpunit :)

require_once('../WebSocketServer.php');

// A single-frame unmasked text message

assertFrame(array(
	'isFin' => true,
	'opcode' => WebSocketServer::OP_TEXT,
	'isControl' => false,
	'isMasked' => false,
	'paylen' => 5,
	'data' => 'Hello'
), '0x81 0x05 0x48 0x65 0x6c 0x6c 0x6f');

// A single-frame masked text message

assertFrame(array(
	'isFin' => true,
	'opcode' => WebSocketServer::OP_TEXT,
	'isControl' => false,
	'isMasked' => true,
	'paylen' => 5,
	'data' => 'Hello'
), '0x81 0x85 0x37 0xfa 0x21 0x3d 0x7f 0x9f 0x4d 0x51 0x58');

// A fragmented unmasked text message

assertFrame(array(
	'isFin' => false,
	'opcode' => WebSocketServer::OP_TEXT,
	'isControl' => false,
	'isMasked' => false,
	'paylen' => 3,
	'data' => 'Hel'
), '0x01 0x03 0x48 0x65 0x6c');

assertFrame(array(
	'isFin' => true,
	'opcode' => WebSocketServer::OP_CONT,
	'isControl' => false,
	'isMasked' => false,
	'paylen' => 2,
	'data' => 'lo'
), '0x80 0x02 0x6c 0x6f');

// Unmasked Ping request and masked Ping response

assertFrame(array(
	'isFin' => true,
	'opcode' => WebSocketServer::OP_PING,
	'isControl' => true,
	'isMasked' => false,
	'paylen' => 5,
	'data' => 'Hello'
), '0x89 0x05 0x48 0x65 0x6c 0x6c 0x6f');

assertFrame(array(
	'isFin' => true,
	'opcode' => WebSocketServer::OP_PONG,
	'isControl' => true,
	'isMasked' => true,
	'paylen' => 5,
	'data' => 'Hello'
), '0x8a 0x85 0x37 0xfa 0x21 0x3d 0x7f 0x9f 0x4d 0x51 0x58');

echo "OK.\n";

function assertFrame($exp, $data)
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

	if ($got === $exp) {
		return true;
	}

	echo "got: " . var_export($got, true) . "\n";
	echo "exp: " . var_export($exp, true) . "\n";

	throw new Exception('failed');
}

