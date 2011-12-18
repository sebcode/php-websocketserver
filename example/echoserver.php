#!/usr/bin/env php
<?php

require_once(dirname(__FILE__) . '/../WebSocketServer.php');

class EchoServer extends WebSocketServer
{
	
	protected function gotText($text)
	{
		$this->sendText($text);
	}

	protected function onClose($code, $reason)
	{
		error_log('Client closed connection (Code: ' . $code . ', reason: '. $reason .')');
	}

}

$s = new EchoServer(STDIN, STDOUT);
$s->process();

