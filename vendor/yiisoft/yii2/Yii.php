<?php
/**
 * Yii bootstrap file.
 * Yii引导文件
 *
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

require(__DIR__ . '/BaseYii.php');

/**
 * 该类在入口文件中引入
 *
 * 这是Yii的工具类文件。
 * 引入了这个类文件后，才能使用Yii的提供的各种工具，才有 Yii::createObject() Yii::$app 之类的东东可以使用。
 *
 *
 * Yii is a helper class serving common framework functionalities.
 * Yii是一个助手类，提供框架的公共功能。
 *
 * It extends from [[\yii\BaseYii]] which provides the actual implementation.
 * 它继承了[[\yii\BaseYii]]，BaseYii提供了确切的实现。
 * By writing your own Yii class, you can customize some functionalities of [[\yii\BaseYii]].
 * 通过编写你自己的Yii类，您可以自定义[[\yii\BaseYii]]的一些功能。
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * 在下面的代码中，Yii类是里面没有任何代码，并未对 BaseYii::autoload() 进行重载，
 * 所以，这个 spl_autoload_register() 实际上将 BaseYii::autoload() 注册为autoloader。
 * 如果，你要实现自己的autoloader，可以在 Yii 类的代码中，对 autoload() 进行重载。
 *
 */
class Yii extends \yii\BaseYii
{
}

//将 Yii::autoload() 作为autoloader插入到栈的最前面。并将 classes.php 读取到 Yii::$classMap 中，保存了一个映射表。
spl_autoload_register(['Yii', 'autoload'], true, true);

//下面的语句读取了一个映射表，这个映射表以类名为键，以实际类文件为值，
//Yii所有的核心类都已经写入到这个 classes.php 文件中，所以，核心类的加载是最便捷，最快的。
Yii::$classMap = require(__DIR__ . '/classes.php');
// 重点看这里。创建一个DI 容器，并由 Yii::$container 引用
Yii::$container = new yii\di\Container();
