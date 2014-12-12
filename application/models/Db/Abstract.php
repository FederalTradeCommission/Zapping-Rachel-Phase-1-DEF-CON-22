<?php

require_once 'models/Db.php';

/**
 * Class for connecting to SQL databases and performing common operations.
 */
abstract class Db_Abstract
{

    /**
     * User-provided configuration
     *
     * @var array
     */
    protected $_config = array();

    /**
     * Fetch mode
     *
     * @var integer
     */
    protected $_fetchMode = Db::FETCH_ASSOC;

    /**
     * Database connection
     *
     * @var object|resource|null
     */
    protected $_connection = null;
    
    /**
     * Prepared statement
     *
     * @var object|null
     * @see http://us.php.net/manual/en/class.mysqli-stmt.php
     */
    protected $_stmt = null;
    
    /**
     * Column names.
     *
     * @var array
     */
    protected $_keys;

    /**
     * Fetched result values.
     *
     * @var array
     */
    protected $_values;
    
    /**
     * @var array
     */
    protected $_meta = null;
    
    /**
     * Keys are UPPERCASE SQL datatypes or the constants
     * Db::INT_TYPE, Db::BIGINT_TYPE, or Db::FLOAT_TYPE.
     *
     * Values are:
     * 0 = 32-bit integer
     * 1 = 64-bit integer
     * 2 = float or decimal
     *
     * @var array Associative array of datatypes to values 0, 1, or 2.
     */
    protected $_numericDataTypes = array(
        Db::INT_TYPE    => Db::INT_TYPE,
        Db::BIGINT_TYPE => Db::BIGINT_TYPE,
        Db::FLOAT_TYPE  => Db::FLOAT_TYPE
    );


    /**
     * Constructor.
     *
     * $config is an array of key/value pairs containing configuration options.  
     *
     * dbname         => (string) The name of the database to user
     * username       => (string) Connect to the database as this username.
     * password       => (string) Password associated with the username.
     * host           => (string) What host to connect to, defaults to localhost
     *
     * Some options are used on a case-by-case basis by adapters:
     *
     * port           => (string) The port of the database
     * persistent     => (boolean) Whether to use a persistent connection or not, defaults to false
     *
     * @param  array An array of configuration data
     * @throws Db_Exception
     */
    public function __construct($config)
    {
        /*
         * Verify that adapter parameters are in an array.
         */
        if (!is_array($config)) { 
            /**
             * @see Db_Exception
             */
            require_once 'Irelo/Db/Exception.php';
            throw new Db_Exception('Adapter parameters must be in an array');
        }

        $this->_checkRequiredOptions($config);

        $this->_config = $config;
    }

    /**
     * Check for config options that are mandatory.
     * Throw exceptions if any are missing.
     *
     * @param array $config
     * @throws Db_Exception
     */
    protected function _checkRequiredOptions(array $config)
    {
        // we need at least a dbname
        if (! array_key_exists('dbname', $config)) {
            throw new Exception("Configuration array must have a key for 'dbname' that names the database instance");
        }

        if (! array_key_exists('password', $config)) {
            throw new Exception("Configuration array must have a key for 'password' for login credentials");
        }

        if (! array_key_exists('username', $config)) {
            throw new Exception("Configuration array must have a key for 'username' for login credentials");
        }
    }

    /**
     * Returns the underlying database connection object or resource.
     * If not presently connected, this initiates the connection.
     *
     * @return object|resource|null
     */
    public function getConnection()
    {
        $this->_connect();
        return $this->_connection;
    }

    /**
     * Returns the configuration variables in this adapter.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->_config;
    }

    /**
     * Prepares and executes an SQL statement with bound data.
     *
     * @param  mixed  $sql  The SQL statement with placeholders.
     * @param  mixed  $bind An array of data to bind to the placeholders.
     * @return Db_Abstract
     */
    public function query($sql, $bind = array())
    {
        // connect to the database if needed
        $this->_connect();

        // make sure $bind to an array;
        if (!is_array($bind)) {
            $bind = array($bind);
        }

        // prepare and execute the statement with profiling
        $this->_prepare($sql);
        $this->_execute($bind);

        return $this;
    }

    /**
     * Get the fetch mode.
     *
     * @return int
     */
    public function getFetchMode()
    {
        return $this->_fetchMode;
    }
    
