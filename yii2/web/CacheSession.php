<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\caching\CacheInterface;
use yii\di\Instance;

/**
 * CacheSession 实现了 使用缓存作为存储媒介 的session组件
 * CacheSession implements a session component using cache as storage medium.
 *
 * 缓存可以是任何缓存应用程序组件。
 * 缓存应用程序组件的ID是通过[[cache]]指定的，它默认为'cache'。
 * The cache being used can be any cache application component.
 * The ID of the cache application component is specified via [[cache]], which defaults to 'cache'.
 *
 * 注意，根据定义，缓存存储是不稳定的，这意味着存储在它们上的数据可能会丢失.
 * 因此，您必须确保该组件使用的缓存不具有易失性。
 * 如果您想将数据库作为存储介质，则[[DbSession]]是更好的选择。
 * Beware, by definition cache storage are volatile, which means the data stored on them
 * may be swapped out and get lost. Therefore, you must make sure the cache used by this component
 * is NOT volatile. If you want to use database as storage medium, [[DbSession]] is a better choice.
 *
 * The following example shows how you can configure the application to use CacheSession:
 * Add the following to your application config under `components`:
 *
 * 下面的示例展示了如何配置应用程序以使用CacheSession：
 * 将以下内容添加到您的`components`应用程序配置下：
 *
 * ```php
 * 'session' => [
 *     'class' => 'yii\web\CacheSession',
 *     // 'cache' => 'mycache',
 * ]
 * ```
 *
 * 是否使用自定义存储，这个属性是只读的。
 * @property bool $useCustomStorage Whether to use custom storage. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class CacheSession extends Session
{
    /**
     * 缓存对象或缓存对象的应用程序组件ID。
     * 会话数据将使用这个缓存对象进行存储。
     * @var CacheInterface|array|string the cache object or the application component ID of the cache object.
     * The session data will be stored using this cache object.
     *
     * 在创建了CacheSession对象之后，如果您想要更改这个属性，那么你只能使用一个缓存对象来分配它。
     * After the CacheSession object is created, if you want to change this property,
     * you should only assign it with a cache object.
     *
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     * 从2.0.2版本开始，这也可以是创建对象的配置数组。
     */
    public $cache = 'cache';


    /**
     * 初始化应用程序组件。
     * Initializes the application component.
     */
    public function init()
    {
        parent::init();
        // 将 $this->cache 引用解析成实际的对象，并确保这个对象的类型
        $this->cache = Instance::ensure($this->cache, 'yii\caching\CacheInterface');
    }

    /**
     * 返回一个表示是否使用自定义会话存储的值.
     * 这个方法覆盖了父实现，并且总是返回true。
     * Returns a value indicating whether to use custom session storage.
     * This method overrides the parent implementation and always returns true.
     * @return bool whether to use custom storage.
     */
    public function getUseCustomStorage()
    {
        return true;
    }

    /**
     * 会话读取处理程序.
     * 不要直接调用这个方法
     * Session read handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return string the session data
     */
    public function readSession($id)
    {
        // 从缓存中获取session
        $data = $this->cache->get($this->calculateKey($id));

        return $data === false ? '' : $data;
    }

    /**
     * 会话写入处理程序.
     * 不要直接调用这个方法
     * Session write handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return bool whether session write is successful
     */
    public function writeSession($id, $data)
    {
        // 将session写入到缓存中
        return $this->cache->set($this->calculateKey($id), $data, $this->getTimeout());
    }

    /**
     * 会话销毁处理程序
     * Session destroy handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return bool whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        $cacheId = $this->calculateKey($id);
        if ($this->cache->exists($cacheId) === false) {
            return true;
        }

        // 从缓存中删除指定的session
        return $this->cache->delete($cacheId);
    }

    /**
     * 生成用于在缓存中存储会话数据的惟一键.
     * Generates a unique key used for storing session data in cache.
     * @param string $id session variable name
     * 返回一个与会话变量名关联的安全缓存键。
     * @return mixed a safe cache key associated with the session variable name
     */
    protected function calculateKey($id)
    {
        return [__CLASS__, $id];
    }
}
