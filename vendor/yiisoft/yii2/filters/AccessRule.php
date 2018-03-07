<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\filters;

use yii\base\Component;
use yii\base\Action;
use yii\web\User;
use yii\web\Request;
use yii\base\Controller;

/**
 * This class represents an access rule defined by the [[AccessControl]] action filter
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AccessRule extends Component
{
    /**
     * 这是一个'allow'规则或者'deny' 规则
     * @var boolean whether this is an 'allow' rule or 'deny' rule.
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
     * @var array list of the controller IDs that this rule applies to. Each controller ID is prefixed with the module ID (if any).
     * The comparison is case-sensitive. If not set or empty, it means this rule applies to all controllers.
     */
    public $controllers;
    /**
     * 这个规则所适用的角色列表。
     * 两个特殊角色被识别，并通过[[User::isGuest]]进行检查:isGuest
     * @var array list of roles that this rule applies to.
     * Two special roles are recognized, and they are checked via [[User::isGuest]]:
     *
     * - `?`: matches a guest user (not authenticated yet) 匹配一个游客用户(尚未登录)
     * - `@`: matches an authenticated user 匹配一个登录的用户
     *
     * 如果您使用的是RBAC(基于角色的访问控制)，您也可以指定角色或权限名。
     * 在本例中，[[User::can()]]将被调用来检查访问权限。
     * If you are using RBAC (Role-Based Access Control), you may also specify role or permission names.
     * In this case, [[User::can()]] will be called to check access.
     *
     * If this property is not set or empty, it means this rule applies to all roles.
     */
    public $roles;
    /**
     * 应用此规则的用户IP地址列表。
     * IP地址可以在末尾包含通配符`*`，这样它就可以使用相同的前缀来匹配IP地址。
     * 例如,'192.168.*' 匹配所有'192.168.' 网段的IP地址。
     * 如果不设置或空，则意味着该规则适用于所有IP地址。
     *
     * @var array list of user IP addresses that this rule applies to.
     * An IP address can contain the wildcard `*` at the end so that it matches IP addresses with the same prefix.
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
     * @var callable a callback that will be called if this rule determines the access to the current action should be denied.
     * If not set, the behavior will be determined by
     * [[AccessControl]].
     *
     * The signature of the callback should be as follows:
     *
     * ```php
     * function ($rule, $action)
     * ```
     *
     * where `$rule` is this rule, and `$action` is the current [[Action|action]] object.
     */
    public $denyCallback;


    /**
     * 检查是否允许Web用户执行指定的操作
     * Checks whether the Web user is allowed to perform the specified action.
     * @param Action $action the action to be performed
     * @param User $user the user object
     * @param Request $request
     * @return boolean|null true if the user is allowed, false if the user is denied, null if the rule does not apply to the user
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
        } else {
            return null;
        }
    }

    /**
     * 这条规则是否适用于这个action
     * @param Action $action the action
     * @return boolean whether the rule applies to the action
     */
    protected function matchAction($action)
    {
        return empty($this->actions) || in_array($action->id, $this->actions, true);
    }

    /**
     * 这条规则是否适用于这个控制器
     * @param Controller $controller the controller
     * @return boolean whether the rule applies to the controller
     */
    protected function matchController($controller)
    {
        return empty($this->controllers) || in_array($controller->uniqueId, $this->controllers, true);
    }

    /**
     * 规则是否适用于这个角色
     * @param User $user the user object
     * @return boolean whether the rule applies to the role
     */
    protected function matchRole($user)
    {
        if (empty($this->roles)) {
            return true;
        }
        foreach ($this->roles as $role) {
            if ($role === '?') {
                if ($user->getIsGuest()) {
                    return true;
                }
            } elseif ($role === '@') {
                if (!$user->getIsGuest()) {
                    return true;
                }
            } elseif ($user->can($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 这个规则是否适用于当前IP地址
     * @param string $ip the IP address
     * @return boolean whether the rule applies to the IP address
     */
    protected function matchIP($ip)
    {
        if (empty($this->ips)) {
            return true;
        }
        foreach ($this->ips as $rule) {
            if ($rule === '*' || $rule === $ip || (($pos = strpos($rule, '*')) !== false && !strncmp($ip, $rule, $pos))) {
                return true;
            }
        }

        return false;
    }

    /**
     * 规则是否适用于当前请求
     * @param string $verb the request method.
     * @return boolean whether the rule applies to the request
     */
    protected function matchVerb($verb)
    {
        return empty($this->verbs) || in_array(strtoupper($verb), array_map('strtoupper', $this->verbs), true);
    }

    /**
     * 是否应该应用规则
     * @param Action $action the action to be performed
     * @return boolean whether the rule should be applied
     */
    protected function matchCustom($action)
    {
        return empty($this->matchCallback) || call_user_func($this->matchCallback, $this, $action);
    }
}
