<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

/**
 * DummyCache是一个占位符缓存组件
 * DummyCache is a placeholder cache component.
 *
 * DummyCache不会缓存任何东西。
 * 它提供了这样一个功能，用户可以随时配置'cache'应用程序组件，并保存`\Yii::$app->cache`。
 * 通过使用其他缓存组件替换DummyCache，可以快速从非缓存模式切换到缓存模式。
 * DummyCache does not cache anything.
 * It is provided so that one can always configure a 'cache' application component and save the check of existence of `\Yii::$app->cache`.
 * By replacing DummyCache with some other cache component, one can quickly switch from non-caching mode to caching mode.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DummyCache extends Cache
{
    /**
     * 使用指定的键从缓存中检索值
     * 这是在父类中声明的方法的实现
     * Retrieves a value from cache with a specified key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key a unique key identifying the cached value
     * @return mixed|false the value stored in cache, false if the value is not in the cache or expired.
     */
    protected function getValue($key)
    {
        return false;
    }

    /**
     * 在缓存中存储一个键对应的值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param mixed $value the value to be cached
     * @param integer $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function setValue($key, $value, $duration)
    {
        return true;
    }

    /**
     * 如果缓存不包含该键，则缓存该键和值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key identifying the value to be cached
     * @param mixed $value the value to be cached
     * @param integer $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function addValue($key, $value, $duration)
    {
        return true;
    }

    /**
     * 从缓存中删除指定键的值。
     * 这是在父类中声明的方法的实现.
     * Deletes a value with the specified key from cache
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key of the value to be deleted
     * @return boolean if no error happens during deletion
     */
    protected function deleteValue($key)
    {
        return true;
    }

    /**
     * 清空所有缓存
     * 这是在父类中声明的方法的实现.
     * Deletes all values from cache.
     * This is the implementation of the method declared in the parent class.
     * @return boolean whether the flush operation was successful.
     */
    protected function flushValues()
    {
        return true;
    }
}
