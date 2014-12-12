<?php

require_once 'models/Call/Abstract.php';

class Call_Status extends Call_Abstract
{
	public function process() {
		// The call has ended.
		
		// Check for negative strikes
		$strikes = 0;

		//////////////////////////////////////////////////////////////
		// 1. Have they called our other honeypot numbers before? //
		//////////////////////////////////////////////////////////////
		$this->getDb()->query("SELECT Called, count(*) as mycount FROM Calls WHERE `From`=? AND `Called`!=? GROUP BY Called", array(
			$this->getRequestParam('From'),
			$this->getRequestParam('Called'),
		));

		$row = $this->getDb()->fetch();
		if ($row != false) {
			$strikes += $row['mycount'];
		}

		////////////////////////////////////////////////
		// 2. Are they calling from outside the US? //
		////////////////////////////////////////////////
		if ($this->getRequestParam('CallerCountry') != 'US') {
			$strikes++;
		}
		
		if ($this->getRequestParam('FromCountry') != 'US') {
			$strikes++;
		}

		if ($this->getRequestParam('FromCountry') == 'US' && $this->getRequestParam('CallerCountry') != 'US') {
			// I'm thinking that this means the caller ID may be spoofed, and the caller is in fact outside the country
			$strikes+=2;
		}

		////////////////////////////////////////
		// 3. Is the called id unavailable? //
		////////////////////////////////////////
		if (strpos($this->getRequestParam('CallerName'), "Unavail") > 0) {
			$strikes++;
		}

		/////////////////////////////////////////////
		// 4. Was the call duration really long? //
		/////////////////////////////////////////////
		if ($this->getRequestParam('CallDuration') > 30) {
			$strikes+=2;
		}

		$this->getDb()->query("UPDATE Calls SET Duration=?, CallDuration=?, CallStatus=?, Strikes=? WHERE CallSid=?", array(
			$this->getRequestParam('Duration'),
			$this->getRequestParam('CallDuration'),
			$this->getRequestParam('CallStatus'),
			$strikes,
			$this->getRequestParam('CallSid'),
		));
	}
}