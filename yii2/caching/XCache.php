<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

/**
 * 使用 PHP XCache扩展。
 * XCache provides XCache caching in terms of an application component.
 *
 * To use this application component, the [XCache PHP extension](http://xcache.lighttpd.net/) must be loaded.
 * Also note that the [[flush()]] functionality will work correctly only if "xcache.admin.enable_auth"
 * is set to "Off" in php.ini.
 *
 * See [[Cache]] for common cache operations that XCache supports.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 * @deprecated since 2.0.14. This class will be removed in 2.1.0.
 */
class XCache extends Cache
{
    /**
     * 检查一个指定的键是否存在于缓存中.
     * 如果缓存的数据很大，这个方法比从缓存中获取值要快。
     * 如果使用的缓存组件支持这个特性，则应该使用缓存组件更加适用的方法覆盖本方法。
     * 如果一个缓存不支持这个特性，那么这个方法将尝试模拟它，但是在获得它的过程中没有性能上的改进。
     * 注意，该方法不检查与缓存数据相关的依赖关系是否已经发生了变化。
     * 因此，当该函数返回true时，调用[[get]]可能返回false。
     * Checks whether a specified key exists in the cache.
     * This can be faster than getting the value from the cache if the data is big.
     * Note that this method does not check whether the dependency associated
     * with the cached data, if there is any, has changed. So a call to [[get]]
     * may return false while exists returns true.
     * @param mixed $key a key identifying the cached value. This can be a simple string or
     * a complex data structure consisting of factors representing the key.
     * @return bool true if a value exists in cache, false if the value is not in the cache or expired.
     */
    public function exists($key)
    {
        $key = $this->buildKey($key);

        return xcache_isset($key);
    }

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
        return xcache_isset($key) ? xcache_get($key) : false;
    }

    /**
     * 在缓存中存储一个键对应的值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param mixed $value the value to be cached. Most often it's a string. If you have disabled [[serializer]],
     * it could be something else.
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function setValue($key, $value, $duration)
    {
        return xcache_set($key, $value, $duration);
    }

    /**
     * 如果缓存不包含该键，则缓存该键和值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param mixed $value the value to be cached. Most often it's a string. If you have disabled [[serializer]],
     * it could be something else.
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function addValue($key, $value, $duration)
    {
        return !xcache_isset($key) ? $this->setValue($key, $value, $duration) : false;
    }

    /**
     * 从缓存中删除指定键的值。
     * 这是在父类中声明的方法的实现.
     * Deletes a value with the specified key from cache
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key of the value to be deleted
     * @return bool if no error happens during deletion
     */
    protected function deleteValue($key)
    {
        return xcache_unset($key);
    }

    /**
     * 清空所有缓存
     * 这是在父类中声明的方法的实现.
     * Deletes all values from cache.
     * This is the implementation of the method declared in the parent class.
     * @return bool whether the flush operation was successful.
     */
    protected function flushValues()
    {
        for ($i = 0, $max = xcache_count(XC_TYPE_VAR); $i < $max; $i++) {
            if (xcache_clear_cache(XC_TYPE_VAR, $i) === false) {
                return false;
            }
        }

        return true;
    }
}
