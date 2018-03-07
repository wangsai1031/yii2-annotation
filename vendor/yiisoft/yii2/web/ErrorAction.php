<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\Action;
use yii\base\Exception;
use yii\base\UserException;

/**
 * ErrorAction使用指定的视图显示应用程序错误
 * ErrorAction displays application errors using a specified view.
 *
 * 要使用ErrorAction，您需要执行以下步骤
 * To use ErrorAction, you need to do the following steps:
 *
 * 首先，在`SiteController`（或其他控制器）类的`actions()`方法中声明ErrorAction类型的动作，如下:
 * First, declare an action of ErrorAction type in the `actions()` method of your `SiteController` class (or whatever controller you prefer), like the following:
 *
 * ```php
 * public function actions()
 * {
 *     return [
 *         'error' => ['class' => 'yii\web\ErrorAction'],
 *     ];
 * }
 * ```
 * 然后，为该操作创建一个视图文件。如果error action的路径是site/error，那么视图文件应该是视图/site/error.php。
 * 在这个视图文件中，以下变量是可用的:
 * Then, create a view file for this action. If the route of your error action is `site/error`, then the view file should be `views/site/error.php`.
 * In this view file, the following variables are available:
 *
 * - `$name`: the error name
 * - `$message`: the error message
 * - `$exception`: the exception being handled
 *
 * 最后，配置“errorHandler”应用程序组件
 * Finally, configure the "errorHandler" application component as follows,
 *
 * ```php
 * 'errorHandler' => [
 *     'errorAction' => 'site/error',
 * ]
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ErrorAction extends Action
{
    /**
     * 要呈现的视图文件，如果不设置，它将取[[id]]的值。
     * 这意味着，如果您将操作命名为“error”，那么视图名将是“error”，相应的视图文件将是"views/site/error.php"。
     * @var string the view file to be rendered. If not set, it will take the value of [[id]].
     * That means, if you name the action as "error" in "SiteController", then the view name would be "error", and the corresponding view file would be "views/site/error.php".
     */
    public $view;
    /**
     * 当异常名称无法确定时，错误的名称。默认为 "Error"
     * @var string the name of the error when the exception name cannot be determined.
     * Defaults to "Error".
     */
    public $defaultName;
    /**
     * 当异常消息包含敏感信息时要显示的消息。
     * 默认为“An internal server error occurred”。
     * @var string the message to be displayed when the exception message contains sensitive information.
     * Defaults to "An internal server error occurred.".
     */
    public $defaultMessage;


    /**
     * Runs the action
     *
     * @return string result content
     */
    public function run()
    {
        // 目前没有正在处理的异常。
        if (($exception = Yii::$app->getErrorHandler()->exception) === null) {
            // action has been invoked not from error handler, but by direct route, so we display '404 Not Found'
            // 操作不是从错误处理程序调用的，而是直接通过路由调用的，因此我们显示“404 not Found”
            $exception = new HttpException(404, Yii::t('yii', 'Page not found.'));
        }

        // Http 异常，获取http状态码
        if ($exception instanceof HttpException) {
            $code = $exception->statusCode;
        } else {
            $code = $exception->getCode();
        }
        if ($exception instanceof Exception) {
            $name = $exception->getName();
        } else {
            $name = $this->defaultName ?: Yii::t('yii', 'Error');
        }
        if ($code) {
            $name .= " (#$code)";
        }

        if ($exception instanceof UserException) {
            $message = $exception->getMessage();
        } else {
            $message = $this->defaultMessage ?: Yii::t('yii', 'An internal server error occurred.');
        }

        if (Yii::$app->getRequest()->getIsAjax()) {
            return "$name: $message";
        } else {
            return $this->controller->render($this->view ?: $this->id, [
                'name' => $name,
                'message' => $message,
                'exception' => $exception,
            ]);
        }
    }
}
