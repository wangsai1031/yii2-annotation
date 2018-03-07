<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db\mysql;

use yii\db\Expression;
use yii\db\TableSchema;
use yii\db\ColumnSchema;

/**
 * Schema is the class for retrieving metadata from a MySQL database (version 4.1.x and 5.x).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Schema extends \yii\db\Schema
{
    /**
     * // 定义从数据库数据类型到n个抽象数据类型间的映射关系
     *
     * @var array mapping from physical column types (keys) to abstract column types (values)
     */
    public $typeMap = [
        'tinyint' => self::TYPE_SMALLINT,
        'bit' => self::TYPE_INTEGER,
        'smallint' => self::TYPE_SMALLINT,
        'mediumint' => self::TYPE_INTEGER,
        'int' => self::TYPE_INTEGER,
        'integer' => self::TYPE_INTEGER,
        'bigint' => self::TYPE_BIGINT,
        'float' => self::TYPE_FLOAT,
        'double' => self::TYPE_DOUBLE,
        // REAL就是DOUBLE ，如果SQL服务器模式包括REAL_AS_FLOAT选项，REAL是FLOAT的同义词而不是DOUBLE的同义词。
        'real' => self::TYPE_FLOAT,
        'decimal' => self::TYPE_DECIMAL,
        // 在myslq5.0中，numeric和decimal数据类型是一致的,两者的精度均准确为M位数字。
        'numeric' => self::TYPE_DECIMAL,
        'tinytext' => self::TYPE_TEXT,
        'mediumtext' => self::TYPE_TEXT,
        'longtext' => self::TYPE_TEXT,
        'longblob' => self::TYPE_BINARY,
        'blob' => self::TYPE_BINARY,
        'text' => self::TYPE_TEXT,
        'varchar' => self::TYPE_STRING,
        'string' => self::TYPE_STRING,
        'char' => self::TYPE_CHAR,
        'datetime' => self::TYPE_DATETIME,
        'year' => self::TYPE_DATE,
        'date' => self::TYPE_DATE,
        'time' => self::TYPE_TIME,
        'timestamp' => self::TYPE_TIMESTAMP,
        'enum' => self::TYPE_STRING,
    ];


    /**
     * Quotes a table name for use in a query.
     * A simple table name has no schema prefix.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteSimpleTableName($name)
    {
        return strpos($name, '`') !== false ? $name : "`$name`";
    }

    /**
     * Quotes a column name for use in a query.
     * A simple column name has no prefix.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteSimpleColumnName($name)
    {
        return strpos($name, '`') !== false || $name === '*' ? $name : "`$name`";
    }

    /**
     * Creates a query builder for the MySQL database.
     * @return QueryBuilder query builder instance
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this->db);
    }

    /**
     * 字段信息的获取和填充
     *
     * 该函数用于加载数据表信息 yii\db\TableSchema ，这是一个抽象函数，具体由各子类实现。
     *
     * Loads the metadata for the specified table.
     * @param string $name table name
     * @return TableSchema driver dependent table metadata. Null if the table does not exist.
     */
    protected function loadTableSchema($name)
    {
        $table = new TableSchema;
        $this->resolveTableNames($table, $name);

        if ($this->findColumns($table)) {
            $this->findConstraints($table);

            return $table;
        } else {
            return null;
        }
    }

    /**
     * Resolves the table name and schema name (if any).
     * @param TableSchema $table the table metadata object
     * @param string $name the table name
     */
    protected function resolveTableNames($table, $name)
    {
        $parts = explode('.', str_replace('`', '', $name));
        if (isset($parts[1])) {
            $table->schemaName = $parts[0];
            $table->name = $parts[1];
            $table->fullName = $table->schemaName . '.' . $table->name;
        } else {
            $table->fullName = $table->name = $parts[0];
        }
    }

    /**
     * 将列信息加载到ColumnSchema对象中。
     *
     * $info数组 由 SQL 语句 "SHOW FULL COLUMNS FROM ..." 而来，形式如下：
     *      Field: id
     *       Type: int(11)
     *  Collation: NULL
     *       Null: NO
     *        Key: PRI
     *    Default: NULL
     *      Extra: auto_increment
     * Privileges: select,insert,update,references
     *    Comment:
     *
     *
     * Loads the column information into a [[ColumnSchema]] object.
     * @param array $info column information
     * @return ColumnSchema the column schema object
     *
     * 通过 SHOW FULL COLUMNS FROM SQL语句获取字段信息，并存储在 $info 数组中。
     * 根据 $info['Type'] 获取字段类型信息。
     * 如果映射表里已经有映射关系的，直接通过映射表，获取相应的抽象类型。
     * 如果映射表没有的，默认地视字段的抽象类型为 TYPE_STRING 。
     * 对于枚举类型，除了转换成 TYPE_STRING 外，还要获取其枚举值，否则，类型信息不完整 。
     * 对于bit类型，在32位及32位以下时，使用 TYPE_INTEGER 抽象类型，在32位以上（bit最大为64 位）时，使用 TYPE_BIGINT 类型。
     *
     */
    protected function loadColumnSchema($info)
    {
        /**
         * @var ColumnSchema $column
         *
         * 创建ColumnSchema对象
         */
        $column = $this->createColumnSchema();
        // 字段名
        $column->name = $info['field'];
        // 是否允许为NULL
        $column->allowNull = $info['null'] === 'YES';
        // 是否是主键： 在 $info['key'] 中查找 'PRI' 是否存在，区分大小写
        $column->isPrimaryKey = strpos($info['key'], 'PRI') !== false;
        // 是否 auto_increment： 在 $info['extra'] 中查找 'auto_increment' 是否存在，不区分大小写
        $column->autoIncrement = stripos($info['extra'], 'auto_increment') !== false;
        // 获取字段注释
        $column->comment = $info['comment'];

        // 重点是这里，获取数据库字段类型，如上面的 int(11)
        $column->dbType = $info['type'];
        // 是否是 unsigned: 在 $info['type'] 中查找 'unsigned' 是否存在，不区分大小写
        $column->unsigned = stripos($column->dbType, 'unsigned') !== false;

        // 以下将把数据库类型，转换成对应的抽象类型，默认为 TYPE_STRING
        $column->type = self::TYPE_STRING;
        if (preg_match('/^(\w+)(?:\(([^\)]+)\))?/', $column->dbType, $matches)) {
            /**
             * $matches:
             *
             *  Array
                (
                    [0] => int(11)
                    [1] => int
                    [2] => 11
                )
             *
                Array
                (
                    [0] => varchar(255)
                    [1] => varchar
                    [2] => 255
                )
             */
            // 获取 int(11) 的 "int" 部分
            $type = strtolower($matches[1]);
            // 如果映射表里有，那就直接映射成抽象类型
            if (isset($this->typeMap[$type])) {
                $column->type = $this->typeMap[$type];
            }

            // 形如int(11) 的括号中的内容: int(11),char(255),varchar(255),decimal(19,4),bit(1),enum('男','女')
            if (!empty($matches[2])) {
                // 枚举类型，还需要将所有枚举值写入 $column->enumValues
                if ($type === 'enum') {
                    // $matches[2] : "'男','女'"
                    $values = explode(',', $matches[2]);
                    foreach ($values as $i => $value) {
                        // 去掉两边的 单引号
                        $values[$i] = trim($value, "'");
                    }
                    /**
                     * $values = ['男', '女']
                     */
                    $column->enumValues = $values;
                } else {
                    // 如果不是枚举类型，那么括号中的内容就是精度了，如 decimal(19,4)
                    $values = explode(',', $matches[2]);
                    // $values = [19, 4]
                    $column->size = $column->precision = (int) $values[0];
                    if (isset($values[1])) {
                        $column->scale = (int) $values[1];
                    }

                    // bit(1) 类型的，转换成 boolean
                    if ($column->size === 1 && $type === 'bit') {
                        $column->type = 'boolean';
                    } elseif ($type === 'bit') {
                        // 由于bit最多64位，如果超过 32 位，那么用一个 bigint 足以。
                        if ($column->size > 32) {
                            $column->type = 'bigint';
                        } elseif ($column->size === 32) {
                            // 如果正好32位，那么用一个 interger 来表示。
                            $column->type = 'integer';
                        }
                    }
                }
            }
        }

        /** 获取PHP数据类型,是把抽像类型转换成PHP类型的关键*/
        $column->phpType = $this->getColumnPhpType($column);

        // 处理默认值
        if (!$column->isPrimaryKey) {
            // timestamp 的话，要实际获取当前时间戳，而不能是字符串 'CURRENT_TIMESTAMP'
            if ($column->type === 'timestamp' && $info['default'] === 'CURRENT_TIMESTAMP') {
                $column->defaultValue = new Expression('CURRENT_TIMESTAMP');
            } elseif (isset($type) && $type === 'bit') {
                /**
                 * bit 的话，要截取对应的内容，并进行进制转换
                 *
                 * bindec() 函数把二进制转换为十进制。
                 */
                $column->defaultValue = bindec(trim($info['default'], 'b\''));
            } else {
                // 其余类型的，直接转换成PHP类型的值
                $column->defaultValue = $column->phpTypecast($info['default']);
            }
        }

        return $column;
    }

    /**
     * 收集数据表 列的信息。
     * Collects the metadata of table columns.
     * @param TableSchema $table the table metadata
     * @return boolean whether the table exists in the database
     * @throws \Exception if DB query fails
     */
    protected function findColumns($table)
    {
        /**
         *  +--------------+--------------+-----------------+------+-----+---------+----------------+---------------------------------+---------+
            | Field        | Type         | Collation       | Null | Key | Default | Extra          | Privileges                      | Comment |
            +--------------+--------------+-----------------+------+-----+---------+----------------+---------------------------------+---------+
            | id           | int(11)      | NULL            | NO   | PRI | NULL    | auto_increment | select,insert,update,references |         |
            | user_id      | int(11)      | NULL            | NO   | MUL | NULL    |                | select,insert,update,references |         |
            | access_token | varchar(255) | utf8_unicode_ci | NO   | UNI | NULL    |                | select,insert,update,references |         |
            | expire_at    | int(11)      | NULL            | NO   | MUL | NULL    |                | select,insert,update,references |         |
            | created_at   | int(11)      | NULL            | NO   |     | NULL    |                | select,insert,update,references |         |
            +--------------+--------------+-----------------+------+-----+---------+----------------+---------------------------------+---------+
         */
        $sql = 'SHOW FULL COLUMNS FROM ' . $this->quoteTableName($table->fullName);
        try {
            /**
             *  [
                    {
                        "Field": "id",
                        "Type": "int(11)",
                        "Collation": null,
                        "Null": "NO",
                        "Key": "PRI",
                        "Default": null,
                        "Extra": "auto_increment",
                        "Privileges": "select,insert,update,references",
                        "Comment": ""
                    },
                    {
                        "Field": "user_id",
                        "Type": "int(11)",
                        "Collation": null,
                        "Null": "NO",
                        "Key": "MUL",
                        "Default": null,
                        "Extra": "",
                        "Privileges": "select,insert,update,references",
                        "Comment": ""
                    },
                    ...
                ]
             */
            $columns = $this->db->createCommand($sql)->queryAll();

        } catch (\Exception $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof \PDOException && strpos($previous->getMessage(), 'SQLSTATE[42S02') !== false) {
                // table does not exist
                // https://dev.mysql.com/doc/refman/5.5/en/error-messages-server.html#error_er_bad_table_error
                return false;
            }
            throw $e;
        }
        /**
         *  $info ：
         *
            {
                "Field": "id",
                "Type": "int(11)",
                "Collation": null,
                "Null": "NO",
                "Key": "PRI",
                "Default": null,
                "Extra": "auto_increment",
                "Privileges": "select,insert,update,references",
                "Comment": ""
            }
         */
        foreach ($columns as $info) {
            /**
             * PDO::getAttribute — 取回一个数据库连接的属性
             * http://php.net/manual/zh/pdo.getattribute.php
             *
             * 判断数据库连接属性的键名是否为小写模式
             */
            if ($this->db->slavePdo->getAttribute(\PDO::ATTR_CASE) !== \PDO::CASE_LOWER) {
                // 将数组的键名全部转换为小写
                $info = array_change_key_case($info, CASE_LOWER);
            }
            // 将列信息加载到ColumnSchema对象中。
            $column = $this->loadColumnSchema($info);
            // 将ColumnSchema对象存储到 TableSchema::columns 属性中
            $table->columns[$column->name] = $column;
            // 主键
            if ($column->isPrimaryKey) {
                // 将主键的ColumnSchema对象存储到 TableSchema::primaryKey 属性中
                $table->primaryKey[] = $column->name;
                // 自增
                if ($column->autoIncrement) {
                    $table->sequenceName = '';
                }
            }
        }

        return true;
    }

    /**
     * Gets the CREATE TABLE sql string.
     * @param TableSchema $table the table metadata
     * @return string $sql the result of 'SHOW CREATE TABLE'
     */
    protected function getCreateTableSql($table)
    {
        $row = $this->db->createCommand('SHOW CREATE TABLE ' . $this->quoteTableName($table->fullName))->queryOne();
        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }

        return $sql;
    }

    /**
     * 收集给定表的外键列详细信息。
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
     * @throws \Exception
     */
    protected function findConstraints($table)
    {
        $sql = <<<SQL
SELECT
    kcu.constraint_name,
    kcu.column_name,
    kcu.referenced_table_name,
    kcu.referenced_column_name
FROM information_schema.referential_constraints AS rc
JOIN information_schema.key_column_usage AS kcu ON
    (
        kcu.constraint_catalog = rc.constraint_catalog OR
        (kcu.constraint_catalog IS NULL AND rc.constraint_catalog IS NULL)
    ) AND
    kcu.constraint_schema = rc.constraint_schema AND
    kcu.constraint_name = rc.constraint_name
WHERE rc.constraint_schema = database() AND kcu.table_schema = database()
AND rc.table_name = :tableName AND kcu.table_name = :tableName1
SQL;

        try {
            $rows = $this->db->createCommand($sql, [':tableName' => $table->name, ':tableName1' => $table->name])->queryAll();
            $constraints = [];
            foreach ($rows as $row) {
                $constraints[$row['constraint_name']]['referenced_table_name'] = $row['referenced_table_name'];
                $constraints[$row['constraint_name']]['columns'][$row['column_name']] = $row['referenced_column_name'];
            }
            $table->foreignKeys = [];
            foreach ($constraints as $constraint) {
                $table->foreignKeys[] = array_merge(
                    [$constraint['referenced_table_name']],
                    $constraint['columns']
                );
            }
        } catch (\Exception $e) {
            $previous = $e->getPrevious();
            if (!$previous instanceof \PDOException || strpos($previous->getMessage(), 'SQLSTATE[42S02') === false) {
                throw $e;
            }

            // table does not exist, try to determine the foreign keys using the table creation sql
            $sql = $this->getCreateTableSql($table);
            $regexp = '/FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
            if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fks = array_map('trim', explode(',', str_replace('`', '', $match[1])));
                    $pks = array_map('trim', explode(',', str_replace('`', '', $match[3])));
                    $constraint = [str_replace('`', '', $match[2])];
                    foreach ($fks as $k => $name) {
                        $constraint[$name] = $pks[$k];
                    }
                    $table->foreignKeys[md5(serialize($constraint))] = $constraint;
                }
                $table->foreignKeys = array_values($table->foreignKeys);
            }
        }
    }

    /**
     * Returns all unique indexes for the given table.
     * Each array element is of the following structure:
     *
     * ```php
     * [
     *     'IndexName1' => ['col1' [, ...]],
     *     'IndexName2' => ['col2' [, ...]],
     * ]
     * ```
     *
     * @param TableSchema $table the table metadata
     * @return array all unique indexes for the given table.
     */
    public function findUniqueIndexes($table)
    {
        $sql = $this->getCreateTableSql($table);
        $uniqueIndexes = [];

        $regexp = '/UNIQUE KEY\s+([^\(\s]+)\s*\(([^\(\)]+)\)/mi';
        if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $indexName = str_replace('`', '', $match[1]);
                $indexColumns = array_map('trim', explode(',', str_replace('`', '', $match[2])));
                $uniqueIndexes[$indexName] = $indexColumns;
            }
        }

        return $uniqueIndexes;
    }

    /**
     * Returns all table names in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @return array all table names in the database. The names have NO schema name prefix.
     */
    protected function findTableNames($schema = '')
    {
        $sql = 'SHOW TABLES';
        if ($schema !== '') {
            $sql .= ' FROM ' . $this->quoteSimpleTableName($schema);
        }

        return $this->db->createCommand($sql)->queryColumn();
    }

    /**
     * @inheritdoc
     */
    public function createColumnSchemaBuilder($type, $length = null)
    {
        return new ColumnSchemaBuilder($type, $length, $this->db);
    }
}
