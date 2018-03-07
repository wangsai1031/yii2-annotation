<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db;

use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * ActiveRelationTrait implements the common methods and properties for active record relational queries.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 *
 * @method ActiveRecordInterface one()
 * @method ActiveRecordInterface[] all()
 * @property ActiveRecord $modelClass
 */
trait ActiveRelationTrait
{
    /**
     * 这个查询是否表示与多个记录的关系
     * 该属性仅在关联表方法中使用
     * @var bool whether this query represents a relation to more than one record.
     * This property is only used in relational context. If true, this relation will
     * populate all query results into AR instances using [[Query::all()|all()]].
     * If false, only the first row of the results will be retrieved using [[Query::one()|one()]].
     */
    public $multiple;
    /**
     * 关系查询的主要模型
     * 这仅在使用动态查询选项的延迟加载中使用
     * @var ActiveRecord the primary model of a relational query.
     * This is used only in lazy loading with dynamic query options.
     */
    public $primaryModel;
    /**
     * 建立关系的主表和外表的列
     * 数组键必须是这个关联表相应的列，而数组的值必须是主表中相应的列。
     * 不要添加前缀或引用列名，因为这将由Yii自动完成。
     * 该属性仅在关联表方法中使用
     * @var array the columns of the primary and foreign tables that establish a relation.
     * The array keys must be columns of the table for this relation, and the array values
     * must be the corresponding columns from the primary table.
     * Do not prefix or quote the column names as this will be done automatically by Yii.
     * This property is only used in relational context.
     */
    public $link;
    /**
     * 与连接表关联的查询
     * 通过调用 via() 方法设置这个属性而不是直接设置它
     * 该属性仅在关联表方法中使用
     * @var array|object the query associated with the junction table. Please call [[via()]]
     * to set this property instead of directly setting it.
     * This property is only used in relational context.
     * @see via()
     */
    public $via;
    /**
     * 当前关联的逆向关联的名称
     * 有时，两个表通过中间表关联，定义这样的关联关系， 可以通过调用 yii\db\ActiveQuery::via() 方法或 viaTable() 方法来定制 yii\db\ActiveQuery 对象 。
     *
     * 例如，一个订单有一个客户，这意味着“客户”关系的反向是“订单”，而“订单”关系的反向是“顾客”。
     * 如果设置了该属性，则主记录将通过指定的关系被引用。
     * 例如，`$customer->orders[0]->customer` and `$customer` 将是相同的对象，访问订单的客户将不会触发新的DB查询。
     * 该属性仅在关系上下文中使用
     * @var string the name of the relation that is the inverse of this relation.
     * For example, an order has a customer, which means the inverse of the "customer" relation
     * is the "orders", and the inverse of the "orders" relation is the "customer".
     * If this property is set, the primary record(s) will be referenced through the specified relation.
     * For example, `$customer->orders[0]->customer` and `$customer` will be the same object,
     * and accessing the customer of an order will not trigger new DB query.
     * This property is only used in relational context.
     * @see inverseOf()
     */
    public $inverseOf;


    /**
     * Clones internal objects.
     */
    public function __clone()
    {
        parent::__clone();
        // make a clone of "via" object so that the same query object can be reused multiple times
        if (is_object($this->via)) {
            $this->via = clone $this->via;
        } elseif (is_array($this->via)) {
            $this->via = [$this->via[0], clone $this->via[1]];
        }
    }

    /**
     * 指定与连接表关联的关系
     * Specifies the relation associated with the junction table.
     * 有时，两个表通过中间表关联，定义这样的关联关系， 可以通过调用 yii\db\ActiveQuery::via() 方法或 viaTable() 方法来定制 yii\db\ActiveQuery 对象 。
     *
     * 当在ActiveRecord类中声明一个关系时，使用该方法指定一个中间记录/表。
     * Use this method to specify a pivot record/table when declaring a relation in the [[ActiveRecord]] class:
     *
     * ```php
     * class Order extends ActiveRecord
     * {
     *    public function getOrderItems() {
     *        return $this->hasMany(OrderItem::className(), ['order_id' => 'id']);
     *    }
     *
     *    public function getItems() {
     *        return $this->hasMany(Item::className(), ['id' => 'item_id'])
     *                    ->via('orderItems');
     *    }
     * }
     * ```
     *
     * @param string $relationName the relation name. This refers to a relation declared in [[primaryModel]].
     * @param callable $callable a PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     * @return $this the relation object itself.
     */
    public function via($relationName, callable $callable = null)
    {
        $relation = $this->primaryModel->getRelation($relationName);
        $this->via = [$relationName, $relation];
        if ($callable !== null) {
            call_user_func($callable, $relation);
        }

        return $this;
    }

