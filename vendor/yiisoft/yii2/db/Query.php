<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db;

use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\helpers\ArrayHelper;
use yii\base\InvalidConfigException;

/**
 * Query represents a SELECT SQL statement in a way that is independent of DBMS.
 * Query类代表一个独立于不同类型数据库的SELECT SQL语句
 *
 * Query provides a set of methods to facilitate the specification of different clauses
 * in a SELECT statement. These methods can be chained together.
 * Query类提供了一系列的方法去简化SELECT语句中不同部分的子句。这些方法可以链式操作。
 *
 * By calling [[createCommand()]], we can get a [[Command]] instance which can be further
 * used to perform/execute the DB query against a database.
 * 通过调用[[createCommand()]]，我们可以得到一个[[Command]]实例，进而向数据库使用或者执行查询。
 *
 * For example,
 * 例如，
 *
 * ```php
 * $query = new Query;
 * // compose the query  //组织查询
 * $query->select('id, name')
 *     ->from('user')
 *     ->limit(10);
 * // build and execute the query  // 生成并执行查询
 * $rows = $query->all();
 * // alternatively, you can create DB command and execute it
 * // 或者，你可以创建一个DB command 并执行它。
 * $command = $query->createCommand();
 * // $command->sql returns the actual SQL
 * // $command->sql 返回的是真正被执行的SQL
 * $rows = $command->queryAll();
 * ```
 *
 * Query internally uses the [[QueryBuilder]] class to generate the SQL statement.
 * Query类在内部使用[[QueryBuilder]]类生成sql语句。
 *
 * A more detailed usage guide on how to work with Query can be found in the [guide article on Query Builder](guide:db-query-builder).
 * 想要更多关于使用Query类的详情，请参考[查询构造器指南]
 * 
 * @property string[] $tablesUsedInFrom Table names indexed by aliases. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class Query extends Component implements QueryInterface, ExpressionInterface
{
    use QueryTrait;

    /**
     * @var array the columns being selected. For example, `['id', 'name']`.
     * This is used to construct the SELECT clause in a SQL statement. If not set, it means selecting all columns.
     * 属性 数组 被选择的列。例如，`['id', 'name']`。它用来创建sql语句中的SELECT子句。如果没有设置，意味着选中所有的列。
     * @see select()
     */
    public $select;
    /**
     * @var string additional option that should be appended to the 'SELECT' keyword. For example,
     * in MySQL, the option 'SQL_CALC_FOUND_ROWS' can be used.
     * 属性 字符串 被添加到select关键字的额外的选项。例如，在MySQL中，可以使用SQL_CALC_FOUND_ROWS选项。
     */
    public $selectOption;
    /**
     * @var bool whether to select distinct rows of data only. If this is set true,
     * the SELECT clause would be changed to SELECT DISTINCT.
     * 属性 boolean 选择的数据是否去重。如果为true，select语句就会被转化为select distinct
     */
    public $distinct;
    /**
     * @var array the table(s) to be selected from. For example, `['user', 'post']`.
     * This is used to construct the FROM clause in a SQL statement.
     * 属性 设置 选择数据的表。例如，`['user', 'post']`。该属性用来构建SQL语句中的from子句
     * @see from()
     */
    public $from;
    /**
     * @var array how to group the query results. For example, `['company', 'department']`.
     * This is used to construct the GROUP BY clause in a SQL statement.
     * 属性 设置 如何对查询结果进行分组。例如，`['company', 'department']`。该属性用来构建sql语句中的GROUP BY子句。
     */
    public $groupBy;
    /**
     * @var array how to join with other tables. Each array element represents the specification
     * of one join which has the following structure:
     * 属性 数组 如何连结其他表。每一个数组元素代表一个连接的详细信息，参考结构如下：
     *
     * ```php
     * [$joinType, $tableName, $joinCondition]
     * ```
     *
     * For example,
     * 例如，
     *
     * ```php
     * [
     *     ['INNER JOIN', 'user', 'user.id = author_id'],
     *     ['LEFT JOIN', 'team', 'team.id = team_id'],
     * ]
     * ```
     */
    public $join;
    /**
     * @var string|array|ExpressionInterface the condition to be applied in the GROUP BY clause.
     * 属性 字符串|数组 应用于GROUP BY 子句的条件。
     * It can be either a string or an array. Please refer to [[where()]] on how to specify the condition.
     * 既可以是字符串也可以是数组。请参考[[where()]]关于如何指定条件。
     */
    public $having;
    /**
     * @var array this is used to construct the UNION clause(s) in a SQL statement.
     * 属性 数组 用来组成sql语句中的UNION子句。
     *
     * Each array element is an array of the following structure:
     * 每一个数组元素得具备如下的结构：
     *
     * - `query`: either a string or a [[Query]] object representing a query
     * - `查询`： 字符串或者代表查询的[[Query]]对象
     * - `all`: boolean, whether it should be `UNION ALL` or `UNION`
     * - `全部`：boolean，是UNION ALL还是UNION
     */
    public $union;
    /**
     * @var array list of query parameter values indexed by parameter placeholders.
     * 属性 数组 查询参数值的列表，采用参数占位符做下标
     *
     * For example, `[':name' => 'Dan', ':age' => 31]`.
     * 例如，`[':name' => 'Dan', ':age' => 31]`
     */
    public $params = [];
    /**
     * @var int|true the default number of seconds that query results can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     * Use a negative number to indicate that query cache should not be used.
     * Use boolean `true` to indicate that [[Connection::queryCacheDuration]] should be used.
     * @see cache()
     * @since 2.0.14
     */
    public $queryCacheDuration;
    /**
     * @var \yii\caching\Dependency the dependency to be associated with the cached query result for this query
     * @see cache()
     * @since 2.0.14
     */
    public $queryCacheDependency;


    /**
     * Creates a DB command that can be used to execute this query.
     * 创建一个DB command，用来执行此次查询。
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * 参数 用来生成sql语句的数据库连接。
     *
     * If this parameter is not given, the `db` application component will be used.
     * 如果没有传递该参数，就会使用db应用组件
     *
     * @return Command the created DB command instance.
     * 返回值 DB command 实例
     */
    public function createCommand($db = null)
    {
        if ($db === null) {
            // 若没有设置db,则使用配置的DB应用程序组件
            $db = Yii::$app->getDb();
        }
        list($sql, $params) = $db->getQueryBuilder()->build($this);

        $command = $db->createCommand($sql, $params);
        $this->setCommandCache($command);

        return $command;
    }

    /**
     * Prepares for building SQL.
     * 准备创建sql
     *
     * This method is called by [[QueryBuilder]] when it starts to build SQL from a query object.
     * 当从query对象开始创建sql语句的时候，该方法被[[QueryBuilder]]调用
     *
     * You may override this method to do some final preparation work when converting a query into a SQL statement.
     * 你可以重写该方法，在查询对象转化为sql语句之前，做一些最终的准备工作。
     *
     * @param QueryBuilder $builder
     * 参数 查询创建器
     *
     * @return $this a prepared query instance which will be used by [[QueryBuilder]] to build the SQL
     * 返回值 一个准备就绪的查询实例。将会被[[QueryBuilder]]创建sql
     */
    public function prepare($builder)
    {
        return $this;
    }

    /**
     * Starts a batch query.
     * 开始一个批次的查询。
     *
     * A batch query supports fetching data in batches, which can keep the memory usage under a limit.
     * 一个批次查询支持分批获取数据，这样可以把内存的使用率维持在一个较低的状态。
     *
     * This method will return a [[BatchQueryResult]] object which implements the [[\Iterator]] interface
     * and can be traversed to retrieve the data in batches.
     * 该方法会返回实现了[[\Iterator]]接口的[[BatchQueryResult]]对象，并且可以遍历检索成批数据
     *
     * For example,
     * 例如，
     *
     * ```php
     * $query = (new Query)->from('user');
     * foreach ($query->batch() as $rows) {
     *     // $rows is an array of 100 or fewer rows from user table
     *     // $rows是来自用户表的100条数据或者更少。
     * }
     * ```
     *
     * @param int $batchSize the number of records to be fetched in each batch.
     * 参数 整型 每一批次中获取记录的条数。
     * 
     * @param Connection $db the database connection. If not set, the "db" application component will be used.
     * 参数 数据库连接，如果每一设置，就会默认使用db应用组件。
     * 
     * @return BatchQueryResult the batch query result. It implements the [[\Iterator]] interface
     * and can be traversed to retrieve the data in batches.
     * 返回值 批量查询结果。它实现了[[\Iterator]]接口，并可以批量遍历成批数据。
     */
    public function batch($batchSize = 100, $db = null)
    {
        return Yii::createObject([
            'class' => BatchQueryResult::className(),
            'query' => $this,
            'batchSize' => $batchSize,
            'db' => $db,
            'each' => false,
        ]);
    }

    /**
     * Starts a batch query and retrieves data row by row.
     * 开始一个批量查询，并逐行遍历数据
     *
     * This method is similar to [[batch()]] except that in each iteration of the result,
     * only one row of data is returned. For example,
     * 该方法跟[[batch()]]类似，除了在对结果的每一个迭代中，只有一行数据被返回。例如，
     *
     * ```php
     * $query = (new Query)->from('user');
     * foreach ($query->each() as $row) {
     * }
     * ```
     *
     * @param int $batchSize the number of records to be fetched in each batch.
     * 参数 整型 每一个批次中获取数据的条数。
     * 
     * @param Connection $db the database connection. If not set, the "db" application component will be used.
     * 参数 数据库连接。如果没有设置，就会使用db应用组件
     * 
     * @return BatchQueryResult the batch query result. It implements the [[\Iterator]] interface
     * and can be traversed to retrieve the data in batches.
     * 返回值 批量查询结果。它实现了[[\Iterator]]接口，并且可以被成批遍历。
     */
    public function each($batchSize = 100, $db = null)
    {
        return Yii::createObject([
            'class' => BatchQueryResult::className(),
            'query' => $this,
            'batchSize' => $batchSize,
            'db' => $db,
            'each' => true,
        ]);
    }

    /**
     * Executes the query and returns all results as an array.
     * 执行查询，并把返回所有的结果以数组的方式返回。
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * 参数 生成sql语句的数据库连接。如果该参数没有设置，就会使用db应用组件。
     *
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     * 返回值  数组 查询结果。如果查询结果为空，就会返回一个空数组。
     */
    public function all($db = null)
    {
        if ($this->emulateExecution) {
            return [];
        }
        $rows = $this->createCommand($db)->queryAll();
        /**
         * 若从 ActiveQuery::all() 方法过来,这里调用的是 @see ActiveQuery::populate()
         */
        return $this->populate($rows);
    }

    /**
     * Converts the raw query results into the format as specified by this query.
     * 把未加工的结果转化为该查询指定的格式。
     *
     * This method is internally used to convert the data fetched from database
     * into the format as required by this query.
     * 该方法在内部使用把从数据库获取的数据转化成该查询需要的格式。
     *
     * @param array $rows the raw query result from database
     * 参数 数组 从数据库获取的初始数据
     *
     * @return array the converted query result
     * 返回值 数组 转化后的查询结果
     */
    public function populate($rows)
    {
        if ($this->indexBy === null) {
            // 若 索引列 为空，则直接返回查出的数据
            return $rows;
        }
        $result = [];
        // 若存在索引列，则遍历查询结果数组
        foreach ($rows as $row) {
            // 若索引列是字符串（字段名），则直接将该字段值复制给$key
            // 若索引列为回调函数，则将该列数组作为参数传入该函数，以返回值作为$key
            $result[ArrayHelper::getValue($row, $this->indexBy)] = $row;
        }

        return $result;
    }

    /**
     * Executes the query and returns a single row of result.
     * 执行查询，并返回一行作为结果
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * 参数 用以生成sql语句的数据库连接。
     *
     * If this parameter is not given, the `db` application component will be used.
     * 如果该参数没有设置，就会默认使用db应用组件。
     * 
     * @return array|bool the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     * 返回值 数组|boolean 查询结果的第一行。如果查询结果为空，就会返回false
     *
     * 注意: one() 方法只返回查询结果当中的第一条数据， 条件语句中不会加上 LIMIT 1 条件。
     * 如果你清楚的知道查询将会只返回一行或几行数据 （例如， 如果你是通过某些主键来查询的），这很好也提倡这样做。
     * 但是，如果查询结果 有机会返回大量的数据时，那么你应该显示调用 limit(1) 方法，以改善性能。
     * 例如， (new \yii\db\Query())->from('user')->limit(1)->one()。
     */
    public function one($db = null)
    {
        if ($this->emulateExecution) {
            return false;
        }

        return $this->createCommand($db)->queryOne();
    }

    /**
     * Returns the query result as a scalar value.
     * 返回查询结果的标量值
     *
     * The value returned will be the first column in the first row of the query results.
     * 被返回的值是查询结果中的第一行的第一列。
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * 参数 用以生成sql语句的数据库连接。如果该参数没有指定，就是用db应用组件
     *
     * @return string|null|false the value of the first column in the first row of the query result.
     * False is returned if the query result is empty.
     * 返回值 字符串|null|false 查询结果的第一行第一列数据。如果查询结果为空，返回false
     */
    public function scalar($db = null)
    {
        if ($this->emulateExecution) {
            return null;
        }

        return $this->createCommand($db)->queryScalar();
    }

    /**
     * Executes the query and returns the first column of the result.
     * 执行查询，并返回查询结果的第一列
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * 参数 用以生成sql语句的数据库连接。如果该参数没有指定，就会用db应用组件
     *
     * @return array the first column of the query result. An empty array is returned if the query results in nothing.
     * 返回值 数组 查询结果的第一列。如果查询结果为空，就会返回一个空数组。
     */
    public function column($db = null)
    {
        if ($this->emulateExecution) {
            return [];
        }

        if ($this->indexBy === null) {
            return $this->createCommand($db)->queryColumn();
        }

        if (is_string($this->indexBy) && is_array($this->select) && count($this->select) === 1) {
            if (strpos($this->indexBy, '.') === false && count($tables = $this->getTablesUsedInFrom()) > 0) {
                $this->select[] = key($tables) . '.' . $this->indexBy;
            } else {
                $this->select[] = $this->indexBy;
            }
        }
        $rows = $this->createCommand($db)->queryAll();
        $results = [];
        foreach ($rows as $row) {
            $value = reset($row);

            if ($this->indexBy instanceof \Closure) {
                $results[call_user_func($this->indexBy, $row)] = $value;
            } else {
                $results[$row[$this->indexBy]] = $value;
            }
        }

        return $results;
    }

    /**
     * Returns the number of records.
     * 返回记录的条数。
     *
     * @param string $q the COUNT expression. Defaults to '*'.
     * Make sure you properly [quote](guide:db-dao#quoting-table-and-column-names) column names in the expression.
     * 参数 字符串 统计表达式。默认是*。确保你在表达式中正确的使用了列名。
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given (or null), the `db` application component will be used.
     * 参数 用以生成sql语句的数据库连接。如果该参数没有指定，就会用db应用组件
     * 
     * @return int|string number of records. The result may be a string depending on the
     * underlying database engine and to support integer values higher than a 32bit PHP integer can handle.
     * 返回值 整型或者字符串 记录的条数。根据底层数据库引擎和返回的结果大于32位php程序可以处理的数字，结果可以是一个字符串
     */
    public function count($q = '*', $db = null)
    {
        if ($this->emulateExecution) {
            return 0;
        }

        return $this->queryScalar("COUNT($q)", $db);
    }

    /**
     * Returns the sum of the specified column values.
     * 返回指定列的值的和。
     *
     * @param string $q the column name or expression.
     * Make sure you properly [quote](guide:db-dao#quoting-table-and-column-names) column names in the expression.
     * 参数 字符串 列名或者表达式。确保你在表达式中正确的使用了列名。
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * 参数 用以生成sql语句的数据库连接。如果该参数没有指定，就会用db应用组件
     *
     * @return mixed the sum of the specified column values.
     * 返回值 混合型 指定列的值的和。
     */
    public function sum($q, $db = null)
    {
        if ($this->emulateExecution) {
            return 0;
        }

        return $this->queryScalar("SUM($q)", $db);
    }

    /**
     * Returns the average of the specified column values.
     * 返回指定列的值的平均值。
     *
     * @param string $q the column name or expression.
     * Make sure you properly [quote](guide:db-dao#quoting-table-and-column-names) column names in the expression.
     * 参数 字符串 列名或表达式。确保你在表达式中正确的使用了列名。
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * 参数 用以生成sql语句的数据库连接。如果该参数没有指定，就会用db应用组件
     *
     * @return mixed the average of the specified column values.
     * 返回值 混合型 指定列值的平均值
     */
    public function average($q, $db = null)
    {
        if ($this->emulateExecution) {
            return 0;
        }

        return $this->queryScalar("AVG($q)", $db);
    }

    /**
     * Returns the minimum of the specified column values.
     * 返回指定列的最小值。
     *
     * @param string $q the column name or expression.
     * Make sure you properly [quote](guide:db-dao#quoting-table-and-column-names) column names in the expression.
     * 参数 字符串 列名或表达式。确保你在表达式中正确的使用了列名。
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * 参数 用以生成sql语句的数据库连接。如果该参数没有指定，就会用db应用组件
     *
     * @return mixed the minimum of the specified column values.
     * 返回值 混合型 指定列的最小值
     */
    public function min($q, $db = null)
    {
        return $this->queryScalar("MIN($q)", $db);
    }

    /**
     * Returns the maximum of the specified column values.
     * 返回指定列的最大值。
     *
     * @param string $q the column name or expression.
     * Make sure you properly [quote](guide:db-dao#quoting-table-and-column-names) column names in the expression.
     * 参数 字符串 列名或表达式。确保你在表达式中正确的使用了列名。
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * 参数 用以生成sql语句的数据库连接。如果该参数没有指定，就会用db应用组件
     *
     * @return mixed the maximum of the specified column values.
     * 返回值 混合型 指定列的最大值
     */
    public function max($q, $db = null)
    {
        return $this->queryScalar("MAX($q)", $db);
    }

    /**
     * Returns a value indicating whether the query result contains any row of data.
     * 返回一个值表示查询结果是否包含数据。
     *
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * 参数 用以生成sql语句的数据库连接。如果该参数没有指定，就会用db应用组件
     * 
     * @return bool whether the query result contains any row of data.
     * 查询结果是否有数据。
     */
    public function exists($db = null)
    {
        if ($this->emulateExecution) {
            return false;
        }
        $command = $this->createCommand($db);
        $params = $command->params;
        $command->setSql($command->db->getQueryBuilder()->selectExists($command->getSql()));
        $command->bindValues($params);
        return (bool) $command->queryScalar();
    }

    /**
     * Queries a scalar value by setting [[select]] first.
     * 首先通过设置[[select]]属性，查询一个标量值
     *
     * Restores the value of select to make this query reusable.
     * 存储查询的值，以便复用查询结果。
     * 
     * @param string|ExpressionInterface $selectExpression
     * 参数 字符串 |表达式
     * 
     * @param Connection|null $db
     * 参数 连接|null
     * 
     * @return bool|string
     * 返回值 boolean|字符串
     */
    protected function queryScalar($selectExpression, $db)
    {
        if ($this->emulateExecution) {
            return null;
        }

        if (
            !$this->distinct
            && empty($this->groupBy)
            && empty($this->having)
            && empty($this->union)
        ) {
            $select = $this->select;
            $order = $this->orderBy;
            $limit = $this->limit;
            $offset = $this->offset;

            $this->select = [$selectExpression];
            $this->orderBy = null;
            $this->limit = null;
            $this->offset = null;
            $command = $this->createCommand($db);

            $this->select = $select;
            $this->orderBy = $order;
            $this->limit = $limit;
            $this->offset = $offset;

            return $command->queryScalar();
        }

        $command = (new self())
            ->select([$selectExpression])
            ->from(['c' => $this])
            ->createCommand($db);
        $this->setCommandCache($command);

        return $command->queryScalar();
    }

    /**
     * Returns table names used in [[from]] indexed by aliases.
     * Both aliases and names are enclosed into {{ and }}.
     * @return string[] table names indexed by aliases
     * @throws \yii\base\InvalidConfigException
     * @since 2.0.12
     */
    public function getTablesUsedInFrom()
    {
        if (empty($this->from)) {
            return [];
        }

        if (is_array($this->from)) {
            $tableNames = $this->from;
        } elseif (is_string($this->from)) {
            $tableNames = preg_split('/\s*,\s*/', trim($this->from), -1, PREG_SPLIT_NO_EMPTY);
        } elseif ($this->from instanceof Expression) {
            $tableNames = [$this->from];
        } else {
            throw new InvalidConfigException(gettype($this->from) . ' in $from is not supported.');
        }

        return $this->cleanUpTableNames($tableNames);
    }

    /**
     * Clean up table names and aliases
     * Both aliases and names are enclosed into {{ and }}.
     * @param array $tableNames non-empty array
     * @return string[] table names indexed by aliases
     * @since 2.0.14
     */
    protected function cleanUpTableNames($tableNames)
    {
        $cleanedUpTableNames = [];
        foreach ($tableNames as $alias => $tableName) {
            if (is_string($tableName) && !is_string($alias)) {
                $pattern = <<<PATTERN
~
^
\s*
(
(?:['"`\[]|{{)
.*?
(?:['"`\]]|}})
|
\(.*?\)
|
.*?
)
(?:
(?:
    \s+
    (?:as)?
    \s*
)
(
   (?:['"`\[]|{{)
    .*?
    (?:['"`\]]|}})
    |
    .*?
)
)?
\s*
$
~iux
PATTERN;
                if (preg_match($pattern, $tableName, $matches)) {
                    if (isset($matches[2])) {
                        list(, $tableName, $alias) = $matches;
                    } else {
                        $tableName = $alias = $matches[1];
                    }
                }
            }


            if ($tableName instanceof Expression) {
                if (!is_string($alias)) {
                    throw new InvalidArgumentException('To use Expression in from() method, pass it in array format with alias.');
                }
                $cleanedUpTableNames[$this->ensureNameQuoted($alias)] = $tableName;
            } elseif ($tableName instanceof self) {
                $cleanedUpTableNames[$this->ensureNameQuoted($alias)] = $tableName;
            } else {
                $cleanedUpTableNames[$this->ensureNameQuoted($alias)] = $this->ensureNameQuoted($tableName);
            }
        }

        return $cleanedUpTableNames;
    }

    /**
     * Ensures name is wrapped with {{ and }}
     * @param string $name
     * @return string
     */
    private function ensureNameQuoted($name)
    {
        $name = str_replace(["'", '"', '`', '[', ']'], '', $name);
        if ($name && !preg_match('/^{{.*}}$/', $name)) {
            return '{{' . $name . '}}';
        }

        return $name;
    }

    /**
     * 设置查询的SELECT部分
     * Sets the SELECT part of the query.
     * @param string|array|ExpressionInterface $columns the columns to be selected.
     * 参数 字符串|数组|表达式 被选择的列
     * 
     * Columns can be specified in either a string (e.g. "id, name") or an array (e.g. ['id', 'name']).
     * 可以通过字符串(例如 "id, name")或者数组['id', 'name']指定列
     * 
     * Columns can be prefixed with table names (e.g. "user.id") and/or contain column aliases (e.g. "user.id AS user_id").
     * 列可以使用表名（例如"user.id"）做前缀，生成列的别名。
     * 
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression). A DB expression may also be passed in form of
     * an [[ExpressionInterface]] object.
     * 该方法会自动引用列名，除非某一个包含插入成分（就是说列包含db表达式）。一个db表达式可以通过[[Expression]]对象传递。
     * 
     * Note that if you are selecting an expression like `CONCAT(first_name, ' ', last_name)`, you should
     * use an array to specify the columns. Otherwise, the expression may be incorrectly split into several parts.
     * 请注意，如果你使用的表达式类似于`CONCAT(first_name, ' ', last_name)`，你应该使用数组去指定列名。否则，该表达式会被错误的分割成几个部分。
     *
     * When the columns are specified as an array, you may also use array keys as the column aliases (if a column
     * does not need alias, do not use a string key).
     * 当列被以数组的方式指定时，你可以使用数组键作为列的别名（如果列不需要别名，不要使用字符串做键）
     *
     * Starting from version 2.0.1, you may also select sub-queries as columns by specifying each such column
     * as a `Query` instance representing the sub-query.
     * 从2.0.2版本开始，你也可以通过指定类似的每一个列为一个Query实例作为子查询，并把子查询作为列
     *
     * @param string $option additional option that should be appended to the 'SELECT' keyword. For example,
     * in MySQL, the option 'SQL_CALC_FOUND_ROWS' can be used.
     * 参数 字符串 要添加到select关键字的额外的选项。例如，在MySQL中，可以使用选项SQL_CALC_FOUND_ROWS。
     *
     * @return $this the query object itself
     * 返回值 查询对象自身。
     *
     * $query->select(['id', 'email']);
     * $query->select(['user.id AS user_id', 'email']);
     * $query->select(['user_id' => 'user.id', 'email']);
     * $query->select('user.id AS user_id, email');
     * $query->select(["CONCAT(first_name, ' ', last_name) AS full_name", 'email']);
     * $query->select('COUNT(*)')
     */
    public function select($columns, $option = null)
    {
        // $columns 继承自 DB 表达式
        if ($columns instanceof ExpressionInterface) {
            $columns = [$columns];
        } elseif (!is_array($columns)) {
            /**
             * @see http://php.net/manual/zh/function.preg-split.php
             * preg_split — 通过一个正则表达式分隔字符串
             * '/\s*,\s* /' : 用 逗号分隔字符串
             * trim($columns) ： 去掉 $columns 两边的空白字符
             * -1 ： limit值为-1， 0或null时都代表"不限制分割元素个数"
             * PREG_SPLIT_NO_EMPTY，如果设定了本标记，则 preg_split() 只返回非空的成分。
             *
             * 将 'id,name,created_by' 转换成 ['id', 'name', 'created_by']
             */
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        }
        $this->select = $this->getUniqueColumns($columns);
        $this->selectOption = $option;
        return $this;
    }

    /**
     * Add more columns to the SELECT part of the query.
     * 添加更多列到查询的SELECT部分
     *
     * Note, that if [[select]] has not been specified before, you should include `*` explicitly
     * if you want to select all remaining columns too:
     * 请注意，如果之前没有调用过select方法，又需要选择所有剩下的列，你应该明确地加入`*`：
     *
     * ```php
     * $query->addSelect(["*", "CONCAT(first_name, ' ', last_name) AS full_name"])->one();
     * ```
     *
     * @param string|array|ExpressionInterface $columns the columns to add to the select. See [[select()]] for more
     * details about the format of this parameter.
     * 参数 字符串|数组|表达式 增加到select子句的表达式。关于该参数的格式，请参考select()方法。
     *
     * @return $this the query object itself
     * 返回值 查询对象本身。
     *
     * 用法与 select() 相同
     * @see select()
     */
    public function addSelect($columns)
    {
        if ($columns instanceof ExpressionInterface) {
            $columns = [$columns];
        } elseif (!is_array($columns)) {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        }
        $columns = $this->getUniqueColumns($columns);
        if ($this->select === null) {
            $this->select = $columns;
        } else {
            $this->select = array_merge($this->select, $columns);
        }

        return $this;
    }

    /**
     * Returns unique column names excluding duplicates.
     * Columns to be removed:
     * - if column definition already present in SELECT part with same alias
     * - if column definition without alias already present in SELECT part without alias too
     * @param array $columns the columns to be merged to the select.
     * @since 2.0.14
     */
    protected function getUniqueColumns($columns)
    {
        $columns = array_unique($columns);
        $unaliasedColumns = $this->getUnaliasedColumnsFromSelect();

        foreach ($columns as $columnAlias => $columnDefinition) {
            if ($columnDefinition instanceof Query) {
                continue;
            }

            if (
                (is_string($columnAlias) && isset($this->select[$columnAlias]) && $this->select[$columnAlias] === $columnDefinition)
                || (is_integer($columnAlias) && in_array($columnDefinition, $unaliasedColumns))
            ) {
                unset($columns[$columnAlias]);
            }
        }
        return $columns;
    }

    /**
     * @return array List of columns without aliases from SELECT statement.
     * @since 2.0.14
     */
    protected function getUnaliasedColumnsFromSelect()
    {
        $result = [];
        if (is_array($this->select)) {
            foreach ($this->select as $name => $value) {
                if (is_integer($name)) {
                    $result[] = $value;
                }
            }
        }
        return array_unique($result);
    }

    /**
     * Sets the value indicating whether to SELECT DISTINCT or not.
     * 设置是否对选择结果去重。
     * 
     * @param bool $value whether to SELECT DISTINCT or not.
     * 参数 boolean 是否去掉结果中的重复值
     *
     * @return $this the query object itself
     * 返回值 查询对象本身
     */
    public function distinct($value = true)
    {
        $this->distinct = $value;
        return $this;
    }

    /**
     * Sets the FROM part of the query.
     * 设置查询语句的from部分。
     * 
     * @param string|array|ExpressionInterface $tables the table(s) to be selected from. This can be either a string (e.g. `'user'`)
     * or an array (e.g. `['user', 'profile']`) specifying one or several table names.
     * 参数 字符串|数组 from哪张表。可以用字符串(例如 `'user'`)也可以是数组(例如 `['user', 'profile']`)来指定一个或多个表。
     * 
     * Table names can contain schema prefixes (e.g. `'public.user'`) and/or table aliases (e.g. `'user u'`).
     * 表名称可以包含数据库前缀（例如`'public.user'`），或者表别名（例如，`'user u'`）。
     * 
     * The method will automatically quote the table names unless it contains some parenthesis
     * (which means the table is given as a sub-query or DB expression).
     * 该方法会自动引用表名，除非它包含一些插入语（也就是作为表的子查询或者DB表达式）。
     *
     * When the tables are specified as an array, you may also use the array keys as the table aliases
     * (if a table does not need alias, do not use a string key).
     * 当表名通过数组来指定的时候，你也可以使用数组键作为表的别名（如果一个表不需要别名，不要使用字符串做键）。
     *
     * Use a Query object to represent a sub-query. In this case, the corresponding array key will be used
     * as the alias for the sub-query.
     * 使用查询对象代表一个子查询。在这种情况下，相应的数组键将会用来做子查询的别名。
     * 
     * To specify the `FROM` part in plain SQL, you may pass an instance of [[ExpressionInterface]].
     *
     * Here are some examples:
     * 举例如下：
     *
     * ```php
     * // SELECT * FROM  `user` `u`, `profile`;
     * $query = (new \yii\db\Query)->from(['u' => 'user', 'profile']);
     *
     * // SELECT * FROM (SELECT * FROM `user` WHERE `active` = 1) `activeusers`;
     * $subquery = (new \yii\db\Query)->from('user')->where(['active' => true])
     * $query = (new \yii\db\Query)->from(['activeusers' => $subquery]);
     *
     * // subquery can also be a string with plain SQL wrapped in parenthesis
     * // 子查询也可以是一个括号包裹的SQL语句
     * // SELECT * FROM (SELECT * FROM `user` WHERE `active` = 1) `activeusers`;
     * $subquery = "(SELECT * FROM `user` WHERE `active` = 1)";
     * $query = (new \yii\db\Query)->from(['activeusers' => $subquery]);
     *
     * $query->from(['public.user u', 'public.post p']);
     * $query->from(['u' => 'public.user', 'p' => 'public.post']);
     *
     * ```
     *
     * @return $this the query object itself
     * 返回值 查询对象自身。
     */
    public function from($tables)
    {
        if ($tables instanceof Expression) {
            $tables = [$tables];
        }
        if (is_string($tables)) {
            /**
             * @see http://php.net/manual/zh/function.preg-split.php
             * preg_split — 通过一个正则表达式分隔字符串
             * '/\s*,\s* /' : 用 逗号分隔字符串
             * trim($columns) ： 去掉 $columns 两边的空白字符
             * -1 ： limit值为-1， 0或null时都代表"不限制分割元素个数"
             * PREG_SPLIT_NO_EMPTY，如果设定了本标记，则 preg_split() 只返回非空的成分。
             *
             * 将 'id,name,created_by' 转换成 ['id', 'name', 'created_by']
             */
            $tables = preg_split('/\s*,\s*/', trim($tables), -1, PREG_SPLIT_NO_EMPTY);
        }
        $this->from = $tables;
        return $this;
    }

    /**
     * Sets the WHERE part of the query.
     * 设置查询的where部分。
     *
     * The method requires a `$condition` parameter, and optionally a `$params` parameter
     * specifying the values to be bound to the query.
     * 该方法需要一个`$condition`参数，也可以提供一个`$params`参数来指定绑定到查询的值。
     *
     * The `$condition` parameter should be either a string (e.g. `'id=1'`) or an array.
     * `$condition`参数应当是一个字符串(例如 `'id=1'`)或者一个数组。
     *
     * {@inheritdoc}
     *
     * @param string|array|ExpressionInterface $condition the conditions that should be put in the WHERE part.
     * 参数 字符串|数组|表达式 应当放到WHERE部分的条件
     *
     * @param array $params the parameters (name => value) to be bound to the query.
     * 参数 数组 绑定到查询的参数键值对
     *
     * @return $this the query object itself
     * 返回值 查询对象自身
     *
     * $query->where('status=1');
     * $query->where('status=:status', [':status' => $status]);
     * $query->where('YEAR(somedate) = 2015');
     *
        // ...WHERE (`status` = 10) AND (`type` IS NULL) AND (`id` IN (4, 8, 15))
        $query->where([
            'status' => 10,
            'type' => null,
            'id' => [4, 8, 15],
        ]);
     *
     *  $userQuery = (new Query())->select('id')->from('user');
        // ...WHERE `id` IN (SELECT `id` FROM `user`)
        $query->where(['id' => $userQuery]);
     *
     *  当使用参数绑定的时候，你可以调用 params() 或者 addParams() 方法 来分别绑定不同的参数。

        $query->where('status=:status')->addParams([':status' => $status]);
     *
     * 千万不要像如下的例子一样直接在条件语句当中嵌入变量，特别是当这些变量来源于终端用户输入的时候， 因为这样我们的软件将很容易受到 SQL 注入的攻击。

        // 危险！千万别这样干，除非你非常的确定 $status 是一个整型数值。
        $query->where("status=$status");
     *
     * @see andWhere()
     * @see orWhere()
     * @see QueryInterface::where()
     */
    public function where($condition, $params = [])
    {
        $this->where = $condition;
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * 在已经存在的where条件上再追加一个。
     * 
     * The new condition and the existing one will be joined using the `AND` operator.
     * 新条件和已经存在的条件之间使用and操作符联接。
     * 
     * @param string|array|ExpressionInterface $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * 参数 字符串|数组|表达式 新的WHERE条件。如何指定该参数，请参考where()方法
     *
     * @param array $params the parameters (name => value) to be bound to the query.
     * 参数 绑定到查询的参数键值对。
     *
     * @return $this the query object itself
     * 返回值 查询对象自身。
     *
     * @see where()
     * @see orWhere()
     */
    public function andWhere($condition, $params = [])
    {
        // 若where 为空，则直接将 条件赋给 where
        if ($this->where === null) {
            $this->where = $condition;
        } elseif (is_array($this->where) && isset($this->where[0]) && strcasecmp($this->where[0], 'and') === 0) {
            $this->where[] = $condition;
        } else {
            // 若 where 不为空，则使用 'and' 连接查询条件
            $this->where = ['and', $this->where, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * 在已经存在的基础上，追加一个额外的where条件。
     *
     * The new condition and the existing one will be joined using the `OR` operator.
     * 新条件和已经存在的条件之间使用or操作符联接。
     * 
     * @param string|array|ExpressionInterface $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * 参数 字符串|数组|表达式 新的WHERE条件。关于如何指定该参数，请参考where()方法指定。
     *
     * @param array $params the parameters (name => value) to be bound to the query.
     * 参数 数组 绑定到查询的参数键值对。
     *
     * @return $this the query object itself
     * 返回值 查询对象自身
     *
     * @see where()
     * @see andWhere()
     */
    public function orWhere($condition, $params = [])
    {
        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where = ['or', $this->where, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds a filtering condition for a specific column and allow the user to choose a filter operator.
     * 为特定的列添加过滤条件，并且允许用户选择一个过滤器操作符。
     *
     * It adds an additional WHERE condition for the given field and determines the comparison operator
     * based on the first few characters of the given value.
     * 它为给定的字段添加了一个where条件并且基于给定值的前边几个字符检测了对比操作符
     *
     * The condition is added in the same way as in [[andFilterWhere]] so [[isEmpty()|empty values]] are ignored.
     * The new condition and the existing one will be joined using the `AND` operator.
     * 该条件的添加方式跟[[andFilterWhere]]方法里边类似，所以[[isEmpty()|empty values]]被忽略了。新条件和已经存在的条件会使用AND操作符联接
     *
     * The comparison operator is intelligently determined based on the first few characters in the given value.
     * 对比操作符在给定的值内部开头的几个字符决定。
     *
     * In particular, it recognizes the following operators if they appear as the leading characters in the given value:
     * 特别地，如果下边的这些操作符出现，它就可以识别：
     *
     * - `<`: the column must be less than the given value.
     * - `<`: 列值必须小于给定的值.
     * - `>`: the column must be greater than the given value.
     * - `>`: 列值必须大于给定的值.
     * - `<=`: the column must be less than or equal to the given value.
     * - `<=`: 列值必须小于或等于给定的值.
     * - `>=`: the column must be greater than or equal to the given value.
     * - `>=`: 列值必须大于等于给定的值.
     * - `<>`: the column must not be the same as the given value.
     * - `<>`: 列值必须不能和给定的值相等.
     * - `=`: the column must be equal to the given value.
     * - `=`: 列值必须等于给定的值.
     * - If none of the above operators is detected, the `$defaultOperator` will be used.
     * - 如果以上的情况都没有检测到, 将会采用参数`$defaultOperator`.
     *
     * @param string $name the column name.
     * 参数 字符串 列名
     *
     * @param string $value the column value optionally prepended with the comparison operator.
     * 参数 字符串 带有可选的对比符的列值
     *
     * @param string $defaultOperator The operator to use, when no operator is given in `$value`.
     * Defaults to `=`, performing an exact match.
     * 参数 字符串 使用到的操作符。如果在$value里边没有给定，默认的是=，执行一个精确匹配。
     *
     * @return $this The query object itself
     * 返回值 查询对象自身
     *
     * @since 2.0.8
     */
    public function andFilterCompare($name, $value, $defaultOperator = '=')
    {
        if (preg_match('/^(<>|>=|>|<=|<|=)/', $value, $matches)) {
            $operator = $matches[1];
            $value = substr($value, strlen($operator));
        } else {
            $operator = $defaultOperator;
        }

        return $this->andFilterWhere([$operator, $name, $value]);
    }

    /**
     * Appends a JOIN part to the query.
     * 给查询添加一个联接。
     *
     * The first parameter specifies what type of join it is.
     * 第一个参数指定了联接的类型。
     *
     * @param string $type the type of join, such as INNER JOIN, LEFT JOIN.
     * 参数 字符串 联接的类型，例如INNER JOIN, LEFT JOIN。
     *
     * @param string|array $table the table to be joined.
     * 参数 字符串|数组 联接的表
     *
     * Use a string to represent the name of the table to be joined.
     * 使用字符串来表示需要联接的表名。
     *
     * The table name can contain a schema prefix (e.g. 'public.user') and/or table alias (e.g. 'user u').
     * The method will automatically quote the table name unless it contains some parenthesis
     * (which means the table is given as a sub-query or DB expression).
     * 表名可以带有数据库前缀（例如 'public.user'）或者表的别名（例如 'user u'）。该方法会自动引用表名除非它包含一些插入语（也就是说表名是子产讯或者DB表达式）
     *
     * Use an array to represent joining with a sub-query. The array must contain only one element.
     * The value must be a [[Query]] object representing the sub-query while the corresponding key
     * represents the alias for the sub-query.
     * 使用数据来表达联接一个子查询。数组只能包含一个元素。值必须是一个Query代表的子查询，键代表子查询的别名。
     *
     * @param string|array $on the join condition that should appear in the ON part.
     * 参数 字符串|数组 出现在on部分的联接的条件
     *
     * Please refer to [[where()]] on how to specify this parameter.
     * 关于如何指定该参数，请参考where()方法
     *
     * Note that the array format of [[where()]] is designed to match columns to values instead of columns to columns, so
     * the following would **not** work as expected: `['post.author_id' => 'user.id']`, it would
     * match the `post.author_id` column value against the string `'user.id'`.
     * It is recommended to use the string syntax here which is more suited for a join:
     * 请注意，where()方法的数组格式的设计，用来匹配列的值而不是列，所以`['post.author_id' => 'user.id']`并不会像预期的那样，它会把`post.author_id`
     * 列和字符串`'user.id'`进行匹配。在这里更推荐使用字符串的语法来指定一个联接条件：
     *
     * ```php
     * 'post.author_id = user.id'
     * ```
     *
     * @param array $params the parameters (name => value) to be bound to the query.
     * 参数 数组 绑定到该查询的参数键值对。
     *
     * @return $this the query object itself
     * 返回值 查询对象本身。
     */
    public function join($type, $table, $on = '', $params = [])
    {
        $this->join[] = [$type, $table, $on];
        return $this->addParams($params);
    }

    /**
     * Appends an INNER JOIN part to the query.
     * 给查询添加一个内联接。
     *
     * @param string|array $table the table to be joined.
     * 参数 字符串|数组 需要联接的表。
     *
     * Use a string to represent the name of the table to be joined.
     * 使用字符串来表示需要联接的表名。
     *
     * The table name can contain a schema prefix (e.g. 'public.user') and/or table alias (e.g. 'user u').
     * The method will automatically quote the table name unless it contains some parenthesis
     * (which means the table is given as a sub-query or DB expression).
     * 表名可以带有数据库前缀（例如 'public.user'）或者表的别名（例如 'user u'）。该方法会自动引用表名除非它包含一些插入语（也就是说表名是子产讯或者DB表达式）
     *
     * Use an array to represent joining with a sub-query. The array must contain only one element.
     * The value must be a [[Query]] object representing the sub-query while the corresponding key
     * represents the alias for the sub-query.
     * 使用数据来表达联接一个子查询。数组只能包含一个元素。值必须是一个Query代表的子查询，键代表子查询的别名。
     *
     * @param string|array $on the join condition that should appear in the ON part.
     * 参数 字符串|数组 出现在on部分的联接的条件
     *
     * Please refer to [[join()]] on how to specify this parameter.
     * 如何指定该参数，请参考join()方法
     *
     * @param array $params the parameters (name => value) to be bound to the query.
     * 参数 数组 绑定到该查询的参数键值对。
     *
     * @return $this the query object itself
     * 返回值 查询对象本身。
     */
    public function innerJoin($table, $on = '', $params = [])
    {
        $this->join[] = ['INNER JOIN', $table, $on];
        return $this->addParams($params);
    }

    /**
     * Appends a LEFT OUTER JOIN part to the query.
     * 给查询添加一个左联接。
     *
     * @param string|array $table the table to be joined.
     * 参数 字符串|数组 需要联接的表。
     *
     * Use a string to represent the name of the table to be joined.
     * 使用字符串来表示需要联接的表名。
     *
     * The table name can contain a schema prefix (e.g. 'public.user') and/or table alias (e.g. 'user u').
     * The method will automatically quote the table name unless it contains some parenthesis
     * (which means the table is given as a sub-query or DB expression).
     * 表名可以带有数据库前缀（例如 'public.user'）或者表的别名（例如 'user u'）。该方法会自动引用表名除非它包含一些插入语（也就是说表名是子产讯或者DB表达式）
     *
     * Use an array to represent joining with a sub-query. The array must contain only one element.
     * The value must be a [[Query]] object representing the sub-query while the corresponding key
     * represents the alias for the sub-query.
     * 使用数据来表达联接一个子查询。数组只能包含一个元素。值必须是一个Query代表的子查询，键代表子查询的别名。
     *
     * @param string|array $on the join condition that should appear in the ON part.
     * 参数 字符串|数组 出现在on部分的联接的条件
     *
     * Please refer to [[join()]] on how to specify this parameter.
     * 如何指定该参数，请参考join()方法
     *
     * @param array $params the parameters (name => value) to be bound to the query
     * 参数 数组 绑定到该查询的参数键值对。
     *
     * @return $this the query object itself
     * 返回值 查询对象本身。
     */
    public function leftJoin($table, $on = '', $params = [])
    {
        $this->join[] = ['LEFT JOIN', $table, $on];
        return $this->addParams($params);
    }

    /**
     * Appends a RIGHT OUTER JOIN part to the query.
     * 给查询添加右联接部分。
     *
     * @param string|array $table the table to be joined.
     * 参数 字符串|数组 联接的表。
     *
     * Use a string to represent the name of the table to be joined.
     * 使用字符串来表示需要联接的表名。
     *
     * The table name can contain a schema prefix (e.g. 'public.user') and/or table alias (e.g. 'user u').
     * The method will automatically quote the table name unless it contains some parenthesis
     * (which means the table is given as a sub-query or DB expression).
     * 表名可以带有数据库前缀（例如 'public.user'）或者表的别名（例如 'user u'）。该方法会自动引用表名除非它包含一些插入语（也就是说表名是子产讯或者DB表达式）
     *
     * Use an array to represent joining with a sub-query. The array must contain only one element.
     * The value must be a [[Query]] object representing the sub-query while the corresponding key
     * represents the alias for the sub-query.
     * 使用数据来表达联接一个子查询。数组只能包含一个元素。值必须是一个Query代表的子查询，键代表子查询的别名。
     *
     * @param string|array $on the join condition that should appear in the ON part.
     * 参数 字符串|数组 出现在on部分的联接的条件
     *
     * Please refer to [[join()]] on how to specify this parameter.
     * 如何指定该参数，请参考join()方法
     *
     * @param array $params the parameters (name => value) to be bound to the query
     * 参数 数组 绑定到该查询的参数键值对。
     *
     * @return $this the query object itself
     * 返回值 查询对象本身。
     */
    public function rightJoin($table, $on = '', $params = [])
    {
        $this->join[] = ['RIGHT JOIN', $table, $on];
        return $this->addParams($params);
    }

    /**
     * Sets the GROUP BY part of the query.
     * 为查询设置GROUP BY部分。
     *
     * @param string|array|ExpressionInterface $columns the columns to be grouped by.
     * 参数 字符串|数组|表达式 需要分组的列。
     *
     * Columns can be specified in either a string (e.g. "id, name") or an array (e.g. ['id', 'name']).
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     * 可以使用字符串(例如 "id, name")或者数组(例如 ['id', 'name'])指定列。该方法会自动引用列名除非列名包含插入语（也就是说列名里边包含DB表达式）
     *
     * Note that if your group-by is an expression containing commas, you should always use an array
     * to represent the group-by information. Otherwise, the method will not be able to correctly determine
     * the group-by columns.
     * 请注意，如果你的group-by是一个包含逗号的表达式，你应该使用数组来表示分组信息。否则，该方法就无法正确检测分组的列。
     *
     * Since version 2.0.7, an [[ExpressionInterface]] object can be passed to specify the GROUP BY part explicitly in plain SQL.
     * 从版本2.0.7以后，可以传递表达式对象明确地在普通SQL中指定GROUP BY的部分
     * 
     * Since version 2.0.14, an [[ExpressionInterface]] object can be passed as well.
     * @return $this the query object itself
     * @see addGroupBy()
     */
    public function groupBy($columns)
    {
        if ($columns instanceof ExpressionInterface) {
            $columns = [$columns];
        } elseif (!is_array($columns)) {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        }
        $this->groupBy = $columns;
        return $this;
    }

    /**
     * Adds additional group-by columns to the existing ones.
     * 追加group by子句到已经存在的部分。
     *
     * @param string|array $columns additional columns to be grouped by.
     * Columns can be specified in either a string (e.g. "id, name") or an array (e.g. ['id', 'name']).
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     * 参数 字符串|数组 额外需要分组的列
     * 可以使用字符串(例如 "id, name")或者数组(例如 ['id', 'name'])指定列。该方法会自动引用列名除非列名包含插入语（也就是说列名里边包含DB表达式）
     *
     * Note that if your group-by is an expression containing commas, you should always use an array
     * to represent the group-by information. Otherwise, the method will not be able to correctly determine
     * the group-by columns.
     * 请注意，如果你的group-by是一个包含逗号的表达式，你应该使用数组来表示分组信息。否则，该方法就无法正确检测分组的列。
     *
     * Since version 2.0.7, an [[Expression]] object can be passed to specify the GROUP BY part explicitly in plain SQL.
     * 从版本2.0.7以后，可以传递表达式对象明确地在普通SQL中指定GROUP BY的部分
     * 
     * Since version 2.0.14, an [[ExpressionInterface]] object can be passed as well.
     * @return $this the query object itself
     * 返回值 查询对象自身。
     *
     * @see groupBy()
     */
    public function addGroupBy($columns)
    {
        if ($columns instanceof ExpressionInterface) {
            $columns = [$columns];
        } elseif (!is_array($columns)) {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        }
        if ($this->groupBy === null) {
            $this->groupBy = $columns;
        } else {
            $this->groupBy = array_merge($this->groupBy, $columns);
        }

        return $this;
    }

    /**
     * Sets the HAVING part of the query.
     * 设置查询的HAVEING部分。
     *
     * @param string|array|ExpressionInterface $condition the conditions to be put after HAVING.
     * Please refer to [[where()]] on how to specify this parameter.
     * 参数 字符串|数组|表达式 放在HAVING后边的条件。如何指定该参数，请参考[[where()]]
     *
     * @param array $params the parameters (name => value) to be bound to the query.
     * 参数 数组 绑定到查询的参数（键值对）
     *
     * @return $this the query object itself
     * 查询对象本身
     *
     * @see andHaving()
     * @see orHaving()
     */
    public function having($condition, $params = [])
    {
        $this->having = $condition;
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional HAVING condition to the existing one.
     * 新增一个额外的HAVING条件到已经存在的sql语句
     * 
     * The new condition and the existing one will be joined using the `AND` operator.
     * 新的条件和已经存在的条件会使用and连接
     *
     * @param string|array|ExpressionInterface $condition the new HAVING condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * 参数 字符串|数组|表达式 新的HAVING条件。关于如何指定该参数，请参考[[where()]]
     *
     * @param array $params the parameters (name => value) to be bound to the query.
     * 参数 数组 绑定到查询的参数（键值对）
     *
     * @return $this the query object itself
     * 返回值 查询对象自身。
     *
     * @see having()
     * @see orHaving()
     */
    public function andHaving($condition, $params = [])
    {
        if ($this->having === null) {
            $this->having = $condition;
        } else {
            $this->having = ['and', $this->having, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional HAVING condition to the existing one.
     * 给已经存在的sql语句添加HAVEING条件。
     *
     * The new condition and the existing one will be joined using the `OR` operator.
     * 新的条件和已经存在的条件会使用OR符连接。
     * 
     * @param string|array|ExpressionInterface $condition the new HAVING condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * 参数 字符串|数组|表达式 新的HAVING条件。请参考[[where()]]方法查看如何指定该参数。
     *
     * @param array $params the parameters (name => value) to be bound to the query.
     * 参数 数组 绑定到查询的参数（键值对）
     *
     * @return $this the query object itself
     * 返回值 查询对象本身。
     *
     * @see having()
     * @see andHaving()
     */
    public function orHaving($condition, $params = [])
    {
        if ($this->having === null) {
            $this->having = $condition;
        } else {
            $this->having = ['or', $this->having, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Sets the HAVING part of the query but ignores [[isEmpty()|empty operands]].
     *
     * This method is similar to [[having()]]. The main difference is that this method will
     * remove [[isEmpty()|empty query operands]]. As a result, this method is best suited
     * for building query conditions based on filter values entered by users.
     *
     * The following code shows the difference between this method and [[having()]]:
     *
     * ```php
     * // HAVING `age`=:age
     * $query->filterHaving(['name' => null, 'age' => 20]);
     * // HAVING `age`=:age
     * $query->having(['age' => 20]);
     * // HAVING `name` IS NULL AND `age`=:age
     * $query->having(['name' => null, 'age' => 20]);
     * ```
     *
     * Note that unlike [[having()]], you cannot pass binding parameters to this method.
     *
     * @param array $condition the conditions that should be put in the HAVING part.
     * See [[having()]] on how to specify this parameter.
     * @return $this the query object itself
     * @see having()
     * @see andFilterHaving()
     * @see orFilterHaving()
     * @since 2.0.11
     */
    public function filterHaving(array $condition)
    {
        $condition = $this->filterCondition($condition);
        if ($condition !== []) {
            $this->having($condition);
        }

        return $this;
    }

    /**
     * Adds an additional HAVING condition to the existing one but ignores [[isEmpty()|empty operands]].
     * The new condition and the existing one will be joined using the `AND` operator.
     *
     * This method is similar to [[andHaving()]]. The main difference is that this method will
     * remove [[isEmpty()|empty query operands]]. As a result, this method is best suited
     * for building query conditions based on filter values entered by users.
     *
     * @param array $condition the new HAVING condition. Please refer to [[having()]]
     * on how to specify this parameter.
     * @return $this the query object itself
     * @see filterHaving()
     * @see orFilterHaving()
     * @since 2.0.11
     */
    public function andFilterHaving(array $condition)
    {
        $condition = $this->filterCondition($condition);
        if ($condition !== []) {
            $this->andHaving($condition);
        }

        return $this;
    }

    /**
     * Adds an additional HAVING condition to the existing one but ignores [[isEmpty()|empty operands]].
     * The new condition and the existing one will be joined using the `OR` operator.
     *
     * This method is similar to [[orHaving()]]. The main difference is that this method will
     * remove [[isEmpty()|empty query operands]]. As a result, this method is best suited
     * for building query conditions based on filter values entered by users.
     *
     * @param array $condition the new HAVING condition. Please refer to [[having()]]
     * on how to specify this parameter.
     * @return $this the query object itself
     * @see filterHaving()
     * @see andFilterHaving()
     * @since 2.0.11
     */
    public function orFilterHaving(array $condition)
    {
        $condition = $this->filterCondition($condition);
        if ($condition !== []) {
            $this->orHaving($condition);
        }

        return $this;
    }

    /**
     *  $query1 = (new \yii\db\Query())
     *      ->select("id, category_id AS type, name")
     *      ->from('post')
     *      ->limit(10);
     *  $query2 = (new \yii\db\Query())
     *      ->select('id, type, name')
     *      ->from('user')
     *      ->limit(10);
     * 
     *  $query1->union($query2);
     * 
     * 你可以通过多次调用 union() 方法来追加更多的 UNION 子句。
     *
     * Appends a SQL statement using UNION operator.
     * 把UNION操作增加到一个sql语句
     *
     * @param string|Query $sql the SQL statement to be appended using UNION
     * 参数 字符串|查询 要增加到的sql语句
     * 
     * @param bool $all TRUE if using UNION ALL and FALSE if using UNION
     * 参数 boolean 为真使用UNION ALL 为假使用UNION
     * 
     * @return $this the query object itself
     * 返回值 查询对象本身
     */
    public function union($sql, $all = false)
    {
        $this->union[] = ['query' => $sql, 'all' => $all];
        return $this;
    }

    /**
     * Sets the parameters to be bound to the query.
     * 设置绑定到查询的参数。
     *
     * @param array $params list of query parameter values indexed by parameter placeholders.
     * For example, `[':name' => 'Dan', ':age' => 31]`.
     * 参数 数组 查询参数列表，使用参数的占位符做索引。例如`[':name' => 'Dan', ':age' => 31]`
     *
     * @return $this the query object itself
     * 返回值 查询对象本身。
     *
     * @see addParams()
     */
    public function params($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Adds additional parameters to be bound to the query.
     * 增加额外的绑定到查询的参数，
     *
     * @param array $params list of query parameter values indexed by parameter placeholders.
     * For example, `[':name' => 'Dan', ':age' => 31]`.
     * 参数 数组 查询参数列表，使用参数的占位符做索引。例如`[':name' => 'Dan', ':age' => 31]`
     *
     * @return $this the query object itself
     * 返回值 查询对象本身。
     *
     * @see params()
     */
    public function addParams($params)
    {
        if (!empty($params)) {
            if (empty($this->params)) {
                // 若 params 为空，则直接将 $params 赋给 $this->params
                $this->params = $params;
            } else {
                // 遍历 $params
                foreach ($params as $name => $value) {
                    if (is_int($name)) {
                        $this->params[] = $value;
                    } else {
                        $this->params[$name] = $value;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Enables query cache for this Query.
     * @param int|true $duration the number of seconds that query results can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     * Use a negative number to indicate that query cache should not be used.
     * Use boolean `true` to indicate that [[Connection::queryCacheDuration]] should be used.
     * Defaults to `true`.
     * @param \yii\caching\Dependency $dependency the cache dependency associated with the cached result.
     * @return $this the Query object itself
     * @since 2.0.14
     */
    public function cache($duration = true, $dependency = null)
    {
        $this->queryCacheDuration = $duration;
        $this->queryCacheDependency = $dependency;
        return $this;
    }

    /**
     * Disables query cache for this Query.
     * @return $this the Query object itself
     * @since 2.0.14
     */
    public function noCache()
    {
        $this->queryCacheDuration = -1;
        return $this;
    }

    /**
     * Sets $command cache, if this query has enabled caching.
     *
     * @param Command $command
     * @return Command
     * @since 2.0.14
     */
    protected function setCommandCache($command)
    {
        if ($this->queryCacheDuration !== null || $this->queryCacheDependency !== null) {
            $duration = $this->queryCacheDuration === true ? null : $this->queryCacheDuration;
            $command->cache($duration, $this->queryCacheDependency);
        }

        return $command;
    }

    /**
     * Creates a new Query object and copies its property values from an existing one.
     * 创建一个新的查询对象，并从已经存在的属性中复制它的属性值。
     *
     * The properties being copies are the ones to be used by query builders.
     * 被复制的属性就是被查询生成器使用的那些。
     *
     * @param Query $from the source query object
     * 参数 源查询对象
     *
     * @return Query the new Query object
     * 返回值 新的查询对象
     */
    public static function create($from)
    {
        return new self([
            'where' => $from->where,
            'limit' => $from->limit,
            'offset' => $from->offset,
            'orderBy' => $from->orderBy,
            'indexBy' => $from->indexBy,
            'select' => $from->select,
            'selectOption' => $from->selectOption,
            'distinct' => $from->distinct,
            'from' => $from->from,
            'groupBy' => $from->groupBy,
            'join' => $from->join,
            'having' => $from->having,
            'union' => $from->union,
            'params' => $from->params,
        ]);
    }

    /**
     * Returns the SQL representation of Query
     * @return string
     */
    public function __toString()
    {
        return serialize($this);
    }
}
