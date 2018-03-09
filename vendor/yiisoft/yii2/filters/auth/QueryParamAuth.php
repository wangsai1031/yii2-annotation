<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\filters\auth;

/**
 * QueryParamAuth 支持 通过查询参数传递的访问令牌来身份验证
 * QueryParamAuth is an action filter that supports the authentication based on the access token passed through a query parameter.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class QueryParamAuth extends AuthMethod
{
    /**
     * 传递访问令牌的参数名称
     * @var string the parameter name for passing the access token
     */
    public $tokenParam = 'access-token';


    /**
     * {@inheritdoc}
     */
    public function authenticate($user, $request, $response)
    {
        // 获取accessToken
        $accessToken = $request->get($this->tokenParam);
        if (is_string($accessToken)) {
            // 通过accessToken进行登录
            $identity = $user->loginByAccessToken($accessToken, get_class($this));
            if ($identity !== null) {
                return $identity;
            }
        }
        if ($accessToken !== null) {
            $this->handleFailure($response);
        }

        return null;
    }
}
