<?php
/**
 * PDO自製封裝Class
 *
 * 相容兩種寫法,自動繫結與引數形式,引數形式寫法優先於自動繫結
 * 自動繫結寫法:
 * Example-1:初始化
 * // step_1:引入檔案
 * require_once 'xxx.php';
 * // 連線資訊(可省略,可在檔案內寫好配置)
 * MYPDO::$host = '127.0.0.1';
 * MYPDO::$user = 'root';
 * MYPDO::$pwd = 'root';
 * MYPDO::$db = 'test';
 * // step_2:繫結操作資訊
 * MYPDO::$tbale = 'tablename';
 * // step_3:開始使用
 * MYPDO::select();// 該操作最終執行的語句是:SELECT * FROM `tablename`
 * 更多範例用法請參閱說明手冊
 * 說明手冊NAS路徑:/RD/專案/ASTRA⁺/程式說明文件/PDO Class 使用說明.odoc
 */
class MYPDO
{
// Host address
    public static $host = '127.0.0.1';
// Host port
    public static $port = 3306;
// Username
    public static $user = '';
// Password
    public static $pwd = '';
// Database
    public static $db = '';
// Character set
    public static $charset = 'utf8mb4';
// Persistent connection
    public static $pconnect = true;
// Connection object
    public static $conn = null;
// Table name
    public static $table = '';
// Core container
    public static $data = '';
    public static $field = '*';
    public static $where = '';
    public static $order = '';
    public static $group = '';
    public static $limit = '';
    public static $join = '';
    public static $bind = [];
    public static $sql = '';

// Initialization
    public static function init(array $conf = array(), bool $reconnect = false): void
    {
        class_exists('PDO') or exit("PDO: class not exists.");
        empty($conf['host']) or self::$host = $conf['host'];
        empty($conf['port']) or self::$port = $conf['port'];
        empty($conf['user']) or self::$user = $conf['user'];
        empty($conf['pwd']) or self::$pwd = $conf['pwd'];
        empty($conf['db']) or self::$db = $conf['db'];
        empty($conf['table']) or self::$table = $conf['table'];
        if (is_null(self::$conn) || $reconnect) self::$conn = self::_connect();
    }

// Query or Exec

    protected static function _connect(): void
    {
        $dsn = 'mysql:host=' . self::$host . ';port=' . self::$port . ';dbname=' . self::$db;
        $options = [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . self::$charset,
            PDO::ATTR_PERSISTENT => (bool)self::$pconnect
        ];
        try {
            $dbh = new PDO($dsn, self::$user, self::$pwd, $options);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $dbh->exec('SET NAMES ' . self::$charset);
        } catch (PDOException $e) {
            exit('Connection failed: ' . $e->getMessage());
        }
        self::$conn = $dbh;
        unset($dsn, $dbh, $options);
    }

// Query

    public static function do(string $sql = '', bool $flag = false)
    {
        empty($sql) or self::$sql = $sql;
        $preg = 'INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|LOAD DATA|SELECT .* INTO|COPY|ALTER|GRANT|REVOKE|LOCK|UNLOCK';
        if (preg_match('/^\s*"?(' . $preg . ')\s /i', self::$sql)) return self::exec('', $flag);
        else return self::query('', self::$sql);
    }

// Exec

    public static function exec(string $sql = '', bool $flag = false): int
    {
        $statm = self::_start($sql);
        $row = $statm->rowCount();
        return $flag ? self::$conn->lastInsertId() : $row;
    }

// Insert

    protected static function _start(string $sql = '')
    {
        empty($sql) or self::$sql = $sql;
        !empty(self::$conn) or self::_connect();
        $statm = self::$conn->prepare(self::$sql);
        $statm->execute(self::$bind);
        self::clear();
        return $statm;
    }

// Delete

    public static function clear(): void
    {
        self::$data = '';
        self::$field = '*';
        self::$where = '';
        self::$order = '';
        self::$group = '';
        self::$limit = '';
        self::$join = '';
        self::$bind = [];
    }

// Update

    public static function query(string $sql = '', bool $flag = false): array
    {
        $statm = self::_start($sql);
        $result = $statm->fetchAll(PDO::FETCH_ASSOC);
        return $flag ? $result[0] : $result;
    }

// Select

