<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\base;

/**
 * Behavior is the base class for all behavior classes.
 * Behavior类是所有行为类的基类
 *
 * A behavior can be used to enhance the functionality of an existing component without modifying its code.
 * 使用行为，可以不用更改组件的代码就能增强他们的功能
 *
 * In particular, it can "inject" its own methods and properties into the component
 * and make them directly accessible via the component. It can also respond to the events triggered in the component
 * and thus intercept the normal code execution.
 * 特别是，它能把自己的方法和属性加入到组件，并通过组件直接访问 。它也可以根据组件中的事件触发终止正常代码的执行
 *
 * For more details and usage information on Behavior, see the [guide article on behaviors](guide:concept-behaviors).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 *
 *  class MyBehavior extends Behavior
    {
        // 重载events() 使得在事件触发时，调用行为中的一些方法
        public function events()
        {
            // 在EVENT_BEFORE_VALIDATE事件触发时，调用成员函数 beforeValidate
            return [
                ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            ];
        }

        // 注意beforeValidate 是行为的成员函数，而不是绑定的类的成员函数。
        // 还要注意，这个函数的签名，要满足事件handler的要求。
        public function beforeValidate($event)
        {
            // ...
        }
    }
 *
 */
class Behavior extends BaseObject
{
    /**
     * // 指向行为本身所绑定的Component对象
     * 成员变量，用于指向行为的依附对象；
     * 
     * @var Component|null the owner of this behavior
     * 属性 组件 行为的拥有者
     */
    public $owner;


    /**
     * 用于表示行为所有要响应的事件；
     *
     * Declares event handlers for the [[owner]]'s events.
     * 为组件声明事件处理句柄
     *
     * Child classes may override this method to declare what PHP callbacks should
     * be attached to the events of the [[owner]] component.
     * 子类可以重写该方法，把回调函数添加到组件事件之中
     *
     * The callbacks will be attached to the [[owner]]'s events when the behavior is
     * attached to the owner; and they will be detached from the events when
     * the behavior is detached from the component.
     * 在行为被添加到组件的时候，回调函数可以绑定到组件的事件当中，当行为从组件分离的时候，他们又会被从事件分离
     *
     * The callbacks can be any of the following:
     * 回调有如下几种方式：
     *
     * - method in this behavior: `'handleClick'`, equivalent to `[$this, 'handleClick']`
     * - 行为中的方法：`'handleClick'`相当于`[$this, 'handleClick']`
     *
     * - object method: `[$object, 'handleClick']`
     * - 对象方法： `[$object, 'handleClick']`
     *
     * - static method: `['Page', 'handleClick']`
     * - 静态方法：`['Page', 'handleClick']`
     *
     * - anonymous function: `function ($event) { ... }`
     * - 匿名函数 ： `function ($event) { ... }`
     *
     * The following is an example:
     * 例如：
     *
     * ```php
     * [
     *     Model::EVENT_BEFORE_VALIDATE => 'myBeforeValidate',
     *     Model::EVENT_AFTER_VALIDATE => 'myAfterValidate',
     * ]
     * ```
     *
     * @return array events (array keys) and the corresponding event handler methods (array values).
     * 返回值 数组 事件（数组的键）和 相应的事件处理方法（数组的值）
     *
     * Behavior 基类本身没用，主要是子类使用，重载这个函数返回一个数组表示行为所关联的事件
     */
    public function events()
    {
        return [];
    }

    /**
     * // 绑定行为到 $owner
     * 用于将行为与Component绑定起来；
     *
     * 此方法做了两件事：
     * 设置好行为的 $owner ，使得行为可以访问、操作所依附的对象
     * 遍历行为中的 events() 返回的数组，将准备响应的事件，通过所依附类的 on() 绑定到类上
     *
     * Attaches the behavior object to the component.
     * 把行为对象添加到组件
     *
     * The default implementation will set the [[owner]] property
     * and attach event handlers as declared in [[events]].
     * 默认会添加设置组件属性和事件处理方法（在events方法中声明的）
     *
     * Make sure you call the parent implementation if you override this method.
     * 如果你重写此方法，一定要确保调用父类的实现
     *
     * @param Component $owner the component that this behavior is to be attached to.
     * 参数 组件 行为将要被绑定到的组件
     */
    public function attach($owner)
    {
        // 设置好行为的 $owner ，使得行为可以访问、操作所依附的对象
        $this->owner = $owner;
        // 遍历行为中的 events() 返回的数组，将准备响应的事件，通过所依附类的 on() 绑定到类上
        foreach ($this->events() as $event => $handler) {
            $owner->on($event, is_string($handler) ? [$this, $handler] : $handler);
        }
    }

    /**
     * // 解除绑定
     * 用于将行为从Component上解除。
     *
     * Detaches the behavior object from the component.
     * 把组件中的行为对象分离
     *
     * The default implementation will unset the [[owner]] property
     * and detach event handlers declared in [[events]].
     * 默认会删除组件属性和events中声明的事件处理方法
     *
     * Make sure you call the parent implementation if you override this method.
     * 如果重写此方法，一定要确保调用了父类的实现方法
     */
    public function detach()
    {
        // 这得是个名花有主的行为才有解除一说
        if ($this->owner) {

            // 遍历行为定义的事件，一一解除
            foreach ($this->events() as $event => $handler) {
                $this->owner->off($event, is_string($handler) ? [$this, $handler] : $handler);
            }
            $this->owner = null;
        }
    }
}
