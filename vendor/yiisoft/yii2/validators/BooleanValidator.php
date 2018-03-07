<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\validators;

use Yii;

/**
 * 布尔值验证器检查属性值是否为布尔值
 * BooleanValidator checks if the attribute value is a boolean value.
 *
 * Possible boolean values can be configured via the [[trueValue]] and [[falseValue]] properties.
 * And the comparison can be either [[strict]] or not.
 *
 *  // 检查 "selected" 是否为 0 或 1，无视数据类型
    ['selected', 'boolean'],

    // 检查 "deleted" 是否为布尔类型，即 true 或 false
    ['deleted', 'boolean', 'trueValue' => true, 'falseValue' => false, 'strict' => true],
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class BooleanValidator extends Validator
{
    /**
     * 表示true状态的值,默认为 1
     * @var mixed the value representing true status. Defaults to '1'.
     */
    public $trueValue = '1';
    /**
     * 表示 false 状态的值,默认为 0
     * @var mixed the value representing false status. Defaults to '0'.
     */
    public $falseValue = '0';
    /**
     * 是否要求待测输入必须严格匹配 trueValue 或 falseValue。
     * 当该属性是真时，属性值和类型必须严格比较[[trueValue]] or [[falseValue]]的值
     * 默认值为false，这意味着并非严格比较
     *
     * 注意: 因为通过 HTML 表单传递的输入数据都是字符串类型，所以一般情况下你都需要保持 strict 属性为假。
     *
     * @var boolean whether the comparison to [[trueValue]] and [[falseValue]] is strict.
     * When this is true, the attribute value and type must both match those of [[trueValue]] or [[falseValue]].
     * Defaults to false, meaning only the value needs to be matched.
     */
    public $strict = false;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} must be either "{true}" or "{false}".');
        }
    }

    /**
     * 服务端验证值是否符合规则
     * @inheritdoc
     */
    protected function validateValue($value)
    {
        // 非严格匹配时，用 ==
        $valid = !$this->strict && ($value == $this->trueValue || $value == $this->falseValue)
            // 严格匹配时，用 ===
                 || $this->strict && ($value === $this->trueValue || $value === $this->falseValue);

        if (!$valid) {
            // 返回错误信息和参数
            return [$this->message, [
                'true' => $this->trueValue === true ? 'true' : $this->trueValue,
                'false' => $this->falseValue === false ? 'false' : $this->falseValue,
            ]];
        }

        return null;
    }

    /**
     * 客户端验证
     * @inheritdoc
     */
    public function clientValidateAttribute($model, $attribute, $view)
    {
        // 配置信息
        $options = [
            'trueValue' => $this->trueValue,
            'falseValue' => $this->falseValue,
            'message' => Yii::$app->getI18n()->format($this->message, [
                'attribute' => $model->getAttributeLabel($attribute),
                'true' => $this->trueValue === true ? 'true' : $this->trueValue,
                'false' => $this->falseValue === false ? 'false' : $this->falseValue,
            ], Yii::$app->language),
        ];
        if ($this->skipOnEmpty) {
            $options['skipOnEmpty'] = 1;
        }
        if ($this->strict) {
            $options['strict'] = 1;
        }

        // 注册验证所需前端资源
        ValidationAsset::register($view);

        return 'yii.validation.boolean(value, messages, ' . json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');';
    }
}
