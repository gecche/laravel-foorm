<?php

namespace Cupparis\Form;

use \Illuminate\Database\Connection;

class ModelDBMethods
{
    /*
         * Metodi per popolare i form automaticamente dal database, funzionano solo con Mysql al momento.
         */

    protected $connection = null;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function dataType($type)
    {
        static $types = array
        (
            'blob' => array('type' => 'string', 'binary' => TRUE, 'character_maximum_length' => '65535'),
            'bool' => array('type' => 'bool'),
            'bigint unsigned' => array('type' => 'int', 'min' => '0', 'max' => '18446744073709551615'),
            'datetime' => array('type' => 'string'),
            'decimal unsigned' => array('type' => 'float', 'exact' => TRUE, 'min' => '0'),
            'double' => array('type' => 'float'),
            'double precision unsigned' => array('type' => 'float', 'min' => '0'),
            'double unsigned' => array('type' => 'float', 'min' => '0'),
            'enum' => array('type' => 'string'),
            'fixed' => array('type' => 'float', 'exact' => TRUE),
            'fixed unsigned' => array('type' => 'float', 'exact' => TRUE, 'min' => '0'),
            'float unsigned' => array('type' => 'float', 'min' => '0'),
            'geometry' => array('type' => 'string', 'binary' => TRUE),
            'int unsigned' => array('type' => 'int', 'min' => '0', 'max' => '4294967295'),
            'integer unsigned' => array('type' => 'int', 'min' => '0', 'max' => '4294967295'),
            'longblob' => array('type' => 'string', 'binary' => TRUE, 'character_maximum_length' => '4294967295'),
            'longtext' => array('type' => 'string', 'character_maximum_length' => '4294967295'),
            'mediumblob' => array('type' => 'string', 'binary' => TRUE, 'character_maximum_length' => '16777215'),
            'mediumint' => array('type' => 'int', 'min' => '-8388608', 'max' => '8388607'),
            'mediumint unsigned' => array('type' => 'int', 'min' => '0', 'max' => '16777215'),
            'mediumtext' => array('type' => 'string', 'character_maximum_length' => '16777215'),
            'national varchar' => array('type' => 'string'),
            'numeric unsigned' => array('type' => 'float', 'exact' => TRUE, 'min' => '0'),
            'nvarchar' => array('type' => 'string'),
            'point' => array('type' => 'string', 'binary' => TRUE),
            'real unsigned' => array('type' => 'float', 'min' => '0'),
            'set' => array('type' => 'string'),
            'smallint unsigned' => array('type' => 'int', 'min' => '0', 'max' => '65535'),
            'text' => array('type' => 'string', 'character_maximum_length' => '65535'),
            'tinyblob' => array('type' => 'string', 'binary' => TRUE, 'character_maximum_length' => '255'),
            'tinyint' => array('type' => 'int', 'min' => '-128', 'max' => '127'),
            'tinyint unsigned' => array('type' => 'int', 'min' => '0', 'max' => '255'),
            'tinytext' => array('type' => 'string', 'character_maximum_length' => '255'),
            'year' => array('type' => 'string'),
            // SQL-92
            'bit' => array('type' => 'string', 'exact' => TRUE),
            'bit varying' => array('type' => 'string'),
            'char' => array('type' => 'string', 'exact' => TRUE),
            'char varying' => array('type' => 'string'),
            'character' => array('type' => 'string', 'exact' => TRUE),
            'character varying' => array('type' => 'string'),
            'date' => array('type' => 'string'),
            'dec' => array('type' => 'float', 'exact' => TRUE),
            'decimal' => array('type' => 'float', 'exact' => TRUE),
            'double precision' => array('type' => 'float'),
            'float' => array('type' => 'float'),
            'int' => array('type' => 'int', 'min' => '-2147483648', 'max' => '2147483647'),
            'integer' => array('type' => 'int', 'min' => '-2147483648', 'max' => '2147483647'),
            'interval' => array('type' => 'string'),
            'national char' => array('type' => 'string', 'exact' => TRUE),
            'national char varying' => array('type' => 'string'),
            'national character' => array('type' => 'string', 'exact' => TRUE),
            'national character varying' => array('type' => 'string'),
            'nchar' => array('type' => 'string', 'exact' => TRUE),
            'nchar varying' => array('type' => 'string'),
            'numeric' => array('type' => 'float', 'exact' => TRUE),
            'real' => array('type' => 'float'),
            'smallint' => array('type' => 'int', 'min' => '-32768', 'max' => '32767'),
            'time' => array('type' => 'string'),
            'time with time zone' => array('type' => 'string'),
            'timestamp' => array('type' => 'string'),
            'timestamp with time zone' => array('type' => 'string'),
            'varchar' => array('type' => 'string'),
            // SQL:1999
            'binary large object' => array('type' => 'string', 'binary' => TRUE),
            'blob' => array('type' => 'string', 'binary' => TRUE),
            'boolean' => array('type' => 'bool'),
            'char large object' => array('type' => 'string'),
            'character large object' => array('type' => 'string'),
            'clob' => array('type' => 'string'),
            'national character large object' => array('type' => 'string'),
            'nchar large object' => array('type' => 'string'),
            'nclob' => array('type' => 'string'),
            'time without time zone' => array('type' => 'string'),
            'timestamp without time zone' => array('type' => 'string'),
            // SQL:2003
            'bigint' => array('type' => 'int', 'min' => '-9223372036854775808', 'max' => '9223372036854775807'),
            // SQL:2008
            'binary' => array('type' => 'string', 'binary' => TRUE, 'exact' => TRUE),
            'binary varying' => array('type' => 'string', 'binary' => TRUE),
            'varbinary' => array('type' => 'string', 'binary' => TRUE),);

        $type = str_replace(' zerofill', '', $type);

        if (!isset($types[$type]))
            return array();


        return $types[$type];
    }

