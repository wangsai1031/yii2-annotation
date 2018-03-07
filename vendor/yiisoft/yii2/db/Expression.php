<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db;

/**
 * Expression表示不需要转义或引用的DB表达式
 * Expression represents a DB expression that does not need escaping or quoting.
 *
 * 当表达式对象嵌入到SQL语句或片段中时.
 * 它将被表达式属性值 不经过没有任何DB转义或引用 替换
 * When an Expression object is embedded within a SQL statement or fragment,
 * it will be replaced with the [[expression]] property value without any
 * DB escaping or quoting. For example,
 *
 * ```php
 * $expression = new Expression('NOW()');
 * $now = (new \yii\db\Query)->select($expression)->scalar();  // SELECT NOW();
 * echo $now; // prints the current date
 * ```
 * Expression对象主要用于将原始SQL表达式传递给[[Query]], [[ActiveQuery]] 和相关类的方法。
 * Expression objects are mainly created for passing raw SQL expressions to methods of [[Query]], [[ActiveQuery]], and related classes.
 *
 * Expression objects are mainly created for passing raw SQL expressions to methods of
 * [[Query]], [[ActiveQuery]], and related classes.
 *
 * 表达式也可以通过[[params]]指定的参数绑定。
 * An expression can also be bound with parameters specified via [[params]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Expression extends \yii\base\BaseObject implements ExpressionInterface
{
    /**
     * DB表达式
     * @var string the DB expression
     */
    public $expression;
    /**
     * 应该为该表达式绑定的参数列表。
     * 键是在表达式中出现的占位符，值是对应的参数值。
     * @var array list of parameters that should be bound for this expression.
     * The keys are placeholders appearing in [[expression]] and the values
     * are the corresponding parameter values.
     */
    public $params = [];


    /**
     * Constructor.
     * @param string $expression the DB expression
     * @param array $params parameters
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($expression, $params = [], $config = [])
    {
        $this->expression = $expression;
        $this->params = $params;
        parent::__construct($config);
    }

    /**
     * 魔术方法，当实例被当做字符串使用时，返回expression的值
     * String magic method.
     * @return string the DB expression.
     */
    public function __toString()
    {
        return $this->expression;
    }
}