    /**
     * Returns an array containing all of the result set rows.
     *
     * @param int $style OPTIONAL Fetch mode.
     * @param int $col   OPTIONAL Column number, if fetch mode is by column.
     * @return array Collection of rows, each in a format by the fetch mode.
     */
    public function fetchAll($style = null, $col = null)
    {
        $data = array();

        if ($col === null) {
            while ($row = $this->fetch($style)) {
                $data[] = $row;
            }
        } else {
            while (false !== ($val = $this->fetchColumn($col))) {
                $data[] = $val;
            }
        }
        return $data;
    }

    /**
     * Returns a single column from the next row of a result set.
     *
     * @param int $col OPTIONAL Position of the column to fetch.
     * @return string One value from the next row of result set, or false.
     */
    public function fetchColumn($col = 0)
    {
        $data = array();
        $col = (int) $col;
        $row = $this->fetch(Db::FETCH_NUM);
        if (!is_array($row)) {
            return false;
        }
        return $row[$col];
    }

    /**
     * Fetches the next row and returns it as an object.
     *
     * @param string $class  OPTIONAL Name of the class to create.
     * @param array  $config OPTIONAL Constructor arguments for the class.
     * @return mixed One object instance of the specified class, or false.
     */
    public function fetchObject($class = 'stdClass', array $config = array())
    {
        $obj = new $class($config);
        $row = $this->fetch(Db::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }
        foreach ($row as $key => $val) {
            $obj->$key = $val;
        }
        return $obj;
    }

    /**
     * Quote a raw string.
     *
     * @param string $value     Raw string
     * @return string           Quoted string
     */
    protected function _quote($value)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_float($value)) {
            return sprintf('%F', $value);
        }
        return "'" . addcslashes($value, "\000\n\r\\'\"\032") . "'";
    }

    /**
     * Safely quotes a value for an SQL statement.
     *
     * If an array is passed as the value, the array values are quoted
     * and then returned as a comma-separated string.
     *
     * @param mixed $value The value to quote.
     * @param mixed $type  OPTIONAL the SQL datatype name, or constant, or null.
     * @return mixed An SQL-safe quoted value (or string of separated values).
     */
    public function quote($value, $type = null)
    {
        $this->_connect();

        if (is_array($value)) {
            foreach ($value as &$val) {
                $val = $this->quote($val, $type);
            }
            return implode(', ', $value);
        }

        if ($type !== null && array_key_exists($type = strtoupper($type), $this->_numericDataTypes)) {
            $quotedValue = '0';
            switch ($this->_numericDataTypes[$type]) {
                case Db::INT_TYPE: // 32-bit integer
                    $quotedValue = (string) intval($value);
                    break;
                case Db::BIGINT_TYPE: // 64-bit integer
                    // ANSI SQL-style hex literals (e.g. x'[\dA-F]+')
                    // are not supported here, because these are string
                    // literals, not numeric literals.
                    if (preg_match('/^(
                          [+-]?                  # optional sign
                          (?:
                            0[Xx][\da-fA-F]+     # ODBC-style hexadecimal
                            |\d+                 # decimal or octal, or MySQL ZEROFILL decimal
                            (?:[eE][+-]?\d+)?    # optional exponent on decimals or octals
                          )
                        )/x',
                        (string) $value, $matches)) {
                        $quotedValue = $matches[1];
                    }
                    break;
                case Db::FLOAT_TYPE: // float or decimal
                    $quotedValue = sprintf('%F', $value);
            }
            return $quotedValue;
        }

        return $this->_quote($value);
    }

    /**
     * Abstract Methods
     */
    
    /**
     * Begin a transaction.
     */
    abstract protected function _beginTransaction();
    
    /**
     * Force the connection to close.
     *
     * @return void
     */
    abstract public function closeConnection();
    
    /**
     * Commit a transaction.
     */
    abstract protected function _commit();
    
    /**
     * Creates a connection to the database.
     *
     * @return void
     */
    abstract protected function _connect();

    /**
     * Executes a prepared statement.
     *
     * @param array $params OPTIONAL Values to bind to parameter placeholders.
     * @return bool
     */
    abstract protected function _execute(array $params = null);
    
    /**
     * Test if a connection is active
     *
     * @return boolean
     */
    abstract public function isConnected();
    
    /**
     * Gets the last ID generated automatically by an IDENTITY/AUTOINCREMENT column.
     *
     * @return string
     */
    abstract public function lastInsertId();

    /**
     * Prepare a statement.
     *
     * @param string $sql SQL query
     * @return void
     */
    abstract protected function _prepare($sql);

    /**
     * Roll-back a transaction.
     */
    abstract protected function _rollBack();

    /**
     * Set the fetch mode.
     *
     * @param integer $mode
     * @return void
     */
    abstract public function setFetchMode($mode);

}
