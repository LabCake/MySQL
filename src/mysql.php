<?php

/**
 * A simplified mysqli wrapper for php
 * @author LabCake
 * @copyright 2018 LabCake
 * @license http://www.apache.org/licenses/LICENSE-2.0
 */

namespace LabCake;

class MySQL implements \ArrayAccess, \Iterator, \JsonSerializable
{

    /**
     * @var \mysqli
     */
    protected $link_id;

    /**
     * @var \mysqli_result
     */
    protected $query_id;

    protected $record = array();

    protected $sort;
    protected $limit;
    protected $columns;
    protected $distinct;

    protected $valueData = array();

    protected $table_prefix = "";
    protected $columnPrefix = "";

    protected $table;
    protected $query;
    protected $join = array();

    protected $index = 0;

    private static $s_options = array(
        'hostname' => "",
        'username' => "",
        'password' => "",
        'database' => "",
        'prefix' => "",
        'charset' => "utf8",
        'close' => true, // close mysqli connection on class destruction
        'error' => true, // error logging
        'error.exit' => true
    );

    function __construct($table = null)
    {
        $this->connect();

        if ($table != null)
            $this->setTable($table);
    }

    final private function connect()
    {
        $link = @new \mysqli(self::$s_options['hostname'], self::$s_options['username'], self::$s_options['password']);


        if ($link->connect_error)
            $this->__error("Error number: %s<br (>Could not connect to database &quot;%s@%s&quot;", $link->connect_errno, self::$s_options['username'], self::$s_options['hostname']);

        if (self::$s_options['charset'] != "")
            $link->set_charset(self::$s_options['charset']);

        $link->select_db(self::$s_options['database']);

        if (!empty($link->error))
            $this->__error($link->error);
        $this->link_id = $link;
    }

    final public function getTable()
    {
        return $this->table_prefix . $this->table;
    }

    final public function setTablePrefix($value)
    {
        $this->table_prefix = $value;
    }

    final public function setTable($table)
    {
        $this->table = $table;
    }

    final public function setDistinct($distinct)
    {
        $this->distinct = $distinct;
    }

    final public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    final public function setSort($string)
    {
        $this->sort[] = $string;
    }

    final public function setColumns($columns)
    {
        $this->columns = $columns;
    }

    final public function setColumn($column)
    {
        $this->columns[] = $column;
    }

    final public function setColumnPrefix($prefix)
    {
        $this->columnPrefix = $prefix;
        if (is_array($this->columns)) {
            foreach ($this->columns as $key => $value) {
                $this->columns[$key] = $prefix . $value;
            }
        }
        if (is_array($this->valueData)) {
            foreach ($this->valueData as $key => $value) {
                $this->valueData[$prefix . $key] = $value;
                unset($this->valueData[$key]);
            }
        }
    }

    final public function escape($string)
    {
        return $this->link_id->real_escape_string($string);
    }

    final private function getColNames(array $colArray)
    {
        $colNames = array();
        foreach ($colArray as $col => $val)
            $colNames[] = $col;
        return $colNames;
    }

    final public function f($name)
    {
        if (!isset($this->record[$name]))
            return false;

        return $this->record[$name];
    }

    final public function getAffectedRows()
    {
        return $this->link_id->affected_rows;
    }

    final public function getNumFields()
    {
        return $this->link_id->field_count;
    }

    final public function getNumRows()
    {
        return $this->query_id->num_rows;
    }

    final public function getLink()
    {
        return $this->link_id;
    }

    final public function __set($key, $val)
    {
        $this->valueData[$key] = $val;
    }

    final public function __get($key)
    {
        if (isset($this->colPrefix)) {
            return $this->f($this->columnPrefix . $key);
        } else {
            return $this->f($key);
        }
    }

    final public function freeResults()
    {
        $this->record = array();
    }

    public function close()
    {
        if($this->link_id && $this->link_id instanceof \mysqli)
        $this->link_id->close();
    }

    final private function getJoinType($key)
    {
        $key = strtolower($key);
        switch ($key) {
            default:
            case "left":
                return "LEFT JOIN";
                break;
            case "inner":
                return "INNER JOIN";
                break;
            case "right":
                return "RIGHT JOIN";
                break;
        }
    }

    final public function join($type, $table, $tableAndColA, $tableAndColB)
    {
        $this->join[] = sprintf("%s %s ON %s = %s", $this->getJoinType($type), $this->table_prefix . $table, $tableAndColA, $tableAndColB);
    }

