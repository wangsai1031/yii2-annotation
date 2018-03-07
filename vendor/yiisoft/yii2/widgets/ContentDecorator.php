<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\widgets;

use yii\base\InvalidConfigException;
use yii\base\Widget;

/**
 * 内容装饰器 记录[[begin()]] and [[end()]]调用之间的所有输出，将其作为$content传递给给定的视图文件，然后输出呈现结果
 *
 * ContentDecorator records all output between [[begin()]] and [[end()]] calls, passes it to the given view file
 * as `$content` and then echoes rendering result.
 *
 * ```php
 * <?php ContentDecorator::begin([
 *     'viewFile' => '@app/views/layouts/base.php',
 *     'params' => [],
 *     'view' => $this,
 * ]) ?>
 *
 * some content here
 *
 * <?php ContentDecorator::end() ?>
 * ```
 *
 * 在 [[\yii\base\View]] 组建中， 有 [[\yii\base\View::beginContent()]] and [[\yii\base\View::endContent()]] 包装方法使语法更加友好。
 * There are [[\yii\base\View::beginContent()]] and [[\yii\base\View::endContent()]] wrapper methods in the
 * [[\yii\base\View]] component to make syntax more friendly. In the view these could be used as follows:
 * 在视图中可以使用以下方法
 *
 * ```php
 * <?php $this->beginContent('@app/views/layouts/base.php') ?>
 *
 * some content here
 *
 * <?php $this->endContent() ?>
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ContentDecorator extends Widget
{
    /**
     * 将被用来装饰这个小部件所包含的内容的视图文件。
     * @var string the view file that will be used to decorate the content enclosed by this widget.
     * This can be specified as either the view file path or [path alias](guide:concept-aliases).
     */
    public $viewFile;
    /**
     * 在装饰视图中使用的参数(name => value)
     * @var array the parameters (name => value) to be extracted and made available in the decorative view.
     */
    public $params = [];


    /**
     * 开始一个内容片断
     * Starts recording a clip.
     */
    public function init()
    {
        parent::init();

        if ($this->viewFile === null) {
            throw new InvalidConfigException('ContentDecorator::viewFile must be set.');
        }
        ob_start();
        ob_implicit_flush(false);
    }

    /**
     * 结束并输出内容。
     * 该方法停止输出缓冲，并将呈现结果保存为控制器中的一个指定的片段。
     * Ends recording a clip.
     * This method stops output buffering and saves the rendering result as a named clip in the controller.
     */
    public function run()
    {
        $params = $this->params;
        $params['content'] = ob_get_clean();
        // render under the existing context
        echo $this->view->renderFile($this->viewFile, $params);
    }
}
