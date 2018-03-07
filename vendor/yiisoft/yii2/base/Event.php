<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\base;

use yii\helpers\StringHelper;

/**
 * Event is the base class for all event classes.
 * Event是所有事件类的基类
 *
 * It encapsulates the parameters associated with an event.
 * 它封装了跟事件相关的参数
 *
 * The [[sender]] property describes who raises the event.
 * sender属性描述了谁引发了该事件
 *
 * And the [[handled]] property indicates if the event is handled.
 * handled属性表示该事件是否被处理
 *
 * If an event handler sets [[handled]] to be `true`, the rest of the
 * uninvoked handlers will no longer be called to handle the event.
 * 如果事件处理设置handled为true，那么剩下未调用的处理程序将不会被调用来处理事件
 *
 * Additionally, when attaching an event handler, extra data may be passed
 * and be available via the [[data]] property when the event handler is invoked.
 * 此外，当添加事件处理程序的时候，额外的数据可以在事件处理程序被调用后通过data属性传递。
 *
 * For more details and usage information on Event, see the [guide article on events](guide:concept-events).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *  *
 *
    // 定义事件的关联数据
    class MsgEvent extend yii\base\Event
    {
        public $dateTime;   // 微博发出的时间
        public $author;     // 微博的作者
        public $content;    // 微博的内容
    }

    // 在发布新的微博时，准备好要传递给handler的数据
    $event = new MsgEvent;
    $event->title = $title;
    $event->author = $auhtor;

    // 触发事件
    $msg->trigger(Msg::EVENT_NEW_MESSAGE, $event);
 *
 * 注意这里数据的传入，与使用 on() 绑定handler时传入数据方法的不同。
 * 在 on() 中，使用一个简单变量，传入，并在handler中通过 $event->data 进行访问。
 * 这个是在绑定时确定的数据。
 * 而有的数据是没办法在绑定时确定的，如发出微博的时间。
 * 这个时候，就需要在触发事件时提供其他的数据了。
 * 也就是上面这段代码使用的方法了。
 * 这两种方法，一种用于提供绑定时的相关数据，一种用于提供事件触发时的数据，各有所长，互相补充。
 */
class Event extends BaseObject
{
    /**
     * @var string the event name. This property is set by [[Component::trigger()]] and [[trigger()]].
     * 属性 字符串 事件名 该属性被Component::trigger()和trigger()方法设置
     *
     * Event handlers may use this property to check what event it is handling.
     * 事件处理程序可以使用该属性确定当前正在处理的事件是哪个。
     */
    public $name;
    /**
     * 事件发布者，通常是调用了 trigger() 的对象或类。
     *
     * @var object the sender of this event. If not set, this property will be
     * set as the object whose `trigger()` method is called.
     * 属性 对象 该事件的发送者，如果没设置，该属性会被设置为调用trigger方法的对象
     *
     * This property may also be a `null` when this event is a
     * class-level event which is triggered in a static context.
     * 当事件是类级别的的事件并且被静态环境触发，该属性的值可以是null
     */
    public $sender;
    /**
     * 是否终止事件的后续处理
     *
     * @var boolean whether the event is handled. Defaults to `false`.
     * 属性 boolean 事件是否被处理。默认是false
     *
     * When a handler sets this to be `true`, the event processing will stop and
     * ignore the rest of the uninvoked event handlers.
     * 当事件处理把该属性更改为true的时候，事件处理进程会停止，并忽略未被调用的事件处理程序
     */
    public $handled = false;
    /**
     * 事件相关数据
     *
     * @var mixed the data that is passed to [[Component::on()]] when attaching an event handler.
     * 属性 混合型 当添加事件处理程序时，传递给的Component::on()的数据
     *
     * Note that this varies according to which event handler is currently executing.
     * 注意，该值会因为当前正在执行的事件处理程序而有所不同
     */
    public $data;

    /**
     * @var array contains all globally registered event handlers.
     * 属性 数组 包含所有全局注册的事件处理程序
     */
    private static $_events = [];
    /**
     * @var array the globally registered event handlers attached for wildcard patterns (event name wildcard => handlers)
     * @since 2.0.14
     */
    private static $_eventWildcards = [];