    final public function query($sql)
    {
        $this->query_id = @$this->link_id->query($sql);

        if (!$this->link_id) {
            $this->__error("Connection to mysqli halted");
        }

        if ($this->link_id->error)
            $this->__error($this->link_id->error);

        if (!$this->query_id) {
            $this->__error("Mysqli query id invalid");
        }
        return $this->query_id;
    }

    final public function select($whereStatement = "")
    {
        $where = preg_replace("/where/", "", $whereStatement);
        $args = func_get_args();
        if (count($args) > 1) {
            array_shift($args);
            $where = vsprintf($where, (array_map(array($this, "escape"), $args)));
        }

        $query = array();
        $query[] = ($this->distinct) ? "DISTINCT" : "";
        $query[] = (isset($this->columns)) ? implode(", ", $this->columns) . " \n" : "* ";
        $query[] = $this->getTable();
        $query[] = (count($this->join) > 0) ? implode("\n", $this->join) . "\n" : "";
        $query[] = (!empty($where)) ? "WHERE " . $where . "\n" : "";
        $query[] = (is_array($this->sort)) ? " ORDER BY " . implode(",", $this->sort) : "";
        $query[] = (isset($this->limit)) ? " LIMIT " . $this->limit : "";

        $this->query = vsprintf("SELECT %s %s FROM %s %s %s %s %s", $query);

        return $this->query($this->query);
    }

    final public function insert($update = false)
    {
        $numCols = count($this->valueData);
        if ($numCols == 0)
            $this->__error("No values set for insertion. Use \$this->column_name = \"value\";");

        $colNames = $this->getColNames($this->valueData);
        $values = array();

        foreach ($this->valueData as $key => $val) {
            $value = $val;
            if (is_array($val)) {
                $value = json_encode($value);
            }

            if (is_object($val)) {
                $value = serialize($value);
            }
            $values[$key] = $value;
        }

        $colValues = array_map(array($this, "escape"), $values);
        $this->query = sprintf("INSERT INTO %s (%s) VALUES('%s')", $this->getTable(), (isset($this->columnPrefix)) ? implode(", " . $this->columnPrefix, $colNames) : implode(", ", $colNames), implode("', '", $colValues));

        if ($update) {
            $valueData = array_map(array($this, "escape"), $this->valueData);
            $c = 1;
            $this->query .= "ON DUPLICATE key UPDATE ";
            foreach ($valueData as $col => $val) {
                $this->query .= (isset($this->colPrefix)) ? $this->colPrefix . $col . " = '" . $val . "'" : $col . " = '" . $val . "'";
                $this->query .= ($c < $numCols) ? "," : "";
                $c++;
            }
        }
        return $this->query($this->query);
    }

    final public function Update($whereStatement = "")
    {
        $where = preg_replace("/where/", "", $whereStatement);
        $args = func_get_args();
        if (count($args) > 1) {
            array_shift($args);
            $where = vsprintf($where, (array_map(array($this, "escape"), $args)));
        }

        $numCols = count($this->valueData);
        $valueData = array_map(array($this, "escape"), $this->valueData);

        if ($numCols == 0)
            $this->__error("No values set for update. Use \$this->column_name = \"value\";");

        $this->query = "UPDATE ";
        $this->query .= $this->getTable();
        $this->query .= " SET\n";
        $c = 1;
        foreach ($valueData as $col => $val) {
            if (is_array($val)) {
                $val = json_encode($val);
            }

            if (is_object($val)) {
                $val = serialize($val);
            }
            $this->query .= (isset($this->colPrefix)) ? $this->colPrefix . $col . " = '" . $val . "'" : $col . " = '" . $val . "'";
            $this->query .= ($c < $numCols) ? ",\n" : "";
            $c++;
        }
        $this->query .= "\n";
        if (!empty($where)) {
            if (strtolower($where) != "all") {
                $this->query .= "WHERE " . $where;
            }
        }
        $this->query .= (isset($this->limit)) ? "\nLIMIT " . $this->limit : "";
        return $this->query($this->query);
    }

    final public function delete($whereStatement = "")
    {
        $where = preg_replace("/where/", "", $whereStatement);
        $args = func_get_args();
        if (count($args) > 1) {
            array_shift($args);
            $where = vsprintf($where, (array_map(array($this, "escape"), $args)));
        }
        $this->query = "DELETE FROM ";
        $this->query .= $this->getTable();
        $this->query .= (!empty($where)) ? " WHERE " . $where : "";
        return $this->query($this->query);
    }