    /**
     * 将关系的名称设置为这个关系的逆关系
     *
     * 关联关系通常成对定义，如：Customer 可以有个名为 orders 关联项， 而 Order 也有个名为customer 的关联项：

        class Customer extends ActiveRecord
        {
            ....
            public function getOrders()
            {
                return $this->hasMany(Order::className(), ['customer_id' => 'id']);
            }
        }

        class Order extends ActiveRecord
        {
            ....
            public function getCustomer()
            {
                return $this->hasOne(Customer::className(), ['id' => 'customer_id']);
            }
        }

     * 如果我们执行以下查询，可以发现订单的 customer 和 找到这些订单的客户对象并不是同一个。连接 customer->orders 将触发一条 SQL 语句 而连接一个订单的 customer 将触发另一条 SQL 语句。

        // SELECT * FROM customer WHERE id=1
        $customer = Customer::findOne(1);

        // SELECT * FROM order WHERE customer_id=1
        // SELECT * FROM customer WHERE id=1
        if ($customer->orders[0]->customer === $customer) {
            echo '相同';
        } else {
            echo '不相同';
        }
     *  // 输出 "不相同"
     *
     * 为避免多余执行的后一条语句，我们可以为 customer或 orders 关联关系定义相反的关联关系，通过调用 yii\db\ActiveQuery::inverseOf() 方法可以实现。

        class Customer extends ActiveRecord
        {
            ....
            public function getOrders()
            {
                return $this->hasMany(Order::className(), ['customer_id' => 'id'])->inverseOf('customer');
            }
        }

     * 现在我们同样执行上面的查询，我们将得到：

        // SELECT * FROM customer WHERE id=1
        $customer = Customer::findOne(1);

        // SELECT * FROM order WHERE customer_id=1
        if ($customer->orders[0]->customer === $customer) {
            echo '相同';
        } else {
            echo '不相同';
        }
     *
     * // 输出相同

     * 以上我们展示了如何在延迟加载中使用相对关联关系， 相对关系也可以用在即时加载中：

        // SELECT * FROM customer
        // SELECT * FROM order WHERE customer_id IN (1, 2, ...)
        $customers = Customer::find()->with('orders')->all();

        if ($customers[0]->orders[0]->customer === $customers[0]) {
            echo '相同';
        } else {
            echo '不相同';
        }
     * // 输出相同
     *
     * Note: 相对关系不能在包含中间表的关联关系中定义。
     * 即是，如果你的关系是通过yii\db\ActiveQuery::via() 或 viaTable()方法定义的， 就不能调用yii\db\ActiveQuery::inverseOf()方法了。
     *
     * Sets the name of the relation that is the inverse of this relation.
     * For example, a customer has orders, which means the inverse of the "orders" relation is the "customer".
     * If this property is set, the primary record(s) will be referenced through the specified relation.
     * For example, `$customer->orders[0]->customer` and `$customer` will be the same object,
     * and accessing the customer of an order will not trigger a new DB query.
     *
     * Use this method when declaring a relation in the [[ActiveRecord]] class, e.g. in Customer model:
     *
     * ```php
     * public function getOrders()
     * {
     *     return $this->hasMany(Order::className(), ['customer_id' => 'id'])->inverseOf('customer');
     * }
     * ```
     *
     * This also may be used for Order model, but with caution:
     *
     * ```php
     * public function getCustomer()
     * {
     *     return $this->hasOne(Customer::className(), ['id' => 'customer_id'])->inverseOf('orders');
     * }
     * ```
     *
     * in this case result will depend on how order(s) was loaded.
     * Let's suppose customer has several orders. If only one order was loaded:
     *
     * ```php
     * $orders = Order::find()->where(['id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain only one order. If orders was loaded like this:
     *
     * ```php
     * $orders = Order::find()->with('customer')->where(['customer_id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain all orders of the customer.
     *
     * @param string $relationName the name of the relation that is the inverse of this relation.
     * @return $this the relation object itself.
     */
    public function inverseOf($relationName)
    {
        $this->inverseOf = $relationName;
        return $this;
    }

