<?php

require_once 'models/Call/Abstract.php';

class Call_Log extends Call_Abstract
{
	public function compile() {
		
		$this->getDb()->query("SELECT * FROM Calls WHERE CallStatus IN ('completed','failed') ORDER BY CallTime DESC LIMIT 50");
		return $this->getDb()->fetchAll();
	}
}