    public function migrationDataType($type)
    {



        $types = array
        (
            'blob' => array('type' => 'text'),
            'bool' => array('type' => 'boolean'),
            'bigint unsigned' => array('type' => 'integer'),
            'datetime' => array('type' => 'dateTime'),
            'decimal unsigned' => array('type' => 'float'),
            'double' => array('type' => 'float'),
            'double precision unsigned' => array('type' => 'float'),
            'double unsigned' => array('type' => 'float'),
            'enum' => array('type' => 'enum'),
            'fixed' => array('type' => 'float'),
            'fixed unsigned' => array('type' => 'float'),
            'float unsigned' => array('type' => 'float'),
            'geometry' => array('type' => 'string'),
            'int unsigned' => array('type' => 'integer'),
            'integer unsigned' => array('type' => 'integer'),
            'longblob' => array('type' => 'text'),
            'longtext' => array('type' => 'text'),
            'mediumblob' => array('type' => 'text'),
            'mediumint' => array('type' => 'integer'),
            'mediumint unsigned' => array('type' => 'integer'),
            'mediumtext' => array('type' => 'text'),
            'national varchar' => array('type' => 'string'),
            'numeric unsigned' => array('type' => 'float'),
            'nvarchar' => array('type' => 'string'),
            'point' => array('type' => 'string'),
            'real unsigned' => array('type' => 'float'),
            'set' => array('type' => 'enum'),
            'smallint unsigned' => array('type' => 'integer'),
            'text' => array('type' => 'text'),
            'tinyblob' => array('type' => 'text'),
            'tinyint' => array('type' => 'integer'),
            'tinyint unsigned' => array('type' => 'integer'),
            'tinytext' => array('type' => 'text'),
            'year' => array('type' => 'string'),
            // SQL-92
            'bit' => array('type' => 'string'),
            'bit varying' => array('type' => 'string'),
            'char' => array('type' => 'string'),
            'char varying' => array('type' => 'string'),
            'character' => array('type' => 'string'),
            'character varying' => array('type' => 'string'),
            'date' => array('type' => 'date'),
            'dec' => array('type' => 'decimal'),
            'decimal' => array('type' => 'decimal'),
            'double precision' => array('type' => 'float'),
            'float' => array('type' => 'float'),
            'integer' => array('type' => 'integer'),
            'int' => array('type' => 'integer'),
            'interval' => array('type' => 'string'),
            'national char' => array('type' => 'string'),
            'national char varying' => array('type' => 'string'),
            'national character' => array('type' => 'string'),
            'national character varying' => array('type' => 'string'),
            'nchar' => array('type' => 'string'),
            'nchar varying' => array('type' => 'string'),
            'numeric' => array('type' => 'float'),
            'real' => array('type' => 'float'),
            'smallint' => array('type' => 'integer'),
            'time' => array('type' => 'string'),
            'time with time zone' => array('type' => 'string'),
            'timestamp' => array('type' => 'string'),
            'timestamp with time zone' => array('type' => 'string'),
            'varchar' => array('type' => 'string'),
            // SQL:1999
            'binary large object' => array('type' => 'string'),
            'blob' => array('type' => 'text'),
            'boolean' => array('type' => 'boolean'),
            'char large object' => array('type' => 'string'),
            'character large object' => array('type' => 'string'),
            'clob' => array('type' => 'string'),
            'national character large object' => array('type' => 'string'),
            'nchar large object' => array('type' => 'string'),
            'nclob' => array('type' => 'string'),
            'time without time zone' => array('type' => 'string'),
            'timestamp without time zone' => array('type' => 'string'),
            // SQL:2003
            'bigint' => array('type' => 'integer'),
            // SQL:2008
            'binary' => array('type' => 'string'),
            'binary varying' => array('type' => 'string'),
            'varbinary' => array('type' => 'string'),
        );

        return $types[$type];
    }

