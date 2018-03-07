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
use yii\di\Instance;

/**
 * DbDependency表示基于SQL语句的查询结果的依赖关系
 * DbDependency represents a dependency based on the query result of a SQL statement.
 *
 * 如果指定 SQL 语句的查询结果发生了变化，则依赖改变。
 * If the query result changes, the dependency is considered as changed.
 * The query is specified via the [[sql]] property.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DbDependency extends Dependency
{
    /**
     * DB连接的应用程序组件ID
     * @var string the application component ID of the DB connection.
     */
    public $db = 'db';
    /**
     * 用于确定依赖项是否已被更改的SQL查询语句。
     * 查询结果只有第一行会被使用。
     * @var string the SQL query whose result is used to determine if the dependency has been changed.
     * Only the first row of the query result will be used.
     */
    public $sql;
    /**
     * 将绑定到SQL语句的参数(name => value)
     * @var array the parameters (name => value) to be bound to the SQL statement specified by [[sql]].
     */
    public $params = [];


    /**
     * 生成所需的数据，以确定是否已经更改了依赖关系。
     * 该方法返回全局状态的值。
     * Generates the data needed to determine if dependency has been changed.
     * This method returns the value of the global state.
     * @param Cache $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     * @throws InvalidConfigException if [[db]] is not a valid application component ID
     */
    protected function generateDependencyData($cache)
    {
        // 获取数据库应用组件实例
        /** @var Connection $db */
        $db = Instance::ensure($this->db, Connection::className());
        if ($this->sql === null) {
            // sql语句不能为空
            throw new InvalidConfigException('DbDependency::sql must be set.');
        }

        if ($db->enableQueryCache) {
            // temporarily disable and re-enable query caching
            // 临时禁用查询缓存，避免缓存的查询数据影响查询结果。
            $db->enableQueryCache = false;
            // 获取sql查询结果的第一行。
            $result = $db->createCommand($this->sql, $this->params)->queryOne();
            // 重新启用查询缓存
            $db->enableQueryCache = true;
        } else {
            // 获取sql查询结果的第一行。
            $result = $db->createCommand($this->sql, $this->params)->queryOne();
        }

        return $result;
    }
}