    final public function count($whereStatement = "")
    {
        $where = preg_replace("/where/", "", $whereStatement);
        $args = func_get_args();
        if (count($args) > 1) {
            array_shift($args);
            $where = vsprintf($where, (array_map(array($this, "escape"), $args)));
        }
        $columns = $this->columns;

        $this->setColumn("COUNT(*) as crows");
        $query = array();
        $query[] = ($this->distinct) ? "DISTINCT" : "";
        $query[] = (isset($this->columns)) ? implode(", ", $this->columns) . " \n" : "* ";
        $query[] = $this->getTable();
        $query[] = (count($this->join) > 0) ? implode("\n", $this->join) . "\n" : "";
        $query[] = (!empty($where)) ? "WHERE " . $where . "\n" : "";
        $query[] = (isset($this->group)) ? "GROUP BY " . $this->group : "";
        $query[] = (is_array($this->sort)) ? " ORDER BY " . implode(",", $this->sort) : "";
        $query[] = (isset($this->limit)) ? " LIMIT " . $this->limit : "";

        $this->query = vsprintf("SELECT %s %s FROM %s %s %s %s %s", $query);
        $this->query($this->query);
        $this->record();
        $this->query_id = null;
        $this->setColumns($columns);
        return intval($this->crows);
    }

    final public function record($result_type = "assoc")
    {
        if (!$this->query_id)
            $this->__error("Record() called with no pending query.");

        switch ($result_type) {
            default:
                $this->record = $this->query_id->fetch_assoc();
                break;
            case "num":
                $this->record = $this->query_id->fetch_row();
                break;
            case "both":
                $this->record = $this->query_id->fetch_array();
                break;
        }

        if (!empty($this->link_id->error))
            $this->__error($this->link_id->error);

        return $this->record;
    }

    final static public function setOption($option, $value)
    {
        $option = strtolower($option);
        if (array_key_exists($option, self::$s_options)) {
            $option_value = self::$s_options[$option];
            if ((is_bool($option_value) && !is_bool($value)) || (is_long($option_value) && !is_long($value)) || (is_string($option_value) && !is_string($value))) {
                return self::__error("Illegal option value");
            }
            self::$s_options[$option] = $value;
        } else {
            return self::__error("No such option '%s'", $option);
        }
        return true;
    }

    final function __destruct()
    {
        if (self::$s_options['close'])
            $this->Close();
    }

    final static private function __error()
    {
        if (self::$s_options['error']) {
            $debug = debug_backtrace();
            if ($debug && isset($debug[1])) {
                if (func_num_args()) {
                    $args = func_get_args();
                    $format = array_shift($args);
                    $func_args = "";
                    $arg_count = count($debug[1]['args']);
                    for ($i = 0; $i < $arg_count; $i++) {
                        $func_args .= var_export($debug[1]['args'][$i], true);
                        if ($i != ($arg_count - 1)) {
                            $func_args .= "<b>,</b> ";
                        }
                    }
                    printf("<div style='background: #f8d7da; padding: 10px 18px; border: 1px solid #f5c6cb; color: #721c24;'><strong>%s</strong>::%s<strong>(</strong>%s<strong>):</strong> %s</div>",
                        $debug[1]['class'], $debug[1]['function'], $func_args, vsprintf($format, $args));
                    if (self::$s_options['error.exit']) {
                        exit;
                    }
                }
            }
        }
        return false;
    }

    final public function rewind()
    {
        $this->index = 0;
    }

    final public function current()
    {
        $k = array_keys($this->record);
        $var = $this->record[$k[$this->index]];
        return $var;
    }

    final public function key()
    {
        $k = array_keys($this->record);
        $var = $k[$this->index];
        return $var;
    }

    final public function next()
    {
        $k = array_keys($this->record);
        if (isset($k[++$this->index])) {
            $var = $this->record[$k[$this->index]];
            return $var;
        } else {
            return false;
        }
    }

    final public function valid()
    {
        $k = array_keys($this->record);
        $var = isset($k[$this->index]);
        return $var;
    }

    final public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    final public function offsetExists($offset)
    {
        return isset($this->record[$offset]);
    }

    final public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->record[$offset]);
        }
    }

    final public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->record[$offset] : null;
    }

    final public function json()
    {
        return json_encode($this->record);
    }

    final public function jsonSerialize()
    {
        return $this->record;
    }

}