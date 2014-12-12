<?php

require_once 'models/Db.php';

class Bootstrap
{
    /**
     * Database object 
     */
    protected $_db;

    /**
     * Controller Object
     */
    protected $_front;

    /**
     * Array of resources defined in config
     */
    protected $_resources;
    

    public function __construct($controller, $action)
    {
    	$this->_resources = include('configs/local.php');
    	$this->_db = Db::factory($this->getResource('db'));
        $this->run($controller, $action);
    }
    
    /**
     * Retrieve database instance
     */
    public function getDb()
    {
        return $this->_db;
    }
    
    /**
     * Retrieve a single resource
     *
     * @param  string $key
     * @return mixed
     */
    public function getResource($key)
    {
    	$resources = array_change_key_case($this->_resources, CASE_LOWER);

        if (array_key_exists(strtolower($key), $resources)) {    
            return $resources[strtolower($key)];
        }
        return null;
    }
    
    
    /**
     * Run the application
     *
     * @return mixed
     */
    public function run($controller, $action)
    {   
        $controller .= "Controller";
        // Load the controller
        if (file_exists(realpath(dirname(__FILE__)) . '/controllers/'.$controller.'.php')){
            require_once 'controllers/'.$controller.'.php';
            
            if (!class_exists($controller, false)) {
                throw new Exception('Controller class not found');
            }
            
        } else {
            throw new Exception('No controller file found for ' . $controller);
        }

        $this->_front = new $controller($this);
        $this->_front->dispatch($action);

        return $this;
    }

}