    protected function parseType($type)
    {
        if (($open = strpos($type, '(')) === FALSE) {
            // No length specified
            return array($type, NULL);
        }

        // Closing parenthesis
        $close = strrpos($type, ')', $open);

        // Length without parentheses
        $length = substr($type, $open + 1, $close - 1 - $open);

        // Type without the length
        $type = substr($type, 0, $open) . substr($type, $close + 1);

        return array($type, $length);
    }

    public function listTables()
    {


        $result = $this->connection->select('SHOW TABLES');
        $count = 0;
        $tables = array();
        foreach ($result as $row) {
            $row = (array)$row;
            $table = current($row);

            if ($table) {
                $tables[$table] = $table;
            }
        }

        return $tables;
    }

    public function listColumns($table, $like = NULL, $add_prefix = TRUE)
    {
        // Quote the table name
        //$table = ($add_prefix === TRUE) ? $this->quote_table($table) : $table;
        /*
          if (is_string($like))
          {
          // Search for column names
          $result = $this->query(Database::SELECT, 'SHOW FULL COLUMNS FROM '.$table.' LIKE '.$this->quote($like), FALSE);
          }

          else
          {
          // Find all column names
          $result = $this->query(Database::SELECT, 'SHOW FULL COLUMNS FROM '.$table, FALSE);
          }
         */

        $result = $this->connection->select('SHOW FULL COLUMNS FROM ' . $table);
        $count = 0;
        $columns = array();
        foreach ($result as $row) {
            $row = (array)$row;
            list($type, $length) = $this->parseType($row['Type']);

            $column = $this->dataType($type);

            $column['column_name'] = $row['Field'];
            $column['column_default'] = $row['Default'];
            $column['data_type'] = $type;
            $column['is_nullable'] = ($row['Null'] == 'YES');

            switch ($column['type']) {
                case 'float':
                    if (isset($length)) {
                        list($column['numeric_precision'], $column['numeric_scale']) = explode(',', $length);
                    }
                    break;
                case 'int':
                    if (isset($length)) {
                        // MySQL attribute
                        $column['display'] = $length;
                    }
                    break;
                case 'string':
                    switch ($column['data_type']) {
                        case 'binary':
                        case 'varbinary':
                            $column['character_maximum_length'] = $length;
                            break;
                        case 'char':
                        case 'varchar':
                            $column['character_maximum_length'] = $length;
                        case 'text':
                        case 'tinytext':
                        case 'mediumtext':
                        case 'longtext':
                            $column['collation_name'] = $row['Collation'];
                            break;
                        case 'enum':
                        case 'set':
                            $column['collation_name'] = $row['Collation'];
                            $column['options'] = explode('\',\'', substr($length, 1, -1));
                            break;
                    }
                    break;
            }


            switch ($column['data_type']) {
                case 'enum':
                case 'set':
                    $column['form_type'] = 'list';
                    break;
                case 'date':
                case 'time':
                case 'datetime':
                case 'timestamp':
                    $column['form_type'] = 'datetime';
                    break;
                default:
                    $column['form_type'] = $column['type'];
                    break;
            }


            // MySQL attributes
            $column['comment'] = $row['Comment'];
            $column['extra'] = $row['Extra'];
            $column['key'] = $row['Key'];
            $column['privileges'] = $row['Privileges'];

            $columns[$row['Field']] = $column;
        }

        return $columns;
    }

