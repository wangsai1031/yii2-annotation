<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

use Yii;
use yii\base\InvalidConfigException;

/**
 * 使用 PHP memcache 和 memcached 扩展。
 * 这个选项被看作分布式应用环境中（例如：多台服务器， 有负载均衡等）最快的缓存方案。
 * MemCache implements a cache application component based on [memcache](http://pecl.php.net/package/memcache)
 * and [memcached](http://pecl.php.net/package/memcached).
 *
 * MemCache 对于PHP [memcache] 和 [memcached] 两种扩展都支持。
 * 通过将 [[useMemcached]]设置为true或false，可以让MemCache分别使用memcached或memcache。
 * MemCache supports both [memcache](http://pecl.php.net/package/memcache) and
 * [memcached](http://pecl.php.net/package/memcached). By setting [[useMemcached]] to be true or false,
 * one can let MemCache to use either memcached or memcache, respectively.
 *
 * 可以通过设置它的[[servers]]属性来配置MemCache服务器列表。
 * 默认情况下，MemCache假设有一个MemCache服务器运行在本地主机11211端口上。
 * MemCache can be configured with a list of memcache servers by settings its [[servers]] property.
 * By default, MemCache assumes there is a memcache server running on localhost at port 11211.
 *
 * See [[Cache]] for common cache operations that MemCache supports.
 *
 * 注意，在memcache中没有保护数据的安全措施。
 * 可以通过在系统中运行的任何进程访问memcache中的所有数据。
 * Note, there is no security measure to protected data in memcache.
 * All data in memcache can be accessed by any process running in the system.
 *
 * To use MemCache as the cache application component, configure the application as follows,
 * 要使用MemCache作为缓存应用程序组件，请按照以下方法配置应用程序，
 *
 * ```php
 * [
 *     'components' => [
 *         'cache' => [
 *             'class' => 'yii\caching\MemCache',
 *             'servers' => [
 *                 [
 *                     'host' => 'server1',
 *                     'port' => 11211,
 *                     'weight' => 60,
 *                 ],
 *                 [
 *                     'host' => 'server2',
 *                     'port' => 11211,
 *                     'weight' => 40,
 *                 ],
 *             ],
 *         ],
 *     ],
 * ]
 * ```
 *
 * 在上面方法中，使用了两个memcache服务器:server1和server2。
 * 您可以配置每个服务器的更多属性，例如`persistent`, `weight`, `timeout`。
 * 通过 [[MemCacheServer]] 查看可选项
 * In the above, two memcache servers are used: server1 and server2. You can configure more properties of
 * each server, such as `persistent`, `weight`, `timeout`. Please see [[MemCacheServer]] for available options.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @property \Memcache|\Memcached $memcache The memcache (or memcached) object used by this cache component.
 * This property is read-only.
 * @property MemCacheServer[] $servers List of memcache server configurations. Note that the type of this
 * property differs in getter and setter. See [[getServers()]] and [[setServers()]] for details.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class MemCache extends Cache
{
    /**
     * 是否使用 memcached 作为底层的缓存扩展
     * @var bool whether to use memcached or memcache as the underlying caching extension.
     * If true, [memcached](http://pecl.php.net/package/memcached) will be used.
     * If false, [memcache](http://pecl.php.net/package/memcache) will be used.
     * Defaults to false.
     */
    public $useMemcached = false;
    /**
     * 标识Memcached实例的ID。
     * 只有当 [[useMemcached]] 为 true 时才使用该属性.
     * 默认情况下，Memcached实例在请求结束时被销毁.
     * 要在请求之间创建一个实例，您可以为实例指定一个惟一的ID。
     * 使用相同ID创建的所有实例将共享相同的连接。
     * @var string an ID that identifies a Memcached instance. This property is used only when [[useMemcached]] is true.
     * By default the Memcached instances are destroyed at the end of the request. To create an instance that
     * persists between requests, you may specify a unique ID for the instance. All instances created with the
     * same ID will share the same connection.
     * @see http://ca2.php.net/manual/en/memcached.construct.php
     */
    public $persistentId;
    /**
     * Memcached 的参数
     * 只有当 [[useMemcached]] 为 true 时才使用该属性.
     * @var array options for Memcached. This property is used only when [[useMemcached]] is true.
     * @see http://ca2.php.net/manual/en/memcached.setoptions.php
     */
    public $options;
    /**
     * memcached sasl用户名
     * 只有当 [[useMemcached]] 为 true 时才使用该属性.
     * @var string memcached sasl username. This property is used only when [[useMemcached]] is true.
     * @see http://php.net/manual/en/memcached.setsaslauthdata.php
     */
    public $username;
    /**
     * memcached sasl密码
     * 只有当 [[useMemcached]] 为 true 时才使用该属性.
     * @var string memcached sasl password. This property is used only when [[useMemcached]] is true.
     * @see http://php.net/manual/en/memcached.setsaslauthdata.php
     */
    public $password;

    /**
     * Memcache实例
     * @var \Memcache|\Memcached the Memcache instance
     */
    private $_cache;
    /**
     * memcache服务器配置列表
     * @var array list of memcache server configurations
     */
    private $_servers = [];


    /**
     * 初始化应用程序组件。
     * 它创建memcache实例并添加memcache服务器
     * Initializes this application component.
     * It creates the memcache instance and adds memcache servers.
     */
    public function init()
    {
        parent::init();
        // 创建memcache实例并添加memcache服务器
        $this->addServers($this->getMemcache(), $this->getServers());
    }

    /**
     * 将服务器添加到指定的缓存服务器池中
     * Add servers to the server pool of the cache specified.
     *
     * @param \Memcache|\Memcached $cache
     * @param MemCacheServer[] $servers
     * @throws InvalidConfigException
     */
    protected function addServers($cache, $servers)
    {
        if (empty($servers)) {
            // 若 $servers 为空，则使用默认配置项
            // 本地服务器的 11211 端口
            $servers = [new MemCacheServer([
                'host' => '127.0.0.1',
                'port' => 11211,
            ])];
        } else {
            foreach ($servers as $server) {
                // 检查 每个 $server 是否配置了 host（Memcache运行的主机地址）  参数
                if ($server->host === null) {
                    throw new InvalidConfigException("The 'host' property must be specified for every memcache server.");
                }
            }
        }
        if ($this->useMemcached) {
            // 将服务器添加到指定的缓存服务器池中。
            $this->addMemcachedServers($cache, $servers);
        } else {
            // 将服务器添加到指定的缓存服务器池中。
            $this->addMemcacheServers($cache, $servers);
        }
    }

    /**
     * 将服务器添加到指定的缓存服务器池中。
     * 用于memcached扩展
     * Add servers to the server pool of the cache specified
     * Used for memcached PECL extension.
     *
     * @param \Memcached $cache
     * @param MemCacheServer[] $servers
     */
    protected function addMemcachedServers($cache, $servers)
    {
        $existingServers = [];
        if ($this->persistentId !== null) {
            // 获取缓存服务器池中所有服务器的列表，遍历
            foreach ($cache->getServerList() as $s) {
                // 类似 $existingServers['127.0.0.1:11211'] = true;
                $existingServers[$s['host'] . ':' . $s['port']] = true;
            }
        }
        // 遍历$servers
        foreach ($servers as $server) {
            // 若该缓存服务器不存在，则在服务器池中添加一个服务器。
            if (empty($existingServers) || !isset($existingServers[$server->host . ':' . $server->port])) {
                $cache->addServer($server->host, $server->port, $server->weight);
            }
        }
    }

    /**
     * 将服务器添加到指定的缓存服务器池中。
     * 用于memcache扩展
     * Add servers to the server pool of the cache specified
     * Used for memcache PECL extension.
     *
     * @param \Memcache $cache
     * @param MemCacheServer[] $servers
     */
    protected function addMemcacheServers($cache, $servers)
    {
        $class = new \ReflectionClass($cache);
        // 获取 \Memcache 类中的 addServer() 方法的参数个数，为了兼容不同的memcache 扩展版本
        $paramCount = $class->getMethod('addServer')->getNumberOfParameters();
        foreach ($servers as $server) {
            // $timeout is used for memcache versions that do not have $timeoutms parameter
            // $timeout 用于没有 $timeoutms 参数的memcache版本
            $timeout = (int) ($server->timeout / 1000) + (($server->timeout % 1000 > 0) ? 1 : 0);
            if ($paramCount === 9) {
                // 在服务器池中添加一个服务器。
                $cache->addserver(
                    $server->host,
                    $server->port,
                    $server->persistent,
                    $server->weight,
                    $timeout,
                    $server->retryInterval,
                    $server->status,
                    $server->failureCallback,
                    $server->timeout
                );
            } else {
                // 在服务器池中添加一个服务器。
                $cache->addserver(
                    $server->host,
                    $server->port,
                    $server->persistent,
                    $server->weight,
                    $timeout,
                    $server->retryInterval,
                    $server->status,
                    $server->failureCallback
                );
            }
        }
    }

    /**
     * 返回底层的memcache(或memcached)对象
     * Returns the underlying memcache (or memcached) object.
     * @return \Memcache|\Memcached the memcache (or memcached) object used by this cache component.
     * @throws InvalidConfigException if memcache or memcached extension is not loaded
     */
    public function getMemcache()
    {
        if ($this->_cache === null) {
            // 选择memcache扩展
            $extension = $this->useMemcached ? 'memcached' : 'memcache';
            /**
             * http://php.net/manual/zh/function.extension-loaded.php
             * extension_loaded — 检查一个扩展是否已经加载
             *
             * 检查指定的memcache扩展是否加载
             */
            if (!extension_loaded($extension)) {
                throw new InvalidConfigException("MemCache requires PHP $extension extension to be loaded.");
            }

            if ($this->useMemcached) {
                // 若是memcached 根据 persistentId 创建 Memcached 实例
                $this->_cache = $this->persistentId !== null ? new \Memcached($this->persistentId) : new \Memcached();
                if ($this->username !== null || $this->password !== null) {
                    $this->_cache->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);

                    // 若设置了用户名和密码项，则在memcached中设置用于身份验证的凭据
                    /** @see http://docs.php.net/manual/zh/memcached.setsaslauthdata.php */
                    $this->_cache->setSaslAuthData($this->username, $this->password);
                }
                if (!empty($this->options)) {
                    // 设置 memcached 参数
                    $this->_cache->setOptions($this->options);
                }
            } else {
                // 若是memcache，直接创建 Memcache 实例
                $this->_cache = new \Memcache();
            }
        }

        // 返回Memcache 或 Memcached 实例
        return $this->_cache;
    }

    /**
     * 返回memcache或memcached服务器配置
     * Returns the memcache or memcached server configurations.
     * 返回MemCacheServer[] memcache服务器配置列表
     * @return MemCacheServer[] list of memcache server configurations.
     */
    public function getServers()
    {
        return $this->_servers;
    }

    /**
     * memcache或memcached服务器配置列表.
     * 每个元素必须是一个具有以下键的数组:host, port, persistent, weight, timeout, retryInterval, status。
     * @param array $config list of memcache or memcached server configurations. Each element must be an array
     * with the following keys: host, port, persistent, weight, timeout, retryInterval, status.
     * @see http://php.net/manual/en/memcache.addserver.php
     * @see http://php.net/manual/en/memcached.addserver.php
     */
    public function setServers($config)
    {
        // 遍历配置，依次添加 缓存服务器配置信息
        foreach ($config as $c) {
            $this->_servers[] = new MemCacheServer($c);
        }
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
        return $this->_cache->get($key);
    }

    /**
     * 使用指定的键从缓存中检索多个值
     * Retrieves multiple values from cache with the specified keys.
     * @param array $keys a list of keys identifying the cached values
     * @return array a list of cached values indexed by the keys
     */
    protected function getValues($keys)
    {
        return $this->useMemcached ? $this->_cache->getMulti($keys) : $this->_cache->get($keys);
    }

    /**
     * 在缓存中存储一个键对应的值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param mixed $value the value to be cached.
     * @see [Memcache::set()](http://php.net/manual/en/memcache.set.php)
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function setValue($key, $value, $duration)
    {
        // 使用UNIX时间戳，因为它没有任何限制
        // Use UNIX timestamp since it doesn't have any limitation
        // @see http://php.net/manual/en/memcache.set.php
        // @see http://php.net/manual/en/memcached.expiration.php
        $expire = $duration > 0 ? $duration + time() : 0;

        return $this->useMemcached ? $this->_cache->set($key, $value, $expire) : $this->_cache->set($key, $value, 0, $expire);
    }

    /**
     * 在缓存中存储多个键值对
     * Stores multiple key-value pairs in cache.
     * @param array $data array where key corresponds to cache key while value is the value stored
     * @param int $duration the number of seconds in which the cached values will expire. 0 means never expire.
     * @return array array of failed keys.
     * 返回 添加失败的key的数组。在使用memcached时总是空的。
     */
    protected function setValues($data, $duration)
    {
        if ($this->useMemcached) {
            // 使用UNIX时间戳，因为它没有任何限制
            // Use UNIX timestamp since it doesn't have any limitation
            // @see http://php.net/manual/en/memcache.set.php
            // @see http://php.net/manual/en/memcached.expiration.php
            $expire = $duration > 0 ? $duration + time() : 0;

            // 批量设置缓存
            // Memcached::setMulti() returns boolean
            // @see http://php.net/manual/en/memcached.setmulti.php
            return $this->_cache->setMulti($data, $expire) ? [] : array_keys($data);
        }

        return parent::setValues($data, $duration);
    }

    /**
     * 如果缓存不包含该键，则缓存该键和值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param mixed $value the value to be cached
     * @see [Memcache::set()](http://php.net/manual/en/memcache.set.php)
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function addValue($key, $value, $duration)
    {
        // 使用UNIX时间戳，因为它没有任何限制
        // Use UNIX timestamp since it doesn't have any limitation
        // @see http://php.net/manual/en/memcache.set.php
        // @see http://php.net/manual/en/memcached.expiration.php
        $expire = $duration > 0 ? $duration + time() : 0;

        return $this->useMemcached ? $this->_cache->add($key, $value, $expire) : $this->_cache->add($key, $value, 0, $expire);
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
        return $this->_cache->delete($key, 0);
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
        return $this->_cache->flush();
    }
}
