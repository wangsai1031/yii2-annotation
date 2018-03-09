<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

/**
 * ArrayCache仅通过将值存储在数组中为当前请求提供缓存
 * ArrayCache provides caching for the current request only by storing the values in an array.
 *
 * See [[Cache]] for common cache operations that ArrayCache supports.
 *
 * 与[[Cache]]不同，ArrayCache允许[[set]], [[add]], [[multiSet]] and [[multiAdd]]的过期参数为浮点数，
 * 所以你可以用毫秒来指定时间(例如，0.1将是100毫秒)
 * Unlike the [[Cache]], ArrayCache allows the expire parameter of [[set]], [[add]], [[multiSet]] and [[multiAdd]] to
 * be a floating point number, so you may specify the time in milliseconds (e.g. 0.1 will be 100 milliseconds).
 *
 * For enhanced performance of ArrayCache, you can disable serialization of the stored data by setting [[$serializer]] to `false`.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class ArrayCache extends Cache
{
    /**
     * @var
     * 用于存储缓存数据的数组:
     *
     * $this->_cache[$key] = [$value, $duration === 0 ? 0 : microtime(true) + $duration];
     *
     * 第一个元素为要缓存的值
     * 第二个元素为过期时间（微妙级），0为永不过期
     */
    private $_cache;


    /**
     * 返回一个值，指明某个键是否存在于缓存中。
     * {@inheritdoc}
     */
    public function exists($key)
    {
        // 规范化key
        $key = $this->buildKey($key);
        // 判断指定缓存是否存在。 指定键存在且（缓存永不过期或未到过期时间）
        return isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true));
    }

    /**
     * 使用指定的键从缓存中检索值。
     * {@inheritdoc}
     */
    protected function getValue($key)
    {
        // 判断指定缓存是否存在且有效。 指定键存在且（缓存永不过期或未到过期时间）
        if (isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true))) {
            return $this->_cache[$key][0];
        }

        return false;
    }

    /**
     * 在缓存中存储一个键对应的值。
     * {@inheritdoc}
     */
    protected function setValue($key, $value, $duration)
    {
        /**
         * 设置缓存数据
         *
         * 第一个元素为要缓存的值
         * 第二个元素为过期时间（微妙级），0为永不过期
         */
        $this->_cache[$key] = [$value, $duration === 0 ? 0 : microtime(true) + $duration];
        return true;
    }

    /**
     * 添加缓存值（若存在，则忽略，若不存在，则添加缓存）
     * {@inheritdoc}
     */
    protected function addValue($key, $value, $duration)
    {
        // 判断指定缓存是否存在且有效。 指定键存在且（缓存永不过期或未到过期时间）
        if (isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true))) {
            return false;
        }
        $this->_cache[$key] = [$value, $duration === 0 ? 0 : microtime(true) + $duration];
        return true;
    }

    /**
     * 删除指定缓存
     * {@inheritdoc}
     */
    protected function deleteValue($key)
    {
        unset($this->_cache[$key]);
        return true;
    }

    /**
     * 清空缓存
     * {@inheritdoc}
     */
    protected function flushValues()
    {
        $this->_cache = [];
        return true;
    }
}
