<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\validators;

use Yii;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;
use yii\db\ActiveRecordInterface;
use yii\helpers\Inflector;

/**
 * 检查输入值是否在某表字段中唯一。
 * 它只对活动记录类型的模型类特性起作用， 能支持对一个或多过字段的验证。
 * UniqueValidator validates that the attribute value is unique in the specified database table.
 *
 * UniqueValidator checks if the value being validated is unique in the table column specified by
 * the ActiveRecord class [[targetClass]] and the attribute [[targetAttribute]].
 *
 * The following are examples of validation rules using this validator:
 *
 * ```php
 * // a1 needs to be unique
 * // a1 需要在 "a1" 特性所代表的字段内唯一
 * ['a1', 'unique']
 * // a1 needs to be unique, but column a2 will be used to check the uniqueness of the a1 value
 * // a1 需要唯一，但检验的是 a1 的值在字段 a2 中的唯一性
 * ['a1', 'unique', 'targetAttribute' => 'a2']
 * // a1 and a2 need to be unique together, and they both will receive error message
 * // a1 和 a2 的组合需要唯一，且它们都能收到错误提示
 * [['a1', 'a2'], 'unique', 'targetAttribute' => ['a1', 'a2']]
 * // a1 and a2 need to be unique together, only a1 will receive error message
 *  // a1 和 a2 的组合需要唯一，只有 a1 能接收错误提示
 * ['a1', 'unique', 'targetAttribute' => ['a1', 'a2']]
 * // a1 needs to be unique by checking the uniqueness of both a2 and a3 (using a1 value)
 * // 通过同时在 a2 和 a3 字段中检查 a2 和 a3 的值来确定 a1 的唯一性
 * ['a1', 'unique', 'targetAttribute' => ['a2', 'a1' => 'a3']]
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class UniqueValidator extends Validator
{
    /**
     * 用于查找输入值的目标 AR 类。
     * 若不设置，则会使用正在进行验证的当前模型类。
     * @var string the name of the ActiveRecord class that should be used to validate the uniqueness
     * of the current attribute value. If not set, it will use the ActiveRecord class of the attribute being validated.
     * @see targetAttribute
     */
    public $targetClass;
    /**
     * 用于检查输入值唯一性的 targetClass 的模型特性。
     * 若不设置，它会直接使用待测特性名（整个参数数组的首元素）。
     * 除了指定为字符串以外，你也可以用数组的形式，同时指定多个用于验证的表字段，
     * 数组的键和值都是代表字段的特性名， 值表示 targetClass 的待测数据源字段，而键表示当前模型的待测特性名。
     * 若键和值相同，你可以只指定值。
     * （如:['a2'] 就代表 ['a2'=>'a2']）
     *
     * @var string|array the name of the [[\yii\db\ActiveRecord|ActiveRecord]] attribute that should be used to
     * validate the uniqueness of the current attribute value. If not set, it will use the name
     * of the attribute currently being validated. You may use an array to validate the uniqueness
     * of multiple columns at the same time. The array values are the attributes that will be
     * used to validate the uniqueness, while the array keys are the attributes whose values are to be validated.
     */
    public $targetAttribute;
    /**
     * 用于检查输入值唯一性必然会进行数据库查询， 而该属性为用于进一步筛选该查询的过滤条件。
     * 可以为代表额外查询条件的字符串或数组 （关于查询条件的格式，请参考 yii\db\Query::where()）；
     * 或者样式为 function ($query) 的匿名函数， $query 参数为你希望在该函数内进行修改的 Query 对象。
     *
     * @var string|array|\Closure additional filter to be applied to the DB query used to check the uniqueness of the attribute value.
     * This can be a string or an array representing the additional query condition (refer to [[\yii\db\Query::where()]]
     * on the format of query condition), or an anonymous function with the signature `function ($query)`, where `$query`
     * is the [[\yii\db\Query|Query]] object that you can modify in the function.
     */
    public $filter;
    /**
     * @var string the user-defined error message.
     *
     * When validating single attribute, it may contain
     * the following placeholders which will be replaced accordingly by the validator:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     *
     * When validating mutliple attributes, it may contain the following placeholders:
     *
     * - `{attributes}`: the labels of the attributes being validated.
     * - `{values}`: the values of the attributes being validated.
     */
    public $message;
    /**
     * @var string
     * @since 2.0.9
     * @deprecated since version 2.0.10, to be removed in 2.1. Use [[message]] property
     * to setup custom message for multiple target attributes.
     */
    public $comboNotUnique;
    /**
     * @var string and|or define how target attributes are related
     * @since 2.0.11
     */
    public $targetAttributeJunction = 'and';
    /**
     * @var bool whether this validator is forced to always use master DB
     * @since 2.0.14
     */
    public $forceMasterDb =  true;


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->message !== null) {
            return;
        }
        if (is_array($this->targetAttribute) && count($this->targetAttribute) > 1) {
            // fallback for deprecated `comboNotUnique` property - use it as message if is set
            if ($this->comboNotUnique === null) {
                $this->message = Yii::t('yii', 'The combination {values} of {attributes} has already been taken.');
            } else {
                $this->message = $this->comboNotUnique;
            }
        } else {
            $this->message = Yii::t('yii', '{attribute} "{value}" has already been taken.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateAttribute($model, $attribute)
    {
        /* @var $targetClass ActiveRecordInterface */
        $targetClass = $this->getTargetClass($model);
        $targetAttribute = $this->targetAttribute === null ? $attribute : $this->targetAttribute;
        $rawConditions = $this->prepareConditions($targetAttribute, $model, $attribute);
        $conditions = [$this->targetAttributeJunction === 'or' ? 'or' : 'and'];

        foreach ($rawConditions as $key => $value) {
            if (is_array($value)) {
                $this->addError($model, $attribute, Yii::t('yii', '{attribute} is invalid.'));
                return;
            }
            $conditions[] = [$key => $value];
        }

        $db = $targetClass::getDb();

        $modelExists = false;

        if ($this->forceMasterDb && method_exists($db, 'useMaster')) {
            $db->useMaster(function () use ($targetClass, $conditions, $model, &$modelExists) {
                $modelExists = $this->modelExists($targetClass, $conditions, $model);
            });
        } else {
            $modelExists = $this->modelExists($targetClass, $conditions, $model);
        }

        if ($modelExists) {
            if (is_array($targetAttribute) && count($targetAttribute) > 1) {
                $this->addComboNotUniqueError($model, $attribute);
            } else {
                $this->addError($model, $attribute, $this->message);
            }
        }
    }

    /**
     * @param Model $model the data model to be validated
     * @return string Target class name
     */
    private function getTargetClass($model)
    {
        return $this->targetClass === null ? get_class($model) : $this->targetClass;
    }

    /**
     * Checks whether the $model exists in the database.
     *
     * @param string $targetClass the name of the ActiveRecord class that should be used to validate the uniqueness
     * of the current attribute value.
     * @param array $conditions conditions, compatible with [[\yii\db\Query::where()|Query::where()]] key-value format.
     * @param Model $model the data model to be validated
     *
     * @return bool whether the model already exists
     */
    private function modelExists($targetClass, $conditions, $model)
    {
        /** @var ActiveRecordInterface $targetClass $query */
        $query = $this->prepareQuery($targetClass, $conditions);

        if (!$model instanceof ActiveRecordInterface || $model->getIsNewRecord() || $model->className() !== $targetClass::className()) {
            // if current $model isn't in the database yet then it's OK just to call exists()
            // also there's no need to run check based on primary keys, when $targetClass is not the same as $model's class
            $exists = $query->exists();
        } else {
            // if current $model is in the database already we can't use exists()
            if ($query instanceof \yii\db\ActiveQuery) {
                // only select primary key to optimize query
                $columnsCondition = array_flip($targetClass::primaryKey());
                $query->select(array_flip($this->applyTableAlias($query, $columnsCondition)));
                
                // any with relation can't be loaded because related fields are not selected
                $query->with = null;
            }
            $models = $query->limit(2)->asArray()->all();
            $n = count($models);
            if ($n === 1) {
                // if there is one record, check if it is the currently validated model
                $dbModel = reset($models);
                $pks = $targetClass::primaryKey();
                $pk = [];
                foreach ($pks as $pkAttribute) {
                    $pk[$pkAttribute] = $dbModel[$pkAttribute];
                }
                $exists = ($pk != $model->getOldPrimaryKey(true));
            } else {
                // if there is more than one record, the value is not unique
                $exists = $n > 1;
            }
        }

        return $exists;
    }

    /**
     * Prepares a query by applying filtering conditions defined in $conditions method property
     * and [[filter]] class property.
     *
     * @param ActiveRecordInterface $targetClass the name of the ActiveRecord class that should be used to validate
     * the uniqueness of the current attribute value.
     * @param array $conditions conditions, compatible with [[\yii\db\Query::where()|Query::where()]] key-value format
     *
     * @return ActiveQueryInterface|ActiveQuery
     */
    private function prepareQuery($targetClass, $conditions)
    {
        $query = $targetClass::find();
        $query->andWhere($conditions);
        if ($this->filter instanceof \Closure) {
            call_user_func($this->filter, $query);
        } elseif ($this->filter !== null) {
            $query->andWhere($this->filter);
        }

        return $query;
    }

    /**
     * Processes attributes' relations described in $targetAttribute parameter into conditions, compatible with
     * [[\yii\db\Query::where()|Query::where()]] key-value format.
     *
     * @param string|array $targetAttribute the name of the [[\yii\db\ActiveRecord|ActiveRecord]] attribute that
     * should be used to validate the uniqueness of the current attribute value. You may use an array to validate
     * the uniqueness of multiple columns at the same time. The array values are the attributes that will be
     * used to validate the uniqueness, while the array keys are the attributes whose values are to be validated.
     * If the key and the value are the same, you can just specify the value.
     * @param Model $model the data model to be validated
     * @param string $attribute the name of the attribute to be validated in the $model
     *
     * @return array conditions, compatible with [[\yii\db\Query::where()|Query::where()]] key-value format.
     */
    private function prepareConditions($targetAttribute, $model, $attribute)
    {
        if (is_array($targetAttribute)) {
            $conditions = [];
            foreach ($targetAttribute as $k => $v) {
                $conditions[$v] = is_int($k) ? $model->$v : $model->$k;
            }
        } else {
            $conditions = [$targetAttribute => $model->$attribute];
        }

        $targetModelClass = $this->getTargetClass($model);
        if (!is_subclass_of($targetModelClass, 'yii\db\ActiveRecord')) {
            return $conditions;
        }

        /** @var ActiveRecord $targetModelClass */
        return $this->applyTableAlias($targetModelClass::find(), $conditions);
    }

    /**
     * Builds and adds [[comboNotUnique]] error message to the specified model attribute.
     * @param \yii\base\Model $model the data model.
     * @param string $attribute the name of the attribute.
     */
    private function addComboNotUniqueError($model, $attribute)
    {
        $attributeCombo = [];
        $valueCombo = [];
        foreach ($this->targetAttribute as $key => $value) {
            if (is_int($key)) {
                $attributeCombo[] = $model->getAttributeLabel($value);
                $valueCombo[] = '"' . $model->$value . '"';
            } else {
                $attributeCombo[] = $model->getAttributeLabel($key);
                $valueCombo[] = '"' . $model->$key . '"';
            }
        }
        $this->addError($model, $attribute, $this->message, [
            'attributes' => Inflector::sentence($attributeCombo),
            'values' => implode('-', $valueCombo),
        ]);
    }

    /**
     * Returns conditions with alias.
     * @param ActiveQuery $query
     * @param array $conditions array of condition, keys to be modified
     * @param null|string $alias set empty string for no apply alias. Set null for apply primary table alias
     * @return array
     */
    private function applyTableAlias($query, $conditions, $alias = null)
    {
        if ($alias === null) {
            $alias = array_keys($query->getTablesUsedInFrom())[0];
        }
        $prefixedConditions = [];
        foreach ($conditions as $columnName => $columnValue) {
            if (strpos($columnName, '(') === false) {
                $prefixedColumn = "{$alias}.[[" . preg_replace(
                    '/^' . preg_quote($alias) . '\.(.*)$/',
                    '$1',
                    $columnName) . ']]';
            } else {
                // there is an expression, can't prefix it reliably
                $prefixedColumn = $columnName;
            }

            $prefixedConditions[$prefixedColumn] = $columnValue;
        }

        return $prefixedConditions;
    }
}
