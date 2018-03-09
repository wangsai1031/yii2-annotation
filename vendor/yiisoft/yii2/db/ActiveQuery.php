<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db;

use yii\base\InvalidConfigException;

/**
 * ActiveQuery表示一个与活动记录类相关联的DB查询
 * ActiveQuery represents a DB query associated with an Active Record class.
 *
 * ActiveQuery可以是一个普通的查询，也可以在关联查询中使用
 * An ActiveQuery can be a normal query or be used in a relational context.
 *
 * ActiveQuery实例通常由ActiveRecord::find()和ActiveRecord::findBySql()创建
 * 关联查询是由ActiveRecord::hasOne()和ActiveRecord::hasMany()创建
 * ActiveQuery instances are usually created by [[ActiveRecord::find()]] and [[ActiveRecord::findBySql()]].
 * Relational queries are created by [[ActiveRecord::hasOne()]] and [[ActiveRecord::hasMany()]].
 *
 * Normal Query
 * ------------
 *
 * ActiveQuery mainly provides the following methods to retrieve the query results:
 *
 * - [[one()]]: returns a single record populated with the first row of data.
 * - [[all()]]: returns all records based on the query results.
 * - [[count()]]: returns the number of records.
 * - [[sum()]]: returns the sum over the specified column.
 * - [[average()]]: returns the average over the specified column.
 * - [[min()]]: returns the min over the specified column.
 * - [[max()]]: returns the max over the specified column.
 * - [[scalar()]]: returns the value of the first column in the first row of the query result.
 * - [[column()]]: returns the value of the first column in the query result.
 * - [[exists()]]: returns a value indicating whether the query result has data or not.
 *
 * Because ActiveQuery extends from [[Query]], one can use query methods, such as [[where()]],
 * [[orderBy()]] to customize the query options.
 *
 * ActiveQuery also provides the following additional query options:
 *
 * - [[with()]]: list of relations that this query should be performed with.
 * - [[joinWith()]]: reuse a relation query definition to add a join to a query.
 * - [[indexBy()]]: the name of the column by which the query result should be indexed.
 * - [[asArray()]]: whether to return each record as an array.
 *
 * These options can be configured using methods of the same name. For example:
 *
 * ```php
 * $customers = Customer::find()->with('orders')->asArray()->all();
 * ```
 *
 * Relational query
 * ----------------
 *
 * In relational context ActiveQuery represents a relation between two Active Record classes.
 *
 * Relational ActiveQuery instances are usually created by calling [[ActiveRecord::hasOne()]] and
 * [[ActiveRecord::hasMany()]]. An Active Record class declares a relation by defining
 * a getter method which calls one of the above methods and returns the created ActiveQuery object.
 *
 * A relation is specified by [[link]] which represents the association between columns
 * of different tables; and the multiplicity of the relation is indicated by [[multiple]].
 *
 * If a relation involves a junction table, it may be specified by [[via()]] or [[viaTable()]] method.
 * These methods may only be called in a relational context. Same is true for [[inverseOf()]], which
 * marks a relation as inverse of another relation and [[onCondition()]] which adds a condition that
 * is to be added to relational query join condition.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class ActiveQuery extends Query implements ActiveQueryInterface
{
    use ActiveQueryTrait;
    use ActiveRelationTrait;

    /**
     * 当通过init()初始化查询时触发的事件
     * @event Event an event that is triggered when the query is initialized via [[init()]].
     */
    const EVENT_INIT = 'init';

    /**
     * 为获取AR记录而执行的SQL语句
     * 由[[ActiveRecord::findBySql()]]设置
     * @var string the SQL statement to be executed for retrieving AR records.
     * This is set by [[ActiveRecord::findBySql()]].
     */
    public $sql;
    /**
     * 关联查询时使用的连接条件。
     * 该条件将在ActiveQuery::joinWith()被调用时使用。
     * 否则，条件将在查询的WHERE部分中使用
     * 关于如何指定该参数，请参考 @see Query::where()
     * @var string|array the join condition to be used when this query is used in a relational context.
     * The condition will be used in the ON part when [[ActiveQuery::joinWith()]] is called.
     * Otherwise, the condition will be used in the WHERE part of a query.
     * Please refer to [[Query::where()]] on how to specify this parameter.
     * @see onCondition()
     */
    public $on;
    /**
     * 应该与此查询关联的的关系列表
     * @var array a list of relations that this query should be joined with
     */
    public $joinWith;


    /**
     * 构造方法
     * Constructor.
     * 与此查询相关联的模型类
     * @param string $modelClass the model class associated with this query
     * @param array $config configurations to be applied to the newly created query object
     */
    public function __construct($modelClass, $config = [])
    {
        $this->modelClass = $modelClass;
        parent::__construct($config);
    }

    /**
     * 初始化对象
     * 这个方法在构造函数的末尾被调用。
     * 如果您覆盖了这个方法，确保您在最后调用parent::init()来确保事件的触发
     * Initializes the object.
     * This method is called at the end of the constructor. The default implementation will trigger
     * an [[EVENT_INIT]] event. If you override this method, make sure you call the parent implementation at the end
     * to ensure triggering of the event.
     */
    public function init()
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }

    /**
     * 执行查询并返回所有结果组成的数组
     * Executes query and returns all results as an array.
     * @param Connection $db the DB connection used to create the DB command.
     * If null, the DB connection returned by [[modelClass]] will be used.
     * @return array|ActiveRecord[] the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * 创建SQL前的预处理
     * {@inheritdoc}
     */
    public function prepare($builder)
    {
        // 因为相同的ActiveQuery可能用于构建不同的SQL语句
        // 例如，通过ActiveDataProvider，一个用于计数查询，另一个用于行数据查询
        // 确保相同的ActiveQuery可以多次用于构建SQL语句，这一点很重要
        // NOTE: because the same ActiveQuery may be used to build different SQL statements
        // (e.g. by ActiveDataProvider, one for count query, the other for row data query,
        // it is important to make sure the same ActiveQuery can be used to build SQL statements
        // multiple times.

        // 当存在joinWith时，进行联表
        if (!empty($this->joinWith)) {
            $this->buildJoinWith();
            $this->joinWith = null;    // clean it up to avoid issue https://github.com/yiisoft/yii2/issues/2687
        }

        // 当from为空，则获取modelClass的表名
        if (empty($this->from)) {
            $this->from = [$this->getPrimaryTableName()];
        }

        if (empty($this->select) && !empty($this->join)) {
            list(, $alias) = $this->getTableNameAndAlias();
            $this->select = ["$alias.*"];
        }

        if ($this->primaryModel === null) {
            // eager loading
            // 预先加载
            // 创建一个新的查询对象
            $query = Query::create($this);
        } else {
            // 延迟加载
            // lazy loading of a relation
            $where = $this->where;

            if ($this->via instanceof self) {
                // via junction table
                $viaModels = $this->via->findJunctionRows([$this->primaryModel]);
                $this->filterByModels($viaModels);
            } elseif (is_array($this->via)) {
                // via relation
                /* @var $viaQuery ActiveQuery */
                list($viaName, $viaQuery) = $this->via;
                if ($viaQuery->multiple) {
                    $viaModels = $viaQuery->all();
                    $this->primaryModel->populateRelation($viaName, $viaModels);
                } else {
                    $model = $viaQuery->one();
                    $this->primaryModel->populateRelation($viaName, $model);
                    $viaModels = $model === null ? [] : [$model];
                }
                $this->filterByModels($viaModels);
            } else {
                $this->filterByModels([$this->primaryModel]);
            }

            $query = Query::create($this);
            $this->where = $where;
        }

        if (!empty($this->on)) {
            $query->andWhere($this->on);
        }

        return $query;
    }

    /**
     * 将查询到的内容 $rows 填充到ActiveRecord中
     * {@inheritdoc}
     */
    public function populate($rows)
    {
        if (empty($rows)) {
            return [];
        }

        // 将查询到的行数据转换为模型实例数组
        $models = $this->createModels($rows);
        // 若join不为空却 indexBy为空
        if (!empty($this->join) && $this->indexBy === null) {
            // 当执行连接查询时，这可能会导致返回的重复行。该方法通过检查它们的主键值来删除重复的模型
            $models = $this->removeDuplicatedModels($models);
        }
        if (!empty($this->with)) {
            // 若with 不为空
            $this->findWith($this->with, $models);
        }

        if ($this->inverseOf !== null) {
            // 若存在逆向关联关系
            $this->addInverseRelations($models);
        }

        // 注意：使用 asArray() 将不会触发 AfterFind()事件
        if (!$this->asArray) {
            // afterFind()不干别的，就是专门调用 $this->trigger(self::EVENT_AFTER_FIND) 来触发事件的。
            // 在完成查询之后，查询到了多少个记录， 就会触发多少次实例级别的 EVENT_AFTER_FIND 事件
            foreach ($models as $model) {
                $model->afterFind();
            }
        }

        return parent::populate($models);
    }

    /**
     * 通过检查它们的主键值来删除重复的模型
     * Removes duplicated models by checking their primary key values.
     * 当执行连接查询时，这可能会导致返回的重复行。该方法主要在此时被调用。
     * This method is mainly called when a join query is performed, which may cause duplicated rows being returned.
     * @param array $models the models to be checked
     * @throws InvalidConfigException if model primary key is empty
     * @return array the distinctive models
     */
    private function removeDuplicatedModels($models)
    {
        $hash = [];
        /* @var $class ActiveRecord */
        $class = $this->modelClass;
        $pks = $class::primaryKey();

        if (count($pks) > 1) {
            // 复合主键
            // composite primary key
            foreach ($models as $i => $model) {
                $key = [];
                foreach ($pks as $pk) {
                    if (!isset($model[$pk])) {
                        // 如果主键不是结果集的一部分，就不要继续执行
                        // do not continue if the primary key is not part of the result set
                        break 2;
                    }
                    $key[] = $model[$pk];
                }
                $key = serialize($key);
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } else {
                    $hash[$key] = true;
                }
            }
        } elseif (empty($pks)) {
            throw new InvalidConfigException("Primary key of '{$class}' can not be empty.");
        } else {
            // 单列主键
            // single column primary key
            $pk = reset($pks);
            foreach ($models as $i => $model) {
                if (!isset($model[$pk])) {
                    // 如果主键不是结果集的一部分，就不要继续执行
                    // do not continue if the primary key is not part of the result set
                    break;
                }
                $key = $model[$pk];
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } elseif ($key !== null) {
                    $hash[$key] = true;
                }
            }
        }

        return array_values($models);
    }

    /**
     * 执行查询并返回一行结果
     * Executes query and returns a single row of result.
     * 用于创建DB命令的DB连接
     * @param Connection|null $db the DB connection used to create the DB command.
     * If `null`, the DB connection returned by [[modelClass]] will be used.
     * 单行查询结果。 返回类型取决于 [[asArray]] 设置。
     * 如果没查询到数据，将会返回 null
     * @return ActiveRecord|array|null a single row of query result. Depending on the setting of [[asArray]],
     * the query result may be either an array or an ActiveRecord object. `null` will be returned
     * if the query results in nothing.
     */
    public function one($db = null)
    {
        // 执行查询，并返回一行数据，若没查到，则返回false
        $row = parent::one($db);
        if ($row !== false) {
            // 若查到了，则将查询到的内容 $rows 填充到ActiveRecord中
            // 此时返回的数据是只有一个ActiveRecord的数组
            $models = $this->populate([$row]);
            // 获取第一个元素，即查到的 ActiveRecord
            return reset($models) ?: null;
        }

        return null;
    }

    /**
     * 创建可以用于执行此查询的DB命令
     * Creates a DB command that can be used to execute this query.
     * 用于创建DB命令的DB连接。
     * @param Connection|null $db the DB connection used to create the DB command.
     * If `null`, the DB connection returned by [[modelClass]] will be used.
     * @return Command the created DB command instance.
     */
    public function createCommand($db = null)
    {
        /* @var $modelClass ActiveRecord */
        $modelClass = $this->modelClass;
        if ($db === null) {
            // 若 数据库链接为空，则默认使用ActiveRecord的设定的数据库链接
            $db = $modelClass::getDb();
        }

        if ($this->sql === null) {
            // 若sql为空，则从查询对象的属性中生成一个SELECT语句
            list($sql, $params) = $db->getQueryBuilder()->build($this);
        } else {
            // 若sql不为空，则获取sql和参数（预处理字段等，用于防止sql注入）
            $sql = $this->sql;
            $params = $this->params;
        }

        $command = $db->createCommand($sql, $params);
        $this->setCommandCache($command);

        return $command;
    }

    /**
     * {@inheritdoc}
     */
    protected function queryScalar($selectExpression, $db)
    {
        /* @var $modelClass ActiveRecord */
        $modelClass = $this->modelClass;
        if ($db === null) {
            $db = $modelClass::getDb();
        }

        if ($this->sql === null) {
            return parent::queryScalar($selectExpression, $db);
        }

        $command = (new Query())->select([$selectExpression])
            ->from(['c' => "({$this->sql})"])
            ->params($this->params)
            ->createCommand($db);
        $this->setCommandCache($command);

        return $command->queryScalar();
    }

    /**
     * Joins with the specified relations.
     *
     * This method allows you to reuse existing relation definitions to perform JOIN queries.
     * Based on the definition of the specified relation(s), the method will append one or multiple
     * JOIN statements to the current query.
     *
     * If the `$eagerLoading` parameter is true, the method will also perform eager loading for the specified relations,
     * which is equivalent to calling [[with()]] using the specified relations.
     *
     * Note that because a JOIN query will be performed, you are responsible to disambiguate column names.
     *
     * This method differs from [[with()]] in that it will build up and execute a JOIN SQL statement
     * for the primary table. And when `$eagerLoading` is true, it will call [[with()]] in addition with the specified relations.
     *
     * @param string|array $with the relations to be joined. This can either be a string, representing a relation name or
     * an array with the following semantics:
     *
     * - Each array element represents a single relation.
     * - You may specify the relation name as the array key and provide an anonymous functions that
     *   can be used to modify the relation queries on-the-fly as the array value.
     * - If a relation query does not need modification, you may use the relation name as the array value.
     *
     * The relation name may optionally contain an alias for the relation table (e.g. `books b`).
     *
     * Sub-relations can also be specified, see [[with()]] for the syntax.
     *
     * In the following you find some examples:
     *
     * ```php
     * // 查找包括书籍的所有订单，并以 `INNER JOIN` 的连接方式即时加载 "books" 表
     * // find all orders that contain books, and eager loading "books"
     * Order::find()->joinWith('books', true, 'INNER JOIN')->all();
     * // 找到所有的订单，贪婪加载“书籍”，并将订单和书籍按照书名进行排序。
     * // find all orders, eager loading "books", and sort the orders and books by the book names.
     * Order::find()->joinWith([
     *     'books' => function (\yii\db\ActiveQuery $query) {
     *         $query->orderBy('item.name');
     *     }
     * ])->all();
     * // find all orders that contain books of the category 'Science fiction', using the alias "b" for the books table
     * Order::find()->joinWith(['books b'], true, 'INNER JOIN')->where(['b.category' => 'Science fiction'])->all();
     *
     *  // 连接多重关系
        // 找出24小时内注册客户包含书籍的订单
        $orders = Order::find()->innerJoinWith([
            'books',
            'customer' => function ($query) {
                $query->where('customer.created_at > ' . (time() - 24 * 3600));
            }
        ])->all();
        // 连接嵌套关系：连接 books 表及其 author 列
        $orders = Order::find()->joinWith('books.author')->all();
     *
     * // 查找包括书籍的所有订单，但 "books" 表不使用贪婪加载
        $orders = Order::find()->innerJoinWith('books', false)->all();
        // 等价于：
        $orders = Order::find()->joinWith('books', false, 'INNER JOIN')->all();
     *
     * ```
     *
     * The alias syntax is available since version 2.0.7.
     *
     * @param bool|array $eagerLoading whether to eager load the relations
     * specified in `$with`.  When this is a boolean, it applies to all
     * relations specified in `$with`. Use an array to explicitly list which
     * relations in `$with` need to be eagerly loaded.  Note, that this does
     * not mean, that the relations are populated from the query result. An
     * extra query will still be performed to bring in the related data.
     * Defaults to `true`.
     * @param string|array $joinType the join type of the relations specified in `$with`.
     * When this is a string, it applies to all relations specified in `$with`. Use an array
     * in the format of `relationName => joinType` to specify different join types for different relations.
     * @return $this the query object itself
     */
    public function joinWith($with, $eagerLoading = true, $joinType = 'LEFT JOIN')
    {
        $relations = [];
        foreach ((array) $with as $name => $callback) {

            // $with = ['books'], $name = 0, $callback = 'book'
            if (is_int($name)) {
                // $name = 'book'
                $name = $callback;
                // $callback = null
                $callback = null;
            }

            if (preg_match('/^(.*?)(?:\s+AS\s+|\s+)(\w+)$/i', $name, $matches)) {
                // relation is defined with an alias, adjust callback to apply alias
                list(, $relation, $alias) = $matches;
                $name = $relation;
                $callback = function ($query) use ($callback, $alias) {
                    /* @var $query ActiveQuery */
                    $query->alias($alias);
                    if ($callback !== null) {
                        call_user_func($callback, $query);
                    }
                };
            }

            // $callback === null， $name = 'book'
            if ($callback === null) {
                $relations[] = $name;
            } else {
                $relations[$name] = $callback;
            }
        }
        $this->joinWith[] = [$relations, $eagerLoading, $joinType];
        return $this;
    }

    /**
     * 构建 JoinWith
     */
    private function buildJoinWith()
    {
        $join = $this->join;
        $this->join = [];

        /* @var $modelClass ActiveRecordInterface */
        $modelClass = $this->modelClass;
        $model = $modelClass::instance();
        foreach ($this->joinWith as $config) {
            list($with, $eagerLoading, $joinType) = $config;
            $this->joinWithRelations($model, $with, $joinType);

            if (is_array($eagerLoading)) {
                foreach ($with as $name => $callback) {
                    if (is_int($name)) {
                        if (!in_array($callback, $eagerLoading, true)) {
                            unset($with[$name]);
                        }
                    } elseif (!in_array($name, $eagerLoading, true)) {
                        unset($with[$name]);
                    }
                }
            } elseif (!$eagerLoading) {
                $with = [];
            }

            $this->with($with);
        }

        // remove duplicated joins added by joinWithRelations that may be added
        // e.g. when joining a relation and a via relation at the same time
        $uniqueJoins = [];
        foreach ($this->join as $j) {
            $uniqueJoins[serialize($j)] = $j;
        }
        $this->join = array_values($uniqueJoins);

        if (!empty($join)) {
            // append explicit join to joinWith()
            // https://github.com/yiisoft/yii2/issues/2880
            $this->join = empty($this->join) ? $join : array_merge($this->join, $join);
        }
    }

    /**
     * 与指定关联关系的内连接。
     * 这是一个使用连接类型设置为"INNER JOIN"的[[joinWith()]]的快捷方法。
     * 关于这个方法的详细用法，请参阅joinWith()。
     *
     * Inner joins with the specified relations.
     * This is a shortcut method to [[joinWith()]] with the join type set as "INNER JOIN".
     * Please refer to [[joinWith()]] for detailed usage of this method.
     * @param string|array $with the relations to be joined with.
     * @param bool|array $eagerLoading whether to eager load the relations.
     * Note, that this does not mean, that the relations are populated from the
     * query result. An extra query will still be performed to bring in the
     * related data.
     * @return $this the query object itself
     * @see joinWith()
     */
    public function innerJoinWith($with, $eagerLoading = true)
    {
        return $this->joinWith($with, $eagerLoading, 'INNER JOIN');
    }

    /**
     * 通过添加基于给定关系的连接片段来修改当前查询
     * Modifies the current query by adding join fragments based on the given relations.
     * @param ActiveRecord $model the primary model
     * @param array $with the relations to be joined
     * @param string|array $joinType the join type
     */
    private function joinWithRelations($model, $with, $joinType)
    {
        $relations = [];

        foreach ($with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }

            $primaryModel = $model;
            $parent = $this;
            $prefix = '';
            while (($pos = strpos($name, '.')) !== false) {
                $childName = substr($name, $pos + 1);
                $name = substr($name, 0, $pos);
                $fullName = $prefix === '' ? $name : "$prefix.$name";
                if (!isset($relations[$fullName])) {
                    $relations[$fullName] = $relation = $primaryModel->getRelation($name);
                    $this->joinWithRelation($parent, $relation, $this->getJoinType($joinType, $fullName));
                } else {
                    $relation = $relations[$fullName];
                }
                /* @var $relationModelClass ActiveRecordInterface */
                $relationModelClass = $relation->modelClass;
                $primaryModel = $relationModelClass::instance();
                $parent = $relation;
                $prefix = $fullName;
                $name = $childName;
            }

            $fullName = $prefix === '' ? $name : "$prefix.$name";
            if (!isset($relations[$fullName])) {
                $relations[$fullName] = $relation = $primaryModel->getRelation($name);
                if ($callback !== null) {
                    call_user_func($callback, $relation);
                }
                if (!empty($relation->joinWith)) {
                    $relation->buildJoinWith();
                }
                $this->joinWithRelation($parent, $relation, $this->getJoinType($joinType, $fullName));
            }
        }
    }

    /**
     * 根据给定的连接类型参数和关系名称返回连接类型
     * Returns the join type based on the given join type parameter and the relation name.
     * @param string|array $joinType the given join type(s)
     * @param string $name relation name
     * @return string the real join type
     */
    private function getJoinType($joinType, $name)
    {
        if (is_array($joinType) && isset($joinType[$name])) {
            return $joinType[$name];
        }

        return is_string($joinType) ? $joinType : 'INNER JOIN';
    }

    /**
     * 返回表名和表别名
     * Returns the table name and the table alias for [[modelClass]].
     * @return array the table name and the table alias.
     * @internal
     */
    private function getTableNameAndAlias()
    {
        if (empty($this->from)) {
            $tableName = $this->getPrimaryTableName();
        } else {
            $tableName = '';
            foreach ($this->from as $alias => $tableName) {
                if (is_string($alias)) {
                    return [$tableName, $alias];
                }
                break;
            }
        }

        if (preg_match('/^(.*?)\s+({{\w+}}|\w+)$/', $tableName, $matches)) {
            $alias = $matches[2];
        } else {
            $alias = $tableName;
        }

        return [$tableName, $alias];
    }

    /**
     * 使用子查询连接父查询
     * 当前的查询对象将被相应地修改
     * Joins a parent query with a child query.
     * The current query object will be modified accordingly.
     * @param ActiveQuery $parent
     * @param ActiveQuery $child
     * @param string $joinType
     */
    private function joinWithRelation($parent, $child, $joinType)
    {
        $via = $child->via;
        $child->via = null;
        if ($via instanceof self) {
            // via table
            $this->joinWithRelation($parent, $via, $joinType);
            $this->joinWithRelation($via, $child, $joinType);
            return;
        } elseif (is_array($via)) {
            // via relation
            $this->joinWithRelation($parent, $via[1], $joinType);
            $this->joinWithRelation($via[1], $child, $joinType);
            return;
        }

        list($parentTable, $parentAlias) = $parent->getTableNameAndAlias();
        list($childTable, $childAlias) = $child->getTableNameAndAlias();

        if (!empty($child->link)) {
            if (strpos($parentAlias, '{{') === false) {
                $parentAlias = '{{' . $parentAlias . '}}';
            }
            if (strpos($childAlias, '{{') === false) {
                $childAlias = '{{' . $childAlias . '}}';
            }

            $on = [];
            foreach ($child->link as $childColumn => $parentColumn) {
                $on[] = "$parentAlias.[[$parentColumn]] = $childAlias.[[$childColumn]]";
            }
            $on = implode(' AND ', $on);
            if (!empty($child->on)) {
                $on = ['and', $on, $child->on];
            }
        } else {
            $on = $child->on;
        }
        $this->join($joinType, empty($child->from) ? $childTable : $child->from, $on);

        if (!empty($child->where)) {
            $this->andWhere($child->where);
        }
        if (!empty($child->having)) {
            $this->andHaving($child->having);
        }
        if (!empty($child->orderBy)) {
            $this->addOrderBy($child->orderBy);
        }
        if (!empty($child->groupBy)) {
            $this->addGroupBy($child->groupBy);
        }
        if (!empty($child->params)) {
            $this->addParams($child->params);
        }
        if (!empty($child->join)) {
            foreach ($child->join as $join) {
                $this->join[] = $join;
            }
        }
        if (!empty($child->union)) {
            foreach ($child->union as $union) {
                $this->union[] = $union;
            }
        }
    }

    /**
     * 设置关系查询的ON条件
     * Sets the ON condition for a relational query.
     * The condition will be used in the ON part when [[ActiveQuery::joinWith()]] is called.
     * Otherwise, the condition will be used in the WHERE part of a query.
     *
     * Use this method to specify additional conditions when declaring a relation in the [[ActiveRecord]] class:
     *
     * ```php
     * public function getActiveUsers()
     * {
     *     return $this->hasMany(User::className(), ['id' => 'user_id'])
     *                 ->onCondition(['active' => true]);
     * }
     * ```
     *
     * Note that this condition is applied in case of a join as well as when fetching the related records.
     * Thus only fields of the related table can be used in the condition. Trying to access fields of the primary
     * record will cause an error in a non-join-query.
     *
     * @param string|array $condition the ON condition. Please refer to [[Query::where()]] on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return $this the query object itself
     */
    public function onCondition($condition, $params = [])
    {
        $this->on = $condition;
        $this->addParams($params);
        return $this;
    }

    /**
     * 在现有的条件下添加附加 ON 条件
     * Adds an additional ON condition to the existing one.
     * The new condition and the existing one will be joined using the 'AND' operator.
     * @param string|array $condition the new ON condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return $this the query object itself
     * @see onCondition()
     * @see orOnCondition()
     */
    public function andOnCondition($condition, $params = [])
    {
        if ($this->on === null) {
            $this->on = $condition;
        } else {
            $this->on = ['and', $this->on, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional ON condition to the existing one.
     * The new condition and the existing one will be joined using the 'OR' operator.
     * @param string|array $condition the new ON condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return $this the query object itself
     * @see onCondition()
     * @see andOnCondition()
     */
    public function orOnCondition($condition, $params = [])
    {
        if ($this->on === null) {
            $this->on = $condition;
        } else {
            $this->on = ['or', $this->on, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * 指定关系查询的中间连接表
     * Specifies the junction table for a relational query.
     * 有时，两个表通过中间表关联，定义这样的关联关系， 可以通过调用 yii\db\ActiveQuery::via() 方法或 viaTable() 方法来定制 yii\db\ActiveQuery 对象 。
     *
     * Use this method to specify a junction table when declaring a relation in the [[ActiveRecord]] class:
     *
     * ```php
     * public function getItems()
     * {
     *     return $this->hasMany(Item::className(), ['id' => 'item_id'])
     *                 ->viaTable('order_item', ['order_id' => 'id']);
     * }
     * ```
     *
     * @param string $tableName the name of the junction table.
     * @param array $link the link between the junction table and the table associated with [[primaryModel]].
     * The keys of the array represent the columns in the junction table, and the values represent the columns
     * in the [[primaryModel]] table.
     * @param callable $callable a PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     * @return $this the query object itself
     * @see via()
     */
    public function viaTable($tableName, $link, callable $callable = null)
    {
        $modelClass = $this->primaryModel !== null ? get_class($this->primaryModel) : get_class();

        $relation = new self($modelClass, [
            'from' => [$tableName],
            'link' => $link,
            'multiple' => true,
            'asArray' => true,
        ]);
        $this->via = $relation;
        if ($callable !== null) {
            call_user_func($callable, $relation);
        }

        return $this;
    }

    /**
     * 为模型类中的表定义别名
     * Define an alias for the table defined in [[modelClass]].
     *
     * This method will adjust [[from]] so that an already defined alias will be overwritten.
     * If none was defined, [[from]] will be populated with the given alias.
     *
     * @param string $alias the table alias.
     * @return $this the query object itself
     * @since 2.0.7
     */
    public function alias($alias)
    {
        if (empty($this->from) || count($this->from) < 2) {
            list($tableName) = $this->getTableNameAndAlias();
            $this->from = [$alias => $tableName];
        } else {
            $tableName = $this->getPrimaryTableName();

            foreach ($this->from as $key => $table) {
                if ($table === $tableName) {
                    unset($this->from[$key]);
                    $this->from[$alias] = $tableName;
                }
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @since 2.0.12
     */
    public function getTablesUsedInFrom()
    {
        if (empty($this->from)) {
            return $this->cleanUpTableNames([$this->getPrimaryTableName()]);
        }

        return parent::getTablesUsedInFrom();
    }

    /**
     * @return string primary table name
     * @since 2.0.12
     */
    protected function getPrimaryTableName()
    {
        /* @var $modelClass ActiveRecord */
        $modelClass = $this->modelClass;
        return $modelClass::tableName();
    }
}
