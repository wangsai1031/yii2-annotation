<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

use yii\base\InvalidConfigException;

/**
 * 使用 PHP APC 扩展。这个选项可以 认为是集中式应用程序环境中（例如：单一服务器， 没有独立的负载均衡器等）最快的缓存方案。
 * ApcCache provides APC caching in terms of an application component.
 *
 * 要使用此应用程序组件，[APC PHP 扩展] 必须加载 (http://www.php.net/apc)。
 * To use this application component, the [APC PHP extension](http://www.php.net/apc) must be loaded.
 * Alternatively [APCu PHP extension](http://www.php.net/apcu) could be used via setting `useApcu` to `true`.
 * In order to enable APC or APCu for CLI you should add "apc.enable_cli = 1" to your php.ini.
 *
 * See [[Cache]] for common cache operations that ApcCache supports.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ApcCache extends Cache
{
    /**
     * @var boolean whether to use apcu or apc as the underlying caching extension.
     * If true, [apcu](http://pecl.php.net/package/apcu) will be used.
     * If false, [apc](http://pecl.php.net/package/apc) will be used.
     * Defaults to false.
     * @since 2.0.7
     */
    public $useApcu = false;


    /**
     * 初始化应用程序组件。
     * 检查是否加载了php apcu 或 apc 扩展
     * Initializes this application component.
     * It checks if extension required is loaded.
     */
    public function init()
    {
        parent::init();
        $extension = $this->useApcu ? 'apcu' : 'apc';
        if (!extension_loaded($extension)) {
            throw new InvalidConfigException("ApcCache requires PHP $extension extension to be loaded.");
        }
    }

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
     * @return boolean true if a value exists in cache, false if the value is not in the cache or expired.
     */
    public function exists($key)
    {
        $key = $this->buildKey($key);

        return $this->useApcu ? apcu_exists($key) : apc_exists($key);
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
        return $this->useApcu ? apcu_fetch($key) : apc_fetch($key);
    }

    /**
     * Retrieves multiple values from cache with the specified keys.
     * @param array $keys a list of keys identifying the cached values
     * @return array a list of cached values indexed by the keys
     */
    protected function getValues($keys)
    {
        $values = $this->useApcu ? apcu_fetch($keys) : apc_fetch($keys);
        return is_array($values) ? $values : [];
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
     * @param integer $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise.
     */
    protected function setValue($key, $value, $duration)
    {
        return $this->useApcu ? apcu_store($key, $value, $duration) : apc_store($key, $value, $duration);
    }

    /**
     * Stores multiple key-value pairs in cache.
     * @param array $data array where key corresponds to cache key while value
     * @param integer $duration the number of seconds in which the cached values will expire. 0 means never expire.
     * @return array array of failed keys
     */
    protected function setValues($data, $duration)
    {
        $result = $this->useApcu ? apcu_store($data, null, $duration) : apc_store($data, null, $duration);
        return is_array($result) ? array_keys($result) : [];
    }

    /**
     * 如果缓存不包含该键，则缓存该键和值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key identifying the value to be cached
     * @param mixed $value the value to be cached. Most often it's a string. If you have disabled [[serializer]],
     * it could be something else.
     * @param integer $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function addValue($key, $value, $duration)
    {
        return $this->useApcu ? apcu_add($key, $value, $duration) : apc_add($key, $value, $duration);
    }

    /**
     * Adds multiple key-value pairs to cache.
     * @param array $data array where key corresponds to cache key while value is the value stored
     * @param integer $duration the number of seconds in which the cached values will expire. 0 means never expire.
     * @return array array of failed keys
     */
    protected function addValues($data, $duration)
    {
        $result = $this->useApcu ? apcu_add($data, null, $duration) : apc_add($data, null, $duration);
        return is_array($result) ? array_keys($result) : [];
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
        return $this->useApcu ? apcu_delete($key) : apc_delete($key);
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
        return $this->useApcu ? apcu_clear_cache() : apc_clear_cache('user');
    }
}