    /**
     * Attaches an event handler to a class-level event.
     * 把事件处理程序绑定为一个类级别的事件
     *
     * When a class-level event is triggered, event handlers attached
     * to that class and all parent classes will be invoked.
     * 当类级别的事件触发的时候，事件处理添加到的类及其父类都会被调用
     *
     * For example, the following code attaches an event handler to `ActiveRecord`'s
     * `afterInsert` event:
     * 例如，下面的代码把一个事件处理程序添加到了ActiveRecord的afterInsert事件：
     *
     * ```php
     * Event::on(ActiveRecord::className(), ActiveRecord::EVENT_AFTER_INSERT, function ($event) {
     *     Yii::trace(get_class($event->sender) . ' is inserted.');
     * });
     * ```
     *
     * The handler will be invoked for EVERY successful ActiveRecord insertion.
     * 该处理程序会被每一个成功执行的ActiveRecord插入操作调用
     *
     * Since 2.0.14 you can specify either class name or event name as a wildcard pattern:
     *
     * ```php
     * Event::on('app\models\db\*', '*Insert', function ($event) {
     *     Yii::trace(get_class($event->sender) . ' is inserted.');
     * });
     * ```
     *
     * For more details about how to declare an event handler, please refer to [[Component::on()]].
     * 更多关于如何声明事件处理程序的方法，请参考Component::on()方法
     *
     * @param string $class the fully qualified class name to which the event handler needs to attach.
     * 参数 字符串 需要添加事件处理程序的完全限定类名
     *
     * @param string $name the event name.
     * 参数 字符串 事件名
     *
     * @param callable $handler the event handler.
     * 参数 事件处理程序
     *
     * @param mixed $data the data to be passed to the event handler when the event is triggered.
     * When the event handler is invoked, this data can be accessed via [[Event::data]].
     * 参数 混合型 当事件触发时传递给事件处理程序的数据。当事件处理程序被调用时，该数据可以通过Event::data进行访问
     *
     * @param boolean $append whether to append new event handler to the end of the existing
     * handler list. If `false`, the new handler will be inserted at the beginning of the existing
     * handler list.
     * 参数 boolean 是否添加新的事件处理程序到已经存在的事件处理程序列表的最后。如果为false，新的事件处理程序会被添加到事件处理
     * 程序列表的开头
     *
     * @see off()
     *
     * 先讲讲类级别的事件。类级别事件用于响应所有类实例的事件。
     * 比如，工头需要了解所有工人的下班时间， 那么，对于数百个工人，即数百个Worker实例，工头难道要一个一个去绑定自己的handler么？ 这也太低级了吧？
     * 其实，他只需要绑定一个handler到Worker类，这样每个工人下班时，他都能知道了。
     * 与实例级别的事件不同，类级别事件的绑定需要使用 yii\base\Event::on()
     *
     * Event::on(
            Worker::className(),                     // 第一个参数表示事件发生的类
            Worker::EVENT_OFF_DUTY,                  // 第二个参数表示是什么事件
            function ($event) {                      // 对事件的处理
                echo $event->sender . ' 下班了';
            }
        );
     *
     * 这样，每个工人下班时，会触发自己的事件处理函数，比如去打卡。
     * 之后，会触发类级别事件。
     * 类级别事件的触发仍然是在 yii\base\Component::trigger() 中，还记得该函数的最后一个语句么:
     *
     *  Event::trigger($this, $name, $event);                // 触发类一级的事件
     */
    public static function on($class, $name, $handler, $data = null, $append = true)
    {
        // 去掉类名左边的'\'
        $class = ltrim($class, '\\');

        if (strpos($class, '*') !== false || strpos($name, '*') !== false) {
            if ($append || empty(self::$_eventWildcards[$name][$class])) {
                // 将新的事件放到的事件处理程序列表最后
                self::$_eventWildcards[$name][$class][] = [$handler, $data];
            } else {
                // array_unshift() 函数用于向数组插入新元素。新数组的值将被插入到数组的开头。
                // 将新的事件放到的事件处理程序列表开头
                array_unshift(self::$_eventWildcards[$name][$class], [$handler, $data]);
            }
            return;
        }

        if ($append || empty(self::$_events[$name][$class])) {
            self::$_events[$name][$class][] = [$handler, $data];
        } else {
            array_unshift(self::$_events[$name][$class], [$handler, $data]);
        }
    }

