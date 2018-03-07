<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\data;

use Yii;
use yii\base\Component;
use yii\base\InvalidParamException;

/**
 * BaseDataProvider provides a base class that implements the [[DataProviderInterface]].
 *
 * @property integer $count The number of data models in the current page. This property is read-only.
 * @property array $keys The list of key values corresponding to [[models]]. Each data model in [[models]] is
 * uniquely identified by the corresponding key value in this array.
 * @property array $models The list of data models in the current page.
 * @property Pagination|boolean $pagination The pagination object. If this is false, it means the pagination
 * is disabled. Note that the type of this property differs in getter and setter. See [[getPagination()]] and
 * [[setPagination()]] for details.
 * @property Sort|boolean $sort The sorting object. If this is false, it means the sorting is disabled. Note
 * that the type of this property differs in getter and setter. See [[getSort()]] and [[setSort()]] for details.
 * @property integer $totalCount Total number of possible data models.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class BaseDataProvider extends Component implements DataProviderInterface
{
    /**
     * @var string an ID that uniquely identifies the data provider among all data providers.
     * You should set this property if the same page contains two or more different data providers.
     * Otherwise, the [[pagination]] and [[sort]] may not work properly.
     */
    public $id;

    private $_sort;
    private $_pagination;
    private $_keys;
    private $_models;
    private $_totalCount;


    /**
     * 准备在当前页面中可用的数据模型
     * Prepares the data models that will be made available in the current page.
     * @return array the available data models
     */
    abstract protected function prepareModels();

    /**
     * 准备与当前可用的数据模型相关联的key
     * Prepares the keys associated with the currently available data models.
     * @param array $models the available data models
     * @return array the keys
     */
    abstract protected function prepareKeys($models);

    /**
     * 返回该数据提供者中数据的总数量
     * Returns a value indicating the total number of data models in this data provider.
     * @return integer total number of data models in this data provider.
     */
    abstract protected function prepareTotalCount();

    /**
     * 准备数据模型和key
     * Prepares the data models and keys.
     *
     * 该方法将通过getModels()和getKeys()获取的数据模型和键
     * This method will prepare the data models and keys that can be retrieved
     * via [[getModels()]] and [[getKeys()]].
     *
     * 如果以前没有调用过getmodel()和getKeys()，那么这个方法将被隐式地调用。
     * This method will be implicitly called by [[getModels()]] and [[getKeys()]] if it has not been called before.
     *
     * 是否强制进行数据准备即使之前已经做过了
     * @param boolean $forcePrepare whether to force data preparation even if it has been done before.
     */
    public function prepare($forcePrepare = false)
    {
        if ($forcePrepare || $this->_models === null) {
            $this->_models = $this->prepareModels();
        }
        if ($forcePrepare || $this->_keys === null) {
            $this->_keys = $this->prepareKeys($this->_models);
        }
    }

    /**
     * 返回当前页面中的数据模型
     * Returns the data models in the current page.
     * @return array the list of data models in the current page.
     */
    public function getModels()
    {
        $this->prepare();

        return $this->_models;
    }

    /**
     * 在当前页面中设置数据模型
     * Sets the data models in the current page.
     * @param array $models the models in the current page
     */
    public function setModels($models)
    {
        $this->_models = $models;
    }

    /**
     * 返回与数据模型相关联的键值
     * Returns the key values associated with the data models.
     * @return array the list of key values corresponding to [[models]]. Each data model in [[models]]
     * is uniquely identified by the corresponding key value in this array.
     */
    public function getKeys()
    {
        $this->prepare();

        return $this->_keys;
    }

    /**
     * 设置与数据模型相关联的键值
     * Sets the key values associated with the data models.
     * @param array $keys the list of key values corresponding to [[models]].
     */
    public function setKeys($keys)
    {
        $this->_keys = $keys;
    }

    /**
     * 返回当前页面中数据模型的数量
     * Returns the number of data models in the current page.
     * @return integer the number of data models in the current page.
     */
    public function getCount()
    {
        return count($this->getModels());
    }

    /**
     * 返回数据模型的总数量
     * 当 [[pagination]] 为 false，将返回与 [[count]] 相同的值
     * 否则，将会调用 [[prepareTotalCount()]] 获取值
     * Returns the total number of data models.
     * When [[pagination]] is false, this returns the same value as [[count]].
     * Otherwise, it will call [[prepareTotalCount()]] to get the count.
     * @return integer total number of possible data models.
     */
    public function getTotalCount()
    {
        if ($this->getPagination() === false) {
            return $this->getCount();
        } elseif ($this->_totalCount === null) {
            $this->_totalCount = $this->prepareTotalCount();
        }

        return $this->_totalCount;
    }

    /**
     * 设置数据模型的总数。
     * Sets the total number of data models.
     * @param integer $value the total number of data models.
     */
    public function setTotalCount($value)
    {
        $this->_totalCount = $value;
    }

    /**
     * 返回该数据提供者使用的分页对象
     * Returns the pagination object used by this data provider.
     * Note that you should call [[prepare()]] or [[getModels()]] first to get correct values
     * of [[Pagination::totalCount]] and [[Pagination::pageCount]].
     * @return Pagination|false the pagination object. If this is false, it means the pagination is disabled.
     */
    public function getPagination()
    {
        if ($this->_pagination === null) {
            $this->setPagination([]);
        }

        return $this->_pagination;
    }

    /**
     * 为这个数据提供者程序设置分页对象
     * Sets the pagination for this data provider.
     * @param array|Pagination|boolean $value the pagination to be used by this data provider.
     * This can be one of the following:
     * - 用于创建分页对象的配置数组。"class" 元素默认为'yii\data\Pagination'。
     * - [[Pagination]]或他的子类的实例
     * - 如果为false，则不分页
     * - a configuration array for creating the pagination object. The "class" element defaults
     *   to 'yii\data\Pagination'
     * - an instance of [[Pagination]] or its subclass
     * - false, if pagination needs to be disabled.
     *
     * @throws InvalidParamException
     */
    public function setPagination($value)
    {
        if (is_array($value)) {
            $config = ['class' => Pagination::className()];
            if ($this->id !== null) {
                $config['pageParam'] = $this->id . '-page';
                $config['pageSizeParam'] = $this->id . '-per-page';
            }
            $this->_pagination = Yii::createObject(array_merge($config, $value));
        } elseif ($value instanceof Pagination || $value === false) {
            $this->_pagination = $value;
        } else {
            throw new InvalidParamException('Only Pagination instance, configuration array or false is allowed.');
        }
    }

    /**
     * 返回该数据提供者使用的排序对象
     * Returns the sorting object used by this data provider.
     * @return Sort|boolean the sorting object. If this is false, it means the sorting is disabled.
     */
    public function getSort()
    {
        if ($this->_sort === null) {
            $this->setSort([]);
        }

        return $this->_sort;
    }

    /**
     * 为这个数据提供者设置排序定义
     * Sets the sort definition for this data provider.
     * @param array|Sort|boolean $value the sort definition to be used by this data provider.
     * This can be one of the following:
     *
     * - a configuration array for creating the sort definition object. The "class" element defaults
     *   to 'yii\data\Sort'
     * - an instance of [[Sort]] or its subclass
     * - false, if sorting needs to be disabled.
     *
     * @throws InvalidParamException
     */
    public function setSort($value)
    {
        if (is_array($value)) {
            $config = ['class' => Sort::className()];
            if ($this->id !== null) {
                $config['sortParam'] = $this->id . '-sort';
            }
            $this->_sort = Yii::createObject(array_merge($config, $value));
        } elseif ($value instanceof Sort || $value === false) {
            $this->_sort = $value;
        } else {
            throw new InvalidParamException('Only Sort instance, configuration array or false is allowed.');
        }
    }

    /**
     * 刷新数据提供者
     * Refreshes the data provider.
     * After calling this method, if [[getModels()]], [[getKeys()]] or [[getTotalCount()]] is called again,
     * they will re-execute the query and return the latest data available.
     */
    public function refresh()
    {
        $this->_totalCount = null;
        $this->_models = null;
        $this->_keys = null;
    }
}
