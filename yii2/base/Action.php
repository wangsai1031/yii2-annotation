<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\base;

use Yii;

/**
 * Action is the base class for all controller action classes.
 * Action是所有控制器动作类的基类
 *
 * Action provides a way to reuse action method code. An action method in an Action
 * class can be used in multiple controllers or in different projects.
 * 动作提供了一种重用动作方法的代码。一个动作类里边的动作方法可以在多个控制器或者不同的项目中使用。
 *
 * Derived classes must implement a method named `run()`. This method
 * 派生的类都必须实现run()方法。
 *
 * will be invoked by the controller when the action is requested.
 * 当动作被请求的时候，这个方法会被控制器自动调用。
 *
 * The `run()` method can have parameters which will be filled up
 * with user input values automatically according to their names.
 * run()方法可以带有参数，这些参数会根据名字自动填充
 *
 * For example, if the `run()` method is declared as follows:
 * 例如，如果run()方法被声明如下：
 *
 * ```php
 * public function run($id, $type = 'book') { ... }
 * ```
 *
 * And the parameters provided for the action are: `['id' => 1]`.
 * 并且给动作提供的参数是 ：`['id' => 1]`
 *
 * Then the `run()` method will be invoked as `run(1)` automatically.
 * 那么run()方法会被自动当做run(1)调用
 *
 * For more details and usage information on Action, see the [guide article on actions](guide:structure-controllers).
 *
 * @property string $uniqueId The unique ID of this action among the whole application. This property is
 * read-only.
 * 属性 字符串 动作的id在整个应用中都是唯一的，这个属性只读
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Action extends Component
{
    /**
     * @var string ID of the action
     * 属性 字符串 动作的id
     */
    public $id;
    /**
     * @var Controller|\yii\web\Controller|\yii\console\Controller the controller that owns this action
     * 属性 控制器 或者 \yii\web\Controller 拥有这个动作
     */
    public $controller;


    /**
     * Constructor.
     * 构造函数
     *
     * @param string $id the ID of this action
     * 参数 字符串 动作的id
     *
     * @param Controller $controller the controller that owns this action
     * 参数 控制器 拥有此动作的控制器
     *
     * @param array $config name-value pairs that will be used to initialize the object properties
     * 参数 数组 初始化对象属性的时候用到的键值对
     */
    public function __construct($id, $controller, $config = [])
    {
        $this->id = $id;
        $this->controller = $controller;
        parent::__construct($config);
    }

    /**
     * Returns the unique ID of this action among the whole application.
     * 返回在整个应用中该action的唯一id，包含模块ID(如果存在)、控制器ID,action ID
     *
     * @return string the unique ID of this action among the whole application.
     * 返回值 字符串 此动作在整个应用的唯一id
     */
    public function getUniqueId()
    {
        return $this->controller->getUniqueId() . '/' . $this->id;
    }

    /**
     * Runs this action with the specified parameters.
     * This method is mainly invoked by the controller.
     * 传入指定的参数运行该动作，这个方法主要是被控制器调用
     *
     * 注：一般外部独立Action类才会调用该方法，控制器内部action 一般调用 [[InlineAction::runWithParams()]]方法
     *
     * @param array $params the parameters to be bound to the action's run() method.
     * 参数 数组 会被绑定到动作的run方法里
     *
     * @return mixed the result of the action
     * 返回值 混合类型 动作执行的结果
     *
     * @throws InvalidConfigException if the action class does not have a run() method
     * 抛出 当动作没有run方法的时候抛出异常
     */
    public function runWithParams($params)
    {
        // 注：一般外部独立Action类才会调用该方法，控制器内部action 一般调用 [[InlineAction::runWithParams()]]方法
        // 判断 action 对象是否存在 run() 方法
        if (!method_exists($this, 'run')) {
            throw new InvalidConfigException(get_class($this) . ' must define a "run()" method.');
        }
        //对action的参数进行分析绑定，并且将参数值赋给控制器
        $args = $this->controller->bindActionParams($this, $params);
        Yii::debug('Running action: ' . get_class($this) . '::run()', __METHOD__);
        if (Yii::$app->requestedParams === null) {
            // 将参数值赋给 应用实例
            Yii::$app->requestedParams = $args;
        }
        // 触发 beforeRun() 事件
        if ($this->beforeRun()) {
            // 调用 run() 方法
            $result = call_user_func_array([$this, 'run'], $args);
            // 触发 afterRun() 事件
            $this->afterRun();

            return $result;
        }

        return null;
    }

    /**
     * This method is called right before `run()` is executed.
     * 此方法在执行run()方法以前调用
     *
     * You may override this method to do preparation work for the action run.
     * 您可以重写此方法，以此为run动作做一些准备工作
     *
     * If the method returns false, it will cancel the action.
     * 如果此方法返回false，那么动作将会被取消
     *
     * @return bool whether to run the action.
     * 返回值 boolean 是否执行此动作
     */
    protected function beforeRun()
    {
        return true;
    }

    /**
     * This method is called right after `run()` is executed.
     * 此方法会在run()方法执行后调用
     *
     * You may override this method to do post-processing work for the action run.
     * 您可以重写此方法，为run()方法做一些善后工作
     */
    protected function afterRun()
    {
    }
}
