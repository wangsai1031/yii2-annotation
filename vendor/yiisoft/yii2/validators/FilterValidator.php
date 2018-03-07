<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\validators;

use yii\base\InvalidConfigException;

/**
 * FilterValidator converts the attribute value according to a filter.
 *
 * 该验证器并不进行数据验证。
 * 而是，给输入值应用一个滤镜， 并在检验过程之后把它赋值回特性变量。
 *
 * 有许多PHP函数具有用于筛选回调的属性。
 * 例如类型转换函数（e.g. intval, boolval）来确保属性的特定类型。
 * 您可以简单地指定过滤器的函数名，而不需要将它们封装在一个闭包中:
 * ```
 *  ['property', 'filter', 'filter' => 'boolval'],
    ['property', 'filter', 'filter' => 'intval'],
 * ```
 * FilterValidator is actually not a validator but a data processor.
 * It invokes the specified filter callback to process the attribute value
 * and save the processed value back to the attribute. The filter must be
 * a valid PHP callback with the following signature:
 *
 * ```php
 *  // trim 掉 "username" 和 "email" 输入
 *  // 技巧：如果你只是想要用 trim 处理下输入值，你可以直接用 trim 验证器的。
    [['username', 'email'], 'filter', 'filter' => 'trim', 'skipOnArray' => true],

    // 标准化 "phone" 输入
    ['phone', 'filter', 'filter' => function ($value) {
        // 在此处标准化输入的电话号码
        return $value;
    }],
 * ```
 *
 * ```php
 * function foo($value) {
 *     // compute $newValue here
 *     return $newValue;
 * }
 * ```
 *
 * Many PHP functions qualify this signature (e.g. `trim()`).
 *
 * To specify the filter, set [[filter]] property to be the callback.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class FilterValidator extends Validator
{
    /**
     * 用于定义滤镜的 PHP 回调函数。
     * 可以为全局函数名，匿名函数，或其他。
     * 该函数的样式必须是
     *
     * ```
     * function ($value) {
     *      return $newValue;
     * }
     * ```
     * 该属性不能省略，必须设置。
     *
     * @var callable the filter. This can be a global function name, anonymous function, etc.
     * The function signature must be as follows,
     *
     * ```php
     * function foo($value) {
     *     // compute $newValue here
     *     return $newValue;
     * }
     * ```
     */
    public $filter;
    /**
     * 是否在输入值为数组时跳过滤镜。
     * 默认为 false。
     * 请注意如果滤镜不能处理数组输入，你就应该把该属性设为 true。
     * 否则可能会导致 PHP Error 的发生。
     *
     * @var boolean whether the filter should be skipped if an array input is given.
     * If true and an array input is given, the filter will not be applied.
     */
    public $skipOnArray = false;
    /**
     * @var boolean this property is overwritten to be false so that this validator will
     * be applied when the value being validated is empty.
     */
    public $skipOnEmpty = false;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->filter === null) {
            throw new InvalidConfigException('The "filter" property must be set.');
        }
    }

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        if (!$this->skipOnArray || !is_array($value)) {
            $model->$attribute = call_user_func($this->filter, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function clientValidateAttribute($model, $attribute, $view)
    {
        if ($this->filter !== 'trim') {
            return null;
        }

        $options = [];
        if ($this->skipOnEmpty) {
            $options['skipOnEmpty'] = 1;
        }

        ValidationAsset::register($view);

        return 'value = yii.validation.trim($form, attribute, ' . json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');';
    }
}
