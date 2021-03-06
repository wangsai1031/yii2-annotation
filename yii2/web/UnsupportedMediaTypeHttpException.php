<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

/**
 * 415 "Unsupported Media Type"
 * 当客户端以您的应用程序不理解的格式发送数据时，请使用这个异常。
 * 例如，如果客户端将XML数据发布到只接受JSON的操作或控制器，就会抛出这个异常。
 * UnsupportedMediaTypeHttpException represents an "Unsupported Media Type" HTTP exception with status code 415.
 *
 * Use this exception when the client sends data in a format that your
 * application does not understand. For example, you would throw this exception
 * if the client POSTs XML data to an action or controller that only accepts
 * JSON.
 *
 * @see https://tools.ietf.org/html/rfc7231#section-6.5.13
 * @author Dan Schmidt <danschmidt5189@gmail.com>
 * @since 2.0
 */
class UnsupportedMediaTypeHttpException extends HttpException
{
    /**
     * Constructor.
     * @param string $message error message
     * @param int $code error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct(415, $message, $code, $previous);
    }
}
