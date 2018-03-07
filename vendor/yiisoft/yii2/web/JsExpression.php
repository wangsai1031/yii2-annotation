<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use yii\base\BaseObject;

/**
 * 将字符串标记为JavaScript表达式
 * JsExpression marks a string as a JavaScript expression.
 *
 * When using [[\yii\helpers\Json::encode()]] or [[\yii\helpers\Json::htmlEncode()]] to encode a value, JsonExpression objects
 * will be specially handled and encoded as a JavaScript expression instead of a string.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class JsExpression extends BaseObject
{
    /**
     * @var string the JavaScript expression represented by this object
     */
    public $expression;


    /**
     * Constructor.
     * 由这个对象表示的JavaScript表达式
     * @param string $expression the JavaScript expression represented by this object
     * @param array $config additional configurations for this object
     */
    public function __construct($expression, $config = [])
    {
        $this->expression = $expression;
        parent::__construct($config);
    }

    /**
     * PHP魔术函数将一个对象转换为字符串
     * __toString() 方法用于一个类被当成字符串时应怎样回应。例如 echo $obj; 应该显示些什么。
     *
     * The PHP magic function converting an object into a string.
     * @return string the JavaScript expression.
     */
    public function __toString()
    {
        return $this->expression;
    }
}
