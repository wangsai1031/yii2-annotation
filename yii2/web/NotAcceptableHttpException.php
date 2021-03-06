<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

/**
 * 表示状态码为406 "Not Acceptable" HTTP异常
 * 当客户端请求您的应用程序不能返回的内容类型时，使用这个异常。
 * 请注意，根据HTTP 1.1规范，在这种情况下，您不需要使用这种状态代码来响应。
 * NotAcceptableHttpException represents a "Not Acceptable" HTTP exception with status code 406.
 *
 * Use this exception when the client requests a Content-Type that your
 * application cannot return. Note that, according to the HTTP 1.1 specification,
 * you are not required to respond with this status code in this situation.
 *
 * @see https://tools.ietf.org/html/rfc7231#section-6.5.6
 * @author Dan Schmidt <danschmidt5189@gmail.com>
 * @since 2.0
 */
class NotAcceptableHttpException extends HttpException
{
    /**
     * Constructor.
     * @param string $message error message
     * @param int $code error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct(406, $message, $code, $previous);
    }
}
