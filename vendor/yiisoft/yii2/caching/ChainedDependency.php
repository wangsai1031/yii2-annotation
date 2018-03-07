<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

/**
 * ChainedDependency represents a dependency which is composed of a list of other dependencies.
 * ChainedDependency 表示一个由其他依赖项的列表组成的依赖项。
 *
 * 当[[dependOnAll]]为true时，如果任何依赖关系发生了变化，那么这个依赖关系就会被改变;
 * 当[[dependOnAll]]为false时，如果其中任何一个依赖项没有改变，那么这个依赖关系就不会被改变。
 * When [[dependOnAll]] is true, if any of the dependencies has changed, this dependency is
 * considered changed; When [[dependOnAll]] is false, if one of the dependencies has NOT changed,
 * this dependency is considered NOT changed.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ChainedDependency extends Dependency
{
    /**
     * 有各种依赖项组成的数组列表。
     * 每个数组元素必须是一个依赖对象。
     * @var Dependency[] list of dependencies that this dependency is composed of.
     * Each array element must be a dependency object.
     */
    public $dependencies = [];
    /**
     * 这个依赖是否依赖于依赖关系中的每个依赖项。
     * 默认为true.如果任何依赖关系发生了变化，那么这个依赖关系就会被改变;
     * 当为false时，如果其中任何一个依赖项没有改变，那么这个依赖关系就不会被改变。
     * 
     * @var bool whether this dependency is depending on every dependency in [[dependencies]].
     * Defaults to true, meaning if any of the dependencies has changed, this dependency is considered changed.
     * When it is set false, it means if one of the dependencies has NOT changed, this dependency
     * is considered NOT changed.
     */
    public $dependOnAll = true;


    /**
     * 通过生成和保存与依赖关系相关的数据来评估依赖关系
     * Evaluates the dependency by generating and saving the data related with dependency.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     */
    public function evaluateDependency($cache)
    {
        // 遍历所有依赖项对象，并依次调用每个依赖项对象的evaluateDependency($cache)方法
        foreach ($this->dependencies as $dependency) {
            $dependency->evaluateDependency($cache);
        }
    }

    /**
     * 生成所需的数据，以确定是否已经更改了依赖关系。
     * 这个方法在这个类中什么都不做。
     * Generates the data needed to determine if dependency has been changed.
     * This method does nothing in this class.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     */
    protected function generateDependencyData($cache)
    {
        return null;
    }

    /**
     * 执行实际的依赖项检查。
     *
     * 这个方法非常巧妙，值得借鉴。
     *
     * {@inheritdoc}
     */
    public function isChanged($cache)
    {
        // 遍历每个依赖项对象
        foreach ($this->dependencies as $dependency) {
            // 若 $this->dependOnAll 为true,则任何一个子依赖发生改变，都认为当前依赖发生了改变。返回true.
            if ($this->dependOnAll && $dependency->isChanged($cache)) {
                return true;
            // 若 $this->dependOnAll 为 false,则任何一个子依赖没有发生改变，都认为该依赖没有改变。返回false
            } elseif (!$this->dependOnAll && !$dependency->isChanged($cache)) {
                return false;
            }
        }
        // 若 $this->dependOnAll 为true，但是每个子依赖都没有发生改变，则认为该依赖没有改变，返回false
        // 若 $this->dependOnAll 为false，但是每个子依赖都发生改变，则认为该依赖发生改变，返回true
        return !$this->dependOnAll;
    }
}
