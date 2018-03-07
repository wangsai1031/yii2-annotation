<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\filters;

use Closure;
use yii\base\Action;
use yii\base\Component;
use yii\base\Controller;
use yii\base\InvalidConfigException;
use yii\helpers\StringHelper;
use yii\web\Request;
use yii\web\User;

/**
 * This class represents an access rule defined by the [[AccessControl]] action filter.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AccessRule extends Component
{
    /**
     * 这是一个'allow'规则或者'deny' 规则
     * @var bool whether this is an 'allow' rule or 'deny' rule.
     */
    public $allow;
    /**
     * 这个规则应用到的动作id列表。
     * 比较是区分大小写的。
     * 如果不设置或空，则意味着该规则适用于所有操作。
     *
     * @var array list of action IDs that this rule applies to. The comparison is case-sensitive.
     * If not set or empty, it means this rule applies to all actions.
     */
    public $actions;
    /**
     * 应用到此规则的控制器id列表。
     * 每个控制器ID都前缀模块ID(如果有的话)。
     * 比较是区分大小写的。
     * 如果不设置或空，则意味着该规则适用于所有控制器。
     * @var array list of the controller IDs that this rule applies to.
     *
     * The comparison uses [[\yii\base\Controller::uniqueId]], so each controller ID is prefixed
     * with the module ID (if any). For a `product` controller in the application, you would specify
     * this property like `['product']` and if that controller is located in a `shop` module, this
     * would be `['shop/product']`.
     *
     * The comparison is case-sensitive.
     *
     * If not set or empty, it means this rule applies to all controllers.
     *
     * Since version 2.0.12 controller IDs can be specified as wildcards, e.g. `module/*`.
     */
    public $controllers;
    /**
     * 这个规则所适用的角色列表。
     * 两个特殊角色被识别，并通过[[User::isGuest]]进行检查:isGuest
     * @var array list of roles that this rule applies to (requires properly configured User component).
     * Two special roles are recognized, and they are checked via [[User::isGuest]]:
     *
     * - `?`: matches a guest user (not authenticated yet) 匹配一个游客用户(尚未登录)
     * - `@`: matches an authenticated user 匹配一个登录的用户
     *
     * 如果您使用的是RBAC(基于角色的访问控制)，您也可以指定角色或权限名。
     * 在本例中，[[User::can()]]将被调用来检查访问权限。
     * If you are using RBAC (Role-Based Access Control), you may also specify role names.
     * In this case, [[User::can()]] will be called to check access.
     *
     * Note that it is preferred to check for permissions instead.
     *
     * If this property is not set or empty, it means this rule applies regardless of roles.
     * @see $permissions
     * @see $roleParams
     */
    public $roles;
    /** 
     * @var array list of RBAC (Role-Based Access Control) permissions that this rules applies to.
     * [[User::can()]] will be called to check access.
     * 
     * If this property is not set or empty, it means this rule applies regardless of permissions.
     * @since 2.0.12
     * @see $roles
     * @see $roleParams
     */
    public $permissions;
    /**
     * @var array|Closure parameters to pass to the [[User::can()]] function for evaluating
     * user permissions in [[$roles]].
     *
     * If this is an array, it will be passed directly to [[User::can()]]. For example for passing an
     * ID from the current request, you may use the following:
     *
     * ```php
     * ['postId' => Yii::$app->request->get('id')]
     * ```
     *
     * You may also specify a closure that returns an array. This can be used to
     * evaluate the array values only if they are needed, for example when a model needs to be
     * loaded like in the following code:
     *
     * ```php
     * 'rules' => [
     *     [
     *         'allow' => true,
     *         'actions' => ['update'],
     *         'roles' => ['updatePost'],
     *         'roleParams' => function($rule) {
     *             return ['post' => Post::findOne(Yii::$app->request->get('id'))];
     *         },
     *     ],
     * ],
     * ```
     *
     * A reference to the [[AccessRule]] instance will be passed to the closure as the first parameter.
     *
     * @see $roles
     * @since 2.0.12
     */
    public $roleParams = [];
    /**
     * 应用此规则的用户IP地址列表。
     * IP地址可以在末尾包含通配符`*`，这样它就可以使用相同的前缀来匹配IP地址。
     * 例如,'192.168.*' 匹配所有'192.168.' 网段的IP地址。
     * 如果不设置或空，则意味着该规则适用于所有IP地址。
     * 
     * @var array list of user IP addresses that this rule applies to. An IP address
     * can contain the wildcard `*` at the end so that it matches IP addresses with the same prefix.
     * For example, '192.168.*' matches all IP addresses in the segment '192.168.'.
     * If not set or empty, it means this rule applies to all IP addresses.
     * @see Request::userIP
     */
    public $ips;
    /**
     * 应用该规则的请求方法列表(例如:GET，POST)。
     * 如果不设置或空，则意味着该规则适用于所有请求方法
     * @var array list of request methods (e.g. `GET`, `POST`) that this rule applies to.
     * If not set or empty, it means this rule applies to all request methods.
     * @see \yii\web\Request::method
     */
    public $verbs;
    /**
     * 调用一个回调，以确定是否应该应用该规则。
     * @var callable a callback that will be called to determine if the rule should be applied.
     * The signature of the callback should be as follows:
     *
     * 回调的参数应该如下所列：
     * ```php
     * function ($rule, $action)
     * ```
     *
     * $rule是当前的规则，$action是当前的[[Action|action]]对象。
     * 回调应该返回一个布尔值，指示是否应该应用该规则。
     * where `$rule` is this rule, and `$action` is the current [[Action|action]] object.
     * The callback should return a boolean value indicating whether this rule should be applied.
     */
    public $matchCallback;
    /**
     * 如果该规则拒访问当前action，将调用一个回调。
     * 如果不设置，则行为将由 [[AccessControl]] 判断。
     * @var callable a callback that will be called if this rule determines the access to
     * the current action should be denied. This is the case when this rule matches
     * and [[$allow]] is set to `false`.
     *
     * If not set, the behavior will be determined by [[AccessControl]],
     * either using [[AccessControl::denyAccess()]]
     * or [[AccessControl::$denyCallback]], if configured.
     *
     * The signature of the callback should be as follows:
     *
     * ```php
     * function ($rule, $action)
     * ```
     *
     * where `$rule` is this rule, and `$action` is the current [[Action|action]] object.
     * @see AccessControl::$denyCallback
     */
    public $denyCallback;


    /**
     * 检查是否允许Web用户执行指定的操作
     * Checks whether the Web user is allowed to perform the specified action.
     * @param Action $action the action to be performed
     * @param User|false $user the user object or `false` in case of detached User component
     * @param Request $request
     * @return bool|null `true` if the user is allowed, `false` if the user is denied, `null` if the rule does not apply to the user
     */
    public function allows($action, $user, $request)
    {
        if ($this->matchAction($action)
            && $this->matchRole($user)
            && $this->matchIP($request->getUserIP())
            && $this->matchVerb($request->getMethod())
            && $this->matchController($action->controller)
            && $this->matchCustom($action)
        ) {
            return $this->allow ? true : false;
        }

        return null;
    }

    /**
     * 这条规则是否适用于这个action
     * @param Action $action the action
     * @return bool whether the rule applies to the action
     */
    protected function matchAction($action)
    {
        return empty($this->actions) || in_array($action->id, $this->actions, true);
    }

    /**
     * 这条规则是否适用于这个控制器
     * @param Controller $controller the controller
     * @return bool whether the rule applies to the controller
     */
    protected function matchController($controller)
    {
        if (empty($this->controllers)) {
            return true;
        }

        $id = $controller->getUniqueId();
        foreach ($this->controllers as $pattern) {
            if (StringHelper::matchWildcard($pattern, $id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 规则是否适用于这个角色
     * @param User $user the user object
     * @return bool whether the rule applies to the role
     * @throws InvalidConfigException if User component is detached
     */
    protected function matchRole($user)
    {
        $items = empty($this->roles) ? [] : $this->roles;

        if (!empty($this->permissions)) {
            $items = array_merge($items, $this->permissions);
        }

        if (empty($items)) {
            return true;
        }

        if ($user === false) {
            throw new InvalidConfigException('The user application component must be available to specify roles in AccessRule.');
        }

        foreach ($items as $item) {
            if ($item === '?') {
                if ($user->getIsGuest()) {
                    return true;
                }
            } elseif ($item === '@') {
                if (!$user->getIsGuest()) {
                    return true;
                }
            } else {
                if (!isset($roleParams)) {
                    $roleParams = $this->roleParams instanceof Closure ? call_user_func($this->roleParams, $this) : $this->roleParams;
                }
                if ($user->can($item, $roleParams)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 这个规则是否适用于当前IP地址
     * @param string|null $ip the IP address
     * @return bool whether the rule applies to the IP address
     */
    protected function matchIP($ip)
    {
        if (empty($this->ips)) {
            return true;
        }
        foreach ($this->ips as $rule) {
            if ($rule === '*' ||
                $rule === $ip ||
                (
                    $ip !== null &&
                    ($pos = strpos($rule, '*')) !== false &&
                    strncmp($ip, $rule, $pos) === 0
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 规则是否适用于当前请求
     * @param string $verb the request method.
     * @return bool whether the rule applies to the request
     */
    protected function matchVerb($verb)
    {
        return empty($this->verbs) || in_array(strtoupper($verb), array_map('strtoupper', $this->verbs), true);
    }

    /**
     * 是否应该应用规则
     * @param Action $action the action to be performed
     * @return bool whether the rule should be applied
     */
    protected function matchCustom($action)
    {
        return empty($this->matchCallback) || call_user_func($this->matchCallback, $this, $action);
    }
}
