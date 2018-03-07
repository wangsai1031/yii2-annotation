<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\base;

/**
 * InvalidParamException represents an exception caused by invalid parameters passed to a method.
 * InvalidParamException代表传递给方法错误参数参数的异常。
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 * @deprecated since 2.0.14. Use [[InvalidArgumentException]] instead.
 */
class InvalidParamException extends \BadMethodCallException
{
    /**
     * 对用户友好的异常名称
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Invalid Parameter';
    }
}
