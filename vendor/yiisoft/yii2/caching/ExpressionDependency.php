<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

/**
 * ExpressionDependency 表示依赖于PHP表达式的结果
 * ExpressionDependency represents a dependency based on the result of a PHP expression.
 *
 * 如果指定的 PHP 表达式执行结果发生变化，则依赖改变。
 * ExpressionDependency 将使用eval()要计算PHP表达式。
 * 如果表达式的结果与将数据存储到缓存时所评估的结果相同，则依赖项不变。
 * ExpressionDependency will use `eval()` to evaluate the PHP expression.
 * The dependency is reported as unchanged if and only if the result of the expression is the same as the one evaluated when storing the data to cache.
 *
 * PHP表达式可以是任何有值的PHP代码。
 * 要了解什么表达的更多信息，请参阅 [php 手册](http://www.php.net/manual/en/language.expressions.php).
 * A PHP expression can be any PHP code that has a value.
 * To learn more about what an expression is, please refer to the [php manual](http://www.php.net/manual/en/language.expressions.php).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ExpressionDependency extends Dependency
{
    /**
     * PHP表达式的字符串表示，其结果用于确定依赖关系。
     * 一个 PHP 表达式可以是任何计算结果为一个值的 PHP 代码。
     * 要了解什么表达的更多信息，请参阅 [php 手册](http://www.php.net/manual/en/language.expressions.php).
     *
     * eg: 'function(){return $this->params;}'
     *
     * @var string the string representation of a PHP expression whose result is used to determine the dependency.
     * A PHP expression can be any PHP code that evaluates to a value. To learn more about what an expression is,
     * please refer to the [php manual](http://www.php.net/manual/en/language.expressions.php).
     */
    public $expression = 'true';
    /**
     * 与此依赖相关的自定义参数。
     * 你可以在[[expression]]中使用$this->params来得到这个属性的值。
     * @var mixed custom parameters associated with this dependency.
     * You may get the value of this property in [[expression]] using `$this->params`.
     */
    public $params;


    /**
     * 生成所需的数据，以确定是否已经更改了依赖关系。
     * 这个方法返回PHP表达式的结果。
     * Generates the data needed to determine if dependency has been changed.
     * This method returns the result of the PHP expression.
     * @param Cache $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     */
    protected function generateDependencyData($cache)
    {
        return eval("return {$this->expression};");
    }
}
