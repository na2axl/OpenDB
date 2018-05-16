<?php

/**
 * LightQL - The lightweight PHP ORM
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @category  Library
 * @package   LightQL
 * @author    Axel Nana <ax.lnana@outlook.com>
 * @copyright 2018 Aliens Group, Inc.
 * @license   MIT <https://github.com/ElementaryFramework/LightQL/blob/master/LICENSE>
 * @version   GIT: 0.0.1
 * @link      http://lightql.na2axl.tk
 */

namespace LightQL;

/**
 * LightQL - Database Manager Class
 *
 * @package     LightQL
 * @author      Nana Axel <ax.lnana@outlook.com>
 */
class LightQL
{
    /**
     * Registered SQL operators.
     *
     * @var array
     * @access private
     */
    private static $operators = array('!=', '<>', '<=', '>=', '=', '<', '>');

    /**
     * The database name.
     *
     * @var string
     * @access protected
     */
    protected $database;

    /**
     * The table name.
     *
     * @var string
     * @access protected
     */
    protected $table;

    /**
     * The database server address.
     *
     * @var string
     * @access protected
     */
    protected $hostname;

    /**
     * The database username.
     *
     * @var string
     * @access protected
     */
    protected $username;

    /**
     * The database password.
     *
     * @var string
     * @access protected
     */
    protected $password;

    /**
     * The PDO driver to use.
     *
     * @var string
     * @access private
     */
    private $_driver;

    /**
     * The DBMS to use.
     *
     * @var string
     * @access private
     */
    private $_dbms;

    /**
     * The PDO connection options.
     *
     * @var array
     * @access private
     */
    private $_options;

    /**
     * The DSN used for the PDO connection.
     *
     * @var string
     * @access private
     */
    private $_dsn;

    /**
     * The current PDO instance.
     *
     * @var object
     * @access private
     */
    private $_pdo = NULL;

    /**
     * The where clause.
     *
     * @var string
     * @access private
     */
    private $_where = NULL;

    /**
     * The order clause.
     *
     * @var string
     * @access private
     */
    private $_order = NULL;

    /**
     * The limit clause.
     *
     * @var string
     * @access private
     */
    private $_limit = NULL;

    /**
     * The "group by" clause
     *
     * @var string
     * @access private
     */
    private $_group = NULL;

    /**
     * The distinct clause
     *
     * @var bool
     * @access private
     */
    private $_distinct = FALSE;

    /**
     * The computed query string.
     *
     * @var string
     * @access private
     */
    private $_queryString = NULL;

