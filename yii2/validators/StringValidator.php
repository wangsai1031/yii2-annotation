<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\validators;

use Yii;

/**
 * 检查输入值是否为特定长度的字符串。并检查特性的值是否为某个特定长度。
 * StringValidator validates that the attribute value is of certain length.
 *
 * Note, this validator should only be used with string-typed attributes.
 *
 *  // 检查 "username" 是否为长度 4 到 24 之间的字符串
    ['username', 'string', 'length' => [4, 24]],
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class StringValidator extends Validator
{
    /**
     * 指定待测输入字符串的长度限制。 该属性可以被指定为以下格式之一：
     * 整数：这个字符串的长度必须是这个值;
     * 单元素数组：代表输入字符串的最小长度 (e.g. [8])。这会重写 min 属性。
     * 包含两个元素的数组：代表输入字符串的最小和最大长度(e.g. [8, 128])。 这会同时重写 min 和 max 属性。
     *
     * @var int|array specifies the length limit of the value to be validated.
     * This can be specified in one of the following forms:
     *
     * - an integer: the exact length that the value should be of;
     * - an array of one element: the minimum length that the value should be of. For example, `[8]`.
     *   This will overwrite [[min]].
     * - an array of two elements: the minimum and maximum lengths that the value should be of.
     *   For example, `[8, 128]`. This will overwrite both [[min]] and [[max]].
     * @see tooShort for the customized message for a too short string.
     * @see tooLong for the customized message for a too long string.
     * @see notEqual for the customized message for a string that does not match desired length.
     */
    public $length;
    /**
     * 输入字符串的最大长度。若不设置，则代表不设上限。
     * 
     * @var int maximum length. If not set, it means no maximum length limit.
     * @see tooLong for the customized message for a too long string.
     */
    public $max;
    /**
     * 输入字符串的最小长度。若不设置，则代表不设下限。
     * @var int minimum length. If not set, it means no minimum length limit.
     * @see tooShort for the customized message for a too short string.
     */
    public $min;
    /**
     * @var string user-defined error message used when the value is not a string.
     */
    public $message;
    /**
     * @var string user-defined error message used when the length of the value is smaller than [[min]].
     */
    public $tooShort;
    /**
     * @var string user-defined error message used when the length of the value is greater than [[max]].
     */
    public $tooLong;
    /**
     * @var string user-defined error message used when the length of the value is not equal to [[length]].
     */
    public $notEqual;
    /**
     * 待测字符串的编码方式。若不设置，则使用应用自身的 charset 属性值， 该值默认为 UTF-8。
     * @var string the encoding of the string value to be validated (e.g. 'UTF-8').
     * If this property is not set, [[\yii\base\Application::charset]] will be used.
     */
    public $encoding;


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if (is_array($this->length)) {
            if (isset($this->length[0])) {
                $this->min = $this->length[0];
            }
            if (isset($this->length[1])) {
                $this->max = $this->length[1];
            }
            $this->length = null;
        }
        if ($this->encoding === null) {
            $this->encoding = Yii::$app ? Yii::$app->charset : 'UTF-8';
        }
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} must be a string.');
        }
        if ($this->min !== null && $this->tooShort === null) {
            $this->tooShort = Yii::t('yii', '{attribute} should contain at least {min, number} {min, plural, one{character} other{characters}}.');
        }
        if ($this->max !== null && $this->tooLong === null) {
            $this->tooLong = Yii::t('yii', '{attribute} should contain at most {max, number} {max, plural, one{character} other{characters}}.');
        }
        if ($this->length !== null && $this->notEqual === null) {
            $this->notEqual = Yii::t('yii', '{attribute} should contain {length, number} {length, plural, one{character} other{characters}}.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;

        if (!is_string($value)) {
            $this->addError($model, $attribute, $this->message);

            return;
        }

        $length = mb_strlen($value, $this->encoding);

        if ($this->min !== null && $length < $this->min) {
            $this->addError($model, $attribute, $this->tooShort, ['min' => $this->min]);
        }
        if ($this->max !== null && $length > $this->max) {
            $this->addError($model, $attribute, $this->tooLong, ['max' => $this->max]);
        }
        if ($this->length !== null && $length !== $this->length) {
            $this->addError($model, $attribute, $this->notEqual, ['length' => $this->length]);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validateValue($value)
    {
        if (!is_string($value)) {
            return [$this->message, []];
        }

        $length = mb_strlen($value, $this->encoding);

        if ($this->min !== null && $length < $this->min) {
            return [$this->tooShort, ['min' => $this->min]];
        }
        if ($this->max !== null && $length > $this->max) {
            return [$this->tooLong, ['max' => $this->max]];
        }
        if ($this->length !== null && $length !== $this->length) {
            return [$this->notEqual, ['length' => $this->length]];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function clientValidateAttribute($model, $attribute, $view)
    {
        ValidationAsset::register($view);
        $options = $this->getClientOptions($model, $attribute);

        return 'yii.validation.string(value, messages, ' . json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');';
    }

    /**
     * {@inheritdoc}
     */
    public function getClientOptions($model, $attribute)
    {
        $label = $model->getAttributeLabel($attribute);

        $options = [
            'message' => $this->formatMessage($this->message, [
                'attribute' => $label,
            ]),
        ];

        if ($this->min !== null) {
            $options['min'] = $this->min;
            $options['tooShort'] = $this->formatMessage($this->tooShort, [
                'attribute' => $label,
                'min' => $this->min,
            ]);
        }
        if ($this->max !== null) {
            $options['max'] = $this->max;
            $options['tooLong'] = $this->formatMessage($this->tooLong, [
                'attribute' => $label,
                'max' => $this->max,
            ]);
        }
        if ($this->length !== null) {
            $options['is'] = $this->length;
            $options['notEqual'] = $this->formatMessage($this->notEqual, [
                'attribute' => $label,
                'length' => $this->length,
            ]);
        }
        if ($this->skipOnEmpty) {
            $options['skipOnEmpty'] = 1;
        }

        return $options;
    }
}
