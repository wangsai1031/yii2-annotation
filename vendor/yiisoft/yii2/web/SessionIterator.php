<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

/**
 * SessionIterator实现了一个迭代器[[\Iterator|iterator]]，用于遍历由[[Session]]管理的会话变量。
 * SessionIterator implements an [[\Iterator|iterator]] for traversing session variables managed by [[Session]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class SessionIterator implements \Iterator
{
    /**
     * 映射中的键列表
     * @var array list of keys in the map
     */
    private $_keys;
    /**
     * 当前键
     * @var mixed current key
     */
    private $_key;


    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->_keys = array_keys($_SESSION);
    }

    /**
     * 重置数组指针到数组开头。
     * 这个方法是接口[[\Iterator]]必须的
     * Rewinds internal array pointer.
     * This method is required by the interface [[\Iterator]].
     */
    public function rewind()
    {
        $this->_key = reset($this->_keys);
    }

    /**
     * 返回当前数组元素的键。
     * 这个方法是接口[[\Iterator]]必须的
     * Returns the key of the current array element.
     * This method is required by the interface [[\Iterator]].
     * @return mixed the key of the current array element
     */
    public function key()
    {
        return $this->_key;
    }

    /**
     * 返回当前的数组元素值。
     * 这个方法是接口[[\Iterator]]必须的
     * Returns the current array element.
     * This method is required by the interface [[\Iterator]].
     * @return mixed the current array element
     */
    public function current()
    {
        return isset($_SESSION[$this->_key]) ? $_SESSION[$this->_key] : null;
    }

    /**
     * 将内部指针移动到下一个数组元素
     * 这个方法是接口[[\Iterator]]必须的
     * Moves the internal pointer to the next array element.
     * This method is required by the interface [[\Iterator]].
     */
    public function next()
    {
        do {
            $this->_key = next($this->_keys);
        } while (!isset($_SESSION[$this->_key]) && $this->_key !== false);
    }

    /**
     * 返回当前位置是否有一个元素
     * 这个方法是接口[[\Iterator]]必须的
     * Returns whether there is an element at current position.
     * This method is required by the interface [[\Iterator]].
     * @return boolean
     */
    public function valid()
    {
        return $this->_key !== false;
    }
}
