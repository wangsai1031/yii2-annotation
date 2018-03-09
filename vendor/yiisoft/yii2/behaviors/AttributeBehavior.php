<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\behaviors;

use Closure;
use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecord;

/**
 * 当某些事件发生时，AttributeBehavior自动将指定的值分配给ActiveRecord对象的一个或多个属性。
 * AttributeBehavior automatically assigns a specified value to one or multiple attributes of an ActiveRecord
 * object when certain events happen.
 *
 * 要使用AttributeBehavior，可以配置[[attributes]]属性，指定需要更新的属性列表，以及触发更新的事件。
 * 然后，使用一个PHP可调用的函数配置[[value]]属性，它的返回值将用于分配给当前属性。
 * To use AttributeBehavior, configure the [[attributes]] property which should specify the list of attributes
 * that need to be updated and the corresponding events that should trigger the update. Then configure the
 * [[value]] property with a PHP callable whose return value will be used to assign to the current attribute(s).
 * For example,
 *
 * ```php
 * use yii\behaviors\AttributeBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => AttributeBehavior::className(),
 *             'attributes' => [
 *                 ActiveRecord::EVENT_BEFORE_INSERT => 'attribute1',
 *                 ActiveRecord::EVENT_BEFORE_UPDATE => 'attribute2',
 *             ],
 *             'value' => function ($event) {
 *                 return 'some value';
 *             },
 *         ],
 *     ];
 * }
 * ```
 *
 * 因为属性值是由这个行为自动设置的，所以它们通常不是用户输入，因此不需要验证，
 * 即：它们不应该出现在模型的[[\yii\base\Model::rules()|rules()]]方法中。
 * Because attribute values will be set automatically by this behavior, they are usually not user input and should therefore
 * not be validated, i.e. they should not appear in the [[\yii\base\Model::rules()|rules()]] method of the model.
 *
 * @author Luciano Baraglia <luciano.baraglia@gmail.com>
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AttributeBehavior extends Behavior
{
    /**
     * 通过[[value]]指定的值自动填充的属性列表。
     *
     * 数组的键是要更新属性的ActiveRecord事件，而数组值是要更新的相应属性。
     * 可以使用字符串来表示单个属性，也可以使用一个数组来表示属性列表。
     * @var array list of attributes that are to be automatically filled with the value specified via [[value]].
     * The array keys are the ActiveRecord events upon which the attributes are to be updated,
     * and the array values are the corresponding attribute(s) to be updated. You can use a string to represent
     * a single attribute, or an array to represent a list of attributes. For example,
     *
     * ```php
     * [
     *     ActiveRecord::EVENT_BEFORE_INSERT => ['attribute1', 'attribute2'],
     *     ActiveRecord::EVENT_BEFORE_UPDATE => 'attribute2',
     * ]
     * ```
     */
    public $attributes = [];
    /**
     * 将分配给当前属性的值。
     * 这可以是一个 匿名函数，可调用函数的数组格式(e.g. `[$this, 'methodName']`),
     * 表示DB表达式的[[Expression]]表达式对象(e.g. `new Expression('NOW()')`)，标量，字符串或任意值。
     * 如果是前者，函数的返回值将被分配给属性。
     *
     * @var mixed the value that will be assigned to the current attributes. This can be an anonymous function,
     * callable in array format (e.g. `[$this, 'methodName']`), an [[\yii\db\Expression|Expression]] object representing a DB expression
     * (e.g. `new Expression('NOW()')`), scalar, string or an arbitrary value. If the former, the return value of the
     * function will be assigned to the attributes.
     * The signature of the function should be as follows,
     *
     * 该函数的参数如下
     * ```php
     * function ($event)
     * {
     *     // return value will be assigned to the attribute
     * }
     * ```
     */
    public $value;
    /**
     * 当`$owner` 没有被修改时，是否要跳过这个行为。
     * @var bool whether to skip this behavior when the `$owner` has not been
     * modified
     * @since 2.0.8
     *
     * 当行为的拥有者$owner 未做修改 的时候，是否要跳过这种行为
     *
     * 简单理解即：当ActiveRecord实例没有修改任何字段时就跳过行为
     */
    public $skipUpdateOnClean = true;
    /**
     * @var bool whether to preserve non-empty attribute values.
     * @since 2.0.13
     */
    public $preserveNonEmptyValues = false;


    /**
     * 事件 => callable
     * eg:
     * [
     *     Model::EVENT_BEFORE_VALIDATE => 'evaluateAttributes',
     *     Model::EVENT_AFTER_VALIDATE => 'evaluateAttributes',
     * ]
     * 
     * {@inheritdoc}
     */
    public function events()
    {
        return array_fill_keys(
            array_keys($this->attributes),
            'evaluateAttributes'
        );
    }

    /**
     * 将属性值赋给当前属性
     *
     * Evaluates the attribute value and assigns it to the current attributes.
     * @param Event $event
     */
    public function evaluateAttributes($event)
    {
        // ActiveRecord实例没有修改任何字段时跳过该行为
        if ($this->skipUpdateOnClean
            // 修改事件
            && $event->name == ActiveRecord::EVENT_BEFORE_UPDATE
            // ActiveRecord实例没有修改任何字段
            && empty($this->owner->dirtyAttributes)
        ) {
            return;
        }

        if (!empty($this->attributes[$event->name])) {
            // 要改变的属性
            $attributes = (array) $this->attributes[$event->name];
            // 获取属性值
            $value = $this->getValue($event);
            // 遍历属性，挨个赋值
            foreach ($attributes as $attribute) {
                // 忽略非字符串属性名称(例如,当通过TimestampBehavior::updatedAtAttribute设置false)
                // ignore attribute names which are not string (e.g. when set by TimestampBehavior::updatedAtAttribute)
                if (is_string($attribute)) {
                    if ($this->preserveNonEmptyValues && !empty($this->owner->$attribute)) {
                        continue;
                    }
                    $this->owner->$attribute = $value;
                }
            }
        }
    }

    /**
     * 返回当前属性的值
     *
     * Returns the value for the current attributes.
     *
     * 这个方法被 evaluateAttributes() 调用。它的返回值将会分配给触发事件的属性
     * This method is called by [[evaluateAttributes()]]. Its return value will be assigned
     * to the attributes corresponding to the triggering event.
     * @param Event $event the event that triggers the current attribute updating.
     * @return mixed the attribute value
     */
    protected function getValue($event)
    {
        /**
         * @link http://php.net/manual/zh/function.is-callable.php is_callable()
         * @link http://www.php.net/manual/zh/function.call-user-func.php call_user_func()
         *
         * value 属性是 匿名函数 或者 数组 [className, functionName] 或 [instance, functionName]
         */
        if ($this->value instanceof Closure || (is_array($this->value) && is_callable($this->value))) {
            // 调用传入的方法，并附参数 $event
            return call_user_func($this->value, $event);
        }

        return $this->value;
    }
}
