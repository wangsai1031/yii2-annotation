<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\validators;

/**
 * 将属性设置为指定的默认值
 * DefaultValueValidator sets the attribute to be the specified default value.
 *
 * 该验证器并不进行数据验证。而是， 给为空(null, '', [])的待测特性分配默认值
 * DefaultValueValidator is not really a validator. It is provided mainly to allow
 * specifying attribute default values when they are empty.
 *
 *  // 若 "age" 为空，则将其设为 null
    ['age', 'default', 'value' => null],

    // 若 "country" 为空，则将其设为 "USA"
    ['country', 'default', 'value' => 'USA'],

    // 若 "from" 和 "to" 为空，则分别给他们分配自今天起，3 天后和 6 天后的日期。
    [['from', 'to'], 'default', 'value' => function ($model, $attribute) {
        return date('Y-m-d', strtotime($attribute === 'to' ? '+3 days' ：'+6 days'));
    }],
 *
 * 默认情况下，当输入项为空字符串，空数组，或 null 时，会被视为“空值”。
 * 你也可以通过配置yii\validators\Validator::isEmpty() 属性来自定义空值的判定规则。比如，

    ['agree', 'required', 'isEmpty' => function ($value) {
        return empty($value);
    }]
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DefaultValueValidator extends Validator
{
    /**
     * @var mixed the default value or an anonymous function that returns the default value which will
     * be assigned to the attributes being validated if they are empty. The signature of the anonymous function
     * should be as follows,
     *
     * 默认值，或一个返回默认值的 PHP Callable 对象（即回调函数）。
     * 它们会分配给检测为空的待测特性。PHP 回调方法的样式如下：
     *
     * ```php
     * function($model, $attribute) {
     *     // compute value
     *     return $value;
     * }
     * ```
     */
    public $value;
    /**
     * 该属性被覆盖为false，因此当验证的值为空时，该验证器将被应用。
     * @var boolean this property is overwritten to be false so that this validator will be applied when the value being validated is empty.
     */
    public $skipOnEmpty = false;


    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        if ($this->isEmpty($model->$attribute)) {
            if ($this->value instanceof \Closure) {
                $model->$attribute = call_user_func($this->value, $model, $attribute);
            } else {
                $model->$attribute = $this->value;
            }
        }
    }
}
