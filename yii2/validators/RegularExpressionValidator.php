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
use yii\helpers\Json;
use yii\web\JsExpression;

/**
 * 检查输入值是否匹配指定正则表达式。
 * RegularExpressionValidator validates that the attribute value matches the specified [[pattern]].
 *
 * If the [[not]] property is set true, the validator will ensure the attribute value do NOT match the [[pattern]].
 *
 *  // 检查 "username" 是否由字母开头，且只包含单词字符
    ['username', 'match', 'pattern' => '/^[a-z]\w*$/i']
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class RegularExpressionValidator extends Validator
{
    /**
     * 用于检测输入值的正则表达式。该属性是必须的， 若不设置则会抛出异常。
     * @var string the regular expression to be matched with
     */
    public $pattern;
    /**
     * 是否对验证的结果取反。
     * 默认为 false， 代表输入值匹配正则表达式时验证成功。
     * 如果设为 true， 则输入值不匹配正则时返回匹配成功。
     * @var bool whether to invert the validation logic. Defaults to false. If set to true,
     * the regular expression defined via [[pattern]] should NOT match the attribute value.
     */
    public $not = false;


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->pattern === null) {
            throw new InvalidConfigException('The "pattern" property must be set.');
        }
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} is invalid.');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validateValue($value)
    {
        $valid = !is_array($value) &&
            (!$this->not && preg_match($this->pattern, $value)
            || $this->not && !preg_match($this->pattern, $value));

        return $valid ? null : [$this->message, []];
    }

    /**
     * {@inheritdoc}
     */
    public function clientValidateAttribute($model, $attribute, $view)
    {
        ValidationAsset::register($view);
        $options = $this->getClientOptions($model, $attribute);

        return 'yii.validation.regularExpression(value, messages, ' . Json::htmlEncode($options) . ');';
    }

    /**
     * {@inheritdoc}
     */
    public function getClientOptions($model, $attribute)
    {
        $pattern = Html::escapeJsRegularExpression($this->pattern);

        $options = [
            'pattern' => new JsExpression($pattern),
            'not' => $this->not,
            'message' => $this->formatMessage($this->message, [
                'attribute' => $model->getAttributeLabel($attribute),
            ]),
        ];
        if ($this->skipOnEmpty) {
            $options['skipOnEmpty'] = 1;
        }

        return $options;
    }
}
