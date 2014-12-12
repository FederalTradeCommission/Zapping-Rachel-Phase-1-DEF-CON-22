<?php

require_once 'models/Call/Abstract.php';

class Call_Incoming extends Call_Abstract
{
	public function process() {
		
		$this->getDb()->query("INSERT INTO Calls (`CallSid`,`Called`,`From`,`FromCity`,`FromState`,`FromCountry`,`CallerCountry`,`CallStatus`,`CallerName`,`CallTime`) VALUES (?,?,?,?,?,?,?,?,?,?)", array(
			$this->getRequestParam('CallSid'),
			$this->getRequestParam('Called'),
			$this->getRequestParam('From'),
			$this->getRequestParam('FromCity'),
			$this->getRequestParam('FromState'),
			$this->getRequestParam('FromCountry'),
			$this->getRequestParam('CallerCountry'),
			$this->getRequestParam('CallStatus'),
			$this->getRequestParam('CallerName'),
			time(),
		));
	}
}