    /**
     * 查找指定的主记录的相关记录
     * 当以惰性方式访问ActiveRecord的关系时，就会调用该方法。
     * Finds the related records for the specified primary record.
     * This method is invoked when a relation of an ActiveRecord is being accessed in a lazy fashion.
     * @param string $name the relation name
     * @param ActiveRecordInterface|BaseActiveRecord $model the primary model
     * @return mixed the related record(s)
     * @throws InvalidArgumentException if the relation is invalid
     */
    public function findFor($name, $model)
    {
        // 主要是验证方法名是否符合规范
        if (method_exists($model, 'get' . $name)) {
            $method = new \ReflectionMethod($model, 'get' . $name);
            $realName = lcfirst(substr($method->getName(), 3));
            if ($realName !== $name) {
                throw new InvalidArgumentException('Relation names are case sensitive. ' . get_class($model) . " has a relation named \"$realName\" instead of \"$name\".");
            }
        }

        return $this->multiple ? $this->all() : $this->one();
    }

    /**
     * 将查询的主模型填充到相关活动记录的逆关系中
     * If applicable, populate the query's primary model into the related records' inverse relationship.
     * @param array $result the array of related records as generated by [[populate()]]
     * @since 2.0.9
     */
    private function addInverseRelations(&$result)
    {
        if ($this->inverseOf === null) {
            return;
        }

        foreach ($result as $i => $relatedModel) {
            if ($relatedModel instanceof ActiveRecordInterface) {
                if (!isset($inverseRelation)) {
                    $inverseRelation = $relatedModel->getRelation($this->inverseOf);
                }
                $relatedModel->populateRelation($this->inverseOf, $inverseRelation->multiple ? [$this->primaryModel] : $this->primaryModel);
            } else {
                if (!isset($inverseRelation)) {
                    /* @var $modelClass ActiveRecordInterface */
                    $modelClass = $this->modelClass;
                    $inverseRelation = $modelClass::instance()->getRelation($this->inverseOf);
                }
                $result[$i][$this->inverseOf] = $inverseRelation->multiple ? [$this->primaryModel] : $this->primaryModel;
            }
        }
    }

    /**
     * 找到相关的记录并将它们填充到主要模型中
     * Finds the related records and populates them into the primary models.
     * @param string $name the relation name
     * @param array $primaryModels primary models
     * @return array the related models
     * @throws InvalidConfigException if [[link]] is invalid
     */
    public function populateRelation($name, &$primaryModels)
    {
        if (!is_array($this->link)) {
            throw new InvalidConfigException('Invalid link: it must be an array of key-value pairs.');
        }

        if ($this->via instanceof self) {
            // via junction table
            /* @var $viaQuery ActiveRelationTrait */
            $viaQuery = $this->via;
            $viaModels = $viaQuery->findJunctionRows($primaryModels);
            $this->filterByModels($viaModels);
        } elseif (is_array($this->via)) {
            // via relation
            /* @var $viaQuery ActiveRelationTrait|ActiveQueryTrait */
            list($viaName, $viaQuery) = $this->via;
            if ($viaQuery->asArray === null) {
                // inherit asArray from primary query
                $viaQuery->asArray($this->asArray);
            }
            $viaQuery->primaryModel = null;
            $viaModels = $viaQuery->populateRelation($viaName, $primaryModels);
            $this->filterByModels($viaModels);
        } else {
            $this->filterByModels($primaryModels);
        }

        if (!$this->multiple && count($primaryModels) === 1) {
            $model = $this->one();
            $primaryModel = reset($primaryModels);
            if ($primaryModel instanceof ActiveRecordInterface) {
                $primaryModel->populateRelation($name, $model);
            } else {
                $primaryModels[key($primaryModels)][$name] = $model;
            }
            if ($this->inverseOf !== null) {
                $this->populateInverseRelation($primaryModels, [$model], $name, $this->inverseOf);
            }

            return [$model];
        }

        // https://github.com/yiisoft/yii2/issues/3197
        // delay indexing related models after buckets are built
        $indexBy = $this->indexBy;
        $this->indexBy = null;
        $models = $this->all();

        if (isset($viaModels, $viaQuery)) {
            $buckets = $this->buildBuckets($models, $this->link, $viaModels, $viaQuery->link);
        } else {
            $buckets = $this->buildBuckets($models, $this->link);
        }

        $this->indexBy = $indexBy;
        if ($this->indexBy !== null && $this->multiple) {
            $buckets = $this->indexBuckets($buckets, $this->indexBy);
        }

        $link = array_values(isset($viaQuery) ? $viaQuery->link : $this->link);
        foreach ($primaryModels as $i => $primaryModel) {
            if ($this->multiple && count($link) === 1 && is_array($keys = $primaryModel[reset($link)])) {
                $value = [];
                foreach ($keys as $key) {
                    $key = $this->normalizeModelKey($key);
                    if (isset($buckets[$key])) {
                        if ($this->indexBy !== null) {
                            // if indexBy is set, array_merge will cause renumbering of numeric array
                            foreach ($buckets[$key] as $bucketKey => $bucketValue) {
                                $value[$bucketKey] = $bucketValue;
                            }
                        } else {
                            $value = array_merge($value, $buckets[$key]);
                        }
                    }
                }
            } else {
                $key = $this->getModelKey($primaryModel, $link);
                $value = isset($buckets[$key]) ? $buckets[$key] : ($this->multiple ? [] : null);
            }
            if ($primaryModel instanceof ActiveRecordInterface) {
                $primaryModel->populateRelation($name, $value);
            } else {
                $primaryModels[$i][$name] = $value;
            }
        }
        if ($this->inverseOf !== null) {
            $this->populateInverseRelation($primaryModels, $models, $name, $this->inverseOf);
        }

        return $models;
    }

