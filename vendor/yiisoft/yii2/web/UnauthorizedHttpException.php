<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

/**
 * 代表状态码401 "Unauthorized"HTTP异常.
 * 使用这个异常来表示客户端需要进行身份验证或登录来执行所请求的操作。
 * 如果客户端已经进行了身份验证，但是不允许执行某些操作，应该考虑使用 403 [[ForbiddenHttpException]] or 404 [[NotFoundHttpException]]
 * UnauthorizedHttpException represents an "Unauthorized" HTTP exception with status code 401
 *
 * Use this exception to indicate that a client needs to authenticate or login to perform the requested action.
 * If the client is already authenticated and is simply not allowed to perform the action, consider using a 403 [[ForbiddenHttpException]] or 404 [[NotFoundHttpException]] instead.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.2
 * @author Dan Schmidt <danschmidt5189@gmail.com>
 * @since 2.0
 */
class UnauthorizedHttpException extends HttpException
{
    /**
     * Constructor.
     * @param string $message error message
     * @param integer $code error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct(401, $message, $code, $previous);
    }
}
