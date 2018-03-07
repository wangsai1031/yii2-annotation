<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\data;

use yii\helpers\ArrayHelper;

/**
 * 将一个大的数组依据分页和排序规格返回一部分数据。
 *
 * 注意: 数组数据提供者与 Active Data Provider 和 SQL Data Provider 这两者进行比较的话，
 * 会发现数组数据提供者没有后面那两个高效，这是因为数组数据提供者需要加载所有的数据到内存中。
 *
 * yii\data\ArrayDataProvider 非常适用于大的数组。
 * 数据提供者允许你返回一个 经过一个或者多个字段排序的数组数据页面。
 * 为了使用 yii\data\ArrayDataProvider， 你应该指定 allModels 属性作为一个大的数组。
 * 这个大数组的元素既可以是一些关联数组（例如：DAO查询出来的结果） 也可以是一些对象（例如：Active Record实例） 例如,

 * ```
    use yii\data\ArrayDataProvider;

    $data = [
        ['id' => 1, 'name' => 'name 1', ...],
        ['id' => 2, 'name' => 'name 2', ...],
        ...
        ['id' => 100, 'name' => 'name 100', ...],
    ];

    $provider = new ArrayDataProvider([
        'allModels' => $data,
        'pagination' => [
            'pageSize' => 10,
        ],
        'sort' => [
            'attributes' => ['id', 'name'],
        ],
    ]);

    // 获取当前请求页的每一行数据
    $rows = $provider->getModels();
 *
 * ArrayDataProvider implements a data provider based on a data array.
 *
 * The [[allModels]] property contains all data models that may be sorted and/or paginated.
 * ArrayDataProvider will provide the data after sorting and/or pagination.
 * You may configure the [[sort]] and [[pagination]] properties to
 * customize the sorting and pagination behaviors.
 *
 * Elements in the [[allModels]] array may be either objects (e.g. model objects)
 * or associative arrays (e.g. query results of DAO).
 * Make sure to set the [[key]] property to the name of the field that uniquely
 * identifies a data record or false if you do not have such a field.
 *
 * Compared to [[ActiveDataProvider]], ArrayDataProvider could be less efficient
 * because it needs to have [[allModels]] ready.
 *
 * ArrayDataProvider may be used in the following way:
 *
 * ```php
 * $query = new Query;
 * $provider = new ArrayDataProvider([
 *     'allModels' => $query->from('post')->all(),
 *     'sort' => [
 *         'attributes' => ['id', 'username', 'email'],
 *     ],
 *     'pagination' => [
 *         'pageSize' => 10,
 *     ],
 * ]);
 * // get the posts in the current page
 * $posts = $provider->getModels();
 * ```
 *
 * Note: if you want to use the sorting feature, you must configure the [[sort]] property
 * so that the provider knows which columns can be sorted.
 *
 * For more details and usage information on ArrayDataProvider, see the [guide article on data providers](guide:output-data-providers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ArrayDataProvider extends BaseDataProvider
{
    /**
     * 用作数据模型的关键字的列
     * 这可以是列名称，也可以是返回给定数据模型的键值的匿名方法
     * 如果没有设置，将使用[[models]]数组的键
     * @var string|callable the column that is used as the key of the data models.
     * This can be either a column name, or a callable that returns the key value of a given data model.
     * If this is not set, the index of the [[models]] array will be used.
     * @see getKeys()
     */
    public $key;
    /**
     * 不被分页或排序的数据，当启用分页时，该属性通常包含比[[models]]更多的元素
     * 数组元素必须使用基于零的整数键
     * @var array the data that is not paginated or sorted. When pagination is enabled,
     * this property usually contains more elements than [[models]].
     * The array elements must use zero-based integer keys.
     */
    public $allModels;
    /**
     * [[\yii\base\Model|Model]]类的名称，该属性用于获取列的名称
     * @var string the name of the [[\yii\base\Model|Model]] class that will be represented.
     * This property is used to get columns' names.
     * @since 2.0.9
     */
    public $modelClass;


    /**
     * 准备在当前页面中可用的数据模型
     * {@inheritdoc}
     */
    protected function prepareModels()
    {
        if (($models = $this->allModels) === null) {
            return [];
        }

        if (($sort = $this->getSort()) !== false) {
            $models = $this->sortModels($models, $sort);
        }

        if (($pagination = $this->getPagination()) !== false) {
            $pagination->totalCount = $this->getTotalCount();

            if ($pagination->getPageSize() > 0) {
                $models = array_slice($models, $pagination->getOffset(), $pagination->getLimit(), true);
            }
        }

        return $models;
    }

    /**
     * 准备与当前可用的数据模型相关联的key
     * {@inheritdoc}
     */
    protected function prepareKeys($models)
    {
        if ($this->key !== null) {
            $keys = [];
            foreach ($models as $model) {
                if (is_string($this->key)) {
                    $keys[] = $model[$this->key];
                } else {
                    $keys[] = call_user_func($this->key, $model);
                }
            }

            return $keys;
        }

        return array_keys($models);
    }

    /**
     * 返回该数据提供者中数据的总数量
     * {@inheritdoc}
     */
    protected function prepareTotalCount()
    {
        return count($this->allModels);
    }

    /**
     * 根据给定的排序定义对数据模型进行排序
     * Sorts the data models according to the given sort definition.
     * @param array $models the models to be sorted
     * @param Sort $sort the sort definition
     * @return array the sorted data models
     */
    protected function sortModels($models, $sort)
    {
        $orders = $sort->getOrders();
        if (!empty($orders)) {
            ArrayHelper::multisort($models, array_keys($orders), array_values($orders));
        }

        return $models;
    }
}
