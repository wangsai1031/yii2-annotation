<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\caching;

use Yii;
use yii\base\InvalidConfigException;

/**
 * FileDependency 表示依赖于文件的最后修改时间的依赖关系。
 * FileDependency represents a dependency based on a file's last modification time.
 *
 * 如果文件的最后修改时间发生变化，则依赖改变。
 * If the last modification time of the file specified via [[fileName]] is changed, the dependency is considered as changed.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class FileDependency extends Dependency
{
    /**
     * 文件路径或路径别名，其最后修改时间用于检查是否已更改了依赖关系。
     * @var string the file path or path alias whose last modification time is used to check if the dependency has been changed.
     */
    public $fileName;


    /**
     * 生成所需的数据，以确定是否已经更改了依赖关系。
     * 该方法返回文件的最后修改时间。
     * Generates the data needed to determine if dependency has been changed.
     * This method returns the file's last modification time.
     * @param Cache $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     * @throws InvalidConfigException if [[fileName]] is not set
     */
    protected function generateDependencyData($cache)
    {
        if ($this->fileName === null) {
            // fileName 不能为空
            throw new InvalidConfigException('FileDependency::fileName must be set');
        }

        // 返回文件最后修改时间
        return @filemtime(Yii::getAlias($this->fileName));
    }
}
