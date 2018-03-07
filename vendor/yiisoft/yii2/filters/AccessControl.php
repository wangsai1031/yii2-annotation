<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\filters;

use Yii;
use yii\base\Action;
use yii\base\ActionFilter;
use yii\di\Instance;
use yii\web\ForbiddenHttpException;
use yii\web\User;

/**
 * AccessControl提供基于rules规则的访问控制。
 * 特别是在动作执行之前， 访问控制会检测所有规则并找到第一个符合上下文的变量（比如用户IP地址、登录状态等等）的规则，
 * 来决定允许还是拒绝请求动作的执行， 如果没有规则符合，访问就会被拒绝。
 *
 * AccessControl基于一组规则提供简单的访问控制
 * AccessControl provides simple access control based on a set of rules.
 *
 * 存取控制过滤器（ACF）是一种通过 yii\filters\AccessControl 类来实现的简单授权方法， 非常适用于仅需要简单的存取控制的应用。
 * 正如其名称所指，ACF 是一种动作过滤器 filter，可在控制器或者模块中使用。
 * 当一个用户请求一个动作时， ACF会检查 access rules 列表，判断该用户是否允许执行所请求的动作。
 *
 * AccessControl is an action filter. It will check its [[rules]] to find
 * the first rule that matches the current context variables (such as user IP address, user role).
 * The matching rule will dictate whether to allow or deny the access to the requested controller
 * action. If no rule matches, the access will be denied.
 *
 * To use AccessControl, declare it in the `behaviors()` method of your controller class.
 * For example, the following declarations will allow authenticated users to access the "create"
 * and "update" actions and deny all other users from accessing these two actions.
 *
 * 下述代码展示如何在 site 控制器中使用 ACF：
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'access' => [
 *             'class' => \yii\filters\AccessControl::className(),
 *             'only' => ['create', 'update'],
 *             'rules' => [
 *                 // deny all POST requests
 *                  // 拒绝所有POST请求
 *                 [
 *                     'allow' => false,
 *                     'verbs' => ['POST']
 *                 ],
 *                 // allow authenticated users
 *                 // 允许经过身份验证(即登录)的用户访问
 *                 [
 *                     'allow' => true,
 *                     'roles' => ['@'],
 *                 ],
 *                 // everything else is denied
 *             ],
 *         ],
 *     ];
 * }
 * ```
 * ACF 自顶向下逐一检查存取规则，直到找到一个与当前 欲执行的动作相符的规则。
 * 然后该匹配规则中的 allow 选项的值用于判定该用户是否获得授权。
 * 如果没有找到匹配的规则， 意味着该用户没有获得授权。
 * （译者注： only 中没有列出的动作，将无条件获得授权）
 *
 * 当 ACF 判定一个用户没有获得执行当前动作的授权时，它的默认处理是：

    如果该用户是访客，将调用 yii\web\User::loginRequired() 将用户的浏览器重定向到登录页面。
    如果该用户是已认证用户，将抛出一个 yii\web\ForbiddenHttpException 异常。
    你可以通过配置 yii\filters\AccessControl::$denyCallback 属性定制该行为：
 *
    ```
    [
        'class' => AccessControl::className(),
        ...
        'denyCallback' => function ($rule, $action) {
            throw new \Exception('You are not allowed to access this page');
        }
    ]
 * ```
 * Access rules 支持很多的选项。下列是所支持选项的总览。 你可以派生 yii\filters\AccessRule 来创建自定义的存取规则类。

    allow： 指定该规则是 "允许" 还是 "拒绝" 。（译者注：true是允许，false是拒绝）

    actions：指定该规则用于匹配哪些动作。 它的值应该是动作方法的ID数组。匹配比较是大小写敏感的。如果该选项为空，或者不使用该选项， 意味着当前规则适用于所有的动作。

    controllers：指定该规则用于匹配哪些控制器。 它的值应为控制器ID数组。匹配比较是大小写敏感的。如果该选项为空，或者不使用该选项， 则意味着当前规则适用于所有的动作。（译者注：这个选项一般是在控制器的自定义父类中使用才有意义）

    roles：指定该规则用于匹配哪些用户角色。 系统自带两个特殊的角色，通过 yii\web\User::isGuest 来判断：

        ?： 用于匹配访客用户 （未经认证）
        @： 用于匹配已认证用户
        使用其他角色名时，将触发调用 yii\web\User::can()，这时要求 RBAC 的支持 （在下一节中阐述）。 如果该选项为空或者不使用该选项，意味着该规则适用于所有角色。

    ips：指定该规则用于匹配哪些 yii\web\Request::userIP 。 IP 地址可在其末尾包含通配符 * 以匹配一批前缀相同的IP地址。 例如，192.168.* 匹配所有 192.168. 段的IP地址。 如果该选项为空或者不使用该选项，意味着该规则适用于所有角色。

    verbs：指定该规则用于匹配哪种请求方法（例如GET，POST）。 这里的匹配大小写不敏感。

    matchCallback：指定一个PHP回调函数用于 判定该规则是否满足条件。（译者注：此处的回调函数是匿名函数）

    当这个规则不满足条件时该函数会被调用。（译者注：此处的回调函数是匿名函数）

    以下例子展示了如何使用 matchCallback 选项， 可使你设计任意的访问权限检查逻辑：

    use yii\filters\AccessControl;

    class SiteController extends Controller
    {
        public function behaviors()
        {
            return [
                'access' => [
                    'class' => AccessControl::className(),
                    'only' => ['special-callback'],
                    'rules' => [
                        [
                            'actions' => ['special-callback'],
                            'allow' => true,
                            'matchCallback' => function ($rule, $action) {
                                return date('d-m') === '31-10';
                            }
                        ],
                    ],
                ],
            ];
        }

        // 匹配的回调函数被调用了！这个页面只有每年的10月31号能访问
 *      //（译者注：原文在这里说该方法是回调函数不确切，读者不要和 `matchCallback` 的值即匿名的回调函数混淆理解）。
        public function actionSpecialCallback()
        {
            return $this->render('happy-halloween');
        }
    }
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AccessControl extends ActionFilter
{
    /**
     * user对象表示身份验证状态或用户应用程序组件的ID
     * @var User|array|string|false the user object representing the authentication status or the ID of the user application component.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     * Starting from version 2.0.12, you can set it to `false` to explicitly switch this component support off for the filter.
     */
    public $user = 'user';
    /**
     * 如果对当前用户拒绝访问，将调用的回调方法。
     * 如果不设置，将调用[[denyAccess()]]。
     * @var callable a callback that will be called if the access should be denied
     * to the current user. This is the case when either no rule matches, or a rule with
     * [[AccessRule::$allow|$allow]] set to `false` matches.
     * If not set, [[denyAccess()]] will be called.
     *
     * The signature of the callback should be as follows:
     *
     * 回调的参数应该如下所列
     * ```php
     * function ($rule, $action)
     * ```
     * $rule是拒绝用户的规则，$action是当前的[[Action|action]]对象。
     * 如果访问被拒绝，则$rule可能为null，因为没有一个规则匹配。
     *
     * where `$rule` is the rule that denies the user, and `$action` is the current [[Action|action]] object.
     * `$rule` can be `null` if access is denied because none of the rules matched.
     */
    public $denyCallback;
    /**
     * 访问规则的默认配置。
     * 当配置的规则具有相同属性时，个人通过[[rules]]指定的规则配置将优先考虑。
     * @var array the default configuration of access rules. Individual rule configurations
     * specified via [[rules]] will take precedence when the same property of the rule is configured.
     */
    public $ruleConfig = ['class' => 'yii\filters\AccessRule'];
    /**
     * 用于创建规则对象的访问规则对象或配置数组。
     * 如果规则是通过配置数组来指定的，在创建规则对象之前，它将首先与 [[ruleConfig]]合并。
     * @var array a list of access rule objects or configuration arrays for creating the rule objects.
     * If a rule is specified via a configuration array, it will be merged with [[ruleConfig]] first
     * before it is used for creating the rule object.
     * @see ruleConfig
     */
    public $rules = [];


    /**
     * 通过从配置中实例化规则对象来初始化规则数组
     * Initializes the [[rules]] array by instantiating rule objects from configurations.
     */
    public function init()
    {
        parent::init();
        if ($this->user !== false) {
            $this->user = Instance::ensure($this->user, User::className());
        }
        foreach ($this->rules as $i => $rule) {
            if (is_array($rule)) {
                $this->rules[$i] = Yii::createObject(array_merge($this->ruleConfig, $rule));
            }
        }
    }

    /**
     * 该方法在执行操作之前被调用(在所有可能的过滤器之后)。
     * 你可以重写这个方法，并在最后调用该方法
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * You may override this method to do last-minute preparation for the action.
     * @param Action $action the action to be executed.
     * @return bool whether the action should continue to be executed.
     */
    public function beforeAction($action)
    {
        $user = $this->user;
        $request = Yii::$app->getRequest();
        /* @var $rule AccessRule */
        foreach ($this->rules as $rule) {
            if ($allow = $rule->allows($action, $user, $request)) {
                return true;
            } elseif ($allow === false) {
                if (isset($rule->denyCallback)) {
                    call_user_func($rule->denyCallback, $rule, $action);
                } elseif ($this->denyCallback !== null) {
                    call_user_func($this->denyCallback, $rule, $action);
                } else {
                    $this->denyAccess($user);
                }

                return false;
            }
        }
        if ($this->denyCallback !== null) {
            call_user_func($this->denyCallback, null, $action);
        } else {
            $this->denyAccess($user);
        }

        return false;
    }

    /**
     * 拒绝用户的访问。
     * 如果他是非登录用户，默认的实现将把用户重定向到登录页面。
     * 如果用户已经登录，将抛出 403 HTTP异常
     * Denies the access of the user.
     * The default implementation will redirect the user to the login page if he is a guest;
     * if the user is already logged, a 403 HTTP exception will be thrown.
     * @param User|false $user the current user or boolean `false` in case of detached User component
     * @throws ForbiddenHttpException if the user is already logged in or in case of detached User component.
     */
    protected function denyAccess($user)
    {
        if ($user !== false && $user->getIsGuest()) {
            $user->loginRequired();
        } else {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
        }
    }
}
