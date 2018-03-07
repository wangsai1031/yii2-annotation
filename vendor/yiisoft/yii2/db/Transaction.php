<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db;

use Yii;
use yii\base\InvalidConfigException;

/**
 * Transaction represents a DB transaction.
 * Transaction代表一个数据库事务。
 *
 * It is usually created by calling [[Connection::beginTransaction()]].
 * 通常情况下，它是调用[[Connection::beginTransaction()]]的时候产生的。
 *
 * The following code is a typical example of using transactions (note that some
 * DBMS may not support transactions):
 * 下边的代码是事务的经典使用（请注意一些数据库系统可能不支持事务）：
 *
 * ```php
 * $transaction = $connection->beginTransaction();
 * try {
 *     $connection->createCommand($sql1)->execute();
 *     $connection->createCommand($sql2)->execute();
 *     //.... other SQL executions
 *     $transaction->commit();
 * } catch (\Exception $e) {
 *     $transaction->rollBack();
 *     throw $e;
 * } catch (\Throwable $e) {
 *     $transaction->rollBack();
 *     throw $e;
 * }
 * ```
 * 嵌套事务
 *
 * ```
 *  $outerTransaction = $db->beginTransaction();
    try {
        $db->createCommand($sql1)->execute();

        $innerTransaction = $db->beginTransaction();
        try {
            $db->createCommand($sql2)->execute();
            $db->createCommand($sql3)->execute();
            $innerTransaction->commit();
        } catch (Exception $e) {
            $innerTransaction->rollBack();
        }

        $db->createCommand($sql4)->execute();
        $outerTransaction->commit();
    } catch (Exception $e) {
        $outerTransaction->rollBack();
    }
 * ```
 *
 * > Note: in the above code we have two catch-blocks for compatibility
 * > with PHP 5.x and PHP 7.x. `\Exception` implements the [`\Throwable` interface](http://php.net/manual/en/class.throwable.php)
 * > since PHP 7.0, so you can skip the part with `\Exception` if your app uses only PHP 7.0 and higher.
 *
 * @property bool $isActive Whether this transaction is active. Only an active transaction can [[commit()]] or
 * [[rollBack()]]. This property is read-only.
 * 属性 boolean 当前事务是否被激活。只有激活的事务才可以使用[[commit()]]或[[rollBack()]]。该属性只读。
 * 
 * @property string $isolationLevel The transaction isolation level to use for this transaction. This can be
 * one of [[READ_UNCOMMITTED]], [[READ_COMMITTED]], [[REPEATABLE_READ]] and [[SERIALIZABLE]] but also a string
 * containing DBMS specific syntax to be used after `SET TRANSACTION ISOLATION LEVEL`. This property is
 * write-only.
 * 属性 字符串 当前事务使用的事务隔离级别。可以是如下的[[READ_UNCOMMITTED]], [[READ_COMMITTED]], [[REPEATABLE_READ]] and [[SERIALIZABLE]]
 * 之一，但是也可以是一个用在`SET TRANSACTION ISOLATION LEVEL`之后，包含数据库系统特定语法的字符串。该属性只写
 * 
 * @property int $level The current nesting level of the transaction. This property is read-only.
 * 属性 整型 当期事务的嵌套等级。该属性只读。
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Transaction extends \yii\base\BaseObject
{
    /**
     * A constant representing the transaction isolation level `READ UNCOMMITTED`.
     * 表示事务隔离等级READ UNCOMMITTED的常量
     * @see http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
     */
    const READ_UNCOMMITTED = 'READ UNCOMMITTED';
    /**
     * A constant representing the transaction isolation level `READ COMMITTED`.
     * 表示事务隔离等级READ COMMITTED的常量
     * @see http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
     */
    const READ_COMMITTED = 'READ COMMITTED';
    /**
     * A constant representing the transaction isolation level `REPEATABLE READ`.
     * 表示事务隔离等级REPEATABLE READ的常量
     * @see http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
     */
    const REPEATABLE_READ = 'REPEATABLE READ';
    /**
     * A constant representing the transaction isolation level `SERIALIZABLE`.
     * 表示事务隔离等级SERIALIZABLE的常量
     * @see http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
     */
    const SERIALIZABLE = 'SERIALIZABLE';

    /**
     * @var Connection the database connection that this transaction is associated with.
     * 属性 当前事务关联的数据库链接。
     */
    public $db;

    /**
     * @var int the nesting level of the transaction. 0 means the outermost level.
     * 属性 执行 事务的嵌套等级。0意味这最外层等级。
     */
    private $_level = 0;


    /**
     * 有效的事务
     *
     * 必须同时满足3个条件：
     * _level > 0 。这是由于为0是，要么是刚刚初始化， 要么是所有的事务已经提交或回滚了。也就是说，只有调用过了 begin() 但还没有调用过匹配的 commit() 或 rollBack() 的事务对象，才是有效的。
     * 数据库连接要已经初始化。
     * 数据库连接也必须是有效的。
     *
     * Returns a value indicating whether this transaction is active.
     * 返回当前事务是否处于激活状态的值。
     * 
     * @return bool whether this transaction is active. Only an active transaction
     * can [[commit()]] or [[rollBack()]].
     * 返回值 boolean 当前事务是否被激活。只有激活的时候才可以被[[commit()]] 或 [[rollBack()]]
     */
    public function getIsActive()
    {
        return $this->_level > 0 && $this->db && $this->db->isActive;
    }

    /**
     * 启用事务
     *
     * Begins a transaction.
     * 开启一个事务。
     *
     * @param string|null $isolationLevel The [isolation level][] to use for this transaction.
     * 参数 字符串|null 当前事务使用的事务隔离级别。当前事务使用的事务隔离级别。可以是如下的[[READ_UNCOMMITTED]], [[READ_COMMITTED]], [[REPEATABLE_READ]] and [[SERIALIZABLE]]
     *
     * This can be one of [[READ_UNCOMMITTED]], [[READ_COMMITTED]], [[REPEATABLE_READ]] and [[SERIALIZABLE]] but
     * also a string containing DBMS specific syntax to be used after `SET TRANSACTION ISOLATION LEVEL`.
     * If not specified (`null`) the isolation level will not be set explicitly and the DBMS default will be used.
     * 当前事务使用的事务隔离级别。可以是如下的[[READ_UNCOMMITTED]], [[READ_COMMITTED]], [[REPEATABLE_READ]] and [[SERIALIZABLE]]
     * 之一，但是也可以是一个用在`SET TRANSACTION ISOLATION LEVEL`之后，包含数据库系统特定语法的字符串。如果值为null，就不会指定事务隔离级别。
     * 就会使用数据库系统默认的。
     *
     * > Note: This setting does not work for PostgreSQL, where setting the isolation level before the transaction
     * has no effect. You have to call [[setIsolationLevel()]] in this case after the transaction has started.
     * > 请注意：该设置对于PostgreSQL无效，在事务之前设置事务隔离级别没有效果。在这样的情况下，你必须在事务开启之后调用[[setIsolationLevel()]]
     *
     * > Note: Some DBMS allow setting of the isolation level only for the whole connection so subsequent transactions
     * may get the same isolation level even if you did not specify any. When using this feature
     * you may need to set the isolation level for all transactions explicitly to avoid conflicting settings.
     * At the time of this writing affected DBMS are MSSQL and SQLite.
     * > 请注意：有些数据库系统允许事务隔离级别的设置在整个链接中生效，所以随后的事务也可能得到相同的隔离级别，就算你没有明确指定。当使用该特性的时候，
     * 你需要为所有的事务都指定隔离级别，以避免设置冲突。当前受影响的数据库系统是MSSQL 和SQLite
     *
     * [isolation level]: http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
     * @throws InvalidConfigException if [[db]] is `null`.
     * 当 [[db]] 为null的时候抛出不合法的配置异常。
     */
    public function begin($isolationLevel = null)
    {
        // 没有初始化数据库连接的滚粗
        if ($this->db === null) {
            throw new InvalidConfigException('Transaction::db must be set.');
        }
        $this->db->open();

        // _level 为0 表示的是最外层的事务
        if ($this->_level === 0) {
            // 如果给定了隔离级别，那么就设定之
            if ($isolationLevel !== null) {
                // 设定事务隔离级别
                $this->db->getSchema()->setTransactionIsolationLevel($isolationLevel);
            }
            Yii::debug('Begin transaction' . ($isolationLevel ? ' with isolation level ' . $isolationLevel : ''), __METHOD__);

            // 触发 EVENT_BEGIN_TRANSACTION 事件
            $this->db->trigger(Connection::EVENT_BEGIN_TRANSACTION);
            $this->db->pdo->beginTransaction();
            $this->_level = 1;

            return;
        }

        // 以下 _level>0 表示的是嵌套的事务
        $schema = $this->db->getSchema();

        // 要使用嵌套事务，前提是所使用的数据库要支持
        if ($schema->supportsSavepoint()) {
            Yii::debug('Set savepoint ' . $this->_level, __METHOD__);
            // 使用事务保存点
            $schema->createSavepoint('LEVEL' . $this->_level);
        } else {
            Yii::info('Transaction not started: nested transaction not supported', __METHOD__);
        }

        // 结合 _level == 0 分支中的 $this->_level = 1，
        // 可以得知，一旦调用这个方法， _level 就会自增1
        //对于最外层的事务，即当 _level 为 0 时，最终落到PDO的 beginTransaction() 来启用事务。
        //在启用前，如果开发者给定了隔离级别，那么还需要设定隔离级别。
        //当 _level > 0 时，表示的是嵌套的事务，并非最外层的事务。
        // 对此，Yii使用 SQL 的 SAVEPOINT 和 ROLLBACK TO SAVEPOINT 来实现设置事务保存点和回滚到保存点的操作。
        $this->_level++;
    }

    /**
     * 提交事务
     *
     * Commits a transaction.
     * 提交一个事务。
     *
     * @throws Exception if the transaction is not active
     * 当事务没有激活的时候抛出异常。
     */
    public function commit()
    {
        if (!$this->getIsActive()) {
            throw new Exception('Failed to commit transaction: transaction was inactive.');
        }

        // 与begin()对应，只要调用  commit()，_level 自减1
        $this->_level--;
        // 如果回到了最外层事务，那么应当使用PDO的commit
        if ($this->_level === 0) {
            Yii::debug('Commit transaction', __METHOD__);
            $this->db->pdo->commit();
            $this->db->trigger(Connection::EVENT_COMMIT_TRANSACTION);
            return;
        }

        // 以下是尚未回到最外层的情形
        $schema = $this->db->getSchema();
        if ($schema->supportsSavepoint()) {
            Yii::debug('Release savepoint ' . $this->_level, __METHOD__);
            // 释放那么保存点
            $schema->releaseSavepoint('LEVEL' . $this->_level);
        } else {
            Yii::info('Transaction not committed: nested transaction not supported', __METHOD__);
        }
    }

    /**
     * Rolls back a transaction.
     * 回滚一个事务。
     *
     * @throws Exception if the transaction is not active
     * 当事务没有开启的时候，抛出异常。
     */
    public function rollBack()
    {
        if (!$this->getIsActive()) {
            // do nothing if transaction is not active: this could be the transaction is committed
            // but the event handler to "commitTransaction" throw an exception
            // 如果事务没有开始，什么也不做：可能是事务已经提交但是"commitTransaction"事件处理程序抛出一个异常
            return;
        }

        // 调用 rollBack() 也会使 _level 自减1
        $this->_level--;

        // 如果已经返回到最外层，那么调用  PDO 的 rollBack
        if ($this->_level === 0) {
            Yii::debug('Roll back transaction', __METHOD__);
            $this->db->pdo->rollBack();
            $this->db->trigger(Connection::EVENT_ROLLBACK_TRANSACTION);
            return;
        }

        // 以下是未返回到最外层的情形
        $schema = $this->db->getSchema();
        if ($schema->supportsSavepoint()) {
            Yii::debug('Roll back to savepoint ' . $this->_level, __METHOD__);
            // 那么就回滚到保存点
            $schema->rollBackSavepoint('LEVEL' . $this->_level);
        } else {
            Yii::info('Transaction not rolled back: nested transaction not supported', __METHOD__);
            // throw an exception to fail the outer transaction
            // 抛出异常，终止外层事务。
            throw new Exception('Roll back failed: nested transaction not supported.');
        }
    }

    /**
     * Sets the transaction isolation level for this transaction.
     * 为当前的事务设置事务隔离界别。
     *
     * This method can be used to set the isolation level while the transaction is already active.
     * 就算当前事务已经激活，该方法也可以用来给此事务设置隔离级别。
     *
     * However this is not supported by all DBMS so you might rather specify the isolation level directly
     * when calling [[begin()]].
     * 但是不是所有的数据库系统都支持这样做。所以你最好在调用begin之前直接指定隔离级别。
     *
     * @param string $level The transaction isolation level to use for this transaction.
     * This can be one of [[READ_UNCOMMITTED]], [[READ_COMMITTED]], [[REPEATABLE_READ]] and [[SERIALIZABLE]] but
     * also a string containing DBMS specific syntax to be used after `SET TRANSACTION ISOLATION LEVEL`.
     * 参数 字符串 当前事务的隔离级别。可以是如下的字符串[[READ_UNCOMMITTED]], [[READ_COMMITTED]], [[REPEATABLE_READ]] and [[SERIALIZABLE]]，
     * 但是也可以是一个用在`SET TRANSACTION ISOLATION LEVEL`之后，包含数据库系统特定语法的字符串。
     *
     * @throws Exception if the transaction is not active
     * 当事务没有激活的时候抛出异常。
     *
     * @see http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
     */
    public function setIsolationLevel($level)
    {
        if (!$this->getIsActive()) {
            throw new Exception('Failed to set isolation level: transaction was inactive.');
        }
        Yii::debug('Setting transaction isolation level to ' . $level, __METHOD__);
        $this->db->getSchema()->setTransactionIsolationLevel($level);
    }

    /**
     * @return int The current nesting level of the transaction.
     * 返回值 整型 当前事务的嵌套等级
     * @since 2.0.8
     */
    public function getLevel()
    {
        return $this->_level;
    }
}
