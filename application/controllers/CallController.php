<?php

// include models
require_once 'models/Call/Incoming.php';
require_once 'models/Call/Log.php';
require_once 'models/Call/Status.php';
require_once 'models/Call/Transcribe.php';

class CallController
{
    protected $_bootstrap;

    public function __construct($bootstrap)
    {
        $this->_bootstrap = $bootstrap;
    }

    public function dispatch($action)
    {
        $action.="Action";
        $this->$action();
        $this->render();
    }

    public function incomingAction()
    {
        try {
            $call = new Call_Incoming($this);
            $call->process();
        }
        catch(Exception $e) {
        }

        header("content-type: text/xml");
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
        echo "<Response><Play>http://162.209.75.116/audio/hello.mp3</Play><Pause/><Play>http://162.209.75.116/audio/question.mp3</Play><Record action=\"http://162.209.75.116/call_hangup.php\" timeout=\"10\" transcribe=\"true\" transcribeCallback=\"http://162.209.75.116/call_transcribe.php\" method=\"POST\" playBeep=\"false\" maxLength=\"30\"/></Response>";
    }

    public function statusAction()
    {
        try {
            $call = new Call_Status($this);
            $call->process();
        }
        catch(Exception $e) {
        }
    }

    public function transcribeAction()
    {
        try {
            $call = new Call_Transcribe($this);
            $call->process();
        }
        catch(Exception $e) {
        }
    }

    public function logAction()
    {
        try {
            $log = new Call_Log($this);
            $data = $log->compile();
        }
        catch(Exception $e) {
        }

        include("views/log.phtml");
    }

    public function getBootstrap()
    {
        return $this->_bootstrap;
    }

    public function render()
    {
        
    }
}