    public function listColumnsDefault($table, $like = NULL, $add_prefix = TRUE, $raw = false, $single_column = null)
    {
        // Quote the table name
        //$table = ($add_prefix === TRUE) ? $this->quote_table($table) : $table;
        /*
          if (is_string($like))
          {
          // Search for column names
          $result = $this->query(Database::SELECT, 'SHOW FULL COLUMNS FROM '.$table.' LIKE '.$this->quote($like), FALSE);
          }

          else
          {
          // Find all column names
          $result = $this->query(Database::SELECT, 'SHOW FULL COLUMNS FROM '.$table, FALSE);
          }
         */

        $result = $this->connection->select('SHOW COLUMNS FROM ' . $table);

        $columns = array();
        foreach ($result as $row) {
            $row = (array)$row;

            if ($single_column && $row['Field'] !== $single_column) {
                continue;
            }

            list($type, $length) = $this->parseType($row['Type']);


            if ($raw) {
                $columns[$row['Field']] = $row['Default'];
            } else {
                $column = $this->dataType($type);
                $column['column_default'] = $row['Default'];

                switch ($column['type']) {
                    case 'int':
                        switch ($type) {
                            case 'tinyint':
                                if (isset($length) && $length < 2 && $length > 0) {
                                    $column['options'] = array(
                                        0 => ucfirst(trans("app.no")),
                                        1 => ucfirst(trans("app.yes"))
                                    );
                                    if (array_get($row, 'Null', 'YES') == 'YES') {
                                        $column['options'] = array(env('FORM_ITEM_NONE', -99) => trans_uc('app.item_none')) + $column['options'];
                                    }
                                }
                                break;
                        }
                        break;
                    case 'string':
                        switch ($type) {
                            case 'enum':
                            case 'set':
                                $options = explode('\',\'', substr($length, 1, -1));
                                $column['options'] = array_combine($options, $options);


                                if (array_get($row, 'Null', 'YES') == 'YES') {
                                    $column['options'] = array(env('FORM_ITEM_NONE', -99) => trans_uc('app.item_none')) + $column['options'];
                                }

                                break;
                        }
                        break;
                }

                $columns[$row['Field']] = $column;
            }


        }

        if ($single_column) {
            return array_get($columns, $single_column, array());
        }
        return $columns;
    }

    public function listColumnsDatatypes($table)
    {

        $result = $this->connection->select('SHOW COLUMNS FROM ' . $table);
        $columns = array();
        foreach ($result as $row) {
            $row = (array)$row;
            list($type, $length) = $this->parseType($row['Type']);

            $data_type = $this->dataType($type);

            $columns[$row['Field']] = $data_type;
        }

        return $columns;
    }

    public function listColumnsMigrationDatatypes($table)
    {

        $result = $this->connection->select('SHOW COLUMNS FROM ' . $table);
        $columns = array();
        foreach ($result as $row) {
            $row = (array)$row;
            list($type, $length) = $this->parseType($row['Type']);

            $data_type = $this->migrationDataType($type);

            $columns[$row['Field']] = $data_type;
        }

        return $columns;
    }


    public function listEnumValues($table, $column)
    {
        $type = DB::select(DB::raw("SHOW COLUMNS FROM $table WHERE Field = '$column'"))[0]->Type;
        preg_match('/^enum\((.*)\)$/', $type, $matches);
        $enum = array();
        foreach (explode(',', $matches[1]) as $value) {
            $v = trim($value, "'");
            $enum = array_add($enum, $v, $v);
        }
        return $enum;
    }


    public function getLangFields($table, $lang)
    {

        $result = $this->connection->select('SHOW FULL COLUMNS FROM ' . $table);
        $columns = array();
        foreach ($result as $row) {

            $row = (array)$row;
            $field = $row['Field'];


            //ELIMINO I CAMPI NON IN LINGUA
            if (!ends_with($field, '_' . $lang))
                continue;

            list($type, $length) = $this->parseType($row['Type']);

            $column = $this->dataType($type);

            //ELIMINO I CAMPI NON STRING
            if ($column['type'] !== 'string')
                continue;

            //$column['column_default'] = $row['Default'];
            $column['data_type'] = $type;

            $field_to_get = 1;
            switch ($column['data_type']) {
                case 'char':
                case 'varchar':
                    $column['length'] = $length;
                    $column['nullable'] = ($row['Null'] == 'YES');
                    break;
                case 'text':
                case 'tinytext':
                case 'mediumtext':
                case 'longtext':
                    //$column['collation'] = $row['Collation'];
                    $column['type'] = 'text';
                    $column['length'] = null;
                    $column['nullable'] = null;
                    break;

                //case 'binary':
                //case 'varbinary':
                //$column['character_maximum_length'] = $length;
                //break;
                //case 'enum':
                //case 'set':
                //$column['collation_name'] = $row['Collation'];
                //$column['options'] = explode('\',\'', substr($length, 1, -1));
                //    break;
                default:
                    $field_to_get = 0;
                    break;
            }
            if (!$field_to_get)
                continue;

            if (isset($column['character_maximum_length']))
                unset($column['character_maximum_length']);

            $columns[$row['Field']] = $column;
        }

        return $columns;
    }

    public function getDBLangFields($lang)
    {
        $result = $this->connection->select('SHOW TABLES');

        $tables = array();
        foreach ($result as $row) {

            $row = (array)$row;
            $table = current($row);
            $columns = $this->getLangFields($table, $lang);

            $tables[$table] = $columns;
        }

        return $tables;

    }


    /*
     * Fine dei metodi per popolare i form automaticamente dal database.
     */

}