    /**
     * Class __constructor
     *
     * @param  array $options The lists of options
     *
     * @throws \PDOException
     */
    public function __construct($options = null)
    {
        if (!is_array($options)) {
            return false;
        }

        $attr = array();

        if (isset($options["dbms"])) {
            $this->_dbms = strtolower($options["dbms"]);
        }

        if (isset($options["options"])) {
            $this->_options = $options["options"];
        }

        if (isset($options["command"]) && is_array($options["command"])) {
            $commands = $options["command"];
        }
        else {
            $commands = [];
        }

        if (isset($options["dsn"])) {
            if (is_array($options["dsn"]) && isset($options["dsn"]["driver"])) {
                $this->_driver = $options["dsn"]["driver"];
                unset($options["dsn"]["driver"]);
                $attr = $options["dsn"];
            } else {
                return false;
            }
        } else {
            if (isset($options["port"]) && is_int($options["port"] * 1)) {
                $port = $options["port"];
            }

            switch ($this->_dbms) {
                case "mariadb":
                case "mysql":
                    $this->_driver = "mysql";
                    $attr = array(
                        "dbname" => $options["database"]
                    );

                    if (isset($options["socket"])) {
                        $attr["unix_socket"] = $options["socket"];
                    } else {
                        $attr["host"] = $options["hostname"];
                        if (isset($port)) {
                            $attr["port"] = $port;
                        }
                    }

                    // Make MySQL using standard quoted identifier
                    $commands[] = "SET SQL_MODE=ANSI_QUOTES";
                    break;

                case "pgsql":
                    $this->_driver = "pgsql";
                    $attr = array(
                        "host" => $options["hostname"],
                        "dbname" => $options['database']
                    );

                    if (isset($port)) {
                        $attr["port"] = $port;
                    }
                    break;

                case "sybase":
                    $this->_driver = "dblib";
                    $attr = array(
                        "host" => $options["hostname"],
                        "dbname" => $options["database"]
                    );

                    if (isset($port)) {
                        $attr["port"] = $port;
                    }
                    break;

                case "oracle":
                    $this->_driver = "oci";
                    $attr = array(
                        "dbname" => $options["hostname"] ?
                            "//{$options['server']}" . (isset($port) ? ":{$port}" : ":1521") . "/{$options['database']}" : $options['database']
                    );

                    if (isset($options["charset"])) {
                        $attr["charset"] = $options["charset"];
                    }
                    break;

                case "mssql":
                    if (isset($options["driver"]) && $options["driver"] === "dblib") {
                        $this->_driver = "dblib";
                        $attr = array(
                            "host" => $options["hostname"] . (isset($port) ? ":{$port}" : ""),
                            "dbname" => $options["database"]
                        );
                    } else {
                        $this->_driver = "sqlsrv";
                        $attr = array(
                            "Server" => $options["hostname"] . (isset($port) ? ",{$port}" : ""),
                            "Database" => $options["database"]
                        );
                    }

                    // Keep MSSQL QUOTED_IDENTIFIER is ON for standard quoting
                    $commands[] = "SET QUOTED_IDENTIFIER ON";
                    // Make ANSI_NULLS is ON for NULL value
                    $commands[] = "SET ANSI_NULLS ON";
                    break;

                case "sqlite":
                    $this->_driver = "sqlite";
                    $attr = array(
                        $options['database']
                    );
                    break;
            }
        }

        $stack = [];
        foreach ($attr as $key => $value) {
            $stack[] = is_int($key) ? $value : "{$key}={$value}";
        }

        $this->_dsn = $this->_driver . ":" . implode($stack, ";");

        if (in_array($this->_dbms, ['mariadb', 'mysql', 'pgsql', 'sybase', 'mssql']) && isset($options['charset'])) {
            $commands[] = "SET NAMES '{$options['charset']}'";
        }

        $this->hostname = $options["hostname"];
        $this->database = $options["database"];
        $this->username = isset($options['username']) ? $options['username'] : null;
        $this->password = isset($options['password']) ? $options['password'] : null;

        $this->_instantiate();

        foreach ($commands as $value) {
            $this->_pdo->exec($value);
        }

        return $this;
    }

    /**
     * Closes a connection
     */
    public function close()
    {
        $this->_pdo = FALSE;
    }