    public static function insert(string $table = '', array $data = [], bool $flag = false): int
    {
        $table = !empty($data) ? $table : self::$table;
        $data = !empty($data) ? $data : self::$data;
        $insertData = [];
        if (count($data) == count($data, 1)) $insertData[0] = $data;
        else $insertData = $data;
        $lastId = 0;
        $row = 0;
        foreach ($insertData as $key => $data) {
            $data = self::_format($table, $data);
            $vals = [];
            foreach ($data as $key => $value) {
                $vals[] = self::_setBind(str_replace('`', '', $key), $value);
            }
            $keys = array_keys($data);
            self::$sql = 'INSERT INTO `' . trim($table) . '` (' . implode(',', $keys) . ') VALUES(' . implode(',', $vals) . ')';
            self::exec() && $flag && $row = 1;
        }
        $lastId = self::$conn->lastInsertId();
        unset($insertData, $data);
        return $flag ? $row : $lastId;
    }

// Get a line

    protected static function _format(string $table, $data): array
    {
        if (!is_array($data)) return array();
        $tbColumn = self::_tbInfo($table);
        $res = [];
        foreach ($data as $key => $val) {
            if (!is_scalar($val) && !is_null($val)) continue;
            if (!empty($tbColumn[$key])) {
                $key = self::_avoidKey($key);
                if (is_int($val)) $val = intval($val);
                elseif (is_float($val)) $val = floatval($val);
                elseif (preg_match('/^\(\w*(\ |\-|\*|\/)?\w*\)$/i', $val)) $val = $val;
                elseif (is_string($val)) $val = addslashes($val);
                $res[$key] = $val;
            }
        }
        unset($data);
        return $res;
    }

// Count

    protected static function _tbInfo(string $table = ''): array
    {
        $table = !empty($table) ? $table : self::$table;
        $sql = 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME="' . $table . '" AND TABLE_SCHEMA="' . self::$db . '"';
        !empty(self::$conn) or self::_connect();
        $statm = self::$conn->prepare($sql);
        $statm->execute();
        $result = $statm->fetchAll(PDO::FETCH_ASSOC);
        $res = [];
        foreach ($result as $key => $value) {
            $res[$value['COLUMN_NAME']] = 1;
        }
// unset($result, $statm);
        return $res;
    }

// Avg

    protected static function _avoidKey(string $value): string
    {
        if ('*' == $value || false !== strpos($value, '(') || false !== strpos($value, '.')) ;
        elseif (false === strpos($value, '`')) $value = '`' . trim($value) . '`';
        return $value;
    }

// Sum

    protected static function _setBind(string $key, $value): string
    {
        if (empty(self::$bind[':' . $key])) {
            $k = ':' . $key;
            self::$bind[$k] = $value;
        } else {
            $k = ':' . $key . '_' . mt_rand(1, 9999);
            while (!empty(self::$bind[':' . $k])) {
                $k = ':' . $key . '_' . mt_rand(1, 9999);
            }
            self::$bind[$k] = $value;
        }
        unset($key, $value);
        return $k;
    }

// Min

    public static function del(string $table = '', array $where = []): int
    {
        $table = !empty($data) ? $table : self::$table;
        $where = !empty($where) ? self::_where($where) : self::_where(self::$where);
        if ('' === $where) return 0;
        self::$sql = 'DELETE FROM `' . trim($table) . '` ' . $where;
        unset($table, $where);
        return self::exec();
    }

// Max

    protected static function _where($opt): string
    {
        $where = '';
        if (is_string($opt) && '' !== trim($opt)) return ' WHERE ' . $opt;
        elseif (is_array($opt)) {
            foreach ($opt as $key => $value) {
                $k = self::_avoidKey($key);
                if (is_array($value)) {
                    $key = self::_setBind($key, $value[0]);
                    $relative = !empty($value[1]) ? $value[1] : '=';
                    $link = !empty($value[2]) ? $value[2] : 'AND';
                    $condition = ' (' . $k . ' ' . $relative . ' ' . $key . ') ';
                } else {
                    $key = self::_setBind($key, $value);
                    $link = 'AND';
                    $condition = ' (' . $k . '=' . $key . ') ';
                }
                $where .= $where !== '' ? $link . $condition : ' WHERE ' . $condition;
            }
        }
        unset($opt);
        return $where;
    }

// Dec

    public static function save(string $table = '', array $data = [], $where = []): int
    {
        $table = !empty($table) ? $table : self::$table;
        $data = !empty($data) ? $data : self::$data;
        $where = !empty($where) ? $where : self::$where;
        if (false == $where) {
            $key = self::_tbKey($table);
            $where = [];
            foreach ($key as $k => $v) {
                empty($data[$k]) or $where[$k] = $data[$k];
            }
            $where = self::_where($where);
        } else $where = self::_where($where);
        $data = self::_format($table, $data);
        $kv = [];
        foreach ($data as $key => $value) {
            $k = str_replace('`', '', $key);
            $k = self::_setBind($k, $value);
            $kv[] = $key . '=' . $k;
        }
        $kv_str = implode(',', $kv);
        self::$sql = 'UPDATE `' . trim($table) . '` SET ' . trim($kv_str) . ' ' . trim($where);
        unset($kv_str, $data, $kv, $table);
        if ('' === $where) return 0;
        return self::exec();
    }

// Inc

