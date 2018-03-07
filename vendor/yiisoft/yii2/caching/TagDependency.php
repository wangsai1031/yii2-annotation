<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

/**
 * TagDependency 将一个缓存的数据项与一个或多个[[tags]]关联起来.
 * TagDependency associates a cached data item with one or multiple [[tags]].
 *
 * 通过调用[[invalidate()]]，您可以使 与指定的tag名相关联的所有缓存数据项 失效.
 * By calling [[invalidate()]], you can invalidate all cached data items that are associated with the specified tag name(s).
 *
 * ```php
 * // setting multiple cache keys to store data forever and tagging them with "user-123"
 * // 设置多个缓存键以永久存储数据，并使用"user-123"标记它们
 * Yii::$app->cache->set('user_42_profile', '', 0, new TagDependency(['tags' => 'user-123']));
 * Yii::$app->cache->set('user_42_stats', '', 0, new TagDependency(['tags' => 'user-123']));
 *
 * // invalidating all keys tagged with "user-123"
 * // 令 'user-123 标记的所有缓存失效
 * TagDependency::invalidate(Yii::$app->cache, 'user-123');
 * ```
 *
 * 该类被用于数据库结构缓存
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class TagDependency extends Dependency
{
    /**
     * 此依赖项的 tag 名称列表。对于单个标 tag ，可以将其指定为字符串。
     * @var string|array a list of tag names for this dependency. For a single tag, you may specify it as a string.
     */
    public $tags = [];


    /**
     * 生成所需的数据，以确定是否更改了依赖关系。
     * Generates the data needed to determine if dependency has been changed.
     *
     * @param Cache $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     */
    protected function generateDependencyData($cache)
    {
        // 获取指定标签的时间戳列表
        $timestamps = $this->getTimestamps($cache, (array) $this->tags);

        $newKeys = [];
        /**
         * 遍历时间戳列表数组
         * $key: 经过格式化后的 标签
         * $timestamp: 微秒级时间戳
         */
        foreach ($timestamps as $key => $timestamp) {
            // 若 $timestamp 为false,说明缓存中不存在该tag,则将该tag作为新标签添加到缓存
            if ($timestamp === false) {
                $newKeys[] = $key;
            }
        }
        if (!empty($newKeys)) {
            // 为新标签生成时间戳，并与旧的 $timestamps 合并
            $timestamps = array_merge($timestamps, static::touchKeys($cache, $newKeys));
        }

        return $timestamps;
    }

    /**
     * 检查依赖是否发生改变
     * Performs the actual dependency checking.
     * @param Cache $cache the cache component that is currently evaluating this dependency
     * @return boolean whether the dependency is changed or not.
     */
    public function getHasChanged($cache)
    {
        // 获取 新的 Timestamps， 跟之前的数据对比是否发生变化
        $timestamps = $this->getTimestamps($cache, (array) $this->tags);
        return $timestamps !== $this->data;
    }

    /**
     * 将所有与指定标签相关联的缓存数据项 设为失效
     * Invalidates all of the cached data items that are associated with any of the specified [[tags]].
     * @param Cache $cache the cache component that caches the data items
     * @param string|array $tags
     */
    public static function invalidate($cache, $tags)
    {
        $keys = [];
        foreach ((array) $tags as $tag) {
            // 生成标签缓存键
            $keys[] = $cache->buildKey([__CLASS__, $tag]);
        }
        // 为每个标签重新生成时间戳，则标签的时间戳发生改变，依赖改变，之前依赖的缓存数据失效
        static::touchKeys($cache, $keys);
    }

    /**
     * 为指定的缓存键生成时间戳
     * Generates the timestamp for the specified cache keys.
     * @param Cache $cache
     * @param string[] $keys
     * @return array the timestamp indexed by cache keys
     */
    protected static function touchKeys($cache, $keys)
    {
        $items = [];
        // 微秒级时间戳，比普通的秒级时间戳后面多6位
        $time = microtime();
        foreach ($keys as $key) {
            // 遍历每个标签，将相同的时间戳赋给它们
            $items[$key] = $time;
        }
        // 批量加入到缓存
        $cache->multiSet($items);
        return $items;
    }

    /**
     * 返回指定标签的时间戳
     * Returns the timestamps for the specified tags.
     * @param Cache $cache
     * @param string[] $tags
     * @return array the timestamps indexed by the specified tags.
     */
    protected function getTimestamps($cache, $tags)
    {
        if (empty($tags)) {
            return [];
        }

        $keys = [];
        foreach ($tags as $tag) {
            $keys[] = $cache->buildKey([__CLASS__, $tag]);
        }

        return $cache->multiGet($keys);
    }
}