    /**
     * @param ActiveRecordInterface[] $primaryModels primary models
     * @param ActiveRecordInterface[] $models models
     * @param string $primaryName the primary relation name
     * @param string $name the relation name
     */
    private function populateInverseRelation(&$primaryModels, $models, $primaryName, $name)
    {
        if (empty($models) || empty($primaryModels)) {
            return;
        }
        $model = reset($models);
        /* @var $relation ActiveQueryInterface|ActiveQuery */
        if ($model instanceof ActiveRecordInterface) {
            $relation = $model->getRelation($name);
        } else {
            /* @var $modelClass ActiveRecordInterface */
            $modelClass = $this->modelClass;
            $relation = $modelClass::instance()->getRelation($name);
        }

        if ($relation->multiple) {
            $buckets = $this->buildBuckets($primaryModels, $relation->link, null, null, false);
            if ($model instanceof ActiveRecordInterface) {
                foreach ($models as $model) {
                    $key = $this->getModelKey($model, $relation->link);
                    $model->populateRelation($name, isset($buckets[$key]) ? $buckets[$key] : []);
                }
            } else {
                foreach ($primaryModels as $i => $primaryModel) {
                    if ($this->multiple) {
                        foreach ($primaryModel as $j => $m) {
                            $key = $this->getModelKey($m, $relation->link);
                            $primaryModels[$i][$j][$name] = isset($buckets[$key]) ? $buckets[$key] : [];
                        }
                    } elseif (!empty($primaryModel[$primaryName])) {
                        $key = $this->getModelKey($primaryModel[$primaryName], $relation->link);
                        $primaryModels[$i][$primaryName][$name] = isset($buckets[$key]) ? $buckets[$key] : [];
                    }
                }
            }
        } else {
            if ($this->multiple) {
                foreach ($primaryModels as $i => $primaryModel) {
                    foreach ($primaryModel[$primaryName] as $j => $m) {
                        if ($m instanceof ActiveRecordInterface) {
                            $m->populateRelation($name, $primaryModel);
                        } else {
                            $primaryModels[$i][$primaryName][$j][$name] = $primaryModel;
                        }
                    }
                }
            } else {
                foreach ($primaryModels as $i => $primaryModel) {
                    if ($primaryModels[$i][$primaryName] instanceof ActiveRecordInterface) {
                        $primaryModels[$i][$primaryName]->populateRelation($name, $primaryModel);
                    } elseif (!empty($primaryModels[$i][$primaryName])) {
                        $primaryModels[$i][$primaryName][$name] = $primaryModel;
                    }
                }
            }
        }
    }

    /**
     * @param array $models
     * @param array $link
     * @param array $viaModels
     * @param array $viaLink
     * @param bool $checkMultiple
     * @return array
     */
    private function buildBuckets($models, $link, $viaModels = null, $viaLink = null, $checkMultiple = true)
    {
        if ($viaModels !== null) {
            $map = [];
            $viaLinkKeys = array_keys($viaLink);
            $linkValues = array_values($link);
            foreach ($viaModels as $viaModel) {
                $key1 = $this->getModelKey($viaModel, $viaLinkKeys);
                $key2 = $this->getModelKey($viaModel, $linkValues);
                $map[$key2][$key1] = true;
            }
        }

        $buckets = [];
        $linkKeys = array_keys($link);

        if (isset($map)) {
            foreach ($models as $model) {
                $key = $this->getModelKey($model, $linkKeys);
                if (isset($map[$key])) {
                    foreach (array_keys($map[$key]) as $key2) {
                        $buckets[$key2][] = $model;
                    }
                }
            }
        } else {
            foreach ($models as $model) {
                $key = $this->getModelKey($model, $linkKeys);
                $buckets[$key][] = $model;
            }
        }

        if ($checkMultiple && !$this->multiple) {
            foreach ($buckets as $i => $bucket) {
                $buckets[$i] = reset($bucket);
            }
        }

        return $buckets;
    }