    /**
     * Detaches an event handler from a class-level event.
     * 删除一个类级别的事件处理程序
     *
     * This method is the opposite of [[on()]].
     * 该方法的作用跟on相反
     *
     * Note: in case wildcard pattern is passed for class name or event name, only the handlers registered with this
     * wildcard will be removed, while handlers registered with plain names matching this wildcard will remain.
     *
     * @param string $class the fully qualified class name from which the event handler needs to be detached.
     * 参数 字符串 需要删除事件处理程序的完全限定类名
     *
     * @param string $name the event name.
     * 参数 字符串 事件名
     *
     * @param callable $handler the event handler to be removed.
     * If it is `null`, all handlers attached to the named event will be removed.
     * 参数 被删除的事件处理程序，如果为null，所有的事件处理程序都会被删除
     *
     * @return boolean whether a handler is found and detached.
     * 返回值 boolean 事件处理程序是否被找到并删除。
     * @see on()
     */
    public static function off($class, $name, $handler = null)
    {
        $class = ltrim($class, '\\');
        // 如果$_events[$name][$class]本来就是空的，则返回false
        if (empty(self::$_events[$name][$class]) && empty(self::$_eventWildcards[$name][$class])) {
            return false;
        }
        // 如果$handler 为 null，所有的事件处理程序都会被删除
        if ($handler === null) {
            unset(self::$_events[$name][$class]);
            unset(self::$_eventWildcards[$name][$class]);
            return true;
        }

        // plain event names
        if (isset(self::$_events[$name][$class])) {
            $removed = false;
            // 遍历每个event
            foreach (self::$_events[$name][$class] as $i => $event) {
                // 由于 $event = [$handler, $data];
                // 所以 $event[0] = $handler;
                if ($event[0] === $handler) {
                    // 删除掉指定的 $event
                    unset(self::$_events[$name][$class][$i]);
                    $removed = true;
                }
            }
            // 若有event被删除了，则重新整理events数组，防止索引数组变为关联数组：
            /**
             * $_events[$name][$class] = [
             *      '1' => [$handler1, $data1],
             *      '3' => [$handler2, $data2],
             * ];
             */
            if ($removed) {
                self::$_events[$name][$class] = array_values(self::$_events[$name][$class]);
                return $removed;
            }
        }

        // wildcard event names
        $removed = false;
        foreach (self::$_eventWildcards[$name][$class] as $i => $event) {
            if ($event[0] === $handler) {
                unset(self::$_eventWildcards[$name][$class][$i]);
                $removed = true;
            }
        }
        if ($removed) {
            self::$_eventWildcards[$name][$class] = array_values(self::$_eventWildcards[$name][$class]);
            // remove empty wildcards to save future redundant regex checks :
            if (empty(self::$_eventWildcards[$name][$class])) {
                unset(self::$_eventWildcards[$name][$class]);
                if (empty(self::$_eventWildcards[$name])) {
                    unset(self::$_eventWildcards[$name]);
                }
            }
        }

        return $removed;
    }

    /**
     * Detaches all registered class-level event handlers.
     * 删除所有注册的类级别的事件处理程序
     * @see on()
     * @see off()
     * @since 2.0.10
     */
    public static function offAll()
    {
        self::$_events = [];
        self::$_eventWildcards = [];
    }

