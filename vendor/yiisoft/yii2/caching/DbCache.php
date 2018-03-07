<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;

/**
 * 使用一个数据库的表存储缓存数据。
 * 要使用这个缓存，你必须 创建一个与 yii\caching\DbCache::$cacheTable 对应的表。
 * DbCache implements a cache application component by storing cached data in a database.
 *
 * 默认情况下，DbCache将会话数据存储在一个名为'cache'的数据表中。
 * 这个表必须预先创建。
 * 表明可以通过设置[[cacheTable]]修改。
 * By default, DbCache stores session data in a DB table named 'cache'.
 * This table must be pre-created. The table name can be changed by setting [[cacheTable]].
 *
 * 对于由DbCache支持的常见缓存操作，请参考[[Cache]]。
 * Please refer to [[Cache]] for common cache operations that are supported by DbCache.
 *
 * The following example shows how you can configure the application to use DbCache:
 *
 * ```php
 * 'cache' => [
 *     'class' => 'yii\caching\DbCache',
 *     // 'db' => 'mydb',
 *     // 'cacheTable' => 'my_cache',
 * ]
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DbCache extends Cache
{
    /**
     * DB连接对象或DB连接的应用程序组件ID。
     * 在创建DbCache对象之后，如果您想要更改这个属性，您应该只使用一个DB连接对象来分配它。
     * 从2.0.2版本开始，这也可以是创建对象的配置数组
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection.
     * After the DbCache object is created, if you want to change this property, you should only assign it with a DB connection object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db = 'db';
    /**
     * 存储缓存内容的数据表名称
     * @var string name of the DB table to store cache content.
     * The table should be pre-created as follows:
     *
     * ```php
     * CREATE TABLE cache (
     *     id char(128) NOT NULL PRIMARY KEY,
     *     expire int(11),
     *     data BLOB
     * );
     * ```
     * “BLOB”指的是您首选的DBMS的BLOB类型。
     * 下面是一些流行地关系型数据库的BLOB类型。
     * where 'BLOB' refers to the BLOB-type of your preferred DBMS.
     * Below are the BLOB type that can be used for some popular DBMS:
     *
     * - MySQL: LONGBLOB
     * - PostgreSQL: BYTEA
     * - MSSQL: BLOB
     *
     * 当在生产服务器中使用DbCache时，我们建议您为缓存表中的“过期”列创建一个DB索引，以提高性能。
     * When using DbCache in a production server, we recommend you create a DB index for the 'expire' column in the cache table to improve the performance.
     */
    public $cacheTable = '{{%cache}}';
    /**
     * 当在缓存中存储数据时，应该执行垃圾回收(GC)的概率(百万分之一)
     * 默认是100,意味着 0.01% 的概率。
     * 这个数字应该在0到1000000之间。
     * 0 意味着不会执行垃圾回收
     * @var integer the probability (parts per million) that garbage collection (GC) should be performed when storing a piece of data in the cache.
     * Defaults to 100, meaning 0.01% chance.
     * This number should be between 0 and 1000000. A value 0 meaning no GC will be performed at all.
     */
    public $gcProbability = 100;


    /**
     * 初始化DbCache组件。
     * 该方法将初始化 [[db]]属性，以确保它引用一个有效的数据库连接。
     * Initializes the DbCache component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init()
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::className());
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
        // 生成规范化key
        $key = $this->buildKey($key);

        $query = new Query;
        // 拼接查询Sql语句，查找 id 为 key,过期时间为0 或过期时间大于现在的内容条数（0 或 1）。
        $query->select(['COUNT(*)'])
            ->from($this->cacheTable)
            ->where('[[id]] = :id AND ([[expire]] = 0 OR [[expire]] >' . time() . ')', [':id' => $key]);
        if ($this->db->enableQueryCache) {
            // temporarily disable and re-enable query caching
            // 临时禁用查询缓存，避免缓存的查询数据影响查询结果。
            $this->db->enableQueryCache = false;
            // 执行Sql语句
            $result = $query->createCommand($this->db)->queryScalar();
            // 重新启用查询缓存
            $this->db->enableQueryCache = true;
        } else {
            // 执行Sql语句
            $result = $query->createCommand($this->db)->queryScalar();
        }

        return $result > 0;
    }

    /**
     * 使用指定的键从缓存中检索值
     * 这是在父类中声明的方法的实现
     * Retrieves a value from cache with a specified key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key a unique key identifying the cached value
     * @return string|false the value stored in cache, false if the value is not in the cache or expired.
     */
    protected function getValue($key)
    {
        $query = new Query;
        // 拼接查询Sql语句，查找 id 为 key,过期时间为0 或过期时间大于现在的内容。
        $query->select(['data'])
            ->from($this->cacheTable)
            ->where('[[id]] = :id AND ([[expire]] = 0 OR [[expire]] >' . time() . ')', [':id' => $key]);
        if ($this->db->enableQueryCache) {
            // temporarily disable and re-enable query caching
            // 临时禁用查询缓存，避免缓存的查询数据影响查询结果。
            $this->db->enableQueryCache = false;
            // 执行Sql语句，查询出'data'字段内容
            $result = $query->createCommand($this->db)->queryScalar();
            // 重新启用查询缓存
            $this->db->enableQueryCache = true;

            return $result;
        } else {
            // 执行Sql语句，查询出'data'字段内容
            return $query->createCommand($this->db)->queryScalar();
        }
    }

    /**
     * 使用指定的键从缓存中检索多个值
     * Retrieves multiple values from cache with the specified keys.
     * @param array $keys a list of keys identifying the cached values
     * @return array a list of cached values indexed by the keys
     */
    protected function getValues($keys)
    {
        if (empty($keys)) {
            return [];
        }
        // 拼接查询Sql语句，查找 id in [key1, key2, key3] ,过期时间为0 或过期时间大于现在的内容。
        $query = new Query;
        $query->select(['id', 'data'])
            ->from($this->cacheTable)
            ->where(['id' => $keys])
            ->andWhere('([[expire]] = 0 OR [[expire]] > ' . time() . ')');

        if ($this->db->enableQueryCache) {
            // 临时禁用查询缓存，避免缓存的查询数据影响查询结果。
            $this->db->enableQueryCache = false;
            // 执行Sql语句，查询出所有符合条件的数据
            $rows = $query->createCommand($this->db)->queryAll();
            // 重新启用查询缓存
            $this->db->enableQueryCache = true;
        } else {
            // 执行Sql语句，查询出所有符合条件的数据
            $rows = $query->createCommand($this->db)->queryAll();
        }

        $results = [];
        // 先遍历每一个$key,结果全部填充为false,
        // 再遍历查询出的结果，用查询到的数据的'data'字段的值，覆盖$results[$key]预先设置的 false
        // 这样，没有被覆盖的值依然为false,就是没查到，即缓存中没有与该key对应的值。
        foreach ($keys as $key) {
            $results[$key] = false;
        }
        foreach ($rows as $row) {
            $results[$row['id']] = $row['data'];
        }

        return $results;
    }

    /**
     * 在缓存中存储一个键对应的值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached. Other types (if you have disabled [[serializer]]) cannot be saved.
     * @param integer $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function setValue($key, $value, $duration)
    {
        // 先假设指定的key已经存在缓存中，尝试执行更新缓存数据的Sql语句
        $command = $this->db->createCommand()
            ->update($this->cacheTable, [
                'expire' => $duration > 0 ? $duration + time() : 0,
                'data' => [$value, \PDO::PARAM_LOB],
            ], ['id' => $key]);

        // 若更新数据成功，则说明假设成立，并执行垃圾回收方法
        if ($command->execute()) {
            $this->gc();

            return true;
        } else {
            // 若指定的key不存在与缓存中，则执行添加[[addValue()]]方法添加缓存
            return $this->addValue($key, $value, $duration);
        }
    }

    /**
     * 如果缓存不包含该键，则缓存该键和值。
     * 这是在父类中声明的方法的实现.
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached. Other types (if you have disabled [[serializer]]) cannot be saved.
     * @param integer $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function addValue($key, $value, $duration)
    {
        // 执行垃圾回收
        $this->gc();

        try {
            // 执行插入命令，缓存键和值
            $this->db->createCommand()
                ->insert($this->cacheTable, [
                    'id' => $key,
                    'expire' => $duration > 0 ? $duration + time() : 0,
                    'data' => [$value, \PDO::PARAM_LOB],
                ])->execute();

            return true;
        } catch (\Exception $e) {
            return false;
        }
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
        $this->db->createCommand()
            ->delete($this->cacheTable, ['id' => $key])
            ->execute();

        return true;
    }

    /**
     * 删除过期的数据值.
     * 是否强制执行垃圾收集而不考虑垃圾回收执行概率[[gcProbability]].
     * Removes the expired data values.
     * @param boolean $force whether to enforce the garbage collection regardless of [[gcProbability]].
     * Defaults to false, meaning the actual deletion happens with the probability as specified by [[gcProbability]].
     */
    public function gc($force = false)
    {
        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            // 执行Sql语句，删除过期的缓存数据
            $this->db->createCommand()
                ->delete($this->cacheTable, '[[expire]] > 0 AND [[expire]] < ' . time())
                ->execute();
        }
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
        $this->db->createCommand()
            ->delete($this->cacheTable)
            ->execute();

        return true;
    }
}
