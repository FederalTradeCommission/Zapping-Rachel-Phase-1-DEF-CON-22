<?php

require_once 'models/Call/Abstract.php';

class Call_Transcribe extends Call_Abstract
{
	public function process() {
		// The transcription is done.
		
		// Check for negative strikes
		$strikes = 0;

		//////////////////////////////////////
		// 1. Did the transcription fail? //
		//////////////////////////////////////
		if ($this->getRequestParam('TranscriptionStatus') != 'completed') {
			$strikes++;
		}

		/////////////////////////////////////////////////////////////
		// 2. Did the transcription have any of these keywords?  //
		/////////////////////////////////////////////////////////////
		
		$keywords = array("operator", "press", "card");
		$text = strtolower($this->getRequestParam('TranscriptionText'));
		foreach ($keywords as $key) {
			if (stripos($text, $key) > 0) {
				$strikes++;
			}
		}

		/////////////////////////////////////////////////////////////
		// 3. Did the transcription have more than X words?  //
		/////////////////////////////////////////////////////////////
		if (substr_count($text, " ") > 30) {
			$strikes++;
		}

		///////////////////////////////////////////////
		// 4. Does the from contain the word client //
		///////////////////////////////////////////////
		if (substr_count($this->getRequestParam('TranscriptionText'), "client") > 30) {
			$strikes++;
		}

		////////////////////////////////////////////////////////////////////////////////
		// 5. Robodialers are easy to understand. Were any words hard to translate? //
		////////////////////////////////////////////////////////////////////////////////
		if (substr_count($text, "?") > 0) {
			$strikes++;
		}


		/////////////////////////////////////
		// Check for something positive  //
		/////////////////////////////////////
		if (stripos($text, "ninety") >= 0 || stripos($text, "99") >= 0) {
			$strikes -= 5;
		}


		$this->getDb()->query("UPDATE Calls SET TranscriptionSid=?, TranscriptionText=?, TranscriptionStatus=?, ForwardedFrom=?, Strikes=Strikes+" . $strikes . " WHERE CallSid=?", array(
			$this->getRequestParam('TranscriptionSid'),
			$this->getRequestParam('TranscriptionText'),
			$this->getRequestParam('TranscriptionStatus'),
			$this->getRequestParam('ForwardedFrom'),
			$this->getRequestParam('CallSid'),
		));
	}
}