    /**
     * 用于判断是否有相应的handler与事件对应
     *
     * Returns a value indicating whether there is any handler attached to the specified class-level event.
     * 返回表示是否有绑定到指定类级别的事件处理程序的值
     *
     * Note that this method will also check all parent classes to see if there is any handler attached
     * to the named event.
     * 注意，该方法会检测所有的父类，确定是否有事件处理程序绑定到了给定的事件
     *
     * @param string|object $class the object or the fully qualified class name specifying the class-level event.
     * 参数 字符串|对象 对象或 类级别事件的类全名
     *
     * @param string $name the event name.
     * 参数 字符串 事件名
     *
     * @return boolean whether there is any handler attached to the event.
     * 返回值 boolean 是否有事件处理程序绑定到了该事件
     */
    public static function hasHandlers($class, $name)
    {
        // 若不存在该事件名，则直接返回false
        if (empty(self::$_eventWildcards) && empty(self::$_events[$name])) {
            return false;
        }

        // 判断$class 是否是一个对象
        if (is_object($class)) {
            // 返回对象类的名称
            $class = get_class($class);
        } else {
            // 去掉类名最左侧的 '\'
            $class = ltrim($class, '\\');
        }

        // 获取该类所有父类，及实现的所有接口
        $classes = array_merge(
            [$class],
            // 返回指定类所有父类（包含父类的父类）的数组
            class_parents($class, true),
            // 返回指定的类实现的所有接口的数组
            class_implements($class, true)
        );

        // regular events
        // 遍历该类及其所有父类和接口，判断是否绑定对应事件
        foreach ($classes as $class) {
            if (!empty(self::$_events[$name][$class])) {
                return true;
            }
        }

        // wildcard events
        foreach (self::$_eventWildcards as $nameWildcard => $classHandlers) {
            if (!StringHelper::matchWildcard($nameWildcard, $name)) {
                continue;
            }
            foreach ($classHandlers as $classWildcard => $handlers) {
                if (empty($handlers)) {
                    continue;
                }
                foreach ($classes as $class) {
                    if (!StringHelper::matchWildcard($classWildcard, $class)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Triggers a class-level event.
     * 触发一个类级别的事件
     *
     * This method will cause invocation of event handlers that are attached to the named event
     * for the specified class and all its parent classes.
     * 该方法会导致调用指定类名及其父类的，给定事件名的事件处理程序
     *
     * @param string|object $class the object or the fully qualified class name specifying the class-level event.
     * 参数 字符串|对象
     *
     * @param string $name the event name.
     * 参数 字符串 事件名
     *
     * @param Event $event the event parameter. If not set, a default [[Event]] object will be created.
     * 参数 事件 事件参数 ，如果不设置，默认的Event对象将会被创建
     */
    public static function trigger($class, $name, $event = null)
    {
        $wildcardEventHandlers = [];
        foreach (self::$_eventWildcards as $nameWildcard => $classHandlers) {
            if (!StringHelper::matchWildcard($nameWildcard, $name)) {
                continue;
            }
            $wildcardEventHandlers = array_merge($wildcardEventHandlers, $classHandlers);
        }

        if (empty(self::$_events[$name]) && empty($wildcardEventHandlers)) {
            return;
        }

        if ($event === null) {
            $event = new static();
        }
        $event->handled = false;
        $event->name = $name;

        // 这段代码会对 $event->sender 进行设置，如果传入的时候，已经指定了他的值，那么这个值会保留，否则，就会替换成类名。
        // $class 是trigger()的第一个参数，表示类名
        if (is_object($class)) {
            if ($event->sender === null) {
                $event->sender = $class;
            }
            // 传入的是一个实例，则以类名替换之
            $class = get_class($class);
        } else {
            $class = ltrim($class, '\\');
        }

        $classes = array_merge(
            [$class],
            class_parents($class, true),
            class_implements($class, true)
        );

        // 最外面的循环遍历所有祖先类
        foreach ($classes as $class) {
            $eventHandlers = [];
            foreach ($wildcardEventHandlers as $classWildcard => $handlers) {
                if (StringHelper::matchWildcard($classWildcard, $class)) {
                    $eventHandlers = array_merge($eventHandlers, $handlers);
                    unset($wildcardEventHandlers[$classWildcard]);
                }
            }

            if (!empty(self::$_events[$name][$class])) {
                $eventHandlers = array_merge($eventHandlers, self::$_events[$name][$class]);
            }

            foreach ($eventHandlers as $handler) {
                // 由于 $handler = [$handlerFunction, $data];
                // 所以 $handler[0] = $handlerFunction;
                //  $handler[1] = $data;
                $event->data = $handler[1];
                call_user_func($handler[0], $event);

                // 所有的事件都是同一级别，可以随时终止
                if ($event->handled) {
                    return;
                }
            }
        }
    }
}
