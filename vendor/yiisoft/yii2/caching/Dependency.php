<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

/**
 * Dependency是缓存依赖类的基类
 * Dependency is the base class for cache dependency classes.
 *
 * 子类应该覆盖[[generateDependencyData()]]以生成实际的依赖数据。
 * Child classes should override its [[generateDependencyData()]] for generating
 * the actual dependency data.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class Dependency extends \yii\base\BaseObject
{
    /**
     * 用于与最新的依赖项数据进行比较的在缓存中保存的依赖项数据。
     * @var mixed the dependency data that is saved in cache and later is compared with the
     * latest dependency data.
     */
    public $data;
    /**
     * 这种依赖是否可重用。
     * true 意味着这个缓存依赖项的依赖数据只会在每次请求时生成一次。
     * 这允许您对多个单独的缓存调用，使用相同的缓存依赖，在生成相同的页面时，不需要每次重新评估依赖数据的开销。
     * 默认为false
     * @var bool whether this dependency is reusable or not. True value means that dependent
     * data for this cache dependency will be generated only once per request. This allows you
     * to use the same cache dependency for multiple separate cache calls while generating the same
     * page without an overhead of re-evaluating dependency data each time. Defaults to false.
     */
    public $reusable = false;

    /**
     * 缓存数据的静态存储，以供可重用的依赖项
     * @var array static storage of cached data for reusable dependencies.
     */
    private static $_reusableData = [];


    /**
     * 通过生成和保存与依赖关系相关的数据来评估依赖关系。
     * 该方法由缓存组件在将数据写入缓存之前调用。
     * Evaluates the dependency by generating and saving the data related with dependency.
     * This method is invoked by cache before writing data into it.
     *
     * 当前正在评估这种依赖关系的缓存组件
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     */
    public function evaluateDependency($cache)
    {
        // 依赖是否可重用
        if ($this->reusable) {
            // 生成一个惟一的散列，可用于检索可重用的依赖项数据。
            $hash = $this->generateReusableHash();
            // 若 self::$_reusableData 数组中没有缓存这个依赖
            if (!array_key_exists($hash, self::$_reusableData)) {
                // 生成所需的依赖项数据，并缓存到$_reusableData数组中。
                self::$_reusableData[$hash] = $this->generateDependencyData($cache);
            }
            // 获取依赖数据
            $this->data = self::$_reusableData[$hash];
        } else {
            // 若依赖不可重用, 直接生成所需的依赖项数据
            $this->data = $this->generateDependencyData($cache);
        }
    }

    /**
     * 判断依赖项是否改变
     * Returns a value indicating whether the dependency has changed.
     * @deprecated since version 2.0.11. Will be removed in version 2.1. Use [[isChanged()]] instead.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return bool whether the dependency has changed.
     */
    public function getHasChanged($cache)
    {
        return $this->isChanged($cache);
    }

    /**
     * Checks whether the dependency is changed.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return bool whether the dependency has changed.
     * @since 2.0.11
     */
    public function isChanged($cache)
    {
        // 依赖是否可重用
        if ($this->reusable) {
            // 生成一个惟一的散列，可用于检索可重用的依赖项数据。
            $hash = $this->generateReusableHash();
            // 若 self::$_reusableData 数组中没有缓存这个依赖
            if (!array_key_exists($hash, self::$_reusableData)) {
                // 生成所需的依赖项数据，并缓存到$_reusableData数组中。
                self::$_reusableData[$hash] = $this->generateDependencyData($cache);
            }
            // 获取依赖数据
            $data = self::$_reusableData[$hash];
        } else {
            // 若依赖不可重用, 直接生成所需的依赖项数据
            $data = $this->generateDependencyData($cache);
        }

        // 新生成的依赖数据与缓存中的依赖数据进行比较
        return $data !== $this->data;
    }

    /**
     * 清空所有可重用的依赖项数据
     * Resets all cached data for reusable dependencies.
     */
    public static function resetReusableData()
    {
        self::$_reusableData = [];
    }

    /**
     * 生成一个惟一的散列，可用于检索可重用的依赖项数据。
     * Generates a unique hash that can be used for retrieving reusable dependency data.
     * @return string a unique hash value for this cache dependency.
     * @see reusable
     */
    protected function generateReusableHash()
    {
        // 临时将 依赖项数据 保存在 $data 中
        $data = $this->data;
        // 将 $this->data 置空
        $this->data = null;  // https://github.com/yiisoft/yii2/issues/3052
        // 先序列化该实例，然后使用 sha1() 计算字符串的 SHA-1 散列。
        $key = sha1(serialize($this));
        // 将 依赖项数据 还给 $this->data
        $this->data = $data;
        // 返回 $key
        return $key;
    }

    /**
     * 生成所需的依赖项数据，以确定是否已经更改了依赖关系.
     * 派生类应该覆盖这个方法来生成实际的依赖数据。
     * Generates the data needed to determine if dependency is changed.
     * Derived classes should override this method to generate the actual dependency data.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     */
    abstract protected function generateDependencyData($cache);
}
