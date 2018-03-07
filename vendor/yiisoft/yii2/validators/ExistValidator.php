<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\validators;

use Yii;
use yii\base\InvalidConfigException;

/**
 * 验证表中是否存在属性值
 * ExistValidator validates that the attribute value exists in a table.
 *
 * ExistValidator checks if the value being validated can be found in the table column specified by
 * the ActiveRecord class [[targetClass]] and the attribute [[targetAttribute]].
 *
 * This validator is often used to verify that a foreign key contains a value
 * that can be found in the foreign table.
 *
 * The following are examples of validation rules using this validator:
 *
 * ```php
 *  // a1 需要在 "a1" 特性所代表的字段内存在
    ['a1', 'exist'],

    // a1 必需存在，但检验的是 a1 的值在字段 a2 中的存在性
    ['a1', 'exist', 'targetAttribute' => 'a2'],

    // a1 和 a2 的值都需要存在，且它们都能收到错误提示
    [['a1', 'a2'], 'exist', 'targetAttribute' => ['a1', 'a2']],

    // a1 和 a2 的值都需要存在，只有 a1 能接收到错误信息
    ['a1', 'exist', 'targetAttribute' => ['a1', 'a2']],

    // 通过同时在 a2 和 a3 字段中检查 a2 和 a1 的值来确定 a1 的存在性
    ['a1', 'exist', 'targetAttribute' => ['a2', 'a1' => 'a3']],

    // a1 必需存在，若 a1 为数组，则其每个子元素都必须存在。
    ['a1', 'exist', 'allowArray' => true],
 *
 * ```
 *
 * ```php
 * // a1 needs to exist
 * ['a1', 'exist']
 * // a1 needs to exist, but its value will use a2 to check for the existence
 * ['a1', 'exist', 'targetAttribute' => 'a2']
 * // a1 and a2 need to exist together, and they both will receive error message
 * [['a1', 'a2'], 'exist', 'targetAttribute' => ['a1', 'a2']]
 * // a1 and a2 need to exist together, only a1 will receive error message
 * ['a1', 'exist', 'targetAttribute' => ['a1', 'a2']]
 * // a1 needs to exist by checking the existence of both a2 and a3 (using a1 value)
 * ['a1', 'exist', 'targetAttribute' => ['a2', 'a1' => 'a3']]
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ExistValidator extends Validator
{
    /**
     * 用于查找输入值的目标 AR 类。若不设置， 则会使用正在进行验证的当前模型类。
     * @var string the name of the ActiveRecord class that should be used to validate the existence
     * of the current attribute value. It not set, it will use the ActiveRecord class of the attribute being validated.
     * @see targetAttribute
     */
    public $targetClass;
    /**
     * 用于检查输入值存在性的 targetClass 的模型特性。
     * 若不设置，它会直接使用待测特性名（整个参数数组的首元素）。
     * 除了指定为字符串以外，你也可以用数组的形式，同时指定多个用于验证的表字段，
     * 数组的键和值都是代表字段的特性名， 值表示 targetClass 的待测数据源字段，而键表示当前模型的待测特性名。
     * 若键和值相同，你可以只指定值。
     * （如:['a2'] 就代表 ['a2'=>'a2']）
     *
     * @var string|array the name of the ActiveRecord attribute that should be used to
     * validate the existence of the current attribute value. If not set, it will use the name
     * of the attribute currently being validated. You may use an array to validate the existence
     * of multiple columns at the same time. The array values are the attributes that will be
     * used to validate the existence, while the array keys are the attributes whose values are to be validated.
     * If the key and the value are the same, you can just specify the value.
     */
    public $targetAttribute;
    /**
     * 用于检查输入值存在性必然会进行数据库查询，而该属性为用于进一步筛选该查询的过滤条件。
     * 可以为代表额外查询条件的字符串或数组(关于查询条件的格式，请参考 yii\db\Query::where())；
     * 或者样式为 function ($query) 的匿名函数， $query 参数为你希望在该函数内进行修改的 Query 对象。
     *
     * @var string|array|\Closure additional filter to be applied to the DB query used to check the existence of the attribute value.
     * This can be a string or an array representing the additional query condition (refer to [[\yii\db\Query::where()]]
     * on the format of query condition), or an anonymous function with the signature `function ($query)`, where `$query`
     * is the [[\yii\db\Query|Query]] object that you can modify in the function.
     */
    public $filter;
    /**
     * 是否允许输入值为数组。
     * 默认为 false。
     * 若该属性为 true 且输入值为数组，则数组的每个元素都必须在目标字段中存在。
     * 值得注意的是，若用吧 targetAttribute 设为多元素数组来验证被测值在多字段中的存在性时， 该属性不能设置为 true。
     * @var boolean whether to allow array type attribute.
     */
    public $allowArray = false;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} is invalid.');
        }
    }

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        $targetAttribute = $this->targetAttribute === null ? $attribute : $this->targetAttribute;

        if (is_array($targetAttribute)) {
            if ($this->allowArray) {
                throw new InvalidConfigException('The "targetAttribute" property must be configured as a string.');
            }
            $params = [];
            foreach ($targetAttribute as $k => $v) {
                $params[$v] = is_int($k) ? $model->$v : $model->$k;
            }
        } else {
            $params = [$targetAttribute => $model->$attribute];
        }

        if (!$this->allowArray) {
            foreach ($params as $value) {
                if (is_array($value)) {
                    $this->addError($model, $attribute, Yii::t('yii', '{attribute} is invalid.'));

                    return;
                }
            }
        }

        $targetClass = $this->targetClass === null ? get_class($model) : $this->targetClass;
        $query = $this->createQuery($targetClass, $params);

        if (is_array($model->$attribute)) {
            if ($query->count("DISTINCT [[$targetAttribute]]") != count($model->$attribute)) {
                $this->addError($model, $attribute, $this->message);
            }
        } elseif (!$query->exists()) {
            $this->addError($model, $attribute, $this->message);
        }
    }

    /**
     * @inheritdoc
     */
    protected function validateValue($value)
    {
        if ($this->targetClass === null) {
            throw new InvalidConfigException('The "targetClass" property must be set.');
        }
        if (!is_string($this->targetAttribute)) {
            throw new InvalidConfigException('The "targetAttribute" property must be configured as a string.');
        }

        $query = $this->createQuery($this->targetClass, [$this->targetAttribute => $value]);

        if (is_array($value)) {
            if (!$this->allowArray) {
                return [$this->message, []];
            }
            return $query->count("DISTINCT [[$this->targetAttribute]]") == count($value) ? null : [$this->message, []];
        } else {
            return $query->exists() ? null : [$this->message, []];
        }
    }

    /**
     * Creates a query instance with the given condition.
     * @param string $targetClass the target AR class
     * @param mixed $condition query condition
     * @return \yii\db\ActiveQueryInterface the query instance
     */
    protected function createQuery($targetClass, $condition)
    {
        /* @var $targetClass \yii\db\ActiveRecordInterface */
        $query = $targetClass::find()->andWhere($condition);
        if ($this->filter instanceof \Closure) {
            call_user_func($this->filter, $query);
        } elseif ($this->filter !== null) {
            $query->andWhere($this->filter);
        }

        return $query;
    }
}
