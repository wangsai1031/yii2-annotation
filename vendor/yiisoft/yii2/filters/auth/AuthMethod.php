<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\filters\auth;

use Yii;
use yii\base\Action;
use yii\base\ActionFilter;
use yii\helpers\StringHelper;
use yii\web\Request;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\web\User;

/**
 * 认证方法过滤器通常在实现RESTful API中使用
 * AuthMethod is a base class implementing the [[AuthInterface]] interface.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class AuthMethod extends ActionFilter implements AuthInterface
{
    /**
     * 表示用户身份验证状态的用户对象。如果不设置，将使用用户应用程序组件
     * @var User the user object representing the user authentication status. If not set, the `user` application component will be used.
     */
    public $user;
    /**
     * 当前请求，如果不设置，将使用 `request`应用程序组件
     * @var Request the current request. If not set, the `request` application component will be used.
     */
    public $request;
    /**
     * 发送的响应。如果不设置，将使用`response`应用程序组件。
     * @var Response the response to be sent. If not set, the `response` application component will be used.
     */
    public $response;
    /**
     * 该筛选器将应用到的 身份验证失败也不会导致错误 action ID列表。
     * 它可以用于允许公开的action，但还可以为经过身份验证的用户返回一些额外的数据。
     * 默认为空，这意味着任何action都必须经过身份验证。
     * @var array list of action IDs that this filter will be applied to, but auth failure will not lead to error.
     * It may be used for actions, that are allowed for public, but return some additional data for authenticated users.
     * Defaults to empty, meaning authentication is not optional for any action.
     * Since version 2.0.10 action IDs can be specified as wildcards, e.g. `site/*`.
     * @see isOptional()
     * @since 2.0.7
     */
    public $optional = [];


    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        $response = $this->response ?: Yii::$app->getResponse();

        try {
            // 验证身份信息
            $identity = $this->authenticate(
                $this->user ?: Yii::$app->getUser(),
                $this->request ?: Yii::$app->getRequest(),
                $response
            );
        } catch (UnauthorizedHttpException $e) {
            // 没有验证成功，判断该 action 是否可以不经过身份验证即可访问
            if ($this->isOptional($action)) {
                return true;
            }

            throw $e;
        }

        if ($identity !== null || $this->isOptional($action)) {
            return true;
        }

        $this->challenge($response);
        $this->handleFailure($response);

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function challenge($response)
    {
    }

    /**
     * 验证失败抛异常
     * {@inheritdoc}
     */
    public function handleFailure($response)
    {
        throw new UnauthorizedHttpException('Your request was made with invalid credentials.');
    }

    /**
     * 检查给定的操作 是否可以不经过身份验证即可访问
     * Checks, whether authentication is optional for the given action.
     *
     * @param Action $action action to be checked.
     * @return bool whether authentication is optional or not.
     * @see optional
     * @since 2.0.7
     */
    protected function isOptional($action)
    {
        // 将 [[Action::$uniqueId]] 转化为 相对于模块的ID，并返回 action ID
        // 即：去掉[[Action::$uniqueId]]中的模块ID 部分
        $id = $this->getActionId($action);
        foreach ($this->optional as $pattern) {
            if (StringHelper::matchWildcard($pattern, $id)) {
                return true;
            }
        }

        return false;
    }
}
