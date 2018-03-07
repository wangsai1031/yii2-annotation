<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db;

use PDO;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\caching\CacheInterface;

/**
 * Connection 表示通过[PDO]连接到数据库的连接
 * Connection represents a connection to a database via [PDO](http://php.net/manual/en/book.pdo.php).
 *
 * Connection与[[Command]], [[DataReader]]和事务[[Transaction]]一起工作，以便在一组通用的api中为各种不同的DBMS提供数据访问。
 * 它们是对 PHP PDO 扩展的一个简单的包装。
 * Connection works together with [[Command]], [[DataReader]] and [[Transaction]]
 * to provide data access to various DBMS in a common set of APIs. They are a thin wrapper
 * of the [PDO PHP extension](http://php.net/manual/en/book.pdo.php).
 *
 * 连接支持数据库复制和读写分离。
 * 特别是，一个连接组件可以配置多个[[masters]] and [[slaves]]。
 * 它将通过选择适当的服务器来实现负载平衡和故障转移。
 * 它还会自动将读操作功能直接分配给从数据库，并将写操作功能分配给主数据库。
 * Connection supports database replication and read-write splitting. In particular, a Connection component
 * can be configured with multiple [[masters]] and [[slaves]]. It will do load balancing and failover by choosing
 * appropriate servers. It will also automatically direct read operations to the slaves and write operations to
 * the masters.
 *
 * 要建立一个DB连接，需要设置[[dsn]], [[username]] and [[password]]，然后调用[[open()]]连接到数据库服务器。
 * 可以使用[[$isActive]]来检查连接的当前状态
 * To establish a DB connection, set [[dsn]], [[username]] and [[password]], and then
 * call [[open()]] to connect to the database server. The current state of the connection can be checked using [[$isActive]].
 *
 * 下面的例子展示了如何创建Connection实例并建立数据库连接:
 * The following example shows how to create a Connection instance and establish
 * the DB connection:
 *
 * ```php
 * $connection = new \yii\db\Connection([
 *     'dsn' => $dsn,
 *     'username' => $username,
 *     'password' => $password,
 * ]);
 * $connection->open();
 * ```
 *
 * 在建立了DB连接之后，可以执行如下的SQL语句
 * After the DB connection is established, one can execute SQL statements like the following:
 *
 * ```php
 * $command = $connection->createCommand('SELECT * FROM post');
 * $posts = $command->queryAll();
 * $command = $connection->createCommand('UPDATE post SET status=1');
 * $command->execute();
 * ```
 *
 * 还可以执行预处理Sql语句，并绑定参数。
 * 当参数来自用户输入时，您应该使用这种方法来防止SQL注入攻击。
 * One can also do prepared SQL execution and bind parameters to the prepared SQL.
 * When the parameters are coming from user input, you should use this approach
 * to prevent SQL injection attacks. The following is an example:
 *
 * ```php
 * $command = $connection->createCommand('SELECT * FROM post WHERE id=:id');
 * $command->bindValue(':id', $_GET['id']);
 * $post = $command->query();
 * ```
 *
 * 有关如何执行各种DB查询的更多信息，请参见[[Command]]
 * For more information about how to perform various DB queries, please refer to [[Command]].
 *
 * If the underlying DBMS supports transactions, you can perform transactional SQL queries
 * like the following:
 * 如果底层的DBMS支持事务，那么您可以执行如下的事务SQL查询
 *
 * ```php
 * $transaction = $connection->beginTransaction();
 * try {
 *     $connection->createCommand($sql1)->execute();
 *     $connection->createCommand($sql2)->execute();
 *     // ... executing other SQL statements ...
 *     $transaction->commit();
 * } catch (Exception $e) {
 *     $transaction->rollBack();
 * }
 * ```
 *
 * 您也可以像下面这样使用事务快捷方式。
 * You also can use shortcut for the above like the following:
 *
 * ```php
 * $connection->transaction(function () {
 *     $order = new Order($customer);
 *     $order->save();
 *     $order->addItems($items);
 * });
 * ```
 *
 * ：如果需要，可以将事务隔离级别作为第二个参数传递
 * If needed you can pass transaction isolation level as a second parameter:
 *
 * ```php
 * $connection->transaction(function (Connection $db) {
 *     //return $db->...
 * }, Transaction::READ_UNCOMMITTED);
 * ```
 *
 * Connection通常用作应用程序组件，应用程序中配置如下
 * Connection is often used as an application component and configured in the application
 * configuration like the following:
 *
 * ```php
 * 'components' => [
 *     'db' => [
 *         'class' => '\yii\db\Connection',
 *         'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
 *         'username' => 'root',
 *         'password' => '',
 *         'charset' => 'utf8',
 *     ],
 * ],
 * ```
 *
 * DB驱动程序的名称
 * @property string $driverName Name of the DB driver.
 *
 * 是否建立了DB连接。这个属性是只读的。
 * @property bool $isActive Whether the DB connection is established. This property is read-only.
 *
 * 插入的最后一行的行ID，或从序列对象检索的最后一个值。这个属性是只读的
 * @property string $lastInsertID The row ID of the last row inserted, or the last value retrieved from the
 * sequence object. This property is read-only.
 *
 * @property Connection $master The currently active master connection. `null` is returned if there is no
 * master available. This property is read-only.
 * 
 * 当前活动主连接的PDO实例。这个属性是只读的。
 * @property PDO $masterPdo The PDO instance for the currently active master connection. This property is
 * read-only.
 * 
 * 用于当前DB连接的查询构建器。这个属性是只读的
 * @property QueryBuilder $queryBuilder The query builder for the current DB connection. Note that the type of
 * this property differs in getter and setter. See [[getQueryBuilder()]] and [[setQueryBuilder()]] for details.
 * 
 * 该连接打开的数据库的模式信息。这个属性是只读的
 * @property Schema $schema The schema information for the database opened by this connection. This property
 * is read-only.
 * 
 * 当前活跃的从数据库连接。如果没有可用的从数据库，并且`$fallbackToMaster`值为false时，返回Null。这个属性是只读的。
 * @property string $serverVersion Server version as a string. This property is read-only.
 * 
 * 当前活跃的从数据库连接实例。如果没有可用的从数据库，并且`$fallbackToMaster`值为false时，返回Null。这个属性是只读的。
 * @property Connection $slave The currently active slave connection. `null` is returned if there is no slave
 * available and `$fallbackToMaster` is false. This property is read-only.
 * 
 * 当前活跃的从数据库连接实例。如果没有可用的从数据库，并且`$fallbackToMaster`值为false时，返回Null。这个属性是只读的。
 * @property PDO $slavePdo The PDO instance for the currently active slave connection. `null` is returned if
 * no slave connection is available and `$fallbackToMaster` is false. This property is read-only.
 * @property Transaction $transaction The currently active transaction. Null if no active transaction. This
 * property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Connection extends Component
{
    /**
     * 在建立数据库连接后触发的事件
     *
     * 有时你可能想要在建立起数据库连接时立即执行一些语句来初始化一些环境变量 (比如设置时区或者字符集),
     * 你可以通过为数据库连接的 afterOpen 事件注册一个事件处理器来达到目的。
     * 你可以像这样直接在应用配置中注册处理器：

        'db' => [
            // ...
            'on afterOpen' => function($event) {
                // $event->sender refers to the DB connection
                $event->sender->createCommand("SET time_zone = 'UTC'")->execute();
            }
        ],
     * @event Event an event that is triggered after a DB connection is established
     */
    const EVENT_AFTER_OPEN = 'afterOpen';
    /**
     * 在顶级事务开始之前触发的事件
     * @event Event an event that is triggered right before a top-level transaction is started
     */
    const EVENT_BEGIN_TRANSACTION = 'beginTransaction';
    /**
     * 在顶级事务提交之后触发的事件
     * @event Event an event that is triggered right after a top-level transaction is committed
     */
    const EVENT_COMMIT_TRANSACTION = 'commitTransaction';
    /**
     * 在顶级事务回滚之后触发的事件
     * @event Event an event that is triggered right after a top-level transaction is rolled back
     */
    const EVENT_ROLLBACK_TRANSACTION = 'rollbackTransaction';

    /**
     * 数据源名称，即DSN，包含连接到数据库所需的信息。
     * 关于DSN字符串的格式，请参考[PHP manual](http://www.php.net/manual/en/function.PDO-construct.php)
     * @var string the Data Source Name, or DSN, contains the information required to connect to the database.
     * Please refer to the [PHP manual](http://php.net/manual/en/pdo.construct.php) on
     * the format of the DSN string.
     *
     * 对于 [SQLite] 您可以使用路径别名来指定数据库路径。e.g. `sqlite:@app/data/db.sql`
     * For [SQLite](http://php.net/manual/en/ref.pdo-sqlite.connection.php) you may use a [path alias](guide:concept-aliases)
     * for specifying the database path, e.g. `sqlite:@app/data/db.sql`.
     *
     * @see charset
     */
    public $dsn;
    /**
     * 建立数据库连接的用户名。默认值为null，表示不使用用户名。
     * @var string the username for establishing DB connection. Defaults to `null` meaning no username to use.
     */
    public $username;
    /**
     * 建立数据库连接的密码。默认值为null，表示不使用密码。
     * @var string the password for establishing DB connection. Defaults to `null` meaning no password to use.
     */
    public $password;
    /**
     * 在调用[[open()]]建立一个DB连接时设置的PDO属性 (name => value)。
     * 有关可用属性的详细信息，参考[PHP manual](http://www.php.net/manual/en/function.PDO-setAttribute.php)
     *
     * @var array PDO attributes (name => value) that should be set when calling [[open()]]
     * to establish a DB connection. Please refer to the
     * [PHP manual](http://php.net/manual/en/pdo.setattribute.php) for
     * details about available attributes.
     */
    public $attributes;
    /**
     * 与这个DB连接相关联的PHP PDO实例。
     * 该属性主要由open()和close()方法管理。
     * 当一个DB连接活跃时，这个属性将代表一个PDO实例，否则，它将是null。
     * @var PDO the PHP PDO instance associated with this DB connection.
     * This property is mainly managed by [[open()]] and [[close()]] methods.
     * When a DB connection is active, this property will represent a PDO instance;
     * otherwise, it will be null.
     * @see pdoClass
     */
    public $pdo;
    /**
     * 是否启用模式缓存.
     * 请注意，为了真正地启用模式缓存，必须启用[[schemaCache]]指定有效缓存组件，并且[[enableSchemaCache]]必须设置为true
     *
     * @var bool whether to enable schema caching.
     * Note that in order to enable truly schema caching, a valid cache component as specified
     * by [[schemaCache]] must be enabled and [[enableSchemaCache]] must be set true.
     * @see schemaCacheDuration
     * @see schemaCacheExclude
     * @see schemaCache
     */
    public $enableSchemaCache = false;
    /**
     * 表元数据在缓存中保持有效的秒数。
     * 使用0表示缓存的数据永远不会过期。
     *
     * @var int number of seconds that table metadata can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     * @see enableSchemaCache
     */
    public $schemaCacheDuration = 3600;
    /**
     * 不应该缓存元数据的表的列表，默认是空数组。
     * 如果有的话，表名可能包含模式前缀。不要引用表名。
     * @var array list of tables whose metadata should NOT be cached. Defaults to empty array.
     * The table names may contain schema prefix, if any. Do not quote the table names.
     * @see enableSchemaCache
     */
    public $schemaCacheExclude = [];
    /**
     * 缓存对象或缓存应用程序组件的ID，用于缓存表元数据。
     * 
     * @var CacheInterface|string the cache object or the ID of the cache application component that
     * is used to cache the table metadata.
     * @see enableSchemaCache
     */
    public $schemaCache = 'cache';
    /**
     * 是否启用查询缓存。
     * 请注意，为了真正地启用模式缓存，必须启用[[schemaCache]]指定有效缓存组件，并且[[enableSchemaCache]]必须设置为true。
     * 而且，只有[[cache()]]内包含的查询的结果会被缓存。
     * @var bool whether to enable query caching.
     * Note that in order to enable query caching, a valid cache component as specified
     * by [[queryCache]] must be enabled and [[enableQueryCache]] must be set true.
     * Also, only the results of the queries enclosed within [[cache()]] will be cached.
     * @see queryCache
     * @see cache()
     * @see noCache()
     */
    public $enableQueryCache = true;
    /**
     * 查询结果可以在缓存中保持有效的默认秒数。
     * 默认为3600，即3600秒，或1小时。使用0表示缓存的数据永远不会过期。
     * @var int the default number of seconds that query results can remain valid in cache.
     * Defaults to 3600, meaning 3600 seconds, or one hour. Use 0 to indicate that the cached data will never expire.
     * The value of this property will be used when [[cache()]] is called without a cache duration.
     * @see enableQueryCache
     * @see cache()
     */
    public $queryCacheDuration = 3600;
    /**
     * 用于查询缓存的 缓存对象或缓存应用程序组件的ID。
     * @var CacheInterface|string the cache object or the ID of the cache application component
     * that is used for query caching.
     * @see enableQueryCache
     */
    public $queryCache = 'cache';
    /**
     * 用于数据库连接的字符集。
     * 该属性只用于MySQL、PostgreSQL和CUBRID数据库。
     * 默认值为null，这意味着使用数据库配置的默认字符集。
     * @var string the charset used for database connection. The property is only used
     * for MySQL, PostgreSQL and CUBRID databases. Defaults to null, meaning using default charset
     * as configured by the database.
     *
     * 对于Oracle数据库，必须在dsn中指定字符集，例如通过追加 `;charset=UTF-8` 到dsn字符串来设置UTF-8字符集。
     * For Oracle Database, the charset must be specified in the [[dsn]], for example for UTF-8 by appending `;charset=UTF-8`
     * to the DSN string.
     *
     * 如果您使用的是MySQL 的 GBK 或 BIG5 字符集，那么建议通过[[dsn]]指定字符集，比如`'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'`。
     *
     * The same applies for if you're using GBK or BIG5 charset with MySQL, then it's highly recommended to
     * specify charset via [[dsn]] like `'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'`.
     */
    public $charset;
    /**
     * 是否要开启预处理模拟。
     * 默认为false，这意味着PDO将使用本机预处理支持。
     * 对于某些数据库(如MySQL)，这可能需要设置为true，以便PDO可以模拟预处理支持，以绕过本机预处理支持的bug。
     * 默认值为null，这意味着 PDO 的 ATTR_EMULATE_PREPARES 将不会被更改。
     * @var bool whether to turn on prepare emulation. Defaults to false, meaning PDO
     * will use the native prepare support if available. For some databases (such as MySQL),
     * this may need to be set true so that PDO can emulate the prepare support to bypass
     * the buggy native prepare support.
     * The default value is null, which means the PDO ATTR_EMULATE_PREPARES value will not be changed.
     */
    public $emulatePrepare;
    /**
     * 表名的通用前缀或后缀。
     * 如果将表名指定为`{{%TableName}}`，那么 `%` 字符将被该属性值替换。
     * 例如，`{{%post}}` 变为 `{{tbl_post}}`
     * @var string the common prefix or suffix for table names. If a table name is given
     * as `{{%TableName}}`, then the percentage character `%` will be replaced with this
     * property value. For example, `{{%post}}` becomes `{{tbl_post}}`.
     */
    public $tablePrefix = '';
    /**
     * PDO驱动程序名和模式类之间的映射。
     * 数组的键是PDO驱动程序名，而值对应的模式类名或配置。
     * 有关如何指定配置的详细信息，请参考[[Yii::createObject()]]。
     * @var array mapping between PDO driver names and [[Schema]] classes.
     * The keys of the array are PDO driver names while the values are either the corresponding
     * schema class names or configurations. Please refer to [[Yii::createObject()]] for
     * details on how to specify a configuration.
     *
     * 该属性主要用于[[getSchema()]] 获取数据库模式信息。
     * 通常不需要设置此属性，除非您想使用自己的模式类来支持由Yii不支持的DBMS。
     * This property is mainly used by [[getSchema()]] when fetching the database schema information.
     * You normally do not need to set this property unless you want to use your own
     * [[Schema]] class to support DBMS that is not supported by Yii.
     */
    public $schemaMap = [
        'pgsql' => 'yii\db\pgsql\Schema', // PostgreSQL
        'mysqli' => 'yii\db\mysql\Schema', // MySQL
        'mysql' => 'yii\db\mysql\Schema', // MySQL
        'sqlite' => 'yii\db\sqlite\Schema', // sqlite 3
        'sqlite2' => 'yii\db\sqlite\Schema', // sqlite 2
        'sqlsrv' => 'yii\db\mssql\Schema', // newer MSSQL driver on MS Windows hosts
        'oci' => 'yii\db\oci\Schema', // Oracle driver
        'mssql' => 'yii\db\mssql\Schema', // older MSSQL driver on MS Windows hosts
        'dblib' => 'yii\db\mssql\Schema', // dblib drivers on GNU/Linux (and maybe other OSes) hosts
        'cubrid' => 'yii\db\cubrid\Schema', // CUBRID
    ];
    /**
     * 定制的PDO包装类。如果没有设置，它将在使用 MSSQL 时使用[[PDO]] 或 [[yii\db\mssql\PDO]]。
     * @var string Custom PDO wrapper class. If not set, it will use [[PDO]] or [[\yii\db\mssql\PDO]] when MSSQL is used.
     * @see pdo
     */
    public $pdoClass;
    /**
     * 用于创建新的 数据库 [[Command]] 对象的类。
     * 如果您想扩展[[Command]]类，您可以配置该属性以使用您的扩展版本的类。
     * @var string the class used to create new database [[Command]] objects. If you want to extend the [[Command]] class,
     * you may configure this property to use your extended version of the class.
     * Since version 2.0.14 [[$commandMap]] is used if this property is set to its default value.
     * @see createCommand
     * @since 2.0.7
     * @deprecated since 2.0.14. Use [[$commandMap]] for precise configuration.
     */
    public $commandClass = 'yii\db\Command';
    /**
     * @var array mapping between PDO driver names and [[Command]] classes.
     * The keys of the array are PDO driver names while the values are either the corresponding
     * command class names or configurations. Please refer to [[Yii::createObject()]] for
     * details on how to specify a configuration.
     *
     * This property is mainly used by [[createCommand()]] to create new database [[Command]] objects.
     * You normally do not need to set this property unless you want to use your own
     * [[Command]] class or support DBMS that is not supported by Yii.
     * @since 2.0.14
     */
    public $commandMap = [
        'pgsql' => 'yii\db\Command', // PostgreSQL
        'mysqli' => 'yii\db\Command', // MySQL
        'mysql' => 'yii\db\Command', // MySQL
        'sqlite' => 'yii\db\sqlite\Command', // sqlite 3
        'sqlite2' => 'yii\db\sqlite\Command', // sqlite 2
        'sqlsrv' => 'yii\db\Command', // newer MSSQL driver on MS Windows hosts
        'oci' => 'yii\db\Command', // Oracle driver
        'mssql' => 'yii\db\Command', // older MSSQL driver on MS Windows hosts
        'dblib' => 'yii\db\Command', // dblib drivers on GNU/Linux (and maybe other OSes) hosts
        'cubrid' => 'yii\db\Command', // CUBRID
    ];
    /**
     * 是否启用[savepoint|保存点]。
     * 注意，如果底层的DBMS不支持保存点，那么将该属性设置为true将不会有任何效果。
     * @var bool whether to enable [savepoint](http://en.wikipedia.org/wiki/Savepoint).
     * Note that if the underlying DBMS does not support savepoint, setting this property to be true will have no effect.
     */
    public $enableSavepoint = true;
    /**
     * 缓存对象或缓存应用程序组件的ID，用于存储在主数据库[[masters]]和从数据库[[slaves]]中指定的DB服务器的健康状态。
     * 只有当启用 读/写分离 或者 [[masters]] 不是空的时候才使用它。
     * @var CacheInterface|string the cache object or the ID of the cache application component that is used to store
     * the health status of the DB servers specified in [[masters]] and [[slaves]].
     * This is used only when read/write splitting is enabled or [[masters]] is not empty.
     */
    public $serverStatusCache = 'cache';
    /**
     * 在主数据库[[masters]]和从数据库[[slaves]]中的 死服务器列表的 重试时间间隔秒数。
     * 这与$serverStatusCache一起使用。
     * @var int the retry interval in seconds for dead servers listed in [[masters]] and [[slaves]].
     * This is used together with [[serverStatusCache]].
     */
    public $serverRetryInterval = 600;
    /**
     * 是否通过 使用从数据库[[slaves]]读取数据来 启用读/写分离
     * @var bool whether to enable read/write splitting by using [[slaves]] to read data.
     * Note that if [[slaves]] is empty, read/write splitting will NOT be enabled no matter what value this property takes.
     */
    public $enableSlaves = true;
    /**
     * 从数据库连接配置列表。每个配置都用于创建一个从数据库连接。
     * 当启用[[enableSlaves]]时，将选择其中一个配置，并使用它创建一个DB连接来执行读取查询。
     * @var array list of slave connection configurations. Each configuration is used to create a slave DB connection.
     * When [[enableSlaves]] is true, one of these configurations will be chosen and used to create a DB connection
     * for performing read queries only.
     * @see enableSlaves
     * @see slaveConfig
     */
    public $slaves = [];
    /**
     * 所有从数据库的公共配置属性。将会与每个从数据库的配置数组合并。
     * @var array the configuration that should be merged with every slave configuration listed in [[slaves]].
     * For example,
     *
     * ```php
     * [
     *     'username' => 'slave',
     *     'password' => 'slave',
     *     'attributes' => [
     *         // use a smaller connection timeout
     *         PDO::ATTR_TIMEOUT => 10,
     *     ],
     * ]
     * ```
     */
    public $slaveConfig = [];
    /**
     * 主数据库连接的配置列表。每个配置都用于创建一个主数据库连接。
     * 当调用 [[open()]] 时，将选择其中一个配置，并使用它来创建一个DB连接，该连接将被该对象使用。
     * 注意，当该属性不是空的时，这个对象的连接设置(e.g. "dsn", "username")将被忽略。
     * @var array list of master connection configurations. Each configuration is used to create a master DB connection.
     * When [[open()]] is called, one of these configurations will be chosen and used to create a DB connection
     * which will be used by this object.
     * Note that when this property is not empty, the connection setting (e.g. "dsn", "username") of this object will
     * be ignored.
     * @see masterConfig
     * @see shuffleMasters
     */
    public $masters = [];
    /**
     * 所有主数据库的公共配置属性。将会与每个主数据库的配置数组合并。
     * @var array the configuration that should be merged with every master configuration listed in [[masters]].
     * For example,
     *
     * ```php
     * [
     *     'username' => 'master',
     *     'password' => 'master',
     *     'attributes' => [
     *         // use a smaller connection timeout
     *         PDO::ATTR_TIMEOUT => 10,
     *     ],
     * ]
     * ```
     */
    public $masterConfig = [];
    /**
     * @var bool whether to shuffle [[masters]] before getting one.
     * @since 2.0.11
     * @see masters
     */
    public $shuffleMasters = true;
    /**
     * @var bool whether to enable logging of database queries. Defaults to true.
     * You may want to disable this option in a production environment to gain performance
     * if you do not need the information being logged.
     * @since 2.0.12
     * @see enableProfiling
     */
    public $enableLogging = true;
    /**
     * @var bool whether to enable profiling of opening database connection and database queries. Defaults to true.
     * You may want to disable this option in a production environment to gain performance
     * if you do not need the information being logged.
     * @since 2.0.12
     * @see enableLogging
     */
    public $enableProfiling = true;

    /**
     * 保存当前连接的有效Transaction对象
     *
     * @var Transaction the currently active transaction
     */
    private $_transaction;
    /**
     * 数据库的模式
     * @var Schema the database schema
     */
    private $_schema;
    /**
     * @var string driver name
     */
    private $_driverName;
    /**
     * @var Connection|false the currently active master connection
     */
    private $_master = false;
    /**
     * 当前活动的从数据库连接
     * @var Connection|false the currently active slave connection
     */
    private $_slave = false;
    /**
     * [[cache()]]调用的查询缓存参数
     * @var array query cache parameters for the [[cache()]] calls
     */
    private $_queryCacheInfo = [];


    /**
     * 返回一个值，指示是否建立了DB连接
     * Returns a value indicating whether the DB connection is established.
     * @return bool whether the DB connection is established
     */
    public function getIsActive()
    {
        return $this->pdo !== null;
    }

    /**
     * 缓存回调函数中的查询结果。
     * 启用查询缓存时([[enableQueryCache]]为true，而[[queryCache]]引用一个有效的缓存组件)，
     * 在callable中执行的查询将被缓存，如果可用，则其结果将从缓存中获取。
     * Uses query cache for the queries performed with the callable.
     *
     * When query caching is enabled ([[enableQueryCache]] is true and [[queryCache]] refers to a valid cache),
     * queries performed within the callable will be cached and their results will be fetched from cache if available.
     * For example,
     *
     * ```php
     * // The customer will be fetched from cache if available.
     * // If not, the query will be made against DB and cached for use next time.
     * // 如果可用，客户将从缓存中获取。
     * // 如果没有，则将对DB进行查询，并将其缓存在下次使用。
     * $customer = $db->cache(function (Connection $db) {
     *     return $db->createCommand('SELECT * FROM customer WHERE id=1')->queryOne();
     * });
     * ```
     *
     * 注意，查询缓存只对返回结果的查询有意义.
     * 对于执行[[Command::execute()]]的查询，将不使用查询缓存。
     * Note that query cache is only meaningful for queries that return results. For queries performed with
     * [[Command::execute()]], query cache will not be used.
     *
     * @param callable $callable a PHP callable that contains DB queries which will make use of query cache.
     * The signature of the callable is `function (Connection $db)`.
     * @param int $duration the number of seconds that query results can remain valid in the cache. If this is
     * not set, the value of [[queryCacheDuration]] will be used instead.
     * Use 0 to indicate that the cached data will never expire.
     * @param \yii\caching\Dependency $dependency the cache dependency associated with the cached query results.
     * @return mixed the return result of the callable
     * @throws \Exception|\Throwable if there is any exception during query
     * @see enableQueryCache
     * @see queryCache
     * @see noCache()
     */
    public function cache(callable $callable, $duration = null, $dependency = null)
    {
        $this->_queryCacheInfo[] = [$duration === null ? $this->queryCacheDuration : $duration, $dependency];
        try {
            $result = call_user_func($callable, $this);
            array_pop($this->_queryCacheInfo);
            return $result;
        } catch (\Exception $e) {
            array_pop($this->_queryCacheInfo);
            throw $e;
        } catch (\Throwable $e) {
            array_pop($this->_queryCacheInfo);
            throw $e;
        }
    }

    /**
     * Disables query cache temporarily.
     *
     * Queries performed within the callable will not use query cache at all. For example,
     *
     * ```php
     * $db->cache(function (Connection $db) {
     *
     *     // ... queries that use query cache ...
     *
     *     return $db->noCache(function (Connection $db) {
     *         // this query will not use query cache
     *         return $db->createCommand('SELECT * FROM customer WHERE id=1')->queryOne();
     *     });
     * });
     * ```
     *
     * @param callable $callable a PHP callable that contains DB queries which should not use query cache.
     * The signature of the callable is `function (Connection $db)`.
     * @return mixed the return result of the callable
     * @throws \Exception|\Throwable if there is any exception during query
     * @see enableQueryCache
     * @see queryCache
     * @see cache()
     */
    public function noCache(callable $callable)
    {
        $this->_queryCacheInfo[] = false;
        try {
            $result = call_user_func($callable, $this);
            array_pop($this->_queryCacheInfo);
            return $result;
        } catch (\Exception $e) {
            array_pop($this->_queryCacheInfo);
            throw $e;
        } catch (\Throwable $e) {
            array_pop($this->_queryCacheInfo);
            throw $e;
        }
    }

    /**
     * Returns the current query cache information.
     * This method is used internally by [[Command]].
     * @param int $duration the preferred caching duration. If null, it will be ignored.
     * @param \yii\caching\Dependency $dependency the preferred caching dependency. If null, it will be ignored.
     * @return array the current query cache information, or null if query cache is not enabled.
     * @internal
     */
    public function getQueryCacheInfo($duration, $dependency)
    {
        if (!$this->enableQueryCache) {
            return null;
        }

        $info = end($this->_queryCacheInfo);
        if (is_array($info)) {
            if ($duration === null) {
                $duration = $info[0];
            }
            if ($dependency === null) {
                $dependency = $info[1];
            }
        }

        if ($duration === 0 || $duration > 0) {
            if (is_string($this->queryCache) && Yii::$app) {
                $cache = Yii::$app->get($this->queryCache, false);
            } else {
                $cache = $this->queryCache;
            }
            if ($cache instanceof CacheInterface) {
                return [$cache, $duration, $dependency];
            }
        }

        return null;
    }

    /**
     * Establishes a DB connection.
     * It does nothing if a DB connection has already been established.
     * @throws Exception if connection fails
     */
    public function open()
    {
        if ($this->pdo !== null) {
            return;
        }

        if (!empty($this->masters)) {
            $db = $this->getMaster();
            if ($db !== null) {
                $this->pdo = $db->pdo;
                return;
            }

            throw new InvalidConfigException('None of the master DB servers is available.');
        }

        if (empty($this->dsn)) {
            throw new InvalidConfigException('Connection::dsn cannot be empty.');
        }

        $token = 'Opening DB connection: ' . $this->dsn;
        $enableProfiling = $this->enableProfiling;
        try {
            Yii::info($token, __METHOD__);
            if ($enableProfiling) {
                Yii::beginProfile($token, __METHOD__);
            }

            $this->pdo = $this->createPdoInstance();
            $this->initConnection();

            if ($enableProfiling) {
                Yii::endProfile($token, __METHOD__);
            }
        } catch (\PDOException $e) {
            if ($enableProfiling) {
                Yii::endProfile($token, __METHOD__);
            }

            throw new Exception($e->getMessage(), $e->errorInfo, (int) $e->getCode(), $e);
        }
    }

    /**
     * Closes the currently active DB connection.
     * It does nothing if the connection is already closed.
     */
    public function close()
    {
        if ($this->_master) {
            if ($this->pdo === $this->_master->pdo) {
                $this->pdo = null;
            }

            $this->_master->close();
            $this->_master = false;
        }

        if ($this->pdo !== null) {
            Yii::debug('Closing DB connection: ' . $this->dsn, __METHOD__);
            $this->pdo = null;
            $this->_schema = null;
            $this->_transaction = null;
        }

        if ($this->_slave) {
            $this->_slave->close();
            $this->_slave = false;
        }
    }

    /**
     * Creates the PDO instance.
     *
     * 创建PDO实例
     *
     * This method is called by [[open]] to establish a DB connection.
     * The default implementation will create a PHP PDO instance.
     * You may override this method if the default PDO needs to be adapted for certain DBMS.
     * @return PDO the pdo instance
     */
    protected function createPdoInstance()
    {
        $pdoClass = $this->pdoClass;
        if ($pdoClass === null) {
            $pdoClass = 'PDO';
            if ($this->_driverName !== null) {
                $driver = $this->_driverName;
            } elseif (($pos = strpos($this->dsn, ':')) !== false) {
                $driver = strtolower(substr($this->dsn, 0, $pos));
            }
            if (isset($driver)) {
                if ($driver === 'mssql' || $driver === 'dblib') {
                    $pdoClass = 'yii\db\mssql\PDO';
                } elseif ($driver === 'sqlsrv') {
                    $pdoClass = 'yii\db\mssql\SqlsrvPDO';
                }
            }
        }

        $dsn = $this->dsn;
        if (strncmp('sqlite:@', $dsn, 8) === 0) {
            $dsn = 'sqlite:' . Yii::getAlias(substr($dsn, 7));
        }

        return new $pdoClass($dsn, $this->username, $this->password, $this->attributes);
    }

    /**
     * Initializes the DB connection.
     * 初始化数据库连接
     *
     * This method is invoked right after the DB connection is established.
     * 该方法在建立数据库连接之后被调用
     *
     * The default implementation turns on `PDO::ATTR_EMULATE_PREPARES`
     * if [[emulatePrepare]] is true, and sets the database [[charset]] if it is not empty.
     * It then triggers an [[EVENT_AFTER_OPEN]] event.
     */
    protected function initConnection()
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->emulatePrepare !== null && constant('PDO::ATTR_EMULATE_PREPARES')) {
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $this->emulatePrepare);
        }
        if ($this->charset !== null && in_array($this->getDriverName(), ['pgsql', 'mysql', 'mysqli', 'cubrid'], true)) {
            $this->pdo->exec('SET NAMES ' . $this->pdo->quote($this->charset));
        }
        $this->trigger(self::EVENT_AFTER_OPEN);
    }

    /**
     * 为执行sql语句创建一个命令
     * Creates a command for execution.
     * 执行的SQL语句
     * @param string $sql the SQL statement to be executed
     * @param array $params the parameters to be bound to the SQL statement
     * @return Command the DB command
     */
    public function createCommand($sql = null, $params = [])
    {
        $driver = $this->getDriverName();
        $config = ['class' => 'yii\db\Command'];
        if ($this->commandClass !== $config['class']) {
            $config['class'] = $this->commandClass;
        } elseif (isset($this->commandMap[$driver])) {
            $config = !is_array($this->commandMap[$driver]) ? ['class' => $this->commandMap[$driver]] : $this->commandMap[$driver];
        }
        $config['db'] = $this;
        $config['sql'] = $sql;
        /** @var Command $command */
        $command = Yii::createObject($config);
        // 将一个值列表绑定到相应的参数
        return $command->bindValues($params);
    }

    /**
     * 已经缓存有事务对象，且事务对象有效，则返回该事务对象,否则返回null
     *
     * Returns the currently active transaction.
     * @return Transaction the currently active transaction. Null if no active transaction.
     */
    public function getTransaction()
    {
        return $this->_transaction && $this->_transaction->getIsActive() ? $this->_transaction : null;
    }

    /**
     * Starts a transaction.
     * @param string|null $isolationLevel The isolation level to use for this transaction.
     * See [[Transaction::begin()]] for details.
     * @return Transaction the transaction initiated
     */
    public function beginTransaction($isolationLevel = null)
    {
        $this->open();

        // 尚未初始化当前连接使用的Transaction对象，则创建一个
        // 缓存的事务对象有效，则使用缓存中的事务对象
        // 否则创建一个新的事务对象
        if (($transaction = $this->getTransaction()) === null) {
            // 实例化 Transaction类并为 'db' 赋值
            // 这表示的是当前的 Transaction 所依赖的数据库连接。如果未对其进行初始化， 那么将无法正常使用事务。
            $transaction = $this->_transaction = new Transaction(['db' => $this]);
        }

        // 在获取了 Transaction 之后，就可以调用他的 begin() 方法，来启用事务。
        // 必要的情况下，还可以指定事务隔离级别。
        $transaction->begin($isolationLevel);

        return $transaction;
    }

    /**
     * Executes callback provided in a transaction.
     *
     * @param callable $callback a valid PHP callback that performs the job. Accepts connection instance as parameter.
     * @param string|null $isolationLevel The isolation level to use for this transaction.
     * See [[Transaction::begin()]] for details.
     * @throws \Exception|\Throwable if there is any exception during query. In this case the transaction will be rolled back.
     * @return mixed result of callback function
     */
    public function transaction(callable $callback, $isolationLevel = null)
    {
        $transaction = $this->beginTransaction($isolationLevel);
        $level = $transaction->level;

        try {
            $result = call_user_func($callback, $this);
            if ($transaction->isActive && $transaction->level === $level) {
                $transaction->commit();
            }
        } catch (\Exception $e) {
            $this->rollbackTransactionOnLevel($transaction, $level);
            throw $e;
        } catch (\Throwable $e) {
            $this->rollbackTransactionOnLevel($transaction, $level);
            throw $e;
        }

        return $result;
    }

    /**
     * Rolls back given [[Transaction]] object if it's still active and level match.
     * In some cases rollback can fail, so this method is fail safe. Exception thrown
     * from rollback will be caught and just logged with [[\Yii::error()]].
     * @param Transaction $transaction Transaction object given from [[beginTransaction()]].
     * @param int $level Transaction level just after [[beginTransaction()]] call.
     */
    private function rollbackTransactionOnLevel($transaction, $level)
    {
        if ($transaction->isActive && $transaction->level === $level) {
            // https://github.com/yiisoft/yii2/pull/13347
            try {
                $transaction->rollBack();
            } catch (\Exception $e) {
                \Yii::error($e, __METHOD__);
                // hide this exception to be able to continue throwing original exception outside
            }
        }
    }

    /**
     * 返回 由该连接 打开的 数据库的 模式信息
     * Returns the schema information for the database opened by this connection.
     * @return Schema the schema information for the database opened by this connection.
     * @throws NotSupportedException if there is no support for the current driver type
     */
    public function getSchema()
    {
        // 若模式信息对象已经创建，则直接返回
        if ($this->_schema !== null) {
            return $this->_schema;
        }

        // 返回数据库程序的名称。
        $driver = $this->getDriverName();
        // 若PDO驱动程序名和模式类之间的映射数组中存在该数据库
        if (isset($this->schemaMap[$driver])) {
            // 若 $this->schemaMap[$driver] 是数组，则直接作为配置数组使用。
            // 若 $this->schemaMap[$driver] 不是数组，则将其作为 'class' 配置项。
            $config = !is_array($this->schemaMap[$driver]) ? ['class' => $this->schemaMap[$driver]] : $this->schemaMap[$driver];
            $config['db'] = $this;

            // 创建数据库模式实例
            return $this->_schema = Yii::createObject($config);
        }

        throw new NotSupportedException("Connection does not support reading schema information for '$driver' DBMS.");
    }

    /**
     * 为当前的DB连接返回查询构建器
     * Returns the query builder for the current DB connection.
     * @return QueryBuilder the query builder for the current DB connection.
     */
    public function getQueryBuilder()
    {
        return $this->getSchema()->getQueryBuilder();
    }

    /**
     * Can be used to set [[QueryBuilder]] configuration via Connection configuration array.
     *
     * @param array $value the [[QueryBuilder]] properties to be configured.
     * @since 2.0.14
     */
    public function setQueryBuilder($value)
    {
        Yii::configure($this->getQueryBuilder(), $value);
    }

    /**
     * Obtains the schema information for the named table.
     * @param string $name table name.
     * @param bool $refresh whether to reload the table schema even if it is found in the cache.
     * @return TableSchema table schema information. Null if the named table does not exist.
     */
    public function getTableSchema($name, $refresh = false)
    {
        return $this->getSchema()->getTableSchema($name, $refresh);
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @see http://php.net/manual/en/pdo.lastinsertid.php
     */
    public function getLastInsertID($sequenceName = '')
    {
        return $this->getSchema()->getLastInsertID($sequenceName);
    }

    /**
     * Quotes a string value for use in a query.
     * Note that if the parameter is not a string, it will be returned without change.
     * @param string $value string to be quoted
     * @return string the properly quoted string
     * @see http://php.net/manual/en/pdo.quote.php
     */
    public function quoteValue($value)
    {
        return $this->getSchema()->quoteValue($value);
    }

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * If the table name is already quoted or contains special characters including '(', '[[' and '{{',
     * then this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteTableName($name)
    {
        return $this->getSchema()->quoteTableName($name);
    }

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     * If the column name is already quoted or contains special characters including '(', '[[' and '{{',
     * then this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteColumnName($name)
    {
        return $this->getSchema()->quoteColumnName($name);
    }

    /**
     * Processes a SQL statement by quoting table and column names that are enclosed within double brackets.
     * Tokens enclosed within double curly brackets are treated as table names, while
     * tokens enclosed within double square brackets are column names. They will be quoted accordingly.
     * Also, the percentage character "%" at the beginning or ending of a table name will be replaced
     * with [[tablePrefix]].
     * @param string $sql the SQL to be quoted
     * @return string the quoted SQL
     */
    public function quoteSql($sql)
    {
        return preg_replace_callback(
            '/(\\{\\{(%?[\w\-\. ]+%?)\\}\\}|\\[\\[([\w\-\. ]+)\\]\\])/',
            function ($matches) {
                if (isset($matches[3])) {
                    return $this->quoteColumnName($matches[3]);
                }

                return str_replace('%', $this->tablePrefix, $this->quoteTableName($matches[2]));
            },
            $sql
        );
    }

    /**
     * 返回数据库程序的名称。
     * 基于当前的[[dsn]],以防目标用户没有将它设置明确。
     * Returns the name of the DB driver. Based on the the current [[dsn]], in case it was not set explicitly
     * by an end user.
     * @return string name of the DB driver
     */
    public function getDriverName()
    {
        if ($this->_driverName === null) {
            /**
             * dsn 示例
             *
             *  MySQL, MariaDB: mysql:host=localhost;dbname=mydatabase
                SQLite: sqlite:/path/to/database/file
                PostgreSQL: pgsql:host=localhost;port=5432;dbname=mydatabase
                CUBRID: cubrid:dbname=demodb;host=localhost;port=33000
                MS SQL Server (via sqlsrv driver): sqlsrv:Server=localhost;Database=mydatabase
                MS SQL Server (via dblib driver): dblib:host=localhost;dbname=mydatabase
                MS SQL Server (via mssql driver): mssql:host=localhost;dbname=mydatabase
                Oracle: oci:dbname=//localhost:1521/mydatabase
             *
             */
            if (($pos = strpos($this->dsn, ':')) !== false) {
                // 若dsn中存在冒号，则冒号前的就是 数据库程序的名称 ，将其转为小写字母
                $this->_driverName = strtolower(substr($this->dsn, 0, $pos));
            } else {
                /**
                 * http://php.net/manual/zh/pdo.getattribute.php
                 *
                 * 获取从连接的数据库程序的名称
                 */
                $this->_driverName = strtolower($this->getSlavePdo()->getAttribute(PDO::ATTR_DRIVER_NAME));
            }
        }

        return $this->_driverName;
    }

    /**
     * Changes the current driver name.
     * @param string $driverName name of the DB driver
     */
    public function setDriverName($driverName)
    {
        $this->_driverName = strtolower($driverName);
    }

    /**
     * Returns a server version as a string comparable by [[\version_compare()]].
     * @return string server version as a string.
     * @since 2.0.14
     */
    public function getServerVersion()
    {
        return $this->getSchema()->getServerVersion();
    }

    /**
     *
     * 返回当前活跃的从数据库连接实例。
     * 当启用[[enableSlaves]]时，其中一个从数据库将被用于读取查询，它的PDO实例将被这个方法返回。
     * Returns the PDO instance for the currently active slave connection.
     * When [[enableSlaves]] is true, one of the slaves will be used for read queries, and its PDO instance
     * will be returned by this method.
     * 当所有的从属连接都不可用时，是否返回一个主PDO
     * @param bool $fallbackToMaster whether to return a master PDO in case none of the slave connections is available.
     * @return PDO the PDO instance for the currently active slave connection. `null` is returned if no slave connection
     * is available and `$fallbackToMaster` is false.
     */
    public function getSlavePdo($fallbackToMaster = true)
    {
        // 获取当前活跃的从数据库连接。
        $db = $this->getSlave(false);
        if ($db === null) {
            // 当所有的从属连接都不可用时，是否返回当前活动主连接的PDO实例。
            return $fallbackToMaster ? $this->getMasterPdo() : null;
        }

        return $db->pdo;
    }

    /**
     * 返回当前活动主连接的PDO实例。
     * Returns the PDO instance for the currently active master connection.
     * This method will open the master DB connection and then return [[pdo]].
     * @return PDO the PDO instance for the currently active master connection.
     */
    public function getMasterPdo()
    {
        $this->open();
        return $this->pdo;
    }

    /**
     * 返回当前活跃的从数据库连接。
     * 如果这个方法第一次被调用，当[[enableSlaves]] is true时，它将尝试打开一个从数据库连接。
     * Returns the currently active slave connection.
     * If this method is called for the first time, it will try to open a slave connection when [[enableSlaves]] is true.
     * 若没有可用的从连接，是否返回主连接。
     * @param bool $fallbackToMaster whether to return a master connection in case there is no slave connection available.
     * @return Connection the currently active slave connection. `null` is returned if there is no slave available and
     * `$fallbackToMaster` is false.
     */
    public function getSlave($fallbackToMaster = true)
    {
        // 没有启用从数据库
        if (!$this->enableSlaves) {
            // 若 $fallbackToMaster 为 true,则返回主数据库连接，否则返回 null
            return $fallbackToMaster ? $this : null;
        }

        // 当前没有活动的从数据库连接
        if ($this->_slave === false) {
            // 打开服务器池中的服务器连接。
            $this->_slave = $this->openFromPool($this->slaves, $this->slaveConfig);
        }

        return $this->_slave === null && $fallbackToMaster ? $this : $this->_slave;
    }

    /**
     * Returns the currently active master connection.
     * If this method is called for the first time, it will try to open a master connection.
     * @return Connection the currently active master connection. `null` is returned if there is no master available.
     * @since 2.0.11
     */
    public function getMaster()
    {
        if ($this->_master === false) {
            $this->_master = ($this->shuffleMasters)
                ? $this->openFromPool($this->masters, $this->masterConfig)
                : $this->openFromPoolSequentially($this->masters, $this->masterConfig);
        }

        return $this->_master;
    }

    /**
     * Executes the provided callback by using the master connection.
     *
     * This method is provided so that you can temporarily force using the master connection to perform
     * DB operations even if they are read queries. For example,
     *
     * ```php
     * $result = $db->useMaster(function ($db) {
     *     return $db->createCommand('SELECT * FROM user LIMIT 1')->queryOne();
     * });
     * ```
     *
     * @param callable $callback a PHP callable to be executed by this method. Its signature is
     * `function (Connection $db)`. Its return value will be returned by this method.
     * @return mixed the return value of the callback
     * @throws \Exception|\Throwable if there is any exception thrown from the callback
     */
    public function useMaster(callable $callback)
    {
        if ($this->enableSlaves) {
            $this->enableSlaves = false;
            try {
                $result = call_user_func($callback, $this);
            } catch (\Exception $e) {
                $this->enableSlaves = true;
                throw $e;
            } catch (\Throwable $e) {
                $this->enableSlaves = true;
                throw $e;
            }
            // TODO: use "finally" keyword when miminum required PHP version is >= 5.5
            $this->enableSlaves = true;
        } else {
            $result = call_user_func($callback, $this);
        }

        return $result;
    }

    /**
     * 打开服务器池中的服务器连接。
     * 该方法在给定的服务器列表中实现负载平衡。
     * Opens the connection to a server in the pool.
     * This method implements the load balancing among the given list of the servers.
     * 服务器池中的连接配置列表
     * Connections will be tried in random order.
     * @param array $pool the list of connection configurations in the server pool
     * $pool中给定的配置
     * @param array $sharedConfig the configuration common to those given in `$pool`.
     * @return Connection the opened DB connection, or `null` if no server is available
     * @throws InvalidConfigException if a configuration does not specify "dsn"
     */
    protected function openFromPool(array $pool, array $sharedConfig)
    {
        shuffle($pool);
        return $this->openFromPoolSequentially($pool, $sharedConfig);
    }

    /**
     * Opens the connection to a server in the pool.
     * This method implements the load balancing among the given list of the servers.
     * Connections will be tried in sequential order.
     * @param array $pool the list of connection configurations in the server pool
     * @param array $sharedConfig the configuration common to those given in `$pool`.
     * @return Connection the opened DB connection, or `null` if no server is available
     * @throws InvalidConfigException if a configuration does not specify "dsn"
     * @since 2.0.11
     */
    protected function openFromPoolSequentially(array $pool, array $sharedConfig)
    {
        if (empty($pool)) {
            return null;
        }

        if (!isset($sharedConfig['class'])) {
            $sharedConfig['class'] = get_class($this);
        }

        $cache = is_string($this->serverStatusCache) ? Yii::$app->get($this->serverStatusCache, false) : $this->serverStatusCache;

        foreach ($pool as $config) {
            $config = array_merge($sharedConfig, $config);
            if (empty($config['dsn'])) {
                throw new InvalidConfigException('The "dsn" option must be specified.');
            }

            $key = [__METHOD__, $config['dsn']];
            if ($cache instanceof CacheInterface && $cache->get($key)) {
                // should not try this dead server now
                continue;
            }

            /* @var $db Connection */
            $db = Yii::createObject($config);

            try {
                $db->open();
                return $db;
            } catch (\Exception $e) {
                Yii::warning("Connection ({$config['dsn']}) failed: " . $e->getMessage(), __METHOD__);
                if ($cache instanceof CacheInterface) {
                    // mark this server as dead and only retry it after the specified interval
                    $cache->set($key, 1, $this->serverRetryInterval);
                }
            }
        }

        return null;
    }

    /**
     * Close the connection before serializing.
     * @return array
     */
    public function __sleep()
    {
        $fields = (array) $this;

        unset($fields['pdo']);
        unset($fields["\000" . __CLASS__ . "\000" . '_master']);
        unset($fields["\000" . __CLASS__ . "\000" . '_slave']);
        unset($fields["\000" . __CLASS__ . "\000" . '_transaction']);
        unset($fields["\000" . __CLASS__ . "\000" . '_schema']);

        return array_keys($fields);
    }

    /**
     * Reset the connection after cloning.
     */
    public function __clone()
    {
        parent::__clone();

        $this->_master = false;
        $this->_slave = false;
        $this->_schema = null;
        $this->_transaction = null;
        if (strncmp($this->dsn, 'sqlite::memory:', 15) !== 0) {
            // reset PDO connection, unless its sqlite in-memory, which can only have one connection
            $this->pdo = null;
        }
    }
}
