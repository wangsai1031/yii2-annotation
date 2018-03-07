<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidValueException;
use yii\rbac\CheckAccessInterface;

/**
 * 注意：不要混淆 user 认证类和用户组件 yii\web\User。
 * 前者是实现 认证逻辑的类，通常用关联了 持久性存储的用户信息的AR模型 Active Record 实现。
 * 后者是负责管理用户认证状态的 应用组件。
 *
 *
 * User is the class for the `user` application component that manages the user authentication status.
 * User是用来管理用户认证状态的应用组件。
 *
 * You may use [[isGuest]] to determine whether the current user is a guest or not.
 * 你可以使用isGuest来判断当前的用户是否为游客。
 *
 * If the user is a guest, the [[identity]] property would return `null`. Otherwise, it would
 * be an instance of [[IdentityInterface]].
 * 如果该用户是游客，属性identity就会返回null。否则就会返回IdentityInterface的实例。
 *
 * You may call various methods to change the user authentication status:
 * 你可以调用多种方法来改变用户认证状态：
 *
 * - [[login()]]: sets the specified identity and remembers the authentication status in session and cookie;
 * - [[login()]]: 设置指定的身份，并把认证状态写入到session和cookie之中；
 *
 * - [[logout()]]: marks the user as a guest and clears the relevant information from session and cookie;
 * - [[logout()]]: 把用户标记为游客，并清除相关的session和cookie信息；
 *
 * - [[setIdentity()]]: changes the user identity without touching session or cookie
 *   (this is best used in stateless RESTful API implementation).
 * - [[setIdentity()]]: 改变用户的身份，但并不创建session或者cookie（这个最好用于无状态的RESTful API）
 *
 * Note that User only maintains the user authentication status. It does NOT handle how to authenticate
 * a user. The logic of how to authenticate a user should be done in the class implementing [[IdentityInterface]].
 * 请注意，User只是保存了用户的认证状态，并没有处理如何认证一个用户。认证用户的逻辑应该在实现了[[IdentityInterface]]接口的类中完成。
 *
 * You are also required to set [[identityClass]] with the name of this class.
 * 您必须把[[identityClass]]设置为该类的名字。
 *
 * User is configured as an application component in [[\yii\web\Application]] by default.
 * You can access that instance via `Yii::$app->user`.
 * User默认通过[[\yii\web\Application]]应用组件进行配置。你可以通过`Yii::$app->user`访问该实例。
 *
 * You can modify its configuration by adding an array to your application config under `components`
 * as it is shown in the following example:
 * 你可以参考如下的内容，通过在components应用配置中增加一个数组来改变它的配置：
 *  
 * User类必须实现IdentityInterface
 * ```php
 * 'user' => [
 *     'identityClass' => 'app\models\User', // User must implement the IdentityInterface
 *     'enableAutoLogin' => true,
 *     // 'loginUrl' => ['user/login'],
 *     // ...
 * ]
 * ```
 *
 * @property string|int $id The unique identifier for the user. If `null`, it means the user is a guest. This
 * property is read-only.
 * 属性 字符串|整型 区分用户的唯一表示。如果为空，意味这当前用户是游客身份。 该属性只读
 *
 * @property IdentityInterface|null $identity The identity object associated with the currently logged-in
 * user. `null` is returned if the user is not logged in (not authenticated).
 * 属性 跟当前登陆用户相关联的认证对象。如果用户没有登陆（没有认证），那么就会返回null。
 * 
 * @property bool $isGuest Whether the current user is a guest. This property is read-only.
 * 属性 boolean 当前的用户是否为游客。该属性只读
 * 
 * @property string $returnUrl The URL that the user should be redirected to after login. Note that the type
 * of this property differs in getter and setter. See [[getReturnUrl()]] and [[setReturnUrl()]] for details.
 * 属性 字符串 用户登陆以后重定向的链接。请注意，该属性的类型在调用getter和setter有所不同。详情请参考[[getReturnUrl()]] 和 [[setReturnUrl()]]。
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class User extends Component
{
    /**
     * 认证事件

        yii\web\User 类在登录和注销流程引发一些事件。

        yii\web\User::EVENT_BEFORE_LOGIN：在登录 yii\web\User::login() 时引发。
     * 如果事件句柄将事件对象的 yii\web\UserEvent::isValid 属性设为 false， 登录流程将会被取消。
        yii\web\User::EVENT_AFTER_LOGIN：登录成功后引发。
        yii\web\User::EVENT_BEFORE_LOGOUT：注销 yii\web\User::logout() 前引发。
     * 如果事件句柄将事件对象的 yii\web\UserEvent::isValid 属性设为 false， 注销流程将会被取消。
        yii\web\User::EVENT_AFTER_LOGOUT：成功注销后引发。
        你可以通过响应这些事件来实现一些类似登录统计、在线人数统计的功能。
     * 例如, 在登录后 yii\web\User::EVENT_AFTER_LOGIN 的处理程序，你可以将用户的登录时间和IP记录到 user 表中。
     *
     */
    const EVENT_BEFORE_LOGIN = 'beforeLogin';
    const EVENT_AFTER_LOGIN = 'afterLogin';
    const EVENT_BEFORE_LOGOUT = 'beforeLogout';
    const EVENT_AFTER_LOGOUT = 'afterLogout';

    /**
     * @var string the class name of the [[identity]] object.
     * 变量 字符串 [[identity]]对象的类名。
     */
    public $identityClass;
    /**
     * @var bool whether to enable cookie-based login. Defaults to `false`.
     * 变量 boolean 是否允许基于cookie的自动登陆。默认是false
     * Note that this property will be ignored if [[enableSession]] is `false`.
     * 请注意当[[enableSession]]为false的时候，该属性会被忽略
     */
    public $enableAutoLogin = false;
    /**
     * @var bool whether to use session to persist authentication status across multiple requests.
     * 在多次请求之间是否启用session持久化认证状态
     * 
     * You set this property to be `false` if your application is stateless, which is often the case
     * for RESTful APIs.
     * 你可以把该属性设置为false，如果你的应用是无状态的。经常用于RESTful APIs。
     */
    public $enableSession = true;
    /**
     * @var string|array the URL for login when [[loginRequired()]] is called.
     * 变量 字符串|数组  当[[loginRequired()]]被调用时，登陆的URL。
     * If an array is given, [[UrlManager::createUrl()]] will be called to create the corresponding URL.
     * The first element of the array should be the route to the login action, and the rest of
     * the name-value pairs are GET parameters used to construct the login URL. For example,
     * 如果是一个数组，将会调用[[UrlManager::createUrl()]]方法去生成相应的url。该数组的第一个元素应该是登陆动作的路由，剩余的键值对用来组成登陆url的
     * get参数，例如
     *
     * ```php
     * ['site/login', 'ref' => 1]
     * ```
     *
     * If this property is `null`, a 403 HTTP exception will be raised when [[loginRequired()]] is called.
     * 如果该属性为null，当调用[[loginRequired()]]时就会抛出HTTP403异常。
     */
    public $loginUrl = ['site/login'];
    /**
     * @var array the configuration of the identity cookie. This property is used only when [[enableAutoLogin]] is `true`.
     * 变量 数组 认证cookie的配置项。该属性只能在[[enableAutoLogin]]为true的时候才会使用
     * @see Cookie
     */
    public $identityCookie = ['name' => '_identity', 'httpOnly' => true];
    /**
     * @var int the number of seconds in which the user will be logged out automatically if he
     * remains inactive. If this property is not set, the user will be logged out after
     * the current session expires (c.f. [[Session::timeout]]).
     * 当用户停留多少秒没有操作之后会被自动退出系统。如果该属性没有设置，用户会在当前的session过期以后退出。
     *
     * Note that this will not work if [[enableAutoLogin]] is `true`.
     * 请注意，当[[enableAutoLogin]]为true的时候，该属性不会生效
     */
    public $authTimeout;
    /**
     * @var CheckAccessInterface The access checker to use for checking access.
     * 用于检查访问权限的的访问检测器。
     * 需实现 CheckAccessInterface 接口
     *
     * If not set the application auth manager will be used.
     * 如果没设置，默认将使用应用程序AuthManager
     * @since 2.0.9
     */
    public $accessChecker;
    /**
     * @var int the number of seconds in which the user will be logged out automatically
     * regardless of activity.
     * 用户在多少秒之后会被自动退出系统，不考虑用户是否处于活动状态。
     * 
     * Note that this will not work if [[enableAutoLogin]] is `true`.
     * 请注意，如果[[enableAutoLogin]]为true的时候，该属性无效。
     */
    public $absoluteAuthTimeout;
    /**
     * @var bool whether to automatically renew the identity cookie each time a page is requested.
     * 是否在每次请求页面时自动刷新认证cookie
     * 
     * This property is effective only when [[enableAutoLogin]] is `true`.
     * 该属性只有在[[enableAutoLogin]]为true的时候才生效。
     *
     * When this is `false`, the identity cookie will expire after the specified duration since the user
     * is initially logged in. When this is `true`, the identity cookie will expire after the specified duration
     * since the user visits the site the last time.
     * 当该属性为false时，认证的cookie会在用户初始登陆一段时间以后过期。当该属性为true的时，认证cookie会在用户最后一次访问网站一段时间以后过期。
     * @see enableAutoLogin
     */
    public $autoRenewCookie = true;
    /**
     * @var string the session variable name used to store the value of [[id]].
     * 变量 字符串 用来保存[[id]]的session变量名。
     */
    public $idParam = '__id';
    /**
     * @var string the session variable name used to store the value of expiration timestamp of the authenticated state.
     * This is used when [[authTimeout]] is set.
     * 用来保存认证状态失效的时间戳的session变量名。当[[authTimeout]]设置的时候才会采用该属性。
     */
    public $authTimeoutParam = '__expire';
    /**
     * @var string the session variable name used to store the value of absolute expiration timestamp of the authenticated state.
     * 变量 字符串 用来保存认证状态绝对失效时间戳的session变量名。
     *
     * This is used when [[absoluteAuthTimeout]] is set.
     * 当设置了[[absoluteAuthTimeout]]属性时，使用该属性。
     */
    public $absoluteAuthTimeoutParam = '__absoluteExpire';
    /**
     * @var string the session variable name used to store the value of [[returnUrl]].
     * 变量 字符串 用来保存跳转url的session变量名
     */
    public $returnUrlParam = '__returnUrl';
    /**
     * @var array MIME types for which this component should redirect to the [[loginUrl]].
     * 变量 数组  该组件重定向到[[loginUrl]]的MIME类型
     * @since 2.0.8
     */
    public $acceptableRedirectTypes = ['text/html', 'application/xhtml+xml'];

    // 权限存储
    private $_access = [];


    /**
     * Initializes the application component.
     * 初始化应用组件
     */
    public function init()
    {
        parent::init();

        // 身份验证类必须设置
        if ($this->identityClass === null) {
            throw new InvalidConfigException('User::identityClass must be set.');
        }
        // 若允许自动登录，则identityCookie 数组中必须以后 name 元素
        if ($this->enableAutoLogin && !isset($this->identityCookie['name'])) {
            throw new InvalidConfigException('User::identityCookie must contain the "name" element.');
        }
        if (!empty($this->accessChecker) && is_string($this->accessChecker)) {
            $this->accessChecker = Yii::createObject($this->accessChecker);
        }
    }

    private $_identity = false;

    /**
     * Returns the identity object associated with the currently logged-in user.
     * 返回跟当前登录用户相关的身份对象。
     *
     * When [[enableSession]] is true, this method may attempt to read the user's authentication data
     * stored in session and reconstruct the corresponding identity object, if it has not done so before.
     * 当[[enableSession]]为true的时候，该方法会尝试读取保存在session里边的用户数据，并重新构建相应的认证对象，如果之前没有进行过类似操作
     *
     * @param bool $autoRenew whether to automatically renew authentication status if it has not been done so before.
     * This is only useful when [[enableSession]] is true.
     * 参数 boolean 如果之前没有创建认证状态是否自动新建认证状态。
     * 
     * @return IdentityInterface|null the identity object associated with the currently logged-in user.
     * `null` is returned if the user is not logged in (not authenticated).
     * 返回值 IdentityInterface或null 跟当前登陆用户相关的认证对象。如果用户没有登陆（没有认证），就会返回null
     *
     * @see login()
     * @see logout()
     *
     *  当前用户的身份实例。未认证用户则为 Null 。
        $identity = Yii::$app->user->identity;
     */
    public function getIdentity($autoRenew = true)
    {
        if ($this->_identity === false) {
            // 若 启用session持久化认证，并且允许 自动新建认证状态。
            if ($this->enableSession && $autoRenew) {
                try {
                    // 将$this->_identity置为null,
                    // 后面会调用 $this->setIdentity($identity)方法，可能会重新赋值
                    $this->_identity = null;
                    $this->renewAuthStatus();
                } catch (\Exception $e) {
                    $this->_identity = false;
                    throw $e;
                } catch (\Throwable $e) {
                    $this->_identity = false;
                    throw $e;
                }
            } else {
                return null;
            }
        }

        return $this->_identity;
    }

    /**
     * Sets the user identity object.
     * 设置用户身份对象
     *
     * Note that this method does not deal with session or cookie. You should usually use [[switchIdentity()]]
     * to change the identity of the current user.
     * 请注意，该方法不会处理session或cookie，你应该经常使用[[switchIdentity()]]去改变当前用户的认证。
     *
     * @param IdentityInterface|null $identity the identity object associated with the currently logged user.
     * If null, it means the current user will be a guest without any associated identity.
     * 参数 IdentityInterface|null 跟当前登陆用户相关的认证对象。如果为null，意味这当前用户是没有任何认证的游客。
     *
     * @throws InvalidValueException if `$identity` object does not implement [[IdentityInterface]].
     * 当对象没有实现IdentityInterface接口时，抛出不合法的数据异常。
     */
    public function setIdentity($identity)
    {
        // $identity 是 IdentityInterface 实例
        if ($identity instanceof IdentityInterface) {
            // 将用户身份对象赋值给 $this->_identity
            $this->_identity = $identity;
            // 将权限置空
            $this->_access = [];
        } elseif ($identity === null) {
            $this->_identity = null;
        } else {
            throw new InvalidValueException('The identity object must implement IdentityInterface.');
        }
    }

    /**
     * Logs in a user.
     * 用户登陆
     * 
     * 用户登陆以后，你可以从[[identity]]属性中获取用户的认证信息。如果[[enableSession]]为true的时候，你甚至可以不必再次调用该方法而在下次
     * 请求中获取认证信息。
     * 登陆状态保存的事件根据`$duration`而定：
     * 
     * 如果[[enableSession]]为 `true`:
     * - `$duration == 0`: 认证信息将会保存在session中，并且一直有效的，直到session失效为止
     * - `$duration > 0`:认证信息会被保存到session中。如果[[enableAutoLogin]]为true，还会被保存在`$duration`决定长度的cookie中。只要cookie
     * 和session有效，你就可以通过[[identity]]获取用户信息。
     *
     * 如果[[enableSession]]为 `false`，
     * - `$duration`参数会被忽略，因为在此种情况下它没有意义。
     * 
     * After logging in a user:
     * - the user's identity information is obtainable from the [[identity]] property
     *
     * If [[enableSession]] is `true`:
     * - the identity information will be stored in session and be available in the next requests
     * - in case of `$duration == 0`: as long as the session remains active or till the user closes the browser
     * - in case of `$duration > 0`: as long as the session remains active or as long as the cookie
     *   remains valid by it's `$duration` in seconds when [[enableAutoLogin]] is set `true`.
     *
     * If [[enableSession]] is `false`:
     * - the `$duration` parameter will be ignored
     * 
     * @param IdentityInterface $identity the user identity (which should already be authenticated)
     * @param int $duration number of seconds that the user can remain in logged-in status, defaults to `0`
     * @return bool whether the user is logged in
     * 返回值 boolean 用户是否登陆过。
     *
     * //使用指定用户名获取用户身份实例。
     * //请注意，如果需要的话您可能要检验密码
     * $identity = User::findOne(['username' => $username]);
     * 
     * //登录用户
     * Yii::$app->user->login($identity);
     *
     * yii\web\User::login() 方法将当前用户的身份登记到 yii\web\User。
     * 如果 session 设置为 yii\web\User::enableSession，
     * 则使用 session 记录用户身份，用户的 认证状态将在整个会话中得以维持。
     *
     * 如果开启自动登录 yii\web\User::enableAutoLogin 则基于 cookie 登录（如：记住登录状态），
     * 它将使用 cookie 保存用户身份，这样 只要 cookie 有效就可以恢复登录状态。
     *
     * 为了使用 cookie 登录，你需要在应用配置文件中将 yii\web\User::enableAutoLogin 设为 true。
     * 你还需要在 yii\web\User::login() 方法中 传递有效期（记住登录状态的时长）参数。
     */
    public function login(IdentityInterface $identity, $duration = 0)
    {
        // 执行 beforeLogin 事件
        if ($this->beforeLogin($identity, false, $duration)) {
            // (实现用户登录的主要方法)清除残留的原用户登录状态数据，设置新用户的cookie,session 等
            $this->switchIdentity($identity, $duration);
            // 用户ID
            $id = $identity->getId();
            // 用户IP
            $ip = Yii::$app->getRequest()->getUserIP();
            // 记录日志
            if ($this->enableSession) {
                $log = "User '$id' logged in from $ip with duration $duration.";
            } else {
                $log = "User '$id' logged in from $ip. Session not enabled.";
            }
            Yii::info($log, __METHOD__);
            // 触发 afterLogin 事件
            $this->afterLogin($identity, false, $duration);
        }

        // 返回用户登录状态（非游客）
        return !$this->getIsGuest();
    }

    /**
     * Logs in a user by the given access token.
     * 通过给定的access token登陆用户
     *
     * This method will first authenticate the user by calling [[IdentityInterface::findIdentityByAccessToken()]]
     * with the provided access token. If successful, it will call [[login()]] to log in the authenticated user.
     * 该方法首先使用给定的access token调用[[IdentityInterface::findIdentityByAccessToken()]]认证用户。如果成功，就会调用[[login()]]
     * 登陆已经认证的用户。
     *
     * If authentication fails or [[login()]] is unsuccessful, it will return null.
     * 如果认证失败，或者[[login()]]不成功，将会返回null
     *
     * @param string $token the access token
     * 参数 字符串 access token
     *
     * @param mixed $type the type of the token. The value of this parameter depends on the implementation.
     * For example, [[\yii\filters\auth\HttpBearerAuth]] will set this parameter to be `yii\filters\auth\HttpBearerAuth`.
     * 参数 混合型 令牌的类型，该值取决于对接口的实现。例如[[\yii\filters\auth\HttpBearerAuth]]将会把该值设置为`yii\filters\auth\HttpBearerAuth`
     *
     * @return IdentityInterface|null the identity associated with the given access token. Null is returned if
     * the access token is invalid or [[login()]] is unsuccessful.
     * 返回值 跟给定的access token相关的认证。如果access token不合法或者登陆失败，就会返回null
     */
    public function loginByAccessToken($token, $type = null)
    {
        /* @var $class IdentityInterface */
        $class = $this->identityClass;
        // 根据指定的存取令牌查找 用户身份认证模型类的实例
        $identity = $class::findIdentityByAccessToken($token, $type);
        // 若找到该用户身份对象，则执行登录，并返回用户身份对象，否则返回空
        if ($identity && $this->login($identity)) {
            return $identity;
        }

        return null;
    }

    /**
     * Logs in a user by cookie.
     * 使用cookie进行用户登陆
     *
     * This method attempts to log in a user using the ID and authKey information
     * provided by the [[identityCookie|identity cookie]].
     * 该方法尝试使用[[identityCookie|identity cookie]]提供的ID和认证信息登陆用户。
     */
    protected function loginByCookie()
    {
        // 确定身份认证cookie是否具有有效的格式，并包含有效的身份验证密钥
        // $data = ['identity' => $identity, 'duration' => $duration]
        $data = $this->getIdentityAndDurationFromCookie();
        if (isset($data['identity'], $data['duration'])) {
            // $data 为有效格式，则将身份对象和有效期赋值给变量
            $identity = $data['identity'];
            $duration = $data['duration'];
            // 触发 beforeLogin 事件，是否允许继续登录
            if ($this->beforeLogin($identity, true, $duration)) {
                // (实现用户登录的主要方法)清除残留的原用户登录状态数据，设置新用户的cookie,session 等
                $this->switchIdentity($identity, $this->autoRenewCookie ? $duration : 0);
                // 记录日志
                $id = $identity->getId();
                $ip = Yii::$app->getRequest()->getUserIP();
                Yii::info("User '$id' logged in from $ip via cookie.", __METHOD__);
                // 触发 afterLogin 事件
                $this->afterLogin($identity, true, $duration);
            }
        }
    }

    /**
     * Logs out the current user.
     * 注销当前用户
     *
     * This will remove authentication-related session data.
     * 该方法会删除跟认证相关的session数据。
     *
     * If `$destroySession` is true, all session data will be removed.
     * 如果`$destroySession`为true，所有的session数据都会被删除。
     * 
     * @param bool $destroySession whether to destroy the whole session. Defaults to true.
     * This parameter is ignored if [[enableSession]] is false.
     * 参数 boolean 是否销毁所有的session。默认是true。当[[enableSession]]为false的时候，该参数会被忽略。
     * 
     * @return bool whether the user is logged out
     * 返回值 boolean 当前用户是否成功退出。
     *
     * 请注意，启用 session 时注销用户才有意义。
     * 该方法将从内存和 session 中 同时清理用户认证状态。
     * 默认情况下，它还会注销所有的 用户会话数据。
     * 如果你希望保留这些会话数据，可以换成 Yii::$app->user->logout(false) 。
     */
    public function logout($destroySession = true)
    {
        // 获取用户身份对象
        $identity = $this->getIdentity();

        // 身份对象不为空，并且触发 before login 事件后 允许继续退出
        if ($identity !== null && $this->beforeLogout($identity)) {
            // 注销用户
            $this->switchIdentity(null);
            // 获取用户ID
            $id = $identity->getId();
            // 用户IP地址
            $ip = Yii::$app->getRequest()->getUserIP();
            // 记录日志
            Yii::info("User '$id' logged out from $ip.", __METHOD__);
            if ($destroySession && $this->enableSession) {
                // 销毁所有的session
                Yii::$app->getSession()->destroy();
            }
            // 触发 afterLogout 事件
            $this->afterLogout($identity);
        }
        // 返回用户是否是一个游客（未登录状态）
        return $this->getIsGuest();
    }

    /**
     * Returns a value indicating whether the user is a guest (not authenticated).
     * 判断用户是否为游客（非登录状态）。
     * 
     * @return bool whether the current user is a guest.
     * 返回值 boolean 当前用户是否登陆
     * 
     * @see getIdentity()
     * 
     *
     *  判断当前用户是否是游客（未认证的）
     * $isGuest = Yii::$app->user->isGuest;
     */
    public function getIsGuest()
    {
        return $this->getIdentity() === null;
    }

    /**
     * Returns a value that uniquely represents the user.
     * 返回唯一代表用户的一个值。
     * 
     * @return string|int the unique identifier for the user. If `null`, it means the user is a guest.
     * 返回值 字符串| 整型 用户的唯一认证。如果为null，意味着当前用户没有登陆。
     * 
     * @see getIdentity()
     */
    public function getId()
    {
        $identity = $this->getIdentity();

        return $identity !== null ? $identity->getId() : null;
    }

    /**
     * Returns the URL that the browser should be redirected to after successful login.
     * 返回登陆成功以后浏览器页面跳转的URL。
     *
     * This method reads the return URL from the session. It is usually used by the login action which
     * may call this method to redirect the browser to where it goes after successful authentication.
     * 该方法从session中读取跳转URL。它经常被登陆动作在登陆成功以后调用，来重定向浏览器。
     *
     * @param string|array $defaultUrl the default return URL in case it was not set previously.
     * 参数 字符串|数组 默认的跳转URL，以防没有之前没有设置。
     *
     * If this is null and the return URL was not set previously, [[Application::homeUrl]] will be redirected to.
     * Please refer to [[setReturnUrl()]] on accepted format of the URL.
     * 如果为null，或者之前没有设置，将会跳转到[[Application::homeUrl]]。关于可接受的URL格式，请参考[[setReturnUrl()]]
     *
     * @return string the URL that the user should be redirected to after login.
     * 返回值 字符串 用户登陆成功以后被重定向的页面。
     *
     * @see loginRequired()
     */
    public function getReturnUrl($defaultUrl = null)
    {
        // 从会话中获取 returnUrl，若不存在，则使用默认值
        $url = Yii::$app->getSession()->get($this->returnUrlParam, $defaultUrl);
        if (is_array($url)) {
            // 如果 $url 是索引数组，则返回通过 UrlManager 创建的 $url
            if (isset($url[0])) {
                return Yii::$app->getUrlManager()->createUrl($url);
            }

            // 如果 $url 是关联数组，则url为null
            $url = null;
        }
        // 若$url为空，则返回项目主页地址(一般默认是不带任何路由的域名链接)。
        return $url === null ? Yii::$app->getHomeUrl() : $url;
    }

    /**
     * Remembers the URL in the session so that it can be retrieved back later by [[getReturnUrl()]].
     * 在session中保存URL，以便稍后在[[getReturnUrl()]]方法中取回。
     *
     * @param string|array $url the URL that the user should be redirected to after login.
     * 参数 字符串|设置 用户登陆以后，应该被重定向到的页面。
     *
     * If an array is given, [[UrlManager::createUrl()]] will be called to create the corresponding URL.
     * 如果为数组，会调用[[UrlManager::createUrl()]]方法生成相应的URL。
     *
     * The first element of the array should be the route, and the rest of
     * the name-value pairs are GET parameters used to construct the URL. For example,
     * 数组的第一个元素应该是路由，其他的键值对是用来构建URL的GET参数。例如，
     *
     * ```php
     * ['admin/index', 'ref' => 1]
     * ```
     */
    public function setReturnUrl($url)
    {
        Yii::$app->getSession()->set($this->returnUrlParam, $url);
    }

    /**
     * Redirects the user browser to the login page.
     * 将用户浏览器重定向到登录页面
     *
     * Before the redirection, the current URL (if it's not an AJAX url) will be kept as [[returnUrl]] so that
     * the user browser may be redirected back to the current page after successful login.
     * 重定向以前，当前的URL（非AJAX url）将会保存在[[returnUrl]]属性中，以便用户登陆成功以后可以重定向到用户登陆之前的页面。
     *
     * Make sure you set [[loginUrl]] so that the user browser can be redirected to the specified login URL after
     * calling this method.
     * 确保你设置了[[loginUrl]]，调用此方法以后，用户的浏览器会重定向到指定的登陆URL。
     *
     * Note that when [[loginUrl]] is set, calling this method will NOT terminate the application execution.
     * 请注意，当设置了[[loginUrl]]以后，调用该方法将不会结束应用的执行。
     *
     * @param bool $checkAjax whether to check if the request is an AJAX request. When this is true and the request
     * is an AJAX request, the current URL (for AJAX request) will NOT be set as the return URL.
     * 参数 boolean 是否需要验证当前的请求为ajax请求。该值为true并且请求为ajax时，当前的URL（AJAX 请求）将不会被设置为跳转URL。
     *
     * @param bool $checkAcceptHeader whether to check if the request accepts HTML responses. Defaults to `true`. When this is true and
     * the request does not accept HTML responses the current URL will not be SET as the return URL. Also instead of
     * redirecting the user an ForbiddenHttpException is thrown. This parameter is available since version 2.0.8.
     * 参数 boolean 是否检测请求能否接收HTML响应。默认是true。
     * 当该值为true，但是请求不接收HTML响应时，当前的URL不会被设置为返回URL。
     * 这时不仅不会重定向用户，还会抛出一个ForbiddenHttpException的异常。
     * 该参数在2.0.8版本以后可用。
     * 
     * @return Response the redirection response if [[loginUrl]] is set
     * 返回值 如果[[loginUrl]]被设置了，就返回重定向相应。
     *
     * @throws ForbiddenHttpException the "Access Denied" HTTP exception if [[loginUrl]] is not set or a redirect is
     * not applicable.
     * 如果[[loginUrl]]没有设置或重定向不可用，就会抛出异常ForbiddenHttpException异常
     * @see checkAcceptHeader
     */
    public function loginRequired($checkAjax = true, $checkAcceptHeader = true)
    {
        // 获取请求对象
        $request = Yii::$app->getRequest();
        // 检测请求能否接收HTML响应 || 检测`Accept`头是否包含一个允许重定向到登陆页的content type(内容类型)
        $canRedirect = !$checkAcceptHeader || $this->checkRedirectAcceptable();
        
        // 先存储 returnUrl
        // 启用session
        if ($this->enableSession
            // 是GET请求
            && $request->getIsGet()
            // 不是Ajax请求
            && (!$checkAjax || !$request->getIsAjax())
            && $canRedirect
        ) {
            // 将当前url存储到会话的returnUrl中
            $this->setReturnUrl($request->getUrl());
        }

        // 跳转到登录页面
        if ($this->loginUrl !== null && $canRedirect) {
            $loginUrl = (array) $this->loginUrl;
            // 登录页面路由 不是当前请求的路由（不比较请求参数）
            if ($loginUrl[0] !== Yii::$app->requestedRoute) {
                // 跳转到登录页面
                return Yii::$app->getResponse()->redirect($this->loginUrl);
            }
        }
        throw new ForbiddenHttpException(Yii::t('yii', 'Login Required'));
    }

    /**
     * This method is called before logging in a user.
     * 该方法会在用户登陆以前调用。
     *
     * The default implementation will trigger the [[EVENT_BEFORE_LOGIN]] event.
     * 默认的实现会触发[[EVENT_BEFORE_LOGIN]]事件。
     *
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     * 如果你重写了此方法，为了触发事件，你需要调用父级的实现。
     *
     * @param IdentityInterface $identity the user identity information
     * 参数 用户认证信息
     * 
     * @param bool $cookieBased whether the login is cookie-based
     * 参数 boolean 登陆是否基于cookie
     * 
     * @param int $duration number of seconds that the user can remain in logged-in status.
     * If 0, it means login till the user closes the browser or the session is manually destroyed.
     * 参数 整型 用户可以保持登陆状态的秒数。如果为0，意味这登陆状态持续用户关闭浏览器或session被手动销毁。
     * 
     * @return bool whether the user should continue to be logged in
     * 返回值 boolean 用户是否应该继续登录。
     */
    protected function beforeLogin($identity, $cookieBased, $duration)
    {
        $event = new UserEvent([
            'identity' => $identity,
            'cookieBased' => $cookieBased,
            'duration' => $duration,
        ]);
        $this->trigger(self::EVENT_BEFORE_LOGIN, $event);

        // 可以在绑定的事件中修改该值，以指定是否继续登录
        return $event->isValid;
    }

    /**
     * This method is called after the user is successfully logged in.
     * 该方法会在用户登陆成功之后调用。
     *
     * The default implementation will trigger the [[EVENT_AFTER_LOGIN]] event.
     * 默认的实现会触发[[EVENT_AFTER_LOGIN]]事件。
     *
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     * 如果你重写了该方法，请确保你调用了父类的实现，以保证事件被触发。
     *
     * @param IdentityInterface $identity the user identity information
     * 参数 用户认证信息。
     * 
     * @param bool $cookieBased whether the login is cookie-based
     * 参数 boolean 是否基于cookie登陆用户。
     * 
     * @param int $duration number of seconds that the user can remain in logged-in status.
     * 参数 整型 用户可以保持登陆状态的秒数。
     * 
     * If 0, it means login till the user closes the browser or the session is manually destroyed.
     * 如果为0，意味着登陆状态会持续到用户关闭浏览器或者session被手动的销毁。
     */
    protected function afterLogin($identity, $cookieBased, $duration)
    {
        // 触发 EVENT_AFTER_LOGIN 事件
        // 并且将identity等信息传递给了绑定EVENT_AFTER_LOGIN事件的每一位观察者
        $this->trigger(self::EVENT_AFTER_LOGIN, new UserEvent([
            'identity' => $identity,
            'cookieBased' => $cookieBased,
            'duration' => $duration,
        ]));
    }

    /**
     * This method is invoked when calling [[logout()]] to log out a user.
     * 该方法会在注销用户以前调用。
     *
     * The default implementation will trigger the [[EVENT_BEFORE_LOGOUT]] event.
     * 默认的实现会触发[[EVENT_BEFORE_LOGOUT]]事件。
     *
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     * 如果你重写了此方法，请调用父类的实现，以触发事件。
     *
     * @param IdentityInterface $identity the user identity information
     * 参数 用户认证的信息
     *
     * @return bool whether the user should continue to be logged out
     * 返回值 boolean 用户是否继续退出。
     */
    protected function beforeLogout($identity)
    {
        $event = new UserEvent([
            'identity' => $identity,
        ]);
        $this->trigger(self::EVENT_BEFORE_LOGOUT, $event);

        // 可以在绑定的事件中修改该值，以指定是否继续注销用户登录
        return $event->isValid;
    }

    /**
     * This method is invoked right after a user is logged out via [[logout()]].
     * 该方法会在用户通过[[logout()]]退出以后被调用。
     *
     * The default implementation will trigger the [[EVENT_AFTER_LOGOUT]] event.
     * 默认的实现会触发[[EVENT_AFTER_LOGOUT]]事件
     *
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     * 如果你重写此方法，记得调用一下父级的实现，以触发事件。
     *
     * @param IdentityInterface $identity the user identity information
     * 参数 用户的认证信息。
     */
    protected function afterLogout($identity)
    {
        $this->trigger(self::EVENT_AFTER_LOGOUT, new UserEvent([
            'identity' => $identity,
        ]));
    }

    /**
     * Renews the identity cookie.
     * 更新身份认证cookie。
     * 这个方法把身份认证cookie的过期时间设置为 (当前时间 + 指定的cookie有效期)
     *
     * This method will set the expiration time of the identity cookie to be the current time
     * plus the originally specified cookie duration.
     */
    protected function renewIdentityCookie()
    {
        // Cookie中存储身份认证信息的参数名
        $name = $this->identityCookie['name'];
        // 获取身份认证信息的cookie值：
        // 例：'[32,"K1r5cqGo9D6Rm8eDwBPs1SL4DSRRQhIO",604800]'
        $value = Yii::$app->getRequest()->getCookies()->getValue($name);
        if ($value !== null) {
            // json解码成数组
            $data = json_decode($value, true);
            // 是数组，并且存在第三个元素（cookie有效期）
            if (is_array($data) && isset($data[2])) {
                $cookie = Yii::createObject(array_merge($this->identityCookie, [
                    'class' => 'yii\web\Cookie',
                    'value' => $value,
                    // 重新设置cookie的过期时间
                    'expire' => time() + (int) $data[2],
                ]));
                // 将cookie重新添加到响应中
                Yii::$app->getResponse()->getCookies()->add($cookie);
            }
        }
    }

    /**
     * Sends an identity cookie.
     * 发送身份认证cookie
     *
     * This method is used when [[enableAutoLogin]] is true.
     * 当允许自动登陆的时候调用此方法。
     *
     * It saves [[id]], [[IdentityInterface::getAuthKey()|auth key]], and the duration of cookie-based login
     * information in the cookie.
     * 它把[[id]], [[IdentityInterface::getAuthKey()|auth key]]和基于cookie的登陆信息保存到cookie。
     *
     * @param IdentityInterface $identity
     * @param int $duration number of seconds that the user can remain in logged-in status.
     * 参数 用户可以保持登陆状态的秒数。
     * @see loginByCookie()
     */
    protected function sendIdentityCookie($identity, $duration)
    {
        // 实例化Cookie,并初始化Cookie对象属性参数
        $cookie = Yii::createObject(array_merge($this->identityCookie, [
            'class' => 'yii\web\Cookie',
            'value' => json_encode([
                // 用户ID
                $identity->getId(),
                // 用户AuthKey
                $identity->getAuthKey(),
                // cookie有效期
                $duration,
                // 不要编码 '/' | 以字面编码多字节 Unicode 字符（默认是编码成 \uXXXX）
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'expire' => time() + $duration,
        ]));
        // 将认证 cookie 添加到cookie集合
        Yii::$app->getResponse()->getCookies()->add($cookie);
    }

    /**
     * Determines if an identity cookie has a valid format and contains a valid auth key.
     * 确定身份认证cookie是否具有有效的格式，并包含有效的身份验证密钥
     *
     * This method is used when [[enableAutoLogin]] is true.
     * 当开启自动登陆的时候调用此方法。
     *
     * This method attempts to authenticate a user using the information in the identity cookie.
     * 该方法尝试使用认证cookie里边的信息认证用户。
     *
     * @return array|null Returns an array of 'identity' and 'duration' if valid, otherwise null.
     * 如果合法，返回认证信息和过期事件组成的数组。否则返回null。
     *
     * @see loginByCookie()
     * @since 2.0.9
     */
    protected function getIdentityAndDurationFromCookie()
    {
        /**
         * cookie 预处理详情，查看[[Request::loadCookies()]]
         * @see Request::loadCookies()
         *
         * $value 实际上是 Cookie 对象，强制转为字符串时会调用魔术方法 __toString(),
         * @see Cookie::__toString()
         *
         *  public function __toString()
            {
                return (string) $this->value;
            }
         *
         * 直接返回Cookie对象的value属性。
         *
         * 例：'[32,"K1r5cqGo9D6Rm8eDwBPs1SL4DSRRQhIO",604800]'
         */
        $value = Yii::$app->getRequest()->getCookies()->getValue($this->identityCookie['name']);
        if ($value === null) {
            return null;
        }
        // 将json字符串转换为包含登录信息的数组 [32,"K1r5cqGo9D6Rm8eDwBPs1SL4DSRRQhIO",604800]'
        // 数组包含三个元素分别是： 用户ID, 用户 auth_key(用户注册时随机生成的字符串)，cookie有效期
        $data = json_decode($value, true);
        if (is_array($data) && count($data) == 3) {
            // 将数组中的三个元素分贝赋值给 $id, $authKey, $duration
            list($id, $authKey, $duration) = $data;
            /* @var $class IdentityInterface */
            $class = $this->identityClass;
            // 通过用户ID查找用户身份对象
            $identity = $class::findIdentity($id);
            if ($identity !== null) {
                if (!$identity instanceof IdentityInterface) {
                    throw new InvalidValueException("$class::findIdentity() must return an object implementing IdentityInterface.");
                } elseif (!$identity->validateAuthKey($authKey)) {
                    // 若验证 auth_key 失败，则记录日志
                    Yii::warning("Invalid auth key attempted for user '$id': $authKey", __METHOD__);
                } else {
                    // 若一切正常，返回包含用户身份对象和有效期信息的数组
                    return ['identity' => $identity, 'duration' => $duration];
                }
            }
        }
        // 删除认证信息cookie(之后会重新生成)
        $this->removeIdentityCookie();
        return null;
    }

    /**
     * Removes the identity cookie.
     * 删除认证信息cookie
     *
     * This method is used when [[enableAutoLogin]] is true.
     * 当开启自动登陆时调用此方法。
     *
     * @since 2.0.9
     */
    protected function removeIdentityCookie()
    {
        Yii::$app->getResponse()->getCookies()->remove(Yii::createObject(array_merge($this->identityCookie, [
            'class' => 'yii\web\Cookie',
        ])));
    }

    /**
     * Switches to a new identity for the current user.
     * 为当前的用户更换一个新的认证身份信息。
     *
     * When [[enableSession]] is true, this method may use session and/or cookie to store the user identity information,
     * according to the value of `$duration`. Please refer to [[login()]] for more details.
     * 当[[enableSession]]为true的时候，该方法可能使用session或cookie来存储用户认证的信息。更多详情请参考[[login()]]
     *
     * This method is mainly called by [[login()]], [[logout()]] and [[loginByCookie()]]
     * when the current user needs to be associated with the corresponding identity information.
     * 当当前用户需要跟相应的认证信息关联的时候，该方法主要被[[login()]], [[logout()]] 和 [[loginByCookie()]]调用。
     *
     * @param IdentityInterface|null $identity the identity information to be associated with the current user.
     * If null, it means switching the current user to be a guest.
     * 参数 跟当前用户关联的认证信息。如果为null，意味着注销当前用户。
     * 
     * @param int $duration number of seconds that the user can remain in logged-in status.
     * This parameter is used only when `$identity` is not null.
     * 用户保持登陆状态的秒数。该参数只在`$identity`不为空的时候使用。
     */
    public function switchIdentity($identity, $duration = 0)
    {
        // 设置用户身份对象, 若$identity为null,则注销当前用户
        $this->setIdentity($identity);

        // 若未启用session持久化认证，则忽略后面的操作
        if (!$this->enableSession) {
            return;
        }

        /* Ensure any existing identity cookies are removed. */
        /* 请确保删除了所有存在的认证cookies */
        if ($this->enableAutoLogin && ($this->autoRenewCookie || $identity === null)) {
            // 若允许cookie自动登录，则删除所有身份认证cookie
            $this->removeIdentityCookie();
        }

        // 获取session 实例
        $session = Yii::$app->getSession();
        if (!YII_ENV_TEST) {
            // 若不是测试环境，则使用新生成的会话 ID 更新现有会话 ID
            $session->regenerateID(true);
        }
        // 删除会话中用户ID信息
        $session->remove($this->idParam);
        // 删除会话中的认证失效时间信息
        $session->remove($this->authTimeoutParam);

        // 若身份信息对象不为空，则将用户身份信息添加到session和cookie，
        // 并将cookie发送给浏览器
        if ($identity) {
            // 重新设置会话中的用户ID
            $session->set($this->idParam, $identity->getId());
            if ($this->authTimeout !== null) {
                // 若设置了 无操作自动退出时间,则在会话中添加该项
                $session->set($this->authTimeoutParam, time() + $this->authTimeout);
            }
            if ($this->absoluteAuthTimeout !== null) {
                // 若设置了绝对自动退出时间，则在会话中添加该项
                $session->set($this->absoluteAuthTimeoutParam, time() + $this->absoluteAuthTimeout);
            }
            if ($this->enableAutoLogin && $duration > 0) {
                // 若 用户保持登陆状态的秒数 大于 0，并且允许通过cookie自动登录，则发送认证cookie给浏览器
                $this->sendIdentityCookie($identity, $duration);
            }
        }

        // regenerate CSRF token
        Yii::$app->getRequest()->getCsrfToken(true);
    }

    /**
     * Updates the authentication status using the information from session and cookie.
     * 使用session或者cookie里边的信息更新认证状态。
     *
     * This method will try to determine the user identity using the [[idParam]] session variable.
     * 该方法会使用[[idParam]]session变量尝试决定用户认证
     *
     * If [[authTimeout]] is set, this method will refresh the timer.
     * 如果设置了认证时间限制，该方法会刷新计时器。
     *
     * If the user identity cannot be determined by session, this method will try to [[loginByCookie()|login by cookie]]
     * if [[enableAutoLogin]] is true.
     * 如果无法从session里获取用户认证信息，该方法会在自动登陆开启的时候尝试[[loginByCookie()|login by cookie]]
     */
    protected function renewAuthStatus()
    {
        // 获取session对象
        $session = Yii::$app->getSession();
        // session实例： __flash|a:0:{}__returnUrl|s:1:"/";__id|s:1:"4";
        // 当前的请求发送了会话ID 或者 Session 是开启状态，通过 __id 参数获取用户id.
        $id = $session->getHasSessionId() || $session->getIsActive() ? $session->get($this->idParam) : null;

        if ($id === null) {
            // 若从session中未获取用户id,则$identity为null
            $identity = null;
        } else {
            /* @var $class IdentityInterface */
            $class = $this->identityClass;
            // 通过ID获取用户身份对象
            $identity = $class::findIdentity($id);
        }

        // 设置用户的身份对象
        $this->setIdentity($identity);

        // 身份对象不为空 && (设置了无操作自动退出时间 || 设置了绝对自动退出时间)
        if ($identity !== null && ($this->authTimeout !== null || $this->absoluteAuthTimeout !== null)) {

            // 若设置了无操作自动退出时间，则获取自动退出的时间戳
            $expire = $this->authTimeout !== null ? $session->get($this->authTimeoutParam) : null;
            // 若设置了绝对自动退出时间，则获取绝对自动退出的时间戳
            $expireAbsolute = $this->absoluteAuthTimeout !== null ? $session->get($this->absoluteAuthTimeoutParam) : null;

            // 无操作自动退出时间 或 绝对自动退出的时间 任意一个过期，则推出登录
            if ($expire !== null && $expire < time() || $expireAbsolute !== null && $expireAbsolute < time()) {
                // 注销当前用户，但不销毁session
                $this->logout(false);
            } elseif ($this->authTimeout !== null) {
                // 若设置了无操作自动退出时间，则在session中自动延长该时间
                $session->set($this->authTimeoutParam, time() + $this->authTimeout);
            }
        }

        // 允许基于cookie的自动登录
        if ($this->enableAutoLogin) {
            // 若用户未登录，则通过cookie登录
            if ($this->getIsGuest()) {
                $this->loginByCookie();
            } elseif ($this->autoRenewCookie) {
                // 每次请求页面时自动刷新认证cookie
                $this->renewIdentityCookie();
            }
        }
    }

    /**
     * Checks if the user can perform the operation as specified by the given permission.
     * 根据指定的权限检测用户是否可以执行当前操作
     *
     * Note that you must configure "authManager" application component in order to use this method.
     * Otherwise it will always return false.
     * 请注意，要使用此方法，你必须配置authManager应用组件。否则会一直返回false。
     *
     * @param string $permissionName the name of the permission (e.g. "edit post") that needs access check.
     * 参数  字符串 需要登陆检测的权限名称。
     *
     * @param array $params name-value pairs that would be passed to the rules associated
     * with the roles and permissions assigned to the user.
     * 参数 数组 将会传递给跟角色和分配给用户的权限的规则的键值对。
     *
     * @param bool $allowCaching whether to allow caching the result of access check.
     * 参数 boolean 是否允许缓存访问权限检查结果。
     *
     * When this parameter is true (default), if the access check of an operation was performed
     * before, its result will be directly returned when calling this method to check the same
     * operation. If this parameter is false, this method will always call
     * [[\yii\rbac\CheckAccessInterface::checkAccess()]] to obtain the up-to-date access result. Note that this
     * caching is effective only within the same request and only works when `$params = []`.
     * 当默认情况下，该值为true，如果一个操作的登陆检测之前执行过，当调用该方法来检测同样的操作时，它的结果就会被直接返回。如果该参数为false，
     * 该方法会一直调用[[\yii\rbac\CheckAccessInterface::checkAccess()]]去获取最新的访问结果。请注意，缓存只在详情请求和`$params = []`
     * 为空的时候生效。
     * 
     * @return bool whether the user can perform the operation as specified by the given permission.
     * 返回值 boolean 根据给定的权限，判断该用户是否可以执行特定操作。
     */
    public function can($permissionName, $params = [], $allowCaching = true)
    {
        // 允许缓存 && 参数为空 && 已经存在权限检查结果
        if ($allowCaching && empty($params) && isset($this->_access[$permissionName])) {
            // 直接返回缓存的结果
            return $this->_access[$permissionName];
        }
        // 若没有设置访问权限监测器方法，则直接返回false
        if (($accessChecker = $this->getAccessChecker()) === null) {
            return false;
        }
        // 检查用户是否有指定的权限。
        $access = $accessChecker->checkAccess($this->getId(), $permissionName, $params);
        // 允许缓存 && 参数为空
        if ($allowCaching && empty($params)) {
            // 将验证结果存在权限数组中
            $this->_access[$permissionName] = $access;
        }

        return $access;
    }

    /**
     * Checks if the `Accept` header contains a content type that allows redirection to the login page.
     * 检测`Accept`头是否包含一个允许重定向到登陆页的content type(内容类型)
     *
     * The login page is assumed to serve `text/html` or `application/xhtml+xml` by default. You can change acceptable
     * content types by modifying [[acceptableRedirectTypes]] property.
     * 登陆页面默认假定为`text/html` 或者 `application/xhtml+xml`，你可以更改[[acceptableRedirectTypes]]属性来改变可接受的内容类型。
     *
     * @return bool whether this request may be redirected to the login page.
     * 返回值 boolean 该请求是否可以被重定向到登陆页面。
     * 
     * @see acceptableRedirectTypes
     * @since 2.0.8
     */
    protected function checkRedirectAcceptable()
    {
        // 获取终端用户可接受的内容类型
        $acceptableTypes = Yii::$app->getRequest()->getAcceptableContentTypes();
        // 若 $acceptableTypes 为空，或者$acceptableTypes = ['*/*' => ...]
        // 表示可以接受所有类型的数据，则直接返回true
        if (empty($acceptableTypes) || count($acceptableTypes) === 1 && array_keys($acceptableTypes)[0] === '*/*') {
            return true;
        }

        // 遍历 $acceptableTypes
        foreach ($acceptableTypes as $type => $params) {
            // 若客户端可以接收指定的MIME类型，则返回true
            if (in_array($type, $this->acceptableRedirectTypes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns auth manager associated with the user component.
     * 返回跟用户组件相关的认证管理器。
     *
     * By default this is the `authManager` application component.
     * 默认是认证管理应用组件。
     *
     * You may override this method to return a different auth manager instance if needed.
     * 如果需要你可以重写该方法返回一个不同的认证管理实例。
     *
     * @return \yii\rbac\ManagerInterface
     * @since 2.0.6
     * // 自版本2.0.9 以来已弃用，将会在 2.1.X 版本中删除。使用“getAccessChecker()”替代
     * @deprecated since version 2.0.9, to be removed in 2.1. Use [[getAccessChecker()]] instead.
     */
    protected function getAuthManager()
    {
        return Yii::$app->getAuthManager();
    }

    /**
     * Returns the access checker used for checking access.
     * 返回用于检查访问权限的访问检测器
     *
     * @return CheckAccessInterface
     * @since 2.0.9
     */
    protected function getAccessChecker()
    {
        return $this->accessChecker !== null ? $this->accessChecker : $this->getAuthManager();
    }
}