    protected static function _tbKey(string $table): array
    {
        $sql = 'SELECT k.column_name FROM information_schema.table_constraints t JOIN information_schema.key_column_usage k USING (constraint_name,table_schema,table_name) WHERE t.constraint_type="PRIMARY KEY" AND t.table_schema="' . self::$db . '" AND t.table_name="' . $table . '"';
        !empty(self::$conn) or self::_connect();
        $statm = self::$conn->prepare($sql);
        $statm->execute();
        $result = $statm->fetchAll(PDO::FETCH_ASSOC);
        $res = [];
        foreach ($result as $key => $value) {
            $res[$value['column_name']] = 1;
        }
        unset($result, $statm);
        return $res;
    }

// Clear

    public static function first(string $table = '', array $opt = []): array
    {
        self::$limit = '1';
        $result = self::select($table, $opt);
        if (!empty($result[0]))
            return $result[0];
        else
            return array();
    }

// SetAttribute

    public static function select(string $table = '', array $opt = []): array
    {
        $opt = self::_condition($table, $opt);
        $field = $opt['field'] = !empty($opt['field']) ? $opt['field'] : self::$field;
        if (is_array($field)) {
            foreach ($field as $key => $value) $field[$key] = self::_avoidKey($value);
            $field = implode(',', $field);
        } elseif (is_string($field) && $field != '') ;
        else $field = '*';
        self::$sql = 'SELECT ' . $field . ' FROM `' . $opt['table'] . '` ' . $opt['join'] . $opt['where'] . $opt['group'] . $opt['order'] . $opt['limit'];
        unset($opt);
        return self::query();
    }

// BeginTransaction

    protected static function _condition(string $table, array $opt): array
    {
        $option = [];
        $option['table'] = !empty($table) ? $table : self::$table;
        $option['field'] = !empty($opt['field']) ? $opt['field'] : self::$field;
        $option['join'] = !empty($opt['join']) ? self::_join($opt['join']) : self::_join(self::$join);
        $option['where'] = !empty($opt['where']) ? self::_where($opt['where']) : self::_where(self::$where);
        $option['order'] = !empty($opt['order']) ? self::_order($opt['order']) : self::_order(self::$order);
        $option['group'] = !empty($opt['group']) ? self::_group($opt['group']) : self::_group(self::$group);
        $option['limit'] = !empty($opt['limit']) ? self::_limit($opt['limit']) : self::_limit(self::$limit);
        return $option;
    }

// Commit

    protected static function _join($opt): string
    {
        $join = '';
        if (is_string($opt) && '' !== trim($opt)) return $opt;
        elseif (is_array($opt)) {
            foreach ($opt as $key => $value) {
                $mode = 'INNER';
                if (is_array($value)) {
                    if (!empty($value[2]) && 0 === strcasecmp($value[2], 'LEFT')) $mode = 'LEFT';
                    elseif (!empty($value[2]) && 0 === strcasecmp($value[2], 'RIGHT')) $mode = 'RIGHT';
                    $relative = !empty($value[3]) ? $value[3] : '=';
                    $condition = ' ' . $mode . ' JOIN ' . $key . ' ON ' . self::_avoidKey($value[0]) . $relative . self::_avoidKey($value[1]) . ' ';
                } else {
                    $condition = ' ' . $mode . ' JOIN ' . $key . ' ON ' . $value . ' ';
                }
                $join .= $condition;
            }
        }
        unset($opt);
        return $join;
    }

// RollBack

    protected static function _order($opt): string
    {
        $order = '';
        if (is_string($opt) && '' !== trim($opt)) return ' ORDER BY ' . _avoidKey($opt);
        elseif (is_array($opt)) {
            foreach ($opt as $key => $value) {
                $link = ',';
                if (is_string($key)) {
                    if (0 === strcasecmp($value, 'DESC')) $condition = ' ' . self::_avoidKey($key) . ' DESC ';
                    else $condition = ' ' . self::_avoidKey($key) . ' ASC ';
                } else $condition = ' ' . self::_avoidKey($value) . ' ASC ';
                $order .= $order !== '' ? $link . addslashes($condition) : ' ORDER BY ' . addslashes($condition);
            }
        }
        unset($opt);
        return $order;
    }

// Connect

