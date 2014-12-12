<?php
/**
 * Class for connecting to SQL databases and performing common operations.
 */
class Db
{
    /**
     * Use the INT_TYPE, BIGINT_TYPE, and FLOAT_TYPE with the quote() method.
     */
    const INT_TYPE    = 0;
    const BIGINT_TYPE = 1;
    const FLOAT_TYPE  = 2;
    const FETCH_ASSOC = 1;
    const FETCH_BOTH  = 2;
    const FETCH_NUM   = 3;
    const FETCH_OBJ   = 4;

    /**
     * Factory for Db_Abstract classes.
     *
     * First argument is an associative array of key-value
     * pairs.  This is used as the argument to the adapter constructor.
     * @param  array with adapter parameters.
     * @return Db_Abstract
     * @throws Exception
     */
    public static function factory($config)
    {
        /*
         * Verify that adapter parameters are in an array.
         */
        if (!is_array($config)) {
            throw new Exception('Adapter parameters must be in an array');
        }

        /*
         * Verify that an adapter name has been specified.
         */
        if (!is_string($config['adapter']) || empty($config['adapter'])) {
            throw new Exception('Adapter name must be specified in a string');
        }

        $adapter = str_replace(' ', '_', ucwords(str_replace('_', ' ', strtolower($config['adapter']))));
        $adapterName = 'Db_' . $adapter; 

        /*
         * Create an instance of the adapter class.
         * Pass the config to the adapter class constructor.
         */
        require_once 'models/Db/' . $adapter .  '.php';
        
        if (!class_exists($adapterName, false)) {
            throw new Exception('Adapter ' . $adapter . ' class can not be found');
        }
        
        $dbAdapter = new $adapterName($config);

        /*
         * Verify that the object created is a descendent of the abstract adapter type.
         */
        if (! $dbAdapter instanceof Db_Abstract) {
            throw new Exception("Adapter class '$adapterName' does not extend Db_Abstract");
        }

        return $dbAdapter;
    }

}