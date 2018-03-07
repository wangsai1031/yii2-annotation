<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\widgets;

use yii\base\Widget;

/**
 * Block记录了调用[[begin()]] and [[end()]]之间的所有输出，并将其存储在[[\yii\base\View::$blocks]]。供今后使用。
 * Block records all output between [[begin()]] and [[end()]] calls and stores it in [[\yii\base\View::$blocks]].
 * for later use.
 *
 * [[\yii\base\View]] 组件包含两个方法[[\yii\base\View::beginBlock()]] and [[\yii\base\View::endBlock()]]。
 * [[\yii\base\View]] component contains two methods [[\yii\base\View::beginBlock()]] and [[\yii\base\View::endBlock()]].
 *
 * 一般的用法是，在视图或布局中定义block默认值，并显示。
 * The general idea is that you're defining block default in a view or layout:
 *
 * ```php
 * <?php $this->beginBlock('messages', true) ?>
 * Nothing.
 * <?php $this->endBlock() ?>
 * ```
 * 然后在子视图中覆盖默认值
 * And then overriding default in sub-views:
 *
 * ```php
 * <?php $this->beginBlock('username') ?>
 * Umm... hello?
 * <?php $this->endBlock() ?>
 * ```
 *
 * 第二个参数定义了块内容是否应该在呈现其内容时被输出。
 * Second parameter defines if block content should be outputted which is desired when rendering its content but isn't desired when redefining it in subviews.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Block extends Widget
{
    /**
     * 是否将块内容呈现在当前位置。
     * 默认值为false，这意味着捕获的块内容将不会被显示。
     * @var boolean whether to render the block content in place.
     * Defaults to false, meaning the captured block content will not be displayed.
     */
    public $renderInPlace = false;


    /**
     * Starts recording a block.
     */
    public function init()
    {
        ob_start();
        ob_implicit_flush(false);
    }

    /**
     * 该方法停止输出缓冲，并将呈现结果保存为视图中指定的块。
     * Ends recording a block.
     * This method stops output buffering and saves the rendering result as a named block in the view.
     */
    public function run()
    {
        // 捕获内容
        $block = ob_get_clean();
        // 是否显示捕获到的内容
        if ($this->renderInPlace) {
            echo $block;
        }
        // 将捕获到的内容保存到视图的 blocks 属性中
        $this->view->blocks[$this->getId()] = $block;
    }
}
