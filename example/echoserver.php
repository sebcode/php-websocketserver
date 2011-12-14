#!/usr/bin/env php
<?php

require_once(dirname(__FILE__) . '/../WebSocketServer.php');

class EchoServer extends WebSocketServer
{
	
	protected function gotText($text)
	{
		$this->sendText($text);
	}

}

$s = new EchoServer(STDIN, STDOUT);
$s->process();

