<?php

require_once 'models/Db/Abstract.php';

/**
 * Class for connecting to MySQL databases.
 */
class Db_Mysql extends Db_Abstract
{
    protected $_numericDataTypes = array(
        Db::INT_TYPE    => Db::INT_TYPE,
        Db::BIGINT_TYPE => Db::BIGINT_TYPE,
        Db::FLOAT_TYPE  => Db::FLOAT_TYPE,
        'INT'                => Db::INT_TYPE,
        'INTEGER'            => Db::INT_TYPE,
        'MEDIUMINT'          => Db::INT_TYPE,
        'SMALLINT'           => Db::INT_TYPE,
        'TINYINT'            => Db::INT_TYPE,
        'BIGINT'             => Db::BIGINT_TYPE,
        'SERIAL'             => Db::BIGINT_TYPE,
        'DEC'                => Db::FLOAT_TYPE,
        'DECIMAL'            => Db::FLOAT_TYPE,
        'DOUBLE'             => Db::FLOAT_TYPE,
        'DOUBLE PRECISION'   => Db::FLOAT_TYPE,
        'FIXED'              => Db::FLOAT_TYPE,
        'FLOAT'              => Db::FLOAT_TYPE
    );

    /**
     * Quote a raw string.
     *
     * @param mixed $value Raw string
     * @return string           Quoted string
     */
    protected function _quote($value)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $this->_connect();
        return "'" . $this->_connection->real_escape_string($value) . "'";
    }

    /**
     * Creates a connection to the database.
     *
     * @return void
     * @throws Exception
     */
    protected function _connect()
    {
        if ($this->_connection) {
            return;
        }

        if (!extension_loaded('mysqli')) {
            throw new Exception('The Mysqli extension is required for this adapter but the extension is not loaded');
        }

        if (isset($this->_config['port'])) {
            $port = (integer) $this->_config['port'];
        } else {
            $port = null;
        }

        $this->_connection = mysqli_init();

        // Suppress connection warnings here.
        // Throw an exception instead.
        $_isConnected = @mysqli_real_connect(
            $this->_connection,
            $this->_config['host'],
            $this->_config['username'],
            $this->_config['password'],
            $this->_config['dbname'],
            $port
        );

        if ($_isConnected === false || mysqli_connect_errno()) {

            $this->closeConnection();
            throw new Exception(mysqli_connect_error());
        }

        if (!empty($this->_config['charset'])) {
            mysqli_set_charset($this->_connection, $this->_config['charset']);
        }
    }

    /**
     * Test if a connection is active
     *
     * @return boolean
     */
    public function isConnected()
    {
        return ((bool) ($this->_connection instanceof mysqli));
    }

    /**
     * Force the connection to close.
     *
     * @return void
     */
    public function closeConnection()
    {
        if ($this->isConnected()) {
            $this->_connection->close();
        }
        $this->_connection = null;
    }

    /**
     * Gets the last ID generated automatically by an IDENTITY/AUTOINCREMENT column.
     *
     * @return mixed
     */
    public function lastInsertId()
    {
        return $this->_connection->insert_id;
    }

    /**
     * Begin a transaction.
     *
     * @return void
     */
    protected function _beginTransaction()
    {
        $this->_connect();
        $this->_connection->autocommit(false);
    }

    /**
     * Commit a transaction.
     *
     * @return void
     */
    protected function _commit()
    {
        $this->_connect();
        $this->_connection->commit();
        $this->_connection->autocommit(true);
    }

    /**
     * Roll-back a transaction.
     *
     * @return void
     */
    protected function _rollBack()
    {
        $this->_connect();
        $this->_connection->rollback();
        $this->_connection->autocommit(true);
    }
    
    /**
     * Prepare a MySQL SQL statement
     *
     * @param  string $sql
     * @return void
     * @throws Exception
     */
    protected function _prepare($sql)
    {
        $mysqli = $this->getConnection();

        $this->_stmt = $mysqli->prepare($sql);

        if ($this->_stmt === false || $mysqli->errno) {
            throw new Exception("Mysqli prepare error: " . $mysqli->error, $mysqli->errno);
        }
    }
    
    /**
     * Executes a prepared statement.
     *
     * @param array $params OPTIONAL Values to bind to parameter placeholders.
     * @return bool
     * @throws Exception
     * 
     */
    protected function _execute(array $params = null)
    {
        if (!$this->_stmt) {
            return false;
        }

        // send $params as input parameters to the statement
        if ($params) {
            array_unshift($params, str_repeat('s', count($params)));
            $stmtParams = array();
            foreach ($params as $k => &$value) {
                $stmtParams[$k] = &$value;
            }
            call_user_func_array(
                array($this->_stmt, 'bind_param'),
                $stmtParams
                );
        }

        // execute the statement
        $retval = $this->_stmt->execute();
        if ($retval === false) {
            throw new Exception("Mysqli statement execute error : " . $this->_stmt->error, $this->_stmt->errno);
        }

        // retain metadata
        //if ($this->_meta === null) {
            $this->_meta = $this->_stmt->result_metadata();
            if ($this->_stmt->errno) {
                throw new Exception("Mysqli statement metadata error: " . $this->_stmt->error, $this->_stmt->errno);
            }
        //}

        // statements that have no result set do not return metadata       
        if ($this->_meta !== false) {

            // get the column names that will result
            $this->_keys = array();
            foreach ($this->_meta->fetch_fields() as $col) {
                $this->_keys[] = $col->name;
            }
            // set up a binding space for result variables
            $this->_values = array_fill(0, count($this->_keys), null);

            // set up references to the result binding space.
            // just passing $this->_values in the call_user_func_array()
            // below won't work, you need references.
            $refs = array();
            foreach ($this->_values as $i => &$f) {
                $refs[$i] = &$f;
            }

            $this->_stmt->store_result();
            // bind to the result variables            
            call_user_func_array(
                array($this->_stmt, 'bind_result'),
                $this->_values
            );
        }

        return $retval;
    }
    
    
    
    /**
     * Fetches a row from the result set.
     *
     * @param int $style  OPTIONAL Fetch mode for this fetch operation.
     * @param int $cursor OPTIONAL Absolute, relative, or other.
     * @param int $offset OPTIONAL Number for absolute or relative cursors.
     * @return mixed Array, object, or scalar depending on fetch mode.
     * @throws Exception
     */
    public function fetch($style = null, $cursor = null, $offset = null)
    {
        if (!$this->_stmt) {
            return false;
        }
        // fetch the next result
        $retval = $this->_stmt->fetch();
        switch ($retval) {
            case null: // end of data
            case false: // error occurred
                $this->_stmt->reset();
                return false;
            default:
                // fallthrough
        }

        // make sure we have a fetch mode
        if ($style === null) {
            $style = $this->_fetchMode;
        }

        // dereference the result values, otherwise things like fetchAll()
        // return the same values for every entry (because of the reference).
        $values = array();
        foreach ($this->_values as $key => $val) {
            $values[] = $val;
        }

        $row = false;
        switch ($style) {
            case Db::FETCH_NUM:
                $row = $values;
                break;
            case Db::FETCH_ASSOC:
                $row = array_combine($this->_keys, $values);
                break;
            case Db::FETCH_BOTH:
                $assoc = array_combine($this->_keys, $values);
                $row = array_merge($values, $assoc);
                break;
            case Db::FETCH_OBJ:
                $row = (object) array_combine($this->_keys, $values);
                break;
            default:
                throw new Exception("Invalid fetch mode '$style' specified");
                break;
        }
        return $row;
    }

    /**
     * Set the fetch mode.
     *
     * @param int $mode
     * @return void
     * @throws Exception
     */
    public function setFetchMode($mode)
    {
        switch ($mode) {
            case Db::FETCH_ASSOC:
            case Db::FETCH_NUM:
            case Db::FETCH_BOTH:
            case Db::FETCH_OBJ:
                $this->_fetchMode = $mode;
                break;
            default:
                throw new Exception("Invalid fetch mode '$mode' specified");
        }
    }
}
