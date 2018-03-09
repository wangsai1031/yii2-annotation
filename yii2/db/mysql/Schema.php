<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db\mysql;

use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\ColumnSchema;
use yii\db\Constraint;
use yii\db\ConstraintFinderInterface;
use yii\db\ConstraintFinderTrait;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\ForeignKeyConstraint;
use yii\db\IndexConstraint;
use yii\db\TableSchema;
use yii\helpers\ArrayHelper;

/**
 * Schema is the class for retrieving metadata from a MySQL database (version 4.1.x and 5.x).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Schema extends \yii\db\Schema implements ConstraintFinderInterface
{
    use ConstraintFinderTrait;

    /**
     * @var bool whether MySQL used is older than 5.1.
     */
    private $_oldMysql;


    /**
     * 定义从数据库数据类型到n个抽象数据类型间的映射关系
     * @var array mapping from physical column types (keys) to abstract column types (values)
     */
    public $typeMap = [
        'tinyint' => self::TYPE_TINYINT,
        'bit' => self::TYPE_INTEGER,
        'smallint' => self::TYPE_SMALLINT,
        'mediumint' => self::TYPE_INTEGER,
        'int' => self::TYPE_INTEGER,
        'integer' => self::TYPE_INTEGER,
        'bigint' => self::TYPE_BIGINT,
        'float' => self::TYPE_FLOAT,
        'double' => self::TYPE_DOUBLE,
        'real' => self::TYPE_FLOAT,
        'decimal' => self::TYPE_DECIMAL,
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
        'varbinary' => self::TYPE_BINARY,
        'json' => self::TYPE_JSON,
    ];

    /**
     * {@inheritdoc}
     */
    protected $tableQuoteCharacter = '`';
    /**
     * {@inheritdoc}
     */
    protected $columnQuoteCharacter = '`';

    /**
     * {@inheritdoc}
     */
    protected function resolveTableName($name)
    {
        $resolvedName = new TableSchema();
        $parts = explode('.', str_replace('`', '', $name));
        if (isset($parts[1])) {
            $resolvedName->schemaName = $parts[0];
            $resolvedName->name = $parts[1];
        } else {
            $resolvedName->schemaName = $this->defaultSchema;
            $resolvedName->name = $name;
        }
        $resolvedName->fullName = ($resolvedName->schemaName !== $this->defaultSchema ? $resolvedName->schemaName . '.' : '') . $resolvedName->name;
        return $resolvedName;
    }

    /**
     * {@inheritdoc}
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
     * 字段信息的获取和填充
     *
     * 该函数用于加载数据表信息 yii\db\TableSchema ，这是一个抽象函数，具体由各子类实现。
     * {@inheritdoc}
     */
    protected function loadTableSchema($name)
    {
        $table = new TableSchema();
        $this->resolveTableNames($table, $name);

        if ($this->findColumns($table)) {
            $this->findConstraints($table);
            return $table;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTablePrimaryKey($tableName)
    {
        return $this->loadTableConstraints($tableName, 'primaryKey');
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTableForeignKeys($tableName)
    {
        return $this->loadTableConstraints($tableName, 'foreignKeys');
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTableIndexes($tableName)
    {
        static $sql = <<<'SQL'
SELECT
    `s`.`INDEX_NAME` AS `name`,
    `s`.`COLUMN_NAME` AS `column_name`,
    `s`.`NON_UNIQUE` ^ 1 AS `index_is_unique`,
    `s`.`INDEX_NAME` = 'PRIMARY' AS `index_is_primary`
FROM `information_schema`.`STATISTICS` AS `s`
WHERE `s`.`TABLE_SCHEMA` = COALESCE(:schemaName, DATABASE()) AND `s`.`INDEX_SCHEMA` = `s`.`TABLE_SCHEMA` AND `s`.`TABLE_NAME` = :tableName
ORDER BY `s`.`SEQ_IN_INDEX` ASC
SQL;

        $resolvedName = $this->resolveTableName($tableName);
        $indexes = $this->db->createCommand($sql, [
            ':schemaName' => $resolvedName->schemaName,
            ':tableName' => $resolvedName->name,
        ])->queryAll();
        $indexes = $this->normalizePdoRowKeyCase($indexes, true);
        $indexes = ArrayHelper::index($indexes, null, 'name');
        $result = [];
        foreach ($indexes as $name => $index) {
            $result[] = new IndexConstraint([
                'isPrimary' => (bool) $index[0]['index_is_primary'],
                'isUnique' => (bool) $index[0]['index_is_unique'],
                'name' => $name !== 'PRIMARY' ? $name : null,
                'columnNames' => ArrayHelper::getColumn($index, 'column_name'),
            ]);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTableUniques($tableName)
    {
        return $this->loadTableConstraints($tableName, 'uniques');
    }

    /**
     * {@inheritdoc}
     * @throws NotSupportedException if this method is called.
     */
    protected function loadTableChecks($tableName)
    {
        throw new NotSupportedException('MySQL does not support check constraints.');
    }

    /**
     * {@inheritdoc}
     * @throws NotSupportedException if this method is called.
     */
    protected function loadTableDefaultValues($tableName)
    {
        throw new NotSupportedException('MySQL does not support default value constraints.');
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
                    preg_match_all("/'[^']*'/", $matches[2], $values);
                    foreach ($values[0] as $i => $value) {
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
            if (($column->type === 'timestamp' || $column->type ==='datetime') && $info['default'] === 'CURRENT_TIMESTAMP') {
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
     * @return bool whether the table exists in the database
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
        $sql = <<<'SQL'
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
            foreach ($constraints as $name => $constraint) {
                $table->foreignKeys[$name] = array_merge(
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
     *
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

        $regexp = '/UNIQUE KEY\s+\`(.+)\`\s*\((\`.+\`)+\)/mi';
        if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $indexName = $match[1];
                $indexColumns = array_map('trim', explode('`,`', trim($match[2], '`')));
                $uniqueIndexes[$indexName] = $indexColumns;
            }
        }

        return $uniqueIndexes;
    }

    /**
     * {@inheritdoc}
     */
    public function createColumnSchemaBuilder($type, $length = null)
    {
        return new ColumnSchemaBuilder($type, $length, $this->db);
    }

    /**
     * @return bool whether the version of the MySQL being used is older than 5.1.
     * @throws InvalidConfigException
     * @throws Exception
     * @since 2.0.13
     */
    protected function isOldMysql()
    {
        if ($this->_oldMysql === null) {
            $version = $this->db->getSlavePdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $this->_oldMysql = version_compare($version, '5.1', '<=');
        }

        return $this->_oldMysql;
    }

    /**
     * Loads multiple types of constraints and returns the specified ones.
     * @param string $tableName table name.
     * @param string $returnType return type:
     * - primaryKey
     * - foreignKeys
     * - uniques
     * @return mixed constraints.
     */
    private function loadTableConstraints($tableName, $returnType)
    {
        static $sql = <<<'SQL'
SELECT DISTINCT
    `kcu`.`CONSTRAINT_NAME` AS `name`,
    `kcu`.`COLUMN_NAME` AS `column_name`,
    `tc`.`CONSTRAINT_TYPE` AS `type`,
    CASE
        WHEN :schemaName IS NULL AND `kcu`.`REFERENCED_TABLE_SCHEMA` = `sch`.`name` THEN NULL
        ELSE `kcu`.`REFERENCED_TABLE_SCHEMA`
    END AS `foreign_table_schema`,
    `kcu`.`REFERENCED_TABLE_NAME` AS `foreign_table_name`,
    `kcu`.`REFERENCED_COLUMN_NAME` AS `foreign_column_name`,
    `rc`.`UPDATE_RULE` AS `on_update`,
    `rc`.`DELETE_RULE` AS `on_delete`,
    `kcu`.`ORDINAL_POSITION` as `position`
FROM (SELECT DATABASE() AS `name`) AS `sch`
INNER JOIN `information_schema`.`KEY_COLUMN_USAGE` AS `kcu`
    ON `kcu`.`TABLE_SCHEMA` = COALESCE(:schemaName, `sch`.`name`) AND `kcu`.`CONSTRAINT_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `kcu`.`TABLE_NAME` = :tableName
LEFT JOIN `information_schema`.`REFERENTIAL_CONSTRAINTS` AS `rc`
    ON `rc`.`CONSTRAINT_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `rc`.`CONSTRAINT_NAME` = `kcu`.`CONSTRAINT_NAME`
LEFT JOIN `information_schema`.`TABLE_CONSTRAINTS` AS `tc`
    ON `tc`.`TABLE_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `tc`.`CONSTRAINT_NAME` = `kcu`.`CONSTRAINT_NAME`
ORDER BY `kcu`.`ORDINAL_POSITION` ASC
SQL;

        $resolvedName = $this->resolveTableName($tableName);
        $constraints = $this->db->createCommand($sql, [
            ':schemaName' => $resolvedName->schemaName,
            ':tableName' => $resolvedName->name,
        ])->queryAll();
        $constraints = $this->normalizePdoRowKeyCase($constraints, true);
        $constraints = ArrayHelper::index($constraints, null, ['type', 'name']);
        $result = [
            'primaryKey' => null,
            'foreignKeys' => [],
            'uniques' => [],
        ];
        foreach ($constraints as $type => $names) {
            foreach ($names as $name => $constraint) {
                switch ($type) {
                    case 'PRIMARY KEY':
                        $result['primaryKey'] = new Constraint([
                            'columnNames' => ArrayHelper::getColumn($constraint, 'column_name'),
                        ]);
                        break;
                    case 'FOREIGN KEY':
                        $result['foreignKeys'][] = new ForeignKeyConstraint([
                            'name' => $name,
                            'columnNames' => ArrayHelper::getColumn($constraint, 'column_name'),
                            'foreignSchemaName' => $constraint[0]['foreign_table_schema'],
                            'foreignTableName' => $constraint[0]['foreign_table_name'],
                            'foreignColumnNames' => ArrayHelper::getColumn($constraint, 'foreign_column_name'),
                            'onDelete' => $constraint[0]['on_delete'],
                            'onUpdate' => $constraint[0]['on_update'],
                        ]);
                        break;
                    case 'UNIQUE':
                        $result['uniques'][] = new Constraint([
                            'name' => $name,
                            'columnNames' => ArrayHelper::getColumn($constraint, 'column_name'),
                        ]);
                        break;
                }
            }
        }
        foreach ($result as $type => $data) {
            $this->setTableMetadata($tableName, $type, $data);
        }

        return $result[$returnType];
    }
}
