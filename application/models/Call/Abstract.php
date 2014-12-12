<?php

abstract class Call_Abstract
{
    protected $_controller;
    protected $_db;
    
    public function __construct($controller) {
        $this->_controller = $controller;
        $this->_db = $this->getController()->getBootstrap()->getDb();
    }
    
    public function __destruct() {
        $this->_db->closeConnection();
    }
 
    public function getController() {
        return $this->_controller;
    }

    public function getDb() {
        return $this->_db;
    }

    public function getRequestParam($key) {
        if (is_array($_POST) && array_key_exists($key, $_POST)) {
            return trim(preg_replace('/\'"/','',$_POST[$key]));
        }
        return null;
    }
}