    /**
     * Connect to the database / Instantiate PDO
     *
     * @throws \PDOException
     */
    private function _instantiate()
    {
        try {
            $this->_pdo = new \PDO(
                $this->_dsn,
                $this->username,
                $this->password,
                $this->_options
            );
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage());
        }
    }

    /**
     * Gets the current query string.
     *
     * @return string
     */
    public function getQueryString(): string
    {
        return $this->_queryString;
    }

    /**
     * Changes the currently used table
     *
     * @param string $table The table's name
     *
     * @return LightQL
     */
    public function from($table): LightQL
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Add a where condition
     *
     * @param string|array $condition
     *
     * @return LightQL
     */
    public function where($condition): LightQL
    {
        // where(array('field1'=>'value', 'field2'=>'value'))
        $this->_where = (NULL !== $this->_where) ? "{$this->_where} OR (" : "(";
        if (is_array($condition)) {
            $i = 0;
            $operand = "=";
            foreach ($condition as $field => $value) {
                $this->_where .= ($i > 0) ? " AND " : "";
                if (is_int($field)) {
                    $this->_where .= $value;
                } else {
                    $parts = explode(" ", $value);
                    foreach (self::$operators as $operator) {
                        if (in_array($operator, $parts, TRUE) && $parts[0] === $operator) {
                            $operand = $operator;
                        }
                    }
                    $this->_where .= "{$field} {$operand} " . str_replace($operand, "", $value);
                    $operand = "=";
                }
                ++$i;
            }
        } else {
            $this->_where .= $condition;
        }
        $this->_where .= ")";

        return $this;
    }

    /**
     * Add an order clause.
     *
     * @param string $field
     * @param string $mode
     *
     * @return LightQL
     */
    public function order($field, $mode = "ASC"): LightQL
    {
        $this->_order = " ORDER BY {$field} {$mode} ";
        return $this;
    }

    /**
     * Add a limit clause.
     *
     * @param  int $offset
     * @param  int $count
     *
     * @return LightQL
     */
    public function limit($offset, $count): LightQL
    {
        $this->_limit = " LIMIT {$offset}, {$count} ";
        return $this;
    }

    /**
     * Add a group clause.
     *
     * @param string $field The field used to group results
     *
     * @return LightQL
     */
    public function groupBy($field): LightQL
    {
        $this->_group = $field;
        return $this;
    }

    /**
     * Add a distinct clause.
     *
     * @return LightQL
     */
    public function distinct(): LightQL
    {
        $this->_distinct = TRUE;
        return $this;
    }

    /**
     * Selects data in database.
     *
     * @param  mixed $fields The fields to select. This value can be an array of fields,
     *                             or a string of fields (according to the SELECT SQL query syntax).
     *
     * @throws LightQLException
     *
     * @return \PDOStatement
     */
    public function select($fields = "*"): \PDOStatement
    {
        return $this->_select($fields);
    }

    /**
     * Executes the SELECT SQL query.
     *
     * @param  mixed $fields The fields to select. This value can be an array of fields,
     *                             or a string of fields (according to the SELECT SQL query syntax).
     *
     * @throws LightQLException
     *
     * @return \PDOStatement
     */
    protected function _select($fields): \PDOStatement
    {
        // Constructing the fields list
        if (is_array($fields)) {
            $_fields = "";
            foreach ($fields as $field => $alias) {
                if (is_int($field))
                    $_fields .= "{$alias}, ";
                elseif (is_string($field))
                    $_fields .= "{$field} AS {$alias}, ";
            }
            $fields = trim($_fields, ", ");
        }

        // Constructing the SELECT query string
        $this->_queryString = "SELECT" . (($this->_distinct) ? " DISTINCT " : " ") . "{$fields} FROM {$this->table}" . ((NULL !== $this->_where) ? " WHERE {$this->_where}" : " ") . ((NULL !== $this->_order) ? $this->_order : " ") . ((NULL !== $this->_limit) ? $this->_limit : " ") . ((NULL !== $this->_group) ? "GROUP BY {$this->_group}" : " ");;

        // Preparing the query
        $getFieldsData = $this->prepare($this->_queryString);

        // Executing the query
        if ($getFieldsData->execute() !== FALSE) {
            $this->_reset_clauses();
            return $getFieldsData;
        } else {
            throw new LightQLException($getFieldsData->errorInfo()[2]);
        }
    }

    /**
     * Prepares a query.
     *
     * @uses   \PDO::prepare()
     *
     * @param  string $query The query to execute
     * @param  array $options PDO options
     *
     * @return \PDOStatement
     */
    public function prepare($query, array $options = array()): \PDOStatement
    {
        return $this->_pdo->prepare($query, $options);
    }

    /**
     * Reset all clauses
     * @access protected
     */
    protected function _reset_clauses()
    {
        $this->_distinct = FALSE;
        $this->_where = NULL;
        $this->_order = NULL;
        $this->_limit = NULL;
        $this->_group = NULL;
        $this->_queryString = "";
    }

    /**
     * Selects the first data result of the query.
     *
     * @param  mixed $fields The fields to select. This value can be an array of fields,
     *                             or a string of fields (according to the SELECT SQL query syntax).
     *
     * @throws LightQLException
     *
     * @return array
     */
    public function select_first($fields = "*"): array
    {
        $result = $this->select_array($fields);

        if (count($result) > 0)
            return $result[0];

        return NULL;
    }

    /**
     * Selects data as array of arrays in database.
     *
     * @param  mixed $fields The fields to select. This value can be an array of fields,
     *                             or a string of fields (according to the SELECT SQL query syntax).
     *
     * @throws LightQLException
     *
     * @return array
     */
    public function select_array($fields = "*"): array
    {
        $select = $this->_select($fields);
        $result = array();

        while ($r = $select->fetch(\PDO::FETCH_LAZY)) {
            $result[] = array_diff_key((array)$r, array("queryString" => "queryString"));
        }

        return $result;
    }

    /**
     * Selects data as array of objects in database.
     *
     * @param  mixed $fields The fields to select. This value can be an array of fields,
     *                             or a string of fields (according to the SELECT SQL query syntax).
     *
     * @throws LightQLException
     *
     * @return array
     */
    public function select_object($fields = "*"): array
    {
        $select = $this->_select($fields);
        $result = array();

        while ($r = $select->fetch(\PDO::FETCH_OBJ)) {
            $result[] = $r;
        }

        return $result;
    }

    /**
     * Selects data in database with table joining.
     *
     * @param  mixed $fields The fields to select. This value can be an array of fields,
     *                             or a string of fields (according to the SELECT SQL query syntax).
     * @param  mixed $params The information used for jointures.
     *
     * @throws LightQLException
     *
     * @return \PDOStatement
     */
    public function join($fields, $params): \PDOStatement
    {
        return $this->_join($fields, $params);
    }

    /**
     * Executes a SELECT ... JOIN query.
     *
     * @param  string|array $fields The fields to select. This value can be an array of fields,
     *                              or a string of fields (according to the SELECT SQL query syntax).
     * @param  string|array $params The information used for jointures.
     *
     * @throws LightQLException
     *
     * @return \PDOStatement
     */
    private function _join($fields, $params): \PDOStatement
    {
        $jcond = $params;

        if (is_array($fields)) {
            $fields = implode(",", $fields);
        }

        if (is_array($params)) {
            foreach ($params as $param) {
                $jcond .= " {$param['side']} JOIN {$param['table']} ON {$param['cond']} ";
            }
        }

        $this->_queryString = "SELECT" . (($this->_distinct) ? " DISTINCT " : " ") . "{$fields} FROM {$this->table} {$jcond} " . ((NULL !== $this->_where) ? " WHERE {$this->_where}" : " ") . ((NULL !== $this->_order) ? $this->_order : " ") . ((NULL !== $this->_limit) ? $this->_limit : "");

        $getFieldsData = $this->prepare($this->_queryString);

        if ($getFieldsData->execute() !== FALSE) {
            $this->_reset_clauses();
            return $getFieldsData;
        } else {
            throw new LightQLException($getFieldsData->errorInfo()[2]);
        }
    }

    /**
     * Selects data as array of arrays in database with table joining.
     *
     * @param  mixed $fields The fields to select. This value can be an array of fields,
     *                             or a string of fields (according to the SELECT SQL query syntax).
     * @param  mixed $params The information used for jointures.
     *
     * @throws LightQLException
     *
     * @return array
     */
    public function join_array($fields, $params): array
    {
        $join = $this->_join($fields, $params);
        $result = array();

        while ($r = $join->fetch(\PDO::FETCH_LAZY)) {
            $result[] = array_diff_key((array)$r, array("queryString" => "queryString"));
        }

        return $result;
    }

    /**
     * Selects data as array of objects in database with table joining.
     *
     * @param  mixed $fields The fields to select. This value can be an array of fields,
     *                             or a string of fields (according to the SELECT SQL query syntax).
     * @param  mixed $params The information used for jointures.
     *
     * @throws LightQLException
     *
     * @return array
     */
    public function join_object($fields, $params): array
    {
        $join = $this->_join($fields, $params);
        $result = array();

        while ($r = $join->fetch(\PDO::FETCH_OBJ)) {
            $result[] = $r;
        }

        return $result;
    }

    /**
     * Counts data in table.
     *
     * @param  string|array $fields The fields to select. This value can be an array of fields,
     *                              or a string of fields (according to the SELECT SQL query syntax).
     *
     * @throws LightQLException
     *
     * @return int|array
     */
    public function count($fields = "*")
    {
        if (is_array($fields)) {
            $field = implode(",", $fields);
        }

        $this->_queryString = "SELECT" . ((NULL !== $this->_group) ? "{$this->_group}," : " ") . "COUNT(" . ((isset($field)) ? $field : $fields) . ") AS stormql_count FROM {$this->table}" . ((NULL !== $this->_where) ? " WHERE {$this->_where}" : " ") . ((NULL !== $this->_limit) ? $this->_limit : " ") . ((NULL !== $this->_group) ? "GROUP BY {$this->_group}" : " ");

        $getFieldsData = $this->prepare($this->_queryString);

        if ($getFieldsData->execute() !== FALSE) {
            if (NULL === $this->_group) {
                $this->_reset_clauses();
                $data = $getFieldsData->fetch();
                return (int)$data['stormql_count'];
            }

            $this->_reset_clauses();
            $res = array();
            while ($data = $getFieldsData->fetch()) {
                $res[$data[$this->_group]] = $data['stormql_count'];
            }
            return $res;
        } else {
            throw new LightQLException($getFieldsData->errorInfo()[2]);
        }
    }

    /**
     * Inserts data in table.
     *
     * @param  array $fieldsAndValues The fields and the associated values to insert.
     *
     * @throws LightQLException
     *
     * @return boolean
     */
    public function insert($fieldsAndValues): bool
    {
        $fields = array();
        $values = array();

        foreach ($fieldsAndValues as $field => $value) {
            $fields[] = $field;
            $values[] = $value;
        }

        $field = implode(",", $fields);
        $value = implode(",", $values);

        $this->_queryString = "INSERT INTO {$this->table}({$field}) VALUES({$value})";

        $getFieldsData = $this->prepare($this->_queryString);

        if ($getFieldsData->execute() !== FALSE) {
            $this->_reset_clauses();
            return TRUE;
        } else {
            throw new LightQLException($getFieldsData->errorInfo()[2]);
        }
    }

    /**
     * Updates data in table.
     *
     * @param  array $fieldsAndValues The fields and the associated values to update.
     *
     * @throws LightQLException
     *
     * @return boolean
     */
    public function update($fieldsAndValues): bool
    {
        $updates = "";
        $count = count($fieldsAndValues);

        if (is_array($fieldsAndValues)) {
            foreach ($fieldsAndValues as $field => $value) {
                $count--;
                $updates .= "{$field} = {$value}";
                $updates .= ($count != 0) ? ", " : "";
            }
        } else {
            $updates = $fieldsAndValues;
        }

        $this->_queryString = "UPDATE {$this->table} SET {$updates}" . ((NULL !== $this->_where) ? " WHERE {$this->_where}" : "");

        $getFieldsData = $this->prepare($this->_queryString);

        if ($getFieldsData->execute() !== FALSE) {
            $this->_reset_clauses();
            return TRUE;
        } else {
            throw new LightQLException($getFieldsData->errorInfo()[2]);
        }
    }

    /**
     * Deletes data in table.
     *
     * @throws LightQLException
     *
     * @return boolean
     */
    public function delete(): bool
    {
        $this->_queryString = "DELETE FROM {$this->table}" . ((NULL !== $this->_where) ? " WHERE {$this->_where}" : "");

        $getFieldsData = $this->prepare($this->_queryString);

        if ($getFieldsData->execute() !== FALSE) {
            $this->_reset_clauses();
            return TRUE;
        } else {
            throw new LightQLException($getFieldsData->errorInfo()[2]);
        }
    }

    /**
     * Executes a query.
     *
     * @uses   \PDO::query()
     *
     * @param  string $query The query to execute
     * @param  array $options PDO options
     *
     * @return \PDOStatement
     */
    public function query($query, array $options = array()): \PDOStatement
    {
        return $this->_pdo->query($query, $options);
    }

    /**
     * Quotes a value.
     *
     * @uses   \PDO::quote()
     *
     * @param  string $value
     *
     * @return string
     */
    public function quote($value): string
    {
        return $this->_pdo->quote($value);
    }
}

/**
 * Dummy class used to throw exceptions
 */
class LightQLException extends \Exception
{
}