    protected static function _group($opt): string
    {
        $group = '';
        if (is_string($opt) && '' !== trim($opt)) return ' GROUP BY ' . _avoidKey($opt);
        elseif (is_array($opt)) {
            foreach ($opt as $key => $value) {
                $link = ',';
                $condition = ' ' . self::_avoidKey($value) . ' ';
                $group .= $group !== '' ? $link . addslashes($condition) : ' GROUP BY ' . addslashes($condition);
            }
        }
        unset($opt);
        return $group;
    }

// Mosaic SQL

    protected static function _limit($opt): string
    {
        $limit = '';
        if (is_string($opt) && '' !== trim($opt)) return ' LIMIT ' . $opt;
        elseif (is_array($opt) && 2 == count($opt)) $limit = ' LIMIT ' . (int)$opt[0] . ',' . (int)$opt[1];
        elseif (is_array($opt) && 1 == count($opt)) $limit = ' LIMIT ' . (int)$opt[0];
        unset($opt);
        return $limit;
    }

// Exec SQL common function

    public static function count(string $table = '', array $opt = []): array
    {
        $option = self::_condition($table, $opt);
        return self::_common($option, 'count');
    }

// Common

    protected static function _common(array $opt, string $func): array
    {
        if (is_string($opt['field']) && $opt['field'] != "") {
            $strField = $opt['field'];
            $fieldArr = explode(",", $strField);
            $strField = '_' . implode("_,_", $fieldArr) . '_';
        } elseif (is_array($opt['field'])) {
            $fieldArr = $opt['field'];
            $strField = '_' . implode("_,_", $opt['field']) . '_';
        } else return false;
        foreach ($fieldArr as $v) {
            $val = self::_avoidKey($v);
            $alias = str_replace('.', '_', $val);
            $alias = ' AS ' . (false === strpos($val, '*') ? $alias : '`' . $alias . '`');
            $strField = str_replace('_' . $v . '_', $func . '(' . $val . ') ' . $alias, $strField);
        }
        self::$sql = 'SELECT ' . $strField . ' FROM `' . $opt['table'] . '` ' . $opt['join'] . $opt['where'] . $opt['group'] . $opt['order'] . $opt['limit'];
        unset($opt, $func, $fieldArr, $strField, $alias);
        $result = self::query();
        return count($result) == 1 && !empty($result[0]) ? $result[0] : $result;
    }

// Set field

    public static function avg(string $table = '', array $opt = []): array
    {
        $option = self::_condition($table, $opt);
        return self::_common($option, 'avg');
    }

// Preprocessing

    public static function sum(string $table = '', array $opt = []): array
    {
        $option = self::_condition($table, $opt);
        return self::_common($option, 'sum');
    }

// Join

    public static function min(string $table = '', array $opt = []): array
    {
        $option = self::_condition($table, $opt);
        return self::_common($option, 'min');
    }

// Where

    public static function max(string $table = '', array $opt = []): array
    {
        $option = self::_condition($table, $opt);
        return self::_common($option, 'max');
    }

// Order

    public static function dec(string $table = '', $data = [], $where = []): int
    {
        return self::_setCol($table, $data, $where, '-');
    }

// Limit

    protected static function _setCol(string $table = '', $data = '', $where = [], string $type): int
    {
        $table = !empty($table) ? $table : self::$table;
        $data = !empty($data) ? $data : self::$data;
        $where = !empty($where) ? self::_where($where) : self::_where(self::$where);
        if (is_array($data)) {
            $new_data = [];
            foreach ($data as $key => $value) {
                if (is_string($key)) $new_data[$key] = $key . $type . abs($value);
                else $new_data[$value] = $value . $type . '1';
            }
        } elseif (is_string($data)) $new_data[$data] = $data . $type . '1';
        $kv = [];
        foreach ($new_data as $key => $value) {
            $kv[] = self::_avoidKey($key) . '=' . $value;
        }
        $kv_str = implode(',', $kv);
        self::$sql = 'UPDATE `' . trim($table) . '` SET ' . trim($kv_str) . ' ' . trim($where);
        unset($data);
        if ('' === $where) return 0;
        return self::exec();
    }

// Group

    public static function inc(string $table = '', $data = [], $where = []): int
    {
        return self::_setCol($table, $data, $where, ' ');
    }

// Format data

    public static function setAttr($key, $val): bool
    {
        !empty(self::$conn) or self::_connect();
        return self::$conn->setAttribute($key, $val);
    }

// Table info

    public static function begin(): bool
    {
        !empty(self::$conn) or self::_connect();
        return self::$conn->beginTransaction();
    }

// Get primary key

    public static function commit(): bool
    {
        !empty(self::$conn) or self::_connect();
        return self::$conn->commit();
    }

// Avoid mistakes

    public static function rollBack(): bool
    {
        !empty(self::$conn) or self::_connect();
        return self::$conn->rollBack();
    }
}