    /**
     * 按列名称进行索引
     * Indexes buckets by column name.
     *
     * @param array $buckets
     * @param string|callable $indexBy the name of the column by which the query results should be indexed by.
     * This can also be a callable (e.g. anonymous function) that returns the index value based on the given row data.
     * @return array
     */
    private function indexBuckets($buckets, $indexBy)
    {
        $result = [];
        foreach ($buckets as $key => $models) {
            $result[$key] = [];
            foreach ($models as $model) {
                $index = is_string($indexBy) ? $model[$indexBy] : call_user_func($indexBy, $model);
                $result[$key][$index] = $model;
            }
        }

        return $result;
    }

    /**
     * @param array $attributes the attributes to prefix
     * @return array
     */
    private function prefixKeyColumns($attributes)
    {
        if ($this instanceof ActiveQuery && (!empty($this->join) || !empty($this->joinWith))) {
            if (empty($this->from)) {
                /* @var $modelClass ActiveRecord */
                $modelClass = $this->modelClass;
                $alias = $modelClass::tableName();
            } else {
                foreach ($this->from as $alias => $table) {
                    if (!is_string($alias)) {
                        $alias = $table;
                    }
                    break;
                }
            }
            if (isset($alias)) {
                foreach ($attributes as $i => $attribute) {
                    $attributes[$i] = "$alias.$attribute";
                }
            }
        }

        return $attributes;
    }

    /**
     * @param array $models
     */
    private function filterByModels($models)
    {
        $attributes = array_keys($this->link);

        $attributes = $this->prefixKeyColumns($attributes);

        $values = [];
        if (count($attributes) === 1) {
            // single key
            $attribute = reset($this->link);
            foreach ($models as $model) {
                if (($value = $model[$attribute]) !== null) {
                    if (is_array($value)) {
                        $values = array_merge($values, $value);
                    } else {
                        $values[] = $value;
                    }
                }
            }
            if (empty($values)) {
                $this->emulateExecution();
            }
        } else {
            // composite keys

            // ensure keys of $this->link are prefixed the same way as $attributes
            $prefixedLink = array_combine(
                $attributes,
                array_values($this->link)
            );
            foreach ($models as $model) {
                $v = [];
                foreach ($prefixedLink as $attribute => $link) {
                    $v[$attribute] = $model[$link];
                }
                $values[] = $v;
                if (empty($v)) {
                    $this->emulateExecution();
                }
            }
        }
        $this->andWhere(['in', $attributes, array_unique($values, SORT_REGULAR)]);
    }

    /**
     * @param ActiveRecordInterface|array $model
     * @param array $attributes
     * @return string
     */
    private function getModelKey($model, $attributes)
    {
        $key = [];
        foreach ($attributes as $attribute) {
            $key[] = $this->normalizeModelKey($model[$attribute]);
        }
        if (count($key) > 1) {
            return serialize($key);
        }
        $key = reset($key);
        return is_scalar($key) ? $key : serialize($key);
    }

    /**
     * @param mixed $value raw key value.
     * @return string normalized key value.
     */
    private function normalizeModelKey($value)
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            // ensure matching to special objects, which are convertable to string, for cross-DBMS relations, for example: `|MongoId`
            $value = $value->__toString();
        }

        return $value;
    }

    /**
     * @param array $primaryModels either array of AR instances or arrays
     * @return array
     */
    private function findJunctionRows($primaryModels)
    {
        if (empty($primaryModels)) {
            return [];
        }
        $this->filterByModels($primaryModels);
        /* @var $primaryModel ActiveRecord */
        $primaryModel = reset($primaryModels);
        if (!$primaryModel instanceof ActiveRecordInterface) {
            // when primaryModels are array of arrays (asArray case)
            $primaryModel = $this->modelClass;
        }

        return $this->asArray()->all($primaryModel::getDb());
    }
}
