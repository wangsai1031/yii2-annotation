<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

/**
 * 表示在URL规范化过程中应该执行重定向的信息
 * UrlNormalizerRedirectException represents an information for redirection which should be performed during the URL normalization.
 *
 * @author Robert Korulczyk <robert@korulczyk.pl>
 * @since 2.0.10
 */
class UrlNormalizerRedirectException extends \yii\base\Exception
{
    /**
     * 生成用于重定向的有效URL的参数
     * @var array|string the parameter to be used to generate a valid URL for redirection
     * @see [[\yii\helpers\Url::to()]]
     */
    public $url;
    /**
     * 在生成的URL中使用的用于重定向的URI方案
     * @var boolean|string the URI scheme to use in the generated URL for redirection
     * @see [[\yii\helpers\Url::to()]]
     */
    public $scheme;
    /**
     * HTTP状态代码
     * @var integer the HTTP status code
     */
    public $statusCode;

    /**
     * 生成用于重定向的有效URL的参数。
     * 这将用作[[\yii\helpers\Url::to()]]的第一个参数。
     * @param array|string $url the parameter to be used to generate a valid URL for redirection.
     * This will be used as first parameter for [[\yii\helpers\Url::to()]]
     * @param integer $statusCode HTTP status code used for redirection
     * @param boolean|string $scheme the URI scheme to use in the generated URL for redirection.
     * This will be used as second parameter for [[\yii\helpers\Url::to()]]
     * @param string $message the error message
     * @param integer $code the error code
     * @param \Exception $previous the previous exception used for the exception chaining
     */
    public function __construct($url, $statusCode = 302, $scheme = false, $message = null, $code = 0, \Exception $previous = null)
    {
        $this->url = $url;
        $this->scheme = $scheme;
        $this->statusCode = $statusCode;
        parent::__construct($message, $code, $previous);
    }
}
