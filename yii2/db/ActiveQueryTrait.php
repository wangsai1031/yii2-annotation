<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db;

/**
 * ActiveQueryTrait implements the common methods and properties for active record query classes.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
trait ActiveQueryTrait
{
    /**
     * ActiveRecord类的名称
     * @var string the name of the ActiveRecord class.
     */
    public $modelClass;
    /**
     * @var array a list of relations that this query should be performed with
     */
    public $with;
    /**
     * 是否将查询带的数据以数组的方式返回
     * 默认为false
     * @var bool whether to return each record as an array. If false (default), an object
     * of [[modelClass]] will be created to represent each record.
     */
    public $asArray;


    /**
     * 设置  [[asArray]] 属性
     * Sets the [[asArray]] property.
     * @param bool $value whether to return the query results in terms of arrays instead of Active Records.
     * @return $this the query object itself
     */
    public function asArray($value = true)
    {
        $this->asArray = $value;
        return $this;
    }

    /**
     * 指定该查询应该执行的关系
     *
     *
     * Specifies the relations with which this query should be performed.
     *
     * The parameters to this method can be either one or multiple strings, or a single array
     * of relation names and the optional callbacks to customize the relations.
     *
     * A relation name can refer to a relation defined in [[modelClass]]
     * or a sub-relation that stands for a relation of a related record.
     * For example, `orders.address` means the `address` relation defined
     * in the model class corresponding to the `orders` relation.
     *
     * The following are some usage examples:
     *
     * ```php
     * // find customers together with their orders and country
     * Customer::find()->with('orders', 'country')->all();
     * // find customers together with their orders and the orders' shipping address
     * Customer::find()->with('orders.address')->all();
     * // find customers together with their country and orders of status 1
     * Customer::find()->with([
     *     'orders' => function (\yii\db\ActiveQuery $query) {
     *         $query->andWhere('status = 1');
     *     },
     *     'country',
     * ])->all();
     * ```
     *
     * You can call `with()` multiple times. Each call will add relations to the existing ones.
     * For example, the following two statements are equivalent:
     *
     * ```php
     * Customer::find()->with('orders', 'country')->all();
     * Customer::find()->with('orders')->with('country')->all();
     * ```
     *
     * @return $this the query object itself
     */
    public function with()
    {
        $with = func_get_args();
        if (isset($with[0]) && is_array($with[0])) {
            // the parameter is given as an array
            $with = $with[0];
        }

        if (empty($this->with)) {
            $this->with = $with;
        } elseif (!empty($with)) {
            foreach ($with as $name => $value) {
                if (is_int($name)) {
                    // repeating relation is fine as normalizeRelations() handle it well
                    $this->with[] = $value;
                } else {
                    $this->with[$name] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * 将查询到的行数据转换为模型实例数组
     * Converts found rows into model instances.
     * @param array $rows
     * @return array|ActiveRecord[]
     * @since 2.0.11
     */
    protected function createModels($rows)
    {
        if ($this->asArray) {
            // 如果返回数组形式
            return $rows;
        } else {
            // 如果返回ActiveRecord对象形式
            $models = [];
            /* @var $class ActiveRecord */
            $class = $this->modelClass;
            foreach ($rows as $row) {
                // 创建一个活动记录实例
                $model = $class::instantiate($row);
                // 返回对象实例所属类的名字。
                /** @var  ActiveRecord $modelClass */
                $modelClass = get_class($model);
                // 使用数据库/存储器中的一行数据填充一个活动记录对象
                $modelClass::populateRecord($model, $row);
                // 将活动记录对象存到一个数组中
                $models[] = $model;
            }
            return $models;
        }
    }

    /**
     * 找到对应于一个或多个关连的记录，并将其填充到主模型中。
     * Finds records corresponding to one or multiple relations and populates them into the primary models.
     * @param array $with a list of relations that this query should be performed with. Please
     * refer to [[with()]] for details about specifying this parameter.
     * $with 该查询应该执行的一组关系列表， 有关指定该参数的详细信息，请参考[[with()]]
     * 
     * @param array|ActiveRecord[] $models the primary models (can be either AR instances or arrays)
     * 主模型(可以是AR实例或数组)
     */
    public function findWith($with, &$models)
    {
        // 获取第一个$model
        $primaryModel = reset($models);
        if (!$primaryModel instanceof ActiveRecordInterface) {
            // 若$primaryModel 不是 ActiveRecordInterface 的实例
            // 则重新实例化这个$model类
            /* @var $modelClass ActiveRecordInterface */
            $modelClass = $this->modelClass;
            $primaryModel = $modelClass::instance();
        }
        // 格式化 关联关系
        $relations = $this->normalizeRelations($primaryModel, $with);
        /* @var $relation ActiveQuery */
        foreach ($relations as $name => $relation) {
            if ($relation->asArray === null) {
                // inherit asArray from primary query
                // 从主查询中继承asArray
                $relation->asArray($this->asArray);
            }
            // 找到相关的记录并将它们填充到主要模型中
            $relation->populateRelation($name, $models);
        }
    }

    /**
     * 格式化 关联关系
     * @param ActiveRecord $model
     * @param array $with
     * @return ActiveQueryInterface[]
     */
    private function normalizeRelations($model, $with)
    {
        $relations = [];
        foreach ($with as $name => $callback) {
            if (is_int($name)) {
                // 若name 是 int
                $name = $callback;
                $callback = null;
            }
            // 若name中有 '.' ,则包含子关联关系
            if (($pos = strpos($name, '.')) !== false) {
                // 包含子关系
                // with sub-relations
                // 将 . 后面的关系赋给 $childName
                $childName = substr($name, $pos + 1);
                // 将 . 前面的关系赋给 $name
                $name = substr($name, 0, $pos);
            } else {
                $childName = null;
            }

            # todo 这里不太懂?
            if (!isset($relations[$name])) {
                // 获取关联关系
                $relation = $model->getRelation($name);
                $relation->primaryModel = null;
                // 将relation赋给 $relations[$name]
                $relations[$name] = $relation;
            } else {
                $relation = $relations[$name];
            }

            // 若存在子关联关系
            if (isset($childName)) {
                # todo 这里不太懂?
                $relation->with[$childName] = $callback;
            } elseif ($callback !== null) {
                call_user_func($callback, $relation);
            }
        }

        return $relations;
    }
}
