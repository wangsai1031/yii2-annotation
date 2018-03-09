<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Session提供会话数据管理和相关配置
 * Session provides session data management and the related configurations.
 *
 * Session 是一个Web应用程序组件,可以通过`Yii::$app->session` 访问
 * Session is a Web application component that can be accessed via `Yii::$app->session`.
 *
 * 可使用如下方式访问session中的数据：
    ```
    $session = Yii::$app->session;

    // 获取session中的变量值，以下用法是相同的：
    $language = $session->get('language');
    $language = $session['language'];
    $language = isset($_SESSION['language']) ? $_SESSION['language'] : null;

    // 设置一个session变量，以下用法是相同的：
    $session->set('language', 'en-US');
    $session['language'] = 'en-US';
    $_SESSION['language'] = 'en-US';

    // 删除一个session变量，以下用法是相同的：
    $session->remove('language');
    unset($session['language']);
    unset($_SESSION['language']);

    // 检查session变量是否已存在，以下用法是相同的：
    if ($session->has('language')) ...
    if (isset($session['language'])) ...
    if (isset($_SESSION['language'])) ...

    // 遍历所有session变量，以下用法是相同的：
    foreach ($session as $name => $value) ...
    foreach ($_SESSION as $name => $value) ...

 * ```
 *
 * 注意: 当使用session组件访问session数据时候， 如果session没有开启会自动开启，
 * 这和通过$_SESSION不同，$_SESSION要求先执行session_start()。
 *
 * 当session数据为数组时，session组件会限制你直接修改数据中的单元项， 例如：
    ```
    $session = Yii::$app->session;

    // 如下代码不会生效
    $session['captcha']['number'] = 5;
    $session['captcha']['lifetime'] = 3600;

    // 如下代码会生效：
    $session['captcha'] = [
    'number' => 5,
    'lifetime' => 3600,
    ];

    // 如下代码也会生效：
    echo $session['captcha']['lifetime'];
 *  ```
 *
    可使用以下任意一个变通方法来解决这个问题：

 * ```
    $session = Yii::$app->session;

    // 直接使用$_SESSION (确保Yii::$app->session->open() 已经调用)
    $_SESSION['captcha']['number'] = 5;
    $_SESSION['captcha']['lifetime'] = 3600;

    // 先获取session数据到一个数组，修改数组的值，然后保存数组到session中
    $captcha = $session['captcha'];
    $captcha['number'] = 5;
    $captcha['lifetime'] = 3600;
    $session['captcha'] = $captcha;

    // 使用ArrayObject 数组对象代替数组
    $session['captcha'] = new \ArrayObject;
    ...
    $session['captcha']['number'] = 5;
    $session['captcha']['lifetime'] = 3600;

    // 使用带通用前缀的键来存储数组
    $session['captcha.number'] = 5;
    $session['captcha.lifetime'] = 3600;
 *
 *  ```
    为更好的性能和可读性，推荐最后一种方案， 也就是不用存储session变量为数组， 而是将每个数组项变成有相同键前缀的session变量。
 *
 * 调用[[open()]]启动会话;调用[[close()]]要完成并发送会话数据;调用 [[destroy()]]销毁会话。
 * To start the session, call [[open()]]; To complete and send out session data, call [[close()]];
 * To destroy the session, call [[destroy()]].
 *
 * Session 可以像数组一样来设置和获取会话数据。
 * Session can be used like an array to set and get session data. For example,
 *
 * ```php
 * $session = new Session;
 * $session->open();
 * $value1 = $session['name1'];  // get session variable 'name1'
 * $value2 = $session['name2'];  // get session variable 'name2'
 * foreach ($session as $name => $value) // traverse all session variables
 * $session['name3'] = $value3;  // set session variable 'name3'
 * ```
 *
 * Session 可以扩展以支持定制的会话存储。
 * 为此，重写[[useCustomStorage]]，使其返回true，并使用自定义存储的实际逻辑覆盖这些方法：
 * [[openSession()]], [[closeSession()]], [[readSession()]], [[writeSession()]],[[destroySession()]] and [[gcSession()]].
 * Session can be extended to support customized session storage.
 * To do so, override [[useCustomStorage]] so that it returns true, and
 * override these methods with the actual logic about using custom storage:
 * [[openSession()]], [[closeSession()]], [[readSession()]], [[writeSession()]],
 * [[destroySession()]] and [[gcSession()]].
 *
 * 会话还支持一种特殊类型的会话数据，称为 ‘flash消息’。
 * 一个flash消息只在当前请求和下一个请求中有效。之后，它会被自动删除。
 * Flash消息对于显示确认消息特别有用。
 * 要使用flash消息，只需调用[[setFlash()]], [[getFlash()]]方法。
 * Session also supports a special type of session data, called *flash messages*.
 * A flash message is available only in the current request and the next request.
 * After that, it will be deleted automatically. Flash messages are particularly
 * useful for displaying confirmation messages. To use flash messages, simply
 * call methods such as [[setFlash()]], [[getFlash()]].
 *
 * For more details and usage information on Session, see the [guide article on sessions](guide:runtime-sessions-cookies).
 *
 * @property array $allFlashes Flash messages (key => message or key => [message1, message2]). This property
 * is read-only.
 * Flash消息(key => message or key => [message1, message2])。这个属性是只读的。
 * 
 * @property string $cacheLimiter Current cache limiter. This property is read-only.
 * @property array $cookieParams The session cookie parameters. This property is read-only.
 * @property int $count The number of session variables. This property is read-only.
 * 会话变量的数量。这个属性是只读的。
 * 
 * @property string $flash The key identifying the flash message. Note that flash messages and normal session
 * variables share the same name space. If you have a normal session variable using the same name, its value will
 * be overwritten by this method. This property is write-only.
 * 识别flash消息的关键字。注意，flash消息和普通会话变量共享相同的名称空间。
 * 如果使用相同名称，它的值将被该方法覆盖。这个属性是只写。
 * 
 * @property float $gCProbability The probability (percentage) that the GC (garbage collection) process is
 * started on every session initialization, defaults to 1 meaning 1% chance.
 * GC(垃圾收集)进程在每次会话初始化过程中启动的概率(百分比)，默认为1，即1%的概率。
 * 
 * @property bool $hasSessionId Whether the current request has sent the session ID.
 * 当前的请求是否发送了会话ID
 * 
 * @property string $id The current session ID.
 * 当前会话ID
 * 
 * @property bool $isActive Whether the session has started. This property is read-only.
 * 会话是否已经开始。这个属性是只读的。
 * 
 * @property SessionIterator $iterator An iterator for traversing the session variables. This property is
 * read-only.
 * 用于遍历会话变量的迭代器。这个属性是只读的。
 * 
 * @property string $name The current session name.
 * 当前会话名
 * 
 * @property string $savePath The current session save path, defaults to '/tmp'.
 * 当前会话保存路径，默认为'/tmp'
 * 
 * @property int $timeout The number of seconds after which data will be seen as 'garbage' and cleaned up. The
 * default value is 1440 seconds (or the value of "session.gc_maxlifetime" set in php.ini).
 * 会话有效期。默认值是1440秒(或者在php.ini中设置的"session.gc_maxlifetime"的价。)
 * 
 * @property bool|null $useCookies The value indicating whether cookies should be used to store session IDs.
 * 表示是否使用cookie存储会话id。
 * 
 * @property bool $useCustomStorage Whether to use custom storage. This property is read-only.
 * 是否使用自定义存储。这个属性是只读的。
 * 
 * @property bool $useTransparentSessionID Whether transparent sid support is enabled or not, defaults to
 * false.
 * 是否启用透明的sid支持，默认为false。
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Session extends Component implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * 存储flash消息数据的会话变量的名称
     * @var string the name of the session variable that stores the flash message data.
     */
    public $flashParam = '__flash';
    /**
     * 实现SessionHandlerInterface的对象或配置数组。
     * 如果设置，将被用于代替内置方法来提供存储。
     * @var \SessionHandlerInterface|array an object implementing the SessionHandlerInterface or a configuration array. If set, will be used to provide persistency instead of build-in methods.
     */
    public $handler;

    /**
     * 参数-值对，以覆盖session_set_cookie_params()函数的默认会话cookie参数。
     * 数组可能有以下可选键：'lifetime', 'path', 'domain', 'secure', 'httponly'
     * @var array parameter-value pairs to override default session cookie parameters that are used for session_set_cookie_params() function
     * Array may have the following possible keys: 'lifetime', 'path', 'domain', 'secure', 'httponly'
     * @see http://www.php.net/manual/en/function.session-set-cookie-params.php
     */
    private $_cookieParams = ['httponly' => true];
    /**
     * @var $frozenSessionData array|null is used for saving session between recreations due to session parameters update.
     */
    private $frozenSessionData;


    /**
     * 初始化应用程序组件。
     * 此方法由应用程序调用。
     * Initializes the application component.
     * This method is required by IApplicationComponent and is invoked by application.
     */
    public function init()
    {
        parent::init();
        /**
         * @see http://php.net/manual/zh/function.register-shutdown-function.php
         * 注册一个会在php中止时执行的函数
         *
         * php中止时调用 $this->close()方法
         */
        register_shutdown_function([$this, 'close']);
        // 检查会话是否开启
        if ($this->getIsActive()) {
            Yii::warning('Session is already started', __METHOD__);
            //更新flash消息的计数器，并删除过时的flash消息。
            $this->updateFlashCounters();
        }
    }

    /**
     * 是否使用自定义会话存储.
     *
     * 实现自定义会话存储的子类，应覆盖该方法并返回true。
     * 要实现自定义会话存储，可以覆盖这些方法：[[openSession()]], [[closeSession()]],
     * [[readSession()]], [[writeSession()]], [[destroySession()]] and [[gcSession()]].
     *
     * Returns a value indicating whether to use custom session storage.
     * This method should be overridden to return true by child classes that implement custom session storage.
     * To implement custom session storage, override these methods: [[openSession()]], [[closeSession()]],
     * [[readSession()]], [[writeSession()]], [[destroySession()]] and [[gcSession()]].
     * @return bool whether to use custom storage.
     */
    public function getUseCustomStorage()
    {
        return false;
    }

    /**
     * 开启session。
     * 多次调用open() 和close() 方法并不会产生错误， 因为方法内部会先检查session是否已经开启。
     * Starts the session.
     */
    public function open()
    {
        // 先检查session是否已经开启
        if ($this->getIsActive()) {
            return;
        }

        $this->registerSessionHandler();

        $this->setCookieParamsInternal();

        YII_DEBUG ? session_start() : @session_start();

        if ($this->getIsActive()) {
            Yii::info('Session started', __METHOD__);
            $this->updateFlashCounters();
        } else {
            $error = error_get_last();
            $message = isset($error['message']) ? $error['message'] : 'Failed to start session.';
            Yii::error($message, __METHOD__);
        }
    }

    /**
     * 注册 会话处理程序
     * Registers session handler.
     * @throws \yii\base\InvalidConfigException
     */
    protected function registerSessionHandler()
    {
        // 若自定义的会话处理程序不为空
        if ($this->handler !== null) {
            if (!is_object($this->handler)) {
                // 不是对象，则为配置数组，使用配置数组创建该对象
                $this->handler = Yii::createObject($this->handler);
            }
            // 若 handler 没有实现 \SessionHandlerInterface 接口，则抛出异常
            if (!$this->handler instanceof \SessionHandlerInterface) {
                throw new InvalidConfigException('"' . get_class($this) . '::handler" must implement the SessionHandlerInterface.');
            }
            /**
             * @see http://php.net/manual/zh/function.session-set-save-handler.php
             * session_set_save_handler() 设置用户自定义会话存储函数
             */
            YII_DEBUG ? session_set_save_handler($this->handler, false) : @session_set_save_handler($this->handler, false);
        // 若继承该类的子类使用了自定义会话存储
        } elseif ($this->getUseCustomStorage()) {
            if (YII_DEBUG) {
                session_set_save_handler(
                    [$this, 'openSession'],
                    [$this, 'closeSession'],
                    [$this, 'readSession'],
                    [$this, 'writeSession'],
                    [$this, 'destroySession'],
                    [$this, 'gcSession']
                );
            } else {
                @session_set_save_handler(
                    // open 回调函数类似于类的构造函数， 在会话打开的时候会被调用。
                    // 这是自动开始会话或者通过调用 session_start() 手动开始会话 之后第一个被调用的回调函数。
                    // 此回调函数操作成功返回 TRUE，反之返回 FALSE。
                    [$this, 'openSession'],
                    // close 回调函数类似于类的析构函数。 在 write 回调函数调用之后调用。
                    // 当调用 session_write_close() 函数之后，也会调用 close 回调函数。
                    // 此回调函数操作成功返回 TRUE，反之返回 FALSE。
                    [$this, 'closeSession'],
                    // 如果会话中有数据，read 回调函数必须返回将会话数据编码（序列化）后的字符串。
                    // 如果会话中没有数据，read 回调函数返回空字符串。
                    // 在自动开始会话或者通过调用 session_start() 函数手动开始会话之后，PHP 内部调用 read 回调函数来获取会话数据。
                    // 在调用 read 之前，PHP 会调用 open 回调函数。
                    [$this, 'readSession'],
                    // 在会话保存数据时会调用 write 回调函数。
                    // 此回调函数接收当前会话 ID 以及 $_SESSION 中数据序列化之后的字符串作为参数。
                    // 序列化会话数据的过程由 PHP 根据 session.serialize_handler 设定值来完成。
                    // 序列化后的数据将和会话 ID 关联在一起进行保存。
                    // 当调用 read 回调函数获取数据时，所返回的数据必须要和 传入 write 回调函数的数据完全保持一致。
                    // PHP 会在脚本执行完毕或调用 session_write_close() 函数之后调用此回调函数。
                    // 注意，在调用完此回调函数之后，PHP 内部会调用 close 回调函数。
                    [$this, 'writeSession'],
                    // 当调用 session_destroy() 函数， 或者调用 session_regenerate_id() 函数并且设置 destroy 参数为 TRUE 时， 会调用此回调函数。
                    // 此回调函数操作成功返回 TRUE，反之返回 FALSE。
                    [$this, 'destroySession'],
                    // 为了清理会话中的旧数据，PHP 会不时的调用垃圾收集回调函数。
                    // 调用周期由 session.gc_probability 和 session.gc_divisor 参数控制。
                    // 传入到此回调函数的 lifetime 参数由 session.gc_maxlifetime 设置。
                    // 此回调函数操作成功返回 TRUE，反之返回 FALSE。
                    [$this, 'gcSession']
                );
            }
        }
    }

    /**
     * 关闭当前session并存储会话数据
     * Ends the current session and store session data.
     */
    public function close()
    {
        if ($this->getIsActive()) {
            /**
             * 关闭当前session并存储会话数据
             * @see http://php.net/manual/zh/function.session-write-close.php
             */
            YII_DEBUG ? session_write_close() : @session_write_close();
        }
    }

    /**
     * todo 为何会经历如此复杂的过程?
     * 释放所有会话变量，并销销毁session中所有已注册的数据
     * Frees all session variables and destroys all data registered to a session.
     *
     * This method has no effect when session is not [[getIsActive()|active]].
     * Make sure to call [[open()]] before calling it.
     * @see open()
     * @see isActive
     */
    public function destroy()
    {
        if ($this->getIsActive()) {
            // 获取当前会话ID
            $sessionId = session_id();
            // 关闭当前session并存储会话数据
            $this->close();
            // 设置会话ID
            $this->setId($sessionId);
            // 开启会话
            $this->open();
            // 释放所有的会话变量
            session_unset();
            // 销毁一个会话中的全部数据
            session_destroy();
            $this->setId($sessionId);
        }
    }

    /**
     * 检查session是否开启
     * @return bool whether the session has started
     */
    public function getIsActive()
    {
        /**
         * 返回当前会话状态
         * @see http://php.net/manual/zh/function.session-status.php
         *
         * PHP_SESSION_DISABLED 会话是被禁用的。
         * PHP_SESSION_NONE 会话是启用的，但不存在当前会话。
         * PHP_SESSION_ACTIVE 会话是启用的，而且存在当前会话。
         */
        return session_status() === PHP_SESSION_ACTIVE;
    }

    private $_hasSessionId;

    /**
     * 返回一个值，指示当前的请求是否发送了会话ID
     *
     * 默认的实现是检查cookie和$GET数组中是否有与 会话ID 相同的键。
     *
     * Session会话中传递SESSIONID有两种方式：
        1. 基于cookie传递（常用方式）
        2. 基于URL传递
        如果用户的客户端（浏览器）禁止了cookie，那么基于cookie的传递就不能成功，跨页面就无法传递session值了，
     *  这个时候可以通过php.ini中设置session.use_trans_sid=1,表示当客户端浏览器禁止cookie的时候，
     *  页面上的链接会基于url传递SESSIONID,达到跨页面专递的效果.
     *
     * 如果您通过其他方式发送会话ID，您可能需要覆盖该方法或调用[[setHasSessionId()]]以显式地设置会话ID是否被发送
     * Returns a value indicating whether the current request has sent the session ID.
     * The default implementation will check cookie and $_GET using the session name.
     * If you send session ID via other ways, you may need to override this method
     * or call [[setHasSessionId()]] to explicitly set whether the session ID is sent.
     * @return bool whether the current request has sent the session ID.
     */
    public function getHasSessionId()
    {
        if ($this->_hasSessionId === null) {
            // 获取当前会话的名称
            $name = $this->getName();
            // 获取 Request 对象
            $request = Yii::$app->getRequest();
            /**
             * session.use_cookies:
             * 若session.use_cookies = 1,session id在客户端采用的存储方式，置1代表使用cookie记录客户端的sessionid，
             * 同时，$_COOKIE变量里才会有$_COOKIE['PHPSESSIONID']这个元素存在。
             *
             * 若cookie中存在键名为 会话名称 的值，并且php设置 session.use_cookies = 1。则返回true
             */
            if (!empty($_COOKIE[$name]) && ini_get('session.use_cookies')) {
                $this->_hasSessionId = true;
            /**
             * session.use_only_cookies: 指定是否在客户端仅仅使用 cookie 来存放会话 ID。启用此设定可以防止有关通过 URL 传递会话 ID 的攻击.
             * session.use_trans_sid: 表示当客户端浏览器禁止cookie的时候，是否要使用页面上的url传递SESSIONID.
             *
             * 若未设置客户端仅仅使用 cookie 来存放会话 ID，并且启用 当客户端浏览器禁止cookie的时候，页面上的链接会基于url传递SESSIONID.
             * 则查看GET参数中是否有传递SESSION ID.
             */
            } elseif (!ini_get('session.use_only_cookies') && ini_get('session.use_trans_sid')) {
                $this->_hasSessionId = $request->get($name) != '';
            } else {
                $this->_hasSessionId = false;
            }
        }

        return $this->_hasSessionId;
    }

    /**
     * 设置指示当前的请求是否发送了会话ID的值.
     * 提供此方法是，您可以重写默认方式，指定是否发送了会话 ID。
     * Sets the value indicating whether the current request has sent the session ID.
     * This method is provided so that you can override the default way of determining
     * whether the session ID is sent.
     * @param bool $value whether the current request has sent the session ID.
     */
    public function setHasSessionId($value)
    {
        $this->_hasSessionId = $value;
    }

    /**
     * 获取会话ID
     * Gets the session ID.
     * This is a wrapper for [PHP session_id()](http://php.net/manual/en/function.session-id.php).
     * @return string the current session ID
     */
    public function getId()
    {
        return session_id();
    }

    /**
     * 设置会话ID
     * Sets the session ID.
     * This is a wrapper for [PHP session_id()](http://php.net/manual/en/function.session-id.php).
     * @param string $value the session ID for the current session
     */
    public function setId($value)
    {
        session_id($value);
    }

    /**
     * 使用新生成的会话 ID 更新现有会话 ID
     *
     * session_regenerate_id(): 使用新生成的会话 ID 更新现有会话 ID
     * headers_sent() ： 检查是否已经发送了头信息
     * Updates the current session ID with a newly generated one.
     *
     * Please refer to <http://php.net/session_regenerate_id> for more details.
     *
     * 是否删除关联的旧的会话文件
     * This method has no effect when session is not [[getIsActive()|active]].
     * Make sure to call [[open()]] before calling it.
     *
     * @param bool $deleteOldSession Whether to delete the old associated session file or not.
     * @see open()
     * @see isActive
     */
    public function regenerateID($deleteOldSession = false)
    {
        // 检查session是否开启
        if ($this->getIsActive()) {
            // add @ to inhibit possible warning due to race condition
            // https://github.com/yiisoft/yii2/pull/1812
            // 当两个脚本同时生成会话数据时，会话id更新有时会失败："session_regenerate_id (): Session object destruction failed"
            // 使用 @ 来屏蔽掉警告信息
            if (YII_DEBUG && !headers_sent()) {
                session_regenerate_id($deleteOldSession);
            } else {
                @session_regenerate_id($deleteOldSession);
            }
        }
    }

    /**
     * 获取当前会话的名称
     * Gets the name of the current session.
     * This is a wrapper for [PHP session_name()](http://php.net/manual/en/function.session-name.php).
     * @return string the current session name
     */
    public function getName()
    {
        return session_name();
    }

    /**
     * 设置当前会话的名称。
     * Sets the name for the current session.
     * This is a wrapper for [PHP session_name()](http://php.net/manual/en/function.session-name.php).
     *
     * 当前会话的会话名称必须是一个字母数字字符串,默认是"PHPSESSID"
     * @param string $value the session name for the current session, must be an alphanumeric string.
     * It defaults to "PHPSESSID".
     */
    public function setName($value)
    {
        session_name($value);
    }

    /**
     * 获取当前会话保存路径
     * Gets the current session save path.
     * This is a wrapper for [PHP session_save_path()](http://php.net/manual/en/function.session-save-path.php).
     * @return string the current session save path, defaults to '/tmp'.
     */
    public function getSavePath()
    {
        return session_save_path();
    }

    /**
     * 设置当前会话保存路径
     * Sets the current session save path.
     * This is a wrapper for [PHP session_save_path()](http://php.net/manual/en/function.session-save-path.php).
     * 当前会话保存路径。这可以是目录名，也可以是路径别名。
     * @param string $value the current session save path. This can be either a directory name or a [path alias](guide:concept-aliases).
     * 如果路径不是一个有效的目录，将会抛出异常
     * @throws InvalidArgumentException if the path is not a valid directory
     */
    public function setSavePath($value)
    {
        // 获取别名对应的值（若不是别名，返回原值）
        $path = Yii::getAlias($value);
        // 判断路径是不是有效的目录
        if (is_dir($path)) {
            session_save_path($path);
        } else {
            throw new InvalidArgumentException("Session save path is not a valid directory: $value");
        }
    }

    /**
     * 获取会话 cookie 参数
     * @return array the session cookie parameters.
     * @see http://php.net/manual/en/function.session-get-cookie-params.php
     */
    public function getCookieParams()
    {
        /**
         * @see http://www.w3school.com.cn/php/func_array_change_key_case.asp
         * array_change_key_case($this->_cookieParams) : 将数组的所有的键转换为大写字母
         *
         * @see http://php.net/manual/en/function.session-get-cookie-params.php
         * session_get_cookie_params():获取会话 cookie 参数
         * 返回一个包含当前会话 cookie 信息的数组：
         *  "lifetime"  - cookie 的生命周期，以秒为单位。
            "path"      - cookie 的访问路径。
            "domain"    - cookie 的域。
            "secure"    - 仅在使用安全连接时发送 cookie。
            "httponly"  - 只能通过 http 协议访问 cookie.
         *
         * 当两个数组具有相同的键时，session_get_cookie_params() 的元素会被 $this->_cookieParams 覆盖
         */
        return array_merge(session_get_cookie_params(), array_change_key_case($this->_cookieParams));
    }

    /**
     * 设置会话cookie参数。
     * 传递给该方法的cookie参数将与`session_get_cookie_params()`的结果合并。
     * 具有相同键时会覆盖`session_get_cookie_params()`的结果
     * Sets the session cookie parameters.
     * The cookie parameters passed to this method will be merged with the result
     * of `session_get_cookie_params()`.
     * @param array $value cookie parameters, valid keys include: `lifetime`, `path`, `domain`, `secure` and `httponly`.
     * @throws InvalidArgumentException if the parameters are incomplete.
     * @see http://us2.php.net/manual/en/function.session-set-cookie-params.php
     */
    public function setCookieParams(array $value)
    {
        $this->_cookieParams = $value;
    }

    /**
     * 设置会话cookie参数。
     * 当打开会话时，[[open()]]将调用这个方法。
     * Sets the session cookie parameters.
     * This method is called by [[open()]] when it is about to open the session.
     * @throws InvalidArgumentException if the parameters are incomplete.
     * @see http://us2.php.net/manual/en/function.session-set-cookie-params.php
     */
    private function setCookieParamsInternal()
    {
        // 获取会话cookie参数。
        $data = $this->getCookieParams();
        // 只有当会话cookie参数包含所有五个指定元素时，才会调用`session_set_cookie_params()`方法设置会话 cookie 参数
        if (isset($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly'])) {
            session_set_cookie_params($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly']);
        } else {
            throw new InvalidArgumentException('Please make sure cookieParams contains these elements: lifetime, path, domain, secure and httponly.');
        }
    }

    /**
     * 是否使用cookie存储会话id。
     *
     * 可能有以下三种状态：
     * - true: 仅能使用Cookie存储会话ID
     * - false: cookie不用于存储会话id
     * - null: 如果客户端允许，将使用cookie来存储会话id;如果不行，将使用其他机制(例如GET参数)
     *
     * Returns the value indicating whether cookies should be used to store session IDs.
     * @return bool|null the value indicating whether cookies should be used to store session IDs.
     * @see setUseCookies()
     */
    public function getUseCookies()
    {
        /**
         * 获取PHP设置 session.use_cookies:
         * session id在客户端采用的存储方式，置1代表使用cookie记录客户端的sessionid，
         * 同时，$_COOKIE变量里才会有$_COOKIE['PHPSESSIONID']这个元素存在。
         *
         */
        if (ini_get('session.use_cookies') === '0') {
            // cookie不用于存储会话id
            return false;
        /**
         * session.use_only_cookies: 指定是否在客户端仅仅使用 cookie 来存放会话 ID。启用此设定可以防止有关通过 URL 传递会话 ID 的攻击.
         *
         * 若未设置客户端仅仅使用 cookie 来存放会话 ID，并且启用 当客户端浏览器禁止cookie的时候，页面上的链接会基于url传递SESSIONID.
         */
        } elseif (ini_get('session.use_only_cookies') === '1') {
            // 仅能使用Cookie存储会话ID
            return true;
        }

        // 如果客户端允许，将使用cookie来存储会话id;如果不行，将使用其他机制(例如GET参数)
        return null;
    }

    /**
     * 设置值，指示是否使用cookie存储会话id。
     * 可能有以下三种状态：
     * - true: 仅能使用Cookie存储会话ID
     * - false: cookie不用于存储会话id
     * - null: 如果客户端允许，将使用cookie来存储会话id;如果不行，将使用其他机制(例如GET参数)
     *
     * Sets the value indicating whether cookies should be used to store session IDs.
     *
     * Three states are possible:
     *
     * - true: cookies and only cookies will be used to store session IDs.
     * - false: cookies will not be used to store session IDs.
     * - null: if possible, cookies will be used to store session IDs; if not, other mechanisms will be used (e.g. GET parameter)
     *
     * @param bool|null $value the value indicating whether cookies should be used to store session IDs.
     */
    public function setUseCookies($value)
    {
        $this->freeze();
        /**
         * session.use_cookies:
         * session id在客户端采用的存储方式，置1代表使用cookie记录客户端的sessionid，同时，$_COOKIE变量里才会有$_COOKIE['PHPSESSIONID']这个元素存在。
         *
         * session.use_only_cookies:
         * 指定是否在客户端仅仅使用 cookie 来存放会话 ID。启用此设定可以防止有关通过 URL 传递会话 ID 的攻击.
         * 若未设置客户端仅仅使用 cookie 来存放会话 ID，并且启用 当客户端浏览器禁止cookie的时候，页面上的链接会基于url传递SESSIONID.
         */
        if ($value === false) {
            ini_set('session.use_cookies', '0');
            ini_set('session.use_only_cookies', '0');
        } elseif ($value === true) {
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
        } else {
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '0');
        }
        $this->unfreeze();
    }

    /**
     * GC(垃圾收集)进程在每次会话初始化过程中启动的概率(百分比)。
     * 默认是1，表示1%的概率启动垃圾回收
     * @return float the probability (percentage) that the GC (garbage collection) process is started on every session initialization, defaults to 1 meaning 1% chance.
     */
    public function getGCProbability()
    {
        return (float) (ini_get('session.gc_probability') / ini_get('session.gc_divisor') * 100);
    }

    /**
     * 设置GC(垃圾收集)进程在每次会话初始化时启动的概率(百分比)。
     * @param float $value the probability (percentage) that the GC (garbage collection) process is started on every session initialization.
     * @throws InvalidArgumentException if the value is not between 0 and 100.
     */
    public function setGCProbability($value)
    {
        $this->freeze();
        // 值只能在0~100之间
        if ($value >= 0 && $value <= 100) {
            // 2147483647 是 2^31 - 1, 是32位系统 int最大值
            // percent * 21474837 / 2147483647 ≈ percent * 0.01
            ini_set('session.gc_probability', floor($value * 21474836.47));
            ini_set('session.gc_divisor', 2147483647);
        } else {
            throw new InvalidArgumentException('GCProbability must be a value between 0 and 100.');
        }
        $this->unfreeze();
    }

    /**
     * 是否启用透明的sid支持，默认为false
     * @return bool whether transparent sid support is enabled or not, defaults to false.
     */
    public function getUseTransparentSessionID()
    {
        /**
         * session.use_trans_sid == 1 表示当客户端浏览器禁止cookie的时候，页面会自动通过url传递SESSIONID，这样才能实现跨页传递 session值。
         * 注意，此时 session.use_only_cookies 必须为 0
         */
        return ini_get('session.use_trans_sid') == 1;
    }

    /**
     * 设置 是否启用透明的sid支持
     * @param bool $value whether transparent sid support is enabled or not.
     */
    public function setUseTransparentSessionID($value)
    {
        $this->freeze();
        ini_set('session.use_trans_sid', $value ? '1' : '0');
        $this->unfreeze();
    }

    /**
     * 获取session 有效期。
     * 默认值为1440秒(或php.ini中session.gc_maxlifetime设置的值)。
     * @return int the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * The default value is 1440 seconds (or the value of "session.gc_maxlifetime" set in php.ini).
     */
    public function getTimeout()
    {
        return (int) ini_get('session.gc_maxlifetime');
    }

    /**
     * 设置 session 有效期。秒
     * @param int $value the number of seconds after which data will be seen as 'garbage' and cleaned up
     */
    public function setTimeout($value)
    {
        $this->freeze();
        ini_set('session.gc_maxlifetime', $value);
        $this->unfreeze();
    }

    /**
     * 会话开启处理程序。
     * 如果[[useCustomStorage]]返回true，则该方法将被覆盖。
     * 不要直接调用这个方法。
     * Session open handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param string $savePath session save path
     * @param string $sessionName session name
     * @return bool whether session is opened successfully
     */
    public function openSession($savePath, $sessionName)
    {
        return true;
    }

    /**
     * 会话关闭处理程序。
     * 如果[[useCustomStorage]]返回true，则该方法将被覆盖。
     * 不要直接调用这个方法。
     * Session close handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @return bool whether session is closed successfully
     */
    public function closeSession()
    {
        return true;
    }

    /**
     * 会话读取处理程序。
     * 如果[[useCustomStorage]]返回true，则该方法将被覆盖。
     * 不要直接调用这个方法。
     * Session read handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return string the session data
     */
    public function readSession($id)
    {
        return '';
    }

    /**
     * 会话写入处理程序。
     * 如果[[useCustomStorage]]返回true，则该方法将被覆盖。
     * 不要直接调用这个方法。
     * Session write handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return bool whether session write is successful
     */
    public function writeSession($id, $data)
    {
        return true;
    }

    /**
     * 会话删除处理程序。
     * 如果[[useCustomStorage]]返回true，则该方法将被覆盖。
     * 不要直接调用这个方法。
     * Session destroy handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return bool whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        return true;
    }

    /**
     * 会话过期处理程序。
     * 如果[[useCustomStorage]]返回true，则该方法将被覆盖。
     * 不要直接调用这个方法。
     * Session GC (garbage collection) handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param int $maxLifetime the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * @return bool whether session is GCed successfully
     */
    public function gcSession($maxLifetime)
    {
        return true;
    }

    /**
     * 返回用于遍历会话变量的迭代器。
     * 这个方法是实现接口 IteratorAggregate 必须的
     * Returns an iterator for traversing the session variables.
     * This method is required by the interface [[\IteratorAggregate]].
     * @return SessionIterator an iterator for traversing the session variables.
     */
    public function getIterator()
    {
        $this->open();
        return new SessionIterator();
    }

    /**
     * 返回会话中条目的数量。
     * Returns the number of items in the session.
     * @return int the number of session variables
     */
    public function getCount()
    {
        $this->open();
        return count($_SESSION);
    }

    /**
     * 返回会话中项目的数量。
     * 这个方法是实现[[\Countable]]接口所必须的
     * Returns the number of items in the session.
     * This method is required by [[\Countable]] interface.
     * @return int number of items in the session.
     */
    public function count()
    {
        return $this->getCount();
    }

    /**
     * 使用会话变量名返回会话变量值。
     * 如果会话变量不存在，将返回$defaultValue。
     * Returns the session variable value with the session variable name.
     * If the session variable does not exist, the `$defaultValue` will be returned.
     * @param string $key the session variable name
     * @param mixed $defaultValue the default value to be returned when the session variable does not exist.
     * @return mixed the session variable value, or $defaultValue if the session variable does not exist.
     */
    public function get($key, $defaultValue = null)
    {
        // 开启session。
        $this->open();
        // 返回指定名称的会话或默认值
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $defaultValue;
    }

    /**
     * 添加一个会话变量。
     * 如果指定的名称已经存在，那么旧的值将被覆盖。
     * Adds a session variable.
     * If the specified name already exists, the old value will be overwritten.
     * @param string $key session variable name
     * @param mixed $value session variable value
     */
    public function set($key, $value)
    {
        $this->open();
        $_SESSION[$key] = $value;
    }

    /**
     * 删除一个会话变量
     * Removes a session variable.
     * @param string $key the name of the session variable to be removed
     * @return mixed the removed value, null if no such session variable.
     */
    public function remove($key)
    {
        $this->open();
        if (isset($_SESSION[$key])) {
            // 若存在，则删除，并返回删除的会话的值
            $value = $_SESSION[$key];
            unset($_SESSION[$key]);

            return $value;
        }

        return null;
    }

    /**
     * 删除所有会话变量
     * Removes all session variables.
     */
    public function removeAll()
    {
        $this->open();
        foreach (array_keys($_SESSION) as $key) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * 判断是否有相应的会话变量
     * @param mixed $key session variable name
     * @return bool whether there is the named session variable
     */
    public function has($key)
    {
        $this->open();
        return isset($_SESSION[$key]);
    }

    /**
     * 更新flash消息的计数器，并删除过时的flash消息。
     * 这个方法应该只在[[init()]]中调用一次。
     * Updates the counters for flash messages and removes outdated flash messages.
     * This method should only be called once in [[init()]].
     */
    protected function updateFlashCounters()
    {
        // __flash|a:1:{s:7:"success";i:1;}__id|i:32;__returnUrl|s:51:"http://backend.memory.o/console/official-card/index";success|a:1:{i:0;s:19:"修改卡片成功!";}
        // $counters = ['success' => 1]
        // 获取flash消息数据
        $counters = $this->get($this->flashParam, []);
        if (is_array($counters)) {
            // 遍历数组
            foreach ($counters as $key => $count) {
                // $key = "success"
                // $count = 1
                if ($count > 0) {
                    unset($counters[$key], $_SESSION[$key]);
                    // $counters = []
                    // $_SESSION = __flash|a:0:{}__id|i:32;__returnUrl|s:51:"http://backend.memory.o/console/official-card/index";
                } elseif ($count == 0) {
                    $counters[$key]++;
                }
            }
            $_SESSION[$this->flashParam] = $counters;
        } else {
            // fix the unexpected problem that flashParam doesn't return an array
            // 修正了flash param没有返回数组的意外问题
            unset($_SESSION[$this->flashParam]);
        }
    }

    /**
     * Flash数据是一种特别的session数据，它一旦在某个请求中设置后， 只会在下次请求中有效，然后该数据就会自动被删除。
     * 常用于实现只需显示给终端用户一次的信息， 如用户提交一个表单后显示确认信息。
     * 可通过session应用组件设置或访问session，例如：
        ```
        $session = Yii::$app->session;

        // 请求 #1
        // 设置一个名为"postDeleted" flash 信息
        $session->setFlash('postDeleted', 'You have successfully deleted your post.');

        // 请求 #2
        // 显示名为"postDeleted" flash 信息
        echo $session->getFlash('postDeleted');

        // 请求 #3
        // $result 为 false，因为flash信息已被自动删除
        $result = $session->hasFlash('postDeleted');
     *  ```
     *
     * 返回一个flash消息
     * Returns a flash message.
     * @param string $key the key identifying the flash message
     * @param mixed $defaultValue value to be returned if the flash message does not exist.
     * 
     * 在这个方法被调用之后是否要删除这个flash消息,如果是false，将在下一个请求中自动删除flash消息。
     * @param bool $delete whether to delete this flash message right after this method is called.
     * If false, the flash message will be automatically deleted in the next request.
     *
     * 如果使用了addFlash，那么将会返回一条或一组flash消息
     * @return mixed the flash message or an array of messages if addFlash was used
     * @see setFlash()
     * @see addFlash()
     * @see hasFlash()
     * @see getAllFlashes()
     * @see removeFlash()
     */
    public function getFlash($key, $defaultValue = null, $delete = false)
    {
        // 获取flash计数器
        $counters = $this->get($this->flashParam, []);
        if (isset($counters[$key])) {
            // 若存在指定flash消息，获取flash消息的值
            $value = $this->get($key, $defaultValue);
            if ($delete) {
                // 若指定删除消息
                $this->removeFlash($key);
            } elseif ($counters[$key] < 0) {
                // mark for deletion in the next request
                // 标记该flash,在下一次请求中删除
                $counters[$key] = 1;
                $_SESSION[$this->flashParam] = $counters;
            }

            return $value;
        }

        // 若不存在指定的flash消息，则返回默认值
        return $defaultValue;
    }

    /**
     * 返回所有的flash消息
     * Returns all flash messages.
     *
     * You may use this method to display all the flash messages in a view file:
     * 您可以使用该方法在视图文件中显示所有的flash消息：
     *
     * ```php
     * <?php
     * foreach (Yii::$app->session->getAllFlashes() as $key => $message) {
     *     echo '<div class="alert alert-' . $key . '">' . $message . '</div>';
     * } ?>
     * ```
     * 关于上面的代码，你可以使用[bootstrap alert][]类，如`success`, `info`, `danger`作为影响div颜色的flash消息键。
     * 注意，如果你使用addFlash()，$message将是一个数组，你将不得不调整上面的代码。
     *
     * With the above code you can use the [bootstrap alert][] classes such as `success`, `info`, `danger`
     * as the flash message key to influence the color of the div.
     *
     * Note that if you use [[addFlash()]], `$message` will be an array, and you will have to adjust the above code.
     *
     * [bootstrap alert]: http://getbootstrap.com/components/#alerts
     *
     * 是否在调用该方法之后删除flash消息。如果是false，将在下一个请求中自动删除flash消息。
     * @param bool $delete whether to delete the flash messages right after this method is called.
     * If false, the flash messages will be automatically deleted in the next request.
     * @return array flash messages (key => message or key => [message1, message2]).
     * @see setFlash()
     * @see addFlash()
     * @see getFlash()
     * @see hasFlash()
     * @see removeFlash()
     */
    public function getAllFlashes($delete = false)
    {
        // 获得flash消息计数器
        $counters = $this->get($this->flashParam, []);
        $flashes = [];
        // 遍历flash消息计数器所有的键
        foreach (array_keys($counters) as $key) {
            // 如果存在相应的flash消息
            if (array_key_exists($key, $_SESSION)) {
                $flashes[$key] = $_SESSION[$key];
                if ($delete) {
                    // 若指定删除flash消息，则直接删除计数器和flash消息
                    unset($counters[$key], $_SESSION[$key]);
                } elseif ($counters[$key] < 0) {
                    // mark for deletion in the next request
                    // 标记该消息，将在下一个请求中删除。
                    $counters[$key] = 1;
                }
            } else {
                unset($counters[$key]);
            }
        }

        $_SESSION[$this->flashParam] = $counters;

        return $flashes;
    }

    /**
     *
     * 设置一个flash消息,会自动覆盖相同名的已存在的任何数据.
     * 在请求中访问一个flash消息后，将在下一次请求中自动删除该flash消息。
     * Sets a flash message.
     * A flash message will be automatically deleted after it is accessed in a request and the deletion will happen
     * in the next request.
     * If there is already an existing flash message with the same key, it will be overwritten by the new one.
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space. If you have a normal
     * session variable using the same name, its value will be overwritten by this method.
     * @param mixed $value flash message
     * 是否只有在访问后才会自动删除flash消息。
     * 如果是false，则在下一次请求之后自动删除flash消息，不管是否被访问。
     * 如果是true(默认值)，flash消息将一直保留到它被访问之后才会被自动删除。
     * @param bool $removeAfterAccess whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     * @see getFlash()
     * @see addFlash()
     * @see removeFlash()
     */
    public function setFlash($key, $value = true, $removeAfterAccess = true)
    {
        // 获取计数器
        $counters = $this->get($this->flashParam, []);

        /**
         * 是否只有在访问后才会自动删除flash消息。
         *
         * 关于 $counters[$key]：
         * -1 : 经过`getFlash()`,`getAllFlashes()`访问后会被置为 1。
         * 0 : 经过 'updateFlashCounters()' 后会被置为 1。
         * 1 : 'updateFlashCounters()' 时会被立即删除
         */
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        // 设置 session 变量值
        $_SESSION[$key] = $value;
        // 设置 flash消息计数器变量值
        $_SESSION[$this->flashParam] = $counters;
    }

    /**
     * 添加一个flash消息。
     * 经过addFlash()之后的flash消息会变为数组
     *
     * 如果现有的 flash消息 具有相同的key，那么新的消息将被追加到现有的消息 数组 中。
     * Adds a flash message.
     * If there are existing flash messages with the same key, the new one will be appended to the existing message array.
     * @param string $key the key identifying the flash message.
     * @param mixed $value flash message
     * 
     * 是否只有在访问后才会自动删除flash消息。
     * 如果是false，则在下一次请求之后自动删除flash消息，不管是否被访问。
     * 如果是true(默认值)，flash消息将一直保留到它被访问之后才会被自动删除。
     * @param bool $removeAfterAccess whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     * @see getFlash()
     * @see setFlash()
     * @see removeFlash()
     */
    public function addFlash($key, $value = true, $removeAfterAccess = true)
    {
        // 获取计数器
        $counters = $this->get($this->flashParam, []);
        // 是否只有在访问后才会自动删除flash消息。
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        // 设置 flash消息计数器变量值
        $_SESSION[$this->flashParam] = $counters;

        if (empty($_SESSION[$key])) {
            // 若该flash消息为空，则添加一个flash消息
            $_SESSION[$key] = [$value];
        } else {
            // 若flash消息是数组，则将指定值添加到数组后
            if (is_array($_SESSION[$key])) {
                $_SESSION[$key][] = $value;
            } else {
                // 若 flash消息不是数组，则转为数组
                $_SESSION[$key] = [$_SESSION[$key], $value];
            }
        }
    }

    /**
     * 删除一个flash消息。
     * Removes a flash message.
     * 识别flash消息的键.
     * 注意，flash消息和普通会话变量共享相同的名称空间.
     * 如果有一个使用相同名称的普通会话变量，它将被这个方法删除.
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space.  If you have a normal
     * session variable using the same name, it will be removed by this method.
     *
     * 返回删除的 flash消息。如果不存在，则返回 Null。
     * @return mixed the removed flash message. Null if the flash message does not exist.
     * @see getFlash()
     * @see setFlash()
     * @see addFlash()
     * @see removeAllFlashes()
     */
    public function removeFlash($key)
    {
        // 获取flash消息计数器
        $counters = $this->get($this->flashParam, []);
        // 若flash消息计数器和flash消息主体都存在，则获取flash消息值
        $value = isset($_SESSION[$key], $counters[$key]) ? $_SESSION[$key] : null;
        // 删除flash消息计数器和flash消息主体
        unset($counters[$key], $_SESSION[$key]);
        // 会话记录空的flash消息计数器
        $_SESSION[$this->flashParam] = $counters;

        // 返回被删除的flash消息值
        return $value;
    }

    /**
     * 删除所有flash消息。
     * 注意，flash消息和普通会话变量共享相同的名称空间.
     * 如果有一个使用相同名称的普通会话变量，它将被这个方法删除
     * Removes all flash messages.
     * Note that flash messages and normal session variables share the same name space.
     * If you have a normal session variable using the same name, it will be removed
     * by this method.
     * @see getFlash()
     * @see setFlash()
     * @see addFlash()
     * @see removeFlash()
     */
    public function removeAllFlashes()
    {
        // 获取flash消息计数器
        $counters = $this->get($this->flashParam, []);
        // 遍历 flash消息计数器，依次删除flash消息
        foreach (array_keys($counters) as $key) {
            unset($_SESSION[$key]);
        }
        // 删除会话中的flash计数器
        unset($_SESSION[$this->flashParam]);
    }

    /**
     * 判断是否有与指定键相关联的flash消息。
     * Returns a value indicating whether there are flash messages associated with the specified key.
     * @param string $key key identifying the flash message type
     * @return bool whether any flash messages exist under specified key
     */
    public function hasFlash($key)
    {
        return $this->getFlash($key) !== null;
    }

    /**
     * 这个方法是实现[[\ArrayAccess]]接口所必需的
     * This method is required by the interface [[\ArrayAccess]].
     * @param mixed $offset the offset to check on
     * @return bool
     */
    public function offsetExists($offset)
    {
        $this->open();

        return isset($_SESSION[$offset]);
    }

    /**
     * 这个方法是实现[[\ArrayAccess]]接口所必需的
     * This method is required by the interface [[\ArrayAccess]].
     * @param int $offset the offset to retrieve element.
     * @return mixed the element at the offset, null if no element is found at the offset
     */
    public function offsetGet($offset)
    {
        $this->open();

        return isset($_SESSION[$offset]) ? $_SESSION[$offset] : null;
    }

    /**
     * 这个方法是实现[[\ArrayAccess]]接口所必需的
     * This method is required by the interface [[\ArrayAccess]].
     * @param int $offset the offset to set element
     * @param mixed $item the element value
     */
    public function offsetSet($offset, $item)
    {
        $this->open();
        $_SESSION[$offset] = $item;
    }

    /**
     * 这个方法是实现[[\ArrayAccess]]接口所必需的
     * This method is required by the interface [[\ArrayAccess]].
     * @param mixed $offset the offset to unset element
     */
    public function offsetUnset($offset)
    {
        $this->open();
        unset($_SESSION[$offset]);
    }

    /**
     * If session is started it's not possible to edit session ini settings. In PHP7.2+ it throws exception.
     * This function saves session data to temporary variable and stop session.
     * @since 2.0.14
     */
    protected function freeze()
    {
        if ($this->getIsActive()) {
            if (isset($_SESSION)) {
                $this->frozenSessionData = $_SESSION;
            }
            $this->close();
            Yii::info('Session frozen', __METHOD__);
        }
    }

    /**
     * Starts session and restores data from temporary variable
     * @since 2.0.14
     */
    protected function unfreeze()
    {
        if (null !== $this->frozenSessionData) {

            YII_DEBUG ? session_start() : @session_start();

            if ($this->getIsActive()) {
                Yii::info('Session unfrozen', __METHOD__);
            } else {
                $error = error_get_last();
                $message = isset($error['message']) ? $error['message'] : 'Failed to unfreeze session.';
                Yii::error($message, __METHOD__);
            }

            $_SESSION = $this->frozenSessionData;
            $this->frozenSessionData = null;
        }
    }

    /**
     * Set cache limiter
     *
     * @param string $cacheLimiter
     * @since 2.0.14
     */
    public function setCacheLimiter($cacheLimiter)
    {
        $this->freeze();
        session_cache_limiter($cacheLimiter);
        $this->unfreeze();
    }

    /**
     * Returns current cache limiter
     *
     * @return string current cache limiter
     * @since 2.0.14
     */
    public function getCacheLimiter()
    {
        return session_cache_limiter();
    }
}
