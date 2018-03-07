<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\validators;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Html;

/**
 *
 * 将指定的属性值与另一个值进行比较
 * CompareValidator compares the specified attribute value with another value.
 *
 * The value being compared with can be another attribute value
 * (specified via [[compareAttribute]]) or a constant (specified via
 * [[compareValue]]. When both are specified, the latter takes
 * precedence. If neither is specified, the attribute will be compared
 * with another attribute whose name is by appending "_repeat" to the source
 * attribute name.
 *
 * CompareValidator supports different comparison operators, specified
 * via the [[operator]] property.
 *
 *  // 检查 "password" 特性的值是否与 "password_repeat" 的值相同
    ['password', 'compare'],

    // 检查年龄是否大于等于 30
    ['age', 'compare', 'compareValue' => 30, 'operator' => '>='],
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class CompareValidator extends Validator
{
    /**
     * 用于与原特性相比较的特性名称。
     * 当该验证器被用于验证某目标特性时，该属性会默认为目标属性加后缀 _repeat。
     * 举例来说，若目标特性为 password，则该属性默认为 password_repeat。
     *
     * @var string the name of the attribute to be compared with. When both this property
     * and [[compareValue]] are set, the latter takes precedence. If neither is set,
     * it assumes the comparison is against another attribute whose name is formed by
     * appending '_repeat' to the attribute being validated. For example, if 'password' is
     * being validated, then the attribute to be compared would be 'password_repeat'.
     * @see compareValue
     */
    public $compareAttribute;
    /**
     * 用于与输入值相比较的常量值。
     * 当该属性与 compareAttribute 属性同时被指定时， 该属性优先被使用。
     *
     * @var mixed the constant value to be compared with. When both this property
     * and [[compareAttribute]] are set, this property takes precedence.
     * @see compareAttribute
     */
    public $compareValue;
    /**
     * 被比较的值的类型，支持以下类型：
     * 支持以下类型：
     * - string: 值被作为字符串进行比较。在比较之前不会进行转换。
     * - number: 值被作为数字进行比较,在比较之前，字符串值将被转换为数字（float）。
     *
     * @var string the type of the values being compared. The follow types are supported:
     *
     * - string: the values are being compared as strings. No conversion will be done before comparison.
     * - number: the values are being compared as numbers. String values will be converted into numbers before comparison.
     */
    public $type = 'string';
    /**
     * 比较操作符。默认为 ==，意味着检查输入值是否与 compareAttribute 或 compareValue 的值相等。
     * 该属性支持如下操作符：
     *
     *  ==  ：检查两值是否相等。比对为非严格模式。
        === ：检查两值是否全等。比对为严格模式。
        !=  ：检查两值是否不等。比对为非严格模式。
        !== ：检查两值是否不全等。比对为严格模式。
        >   ：检查待测目标值是否大于给定被测值。
        >=  ：检查待测目标值是否大于等于给定被测值。
        <   ：检查待测目标值是否小于给定被测值。
        <=  ：检查待测目标值是否小于等于给定被测值。
     *
     * @var string the operator for comparison. The following operators are supported:
     *
     * - `==`: check if two values are equal. The comparison is done is non-strict mode.
     * - `===`: check if two values are equal. The comparison is done is strict mode.
     * - `!=`: check if two values are NOT equal. The comparison is done is non-strict mode.
     * - `!==`: check if two values are NOT equal. The comparison is done is strict mode.
     * - `>`: check if value being validated is greater than the value being compared with.
     * - `>=`: check if value being validated is greater than or equal to the value being compared with.
     * - `<`: check if value being validated is less than the value being compared with.
     * - `<=`: check if value being validated is less than or equal to the value being compared with.
     *
     * When you want to compare numbers, make sure to also set [[type]] to `number`.
     */
    public $operator = '==';
    /**
     * 用户定义的错误消息。
     * 它可能包含以下占位符，这些占位符将被验证器所取代:
     * - `{attribute}`: 被验证的属性的标签
     * - `{value}`: 被验证的属性的值
     * - `{compareValue}`: 与之比较的值或属性标签
     * - `{compareAttribute}`: 与之相比较的属性的标签
     * - `{compareValueOrAttribute}`: 与之比较的值或属性标签
     *
     * @var string the user-defined error message.
     * It may contain the following placeholders which will be replaced accordingly by the validator:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     * - `{compareValue}`: the value or the attribute label to be compared with
     * - `{compareAttribute}`: the label of the attribute to be compared with
     * - `{compareValueOrAttribute}`: the value or the attribute label to be compared with
     */
    public $message;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            switch ($this->operator) {
                case '==':
                    $this->message = Yii::t('yii', '{attribute} must be equal to "{compareValueOrAttribute}".');
                    break;
                case '===':
                    $this->message = Yii::t('yii', '{attribute} must be equal to "{compareValueOrAttribute}".');
                    break;
                case '!=':
                    $this->message = Yii::t('yii', '{attribute} must not be equal to "{compareValueOrAttribute}".');
                    break;
                case '!==':
                    $this->message = Yii::t('yii', '{attribute} must not be equal to "{compareValueOrAttribute}".');
                    break;
                case '>':
                    $this->message = Yii::t('yii', '{attribute} must be greater than "{compareValueOrAttribute}".');
                    break;
                case '>=':
                    $this->message = Yii::t('yii', '{attribute} must be greater than or equal to "{compareValueOrAttribute}".');
                    break;
                case '<':
                    $this->message = Yii::t('yii', '{attribute} must be less than "{compareValueOrAttribute}".');
                    break;
                case '<=':
                    $this->message = Yii::t('yii', '{attribute} must be less than or equal to "{compareValueOrAttribute}".');
                    break;
                default:
                    throw new InvalidConfigException("Unknown operator: {$this->operator}");
            }
        }
    }

    /**
     * 验证属性
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        // 获取属性值
        $value = $model->$attribute;
        // 若值是数组，则返回错误
        if (is_array($value)) {
            $this->addError($model, $attribute, Yii::t('yii', '{attribute} is invalid.'));

            return;
        }
        // 若 compareValue 不为空
        if ($this->compareValue !== null) {
            // 连续赋值
            $compareLabel = $compareValue = $compareValueOrAttribute = $this->compareValue;
        } else {
            // 若 $this->compareAttribute 为空，则默认在 $attribute 后接 '_repeat' 拼接成 compareAttribute
            $compareAttribute = $this->compareAttribute === null ? $attribute . '_repeat' : $this->compareAttribute;
            // 获取要对比的值
            $compareValue = $model->$compareAttribute;
            // 获取属性标签
            $compareLabel = $compareValueOrAttribute = $model->getAttributeLabel($compareAttribute);
        }
        // 比较值
        if (!$this->compareValues($this->operator, $this->type, $value, $compareValue)) {
            $this->addError($model, $attribute, $this->message, [
                'compareAttribute' => $compareLabel,
                'compareValue' => $compareValue,
                'compareValueOrAttribute' => $compareValueOrAttribute,
            ]);
        }
    }

    /**
     * todo ？ 用不到啊
     * 验证值
     * @inheritdoc
     */
    protected function validateValue($value)
    {
        // $this->compareValue 不能为空
        if ($this->compareValue === null) {
            throw new InvalidConfigException('CompareValidator::compareValue must be set.');
        }
        // 比较值
        if (!$this->compareValues($this->operator, $this->type, $value, $this->compareValue)) {
            return [$this->message, [
                'compareAttribute' => $this->compareValue,
                'compareValue' => $this->compareValue,
                'compareValueOrAttribute' => $this->compareValue,
            ]];
        } else {
            return null;
        }
    }

    /**
     * 使用指定的操作符将两个值进行比较。
     * Compares two values with the specified operator.
     * @param string $operator the comparison operator
     * @param string $type the type of the values being compared
     * @param mixed $value the value being compared
     * @param mixed $compareValue another value being compared
     * @return boolean whether the comparison using the specified operator is true.
     */
    protected function compareValues($operator, $type, $value, $compareValue)
    {
        // 如果是 数字，则转换为float
        if ($type === 'number') {
            $value = (float) $value;
            $compareValue = (float) $compareValue;
        } else {
            // 否则将转换为字符串
            $value = (string) $value;
            $compareValue = (string) $compareValue;
        }
        switch ($operator) {
            case '==':
                return $value == $compareValue;
            case '===':
                return $value === $compareValue;
            case '!=':
                return $value != $compareValue;
            case '!==':
                return $value !== $compareValue;
            case '>':
                return $value > $compareValue;
            case '>=':
                return $value >= $compareValue;
            case '<':
                return $value < $compareValue;
            case '<=':
                return $value <= $compareValue;
            default:
                return false;
        }
    }

    /**
     * 客户端验证
     * @inheritdoc
     */
    public function clientValidateAttribute($model, $attribute, $view)
    {
        $options = [
            'operator' => $this->operator,
            'type' => $this->type,
        ];

        if ($this->compareValue !== null) {
            $options['compareValue'] = $this->compareValue;
            $compareLabel = $compareValue = $compareValueOrAttribute = $this->compareValue;
        } else {
            $compareAttribute = $this->compareAttribute === null ? $attribute . '_repeat' : $this->compareAttribute;
            $compareValue = $model->getAttributeLabel($compareAttribute);
            $options['compareAttribute'] = Html::getInputId($model, $compareAttribute);
            $compareLabel = $compareValueOrAttribute = $model->getAttributeLabel($compareAttribute);
        }

        if ($this->skipOnEmpty) {
            $options['skipOnEmpty'] = 1;
        }

        $options['message'] = Yii::$app->getI18n()->format($this->message, [
            'attribute' => $model->getAttributeLabel($attribute),
            'compareAttribute' => $compareLabel,
            'compareValue' => $compareValue,
            'compareValueOrAttribute' => $compareValueOrAttribute,
        ], Yii::$app->language);

        ValidationAsset::register($view);

        return 'yii.validation.compare(value, messages, ' . json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');';
    }
}
