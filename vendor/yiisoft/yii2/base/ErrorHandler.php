<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\base;

use Yii;
use yii\helpers\VarDumper;
use yii\web\HttpException;

/**
 * ErrorHandler handles uncaught PHP errors and exceptions.
 * ErrorHandler处理未捕获的错误和异常。
 *
 * ErrorHandler is configured as an application component in [[\yii\base\Application]] by default.
 * 默认情况下错误处理在[[\yii\base\Application]]中被配置为一个应用组件。
 *
 * You can access that instance via `Yii::$app->errorHandler`.
 * 你可以通过`Yii::$app->errorHandler`访问应用实例
 *
 * For more details and usage information on ErrorHandler, see the [guide article on handling errors](guide:runtime-handling-errors).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
abstract class ErrorHandler extends Component
{
    /**
     * 在错误展示以前，是否取消页面的所有输出。默认是true
     * @var bool whether to discard any existing page output before error display. Defaults to true.
     */
    public $discardExistingOutput = true;
    /**
     * @var int the size of the reserved memory. A portion of memory is pre-allocated so that
     * when an out-of-memory issue occurs, the error handler is able to handle the error with
     * the help of this reserved memory. If you set this value to be 0, no memory will be reserved.
     * Defaults to 256KB.
     * 参数 整型 保留内存的大小。预先分配了一部分内存，所以当内存溢出问题产生的时候，错误处理可以使用保留内存去处理该问题。
     * 如果你设置为0，就不会保留内存。默认是256KB
     */
    public $memoryReserveSize = 262144;
    /**
     * 目前正在处理的异常。
     * @var \Exception|null the exception that is being handled currently.
     */
    public $exception;

    /**
     * 为致命错误处理预留的内存
     * @var string Used to reserve memory for fatal error handler.
     */
    private $_memoryReserve;
    /**
     * 来自HHVM的错误保存错误追踪
     * @var \Exception from HHVM error that stores backtrace
     */
    private $_hhvmException;


    /**
     * 注册错误处理
     * Register this error handler.
     */
    public function register()
    {
        // 关闭php默认的错误信息展示
        ini_set('display_errors', false);
        /**
         * 设置用户自定义的异常处理函数。
         * @see http://www.w3school.com.cn/php/php_ref_error.asp
         */
        set_exception_handler([$this, 'handleException']);
        if (defined('HHVM_VERSION')) {
            set_error_handler([$this, 'handleHhvmError']);
        } else {
            set_error_handler([$this, 'handleError']);
        }
        // 预先分配了一部分内存，所以当内存溢出问题产生的时候，错误处理可以使用保留内存去处理该问题
        if ($this->memoryReserveSize > 0) {
            // 用全部为 x 的字符串填充该属性，用于保留内存，
            // 这样发生错误时可以释放掉该字符串，释放出来的内存用来处理错误信息
            $this->_memoryReserve = str_repeat('x', $this->memoryReserveSize);
        }
        /**
         * 注册一个会在php中止时执行的函数
         * @link register_shutdown_function();
         */
        register_shutdown_function([$this, 'handleFatalError']);
    }

    /**
     * 恢复PHP错误和异常处理程序，并取消该错误处理
     * Unregisters this error handler by restoring the PHP error and exception handlers.
     */
    public function unregister()
    {
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * Handles uncaught PHP exceptions.
     * 处理未捕获的PHP异常
     *
     * This method is implemented as a PHP exception handler.
     * 该方法作为PHP异常处理的实现
     *
     * @param \Exception $exception the exception that is not caught
     * 参数 未捕获的异常
     */
    public function handleException($exception)
    {
        if ($exception instanceof ExitException) {
            return;
        }

        $this->exception = $exception;

        // disable error capturing to avoid recursive errors while handling exceptions
        // 禁用错误捕获，从而避免处理错误时产生递归错误
        $this->unregister();

        // set preventive HTTP status code to 500 in case error handling somehow fails and headers are sent
        // HTTP exceptions will override this value in renderException()
        // 设置当前HTTP状态码为500，以防错误处理失败，请求头被发送。
        // HTTP异常会在renderException()方法中修改该值
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }

        try {
            $this->logException($exception);
            if ($this->discardExistingOutput) {
                $this->clearOutput();
            }
            $this->renderException($exception);
            if (!YII_ENV_TEST) {
                \Yii::getLogger()->flush(true);
                if (defined('HHVM_VERSION')) {
                    flush();
                }
                exit(1);
            }
        } catch (\Exception $e) {
            // an other exception could be thrown while displaying the exception
            // 展示异常的时候其他的异常会被抛出
            $this->handleFallbackExceptionMessage($e, $exception);
        } catch (\Throwable $e) {
            // additional check for \Throwable introduced in PHP 7
            $this->handleFallbackExceptionMessage($e, $exception);
        }

        $this->exception = null;
    }

    /**
     * Handles exception thrown during exception processing in [[handleException()]].
     * @param \Exception|\Throwable $exception Exception that was thrown during main exception processing.
     * @param \Exception $previousException Main exception processed in [[handleException()]].
     * @since 2.0.11
     */
    protected function handleFallbackExceptionMessage($exception, $previousException)
    {
        $msg = "An Error occurred while handling another error:\n";
        $msg .= (string) $exception;
        $msg .= "\nPrevious exception:\n";
        $msg .= (string) $previousException;
        if (YII_DEBUG) {
            if (PHP_SAPI === 'cli') {
                echo $msg . "\n";
            } else {
                echo '<pre>' . htmlspecialchars($msg, ENT_QUOTES, Yii::$app->charset) . '</pre>';
            }
        } else {
            echo 'An internal server error occurred.';
        }
        $msg .= "\n\$_SERVER = " . VarDumper::export($_SERVER);
        error_log($msg);
        if (defined('HHVM_VERSION')) {
            flush();
        }
        exit(1);
    }

    /**
     * Handles HHVM execution errors such as warnings and notices.
     * 处理HHVM执行的错误，例如警告和提示。
     *
     * This method is used as a HHVM error handler. It will store exception that will
     * be used in fatal error handler
     * 该方法用作HHVM的错误处理。它会存储用于致命错误处理的异常。
     *
     * @param integer $code the level of the error raised.
     * 参数 整型 错误的等级
     *
     * @param string $message the error message.
     * 参数 字符串 错误信息
     *
     * @param string $file the filename that the error was raised in.
     * 参数 字符串 错误发生的文件名
     *
     * @param integer $line the line number the error was raised at.
     * 参数 整型 错误发生的行数
     *
     * @param mixed $context
     * 参数 混合型 上下文
     *
     * @param mixed $backtrace trace of error
     * 参数 混合型 追踪错误
     *
     * @return boolean whether the normal error handler continues.
     * 返回值 boolean 正常错误处理是否继续
     *
     * @throws ErrorException
     * @since 2.0.6
     */
    public function handleHhvmError($code, $message, $file, $line, $context, $backtrace)
    {
        if ($this->handleError($code, $message, $file, $line)) {
            return true;
        }
        if (E_ERROR & $code) {
            $exception = new ErrorException($message, $code, $code, $file, $line);
            $ref = new \ReflectionProperty('\Exception', 'trace');
            $ref->setAccessible(true);
            $ref->setValue($exception, $backtrace);
            $this->_hhvmException = $exception;
        }

        return false;
    }

    /**
     * Handles PHP execution errors such as warnings and notices.
     * 处理PHP执行时产生的警告和提示错误
     *
     * This method is used as a PHP error handler. It will simply raise an [[ErrorException]].
     * 该方法用作PHP的错误处理。它只是引发一个ErrorException
     *
     * @param integer $code the level of the error raised.
     * 参数 整型 发送错误的级别
     *
     * @param string $message the error message.
     * 参数 字符串 错误信息
     *
     * @param string $file the filename that the error was raised in.
     * 参数 字符串 错误发生的文件
     *
     * @param integer $line the line number the error was raised at.
     * 参数 整型 错误发生的行数
     *
     * @return boolean whether the normal error handler continues.
     * 返回值 boolean 正常错误处理是否继续
     *
     * @throws ErrorException
     */
    public function handleError($code, $message, $file, $line)
    {
        if (error_reporting() & $code) {
            // load ErrorException manually here because autoloading them will not work
            // 在这里手动加载异常错误处理，因为自动加载时，无法生效

            // when error occurs while autoloading a class
            // 发生错误时，自动加载一个类
            if (!class_exists('yii\\base\\ErrorException', false)) {
                require_once __DIR__ . '/ErrorException.php';
            }
            $exception = new ErrorException($message, $code, $code, $file, $line);

            // in case error appeared in __toString method we can't throw any exception
            // 以防错误发生在__toString方法中，我们不能抛出任何异常
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            array_shift($trace);
            foreach ($trace as $frame) {
                if ($frame['function'] === '__toString') {
                    $this->handleException($exception);
                    if (defined('HHVM_VERSION')) {
                        flush();
                    }
                    exit(1);
                }
            }

            throw $exception;
        }

        return false;
    }

    /**
     * Handles fatal PHP errors.
     * 处理php致命错误
     */
    public function handleFatalError()
    {
        // 释放掉用于保留内存的占坑字符串
        unset($this->_memoryReserve);

        // load ErrorException manually here because autoloading them will not work
        // 在这里手动加载异常错误处理，因为自动加载时，无法生效
        // when error occurs while autoloading a class
        // 发生错误时，自动加载一个类
        if (!class_exists('yii\\base\\ErrorException', false)) {
            require_once __DIR__ . '/ErrorException.php';
        }

        $error = error_get_last();

        if (ErrorException::isFatalError($error)) {
            if (!empty($this->_hhvmException)) {
                $exception = $this->_hhvmException;
            } else {
                $exception = new ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']);
            }
            $this->exception = $exception;

            $this->logException($exception);

            if ($this->discardExistingOutput) {
                $this->clearOutput();
            }
            $this->renderException($exception);

            // need to explicitly flush logs because exit() next will terminate the app immediately
            // 需要明确的刷新日志，因为使用exit函数以后，会立刻终止进程
            Yii::getLogger()->flush(true);
            if (defined('HHVM_VERSION')) {
                flush();
            }
            exit(1);
        }
    }

    /**
     * Renders the exception.
     * 渲染异常页面
     *
     * @param \Exception $exception the exception to be rendered.
     * 参数 将要被渲染的异常
     */
    abstract protected function renderException($exception);

    /**
     * Logs the given exception.
     * 记录发生的异常
     *
     * @param \Exception $exception the exception to be logged
     * 参数 被记录的异常
     *
     * @since 2.0.3 this method is now public.
     * 从2.0.3版本以后，该方法为共有
     */
    public function logException($exception)
    {
        $category = get_class($exception);
        if ($exception instanceof HttpException) {
            $category = 'yii\\web\\HttpException:' . $exception->statusCode;
        } elseif ($exception instanceof \ErrorException) {
            $category .= ':' . $exception->getSeverity();
        }
        Yii::error($exception, $category);
    }

    /**
     * Removes all output echoed before calling this method.
     * 调用此方法，删除之前所有的重复输出
     */
    public function clearOutput()
    {
        // the following manual level counting is to deal with zlib.output_compression set to On
        // 以下手动计算级别计数处理取决于zlib.output_compression设置为on
        for ($level = ob_get_level(); $level > 0; --$level) {
            if (!@ob_end_clean()) {
                ob_clean();
            }
        }
    }

    /**
     * Converts an exception into a PHP error.
     * 把异常转化为php错误
     *
     * This method can be used to convert exceptions inside of methods like `__toString()`
     * to PHP errors because exceptions cannot be thrown inside of them.
     * 该方法用于把类似__toString方法的异常转化为php错误，因为这些方法里边无法抛出异常
     *
     * @param \Exception $exception the exception to convert to a PHP error.
     * 参数 要转化为php错误的异常
     */
    public static function convertExceptionToError($exception)
    {
        trigger_error(static::convertExceptionToString($exception), E_USER_ERROR);
    }

    /**
     * Converts an exception into a simple string.
     * 把异常转化为字符串
     * 
     * @param \Exception|\Error $exception the exception being converted
     * 待转化的异常
     * @return string the string representation of the exception.
     * 表示该异常的字符串
     */
    public static function convertExceptionToString($exception)
    {
        if ($exception instanceof UserException) {
            return "{$exception->getName()}: {$exception->getMessage()}";
        }

        if (YII_DEBUG) {
            return static::convertExceptionToVerboseString($exception);
        }

        return 'An internal server error occurred.';
    }

    /**
     * Converts an exception into a string that has verbose information about the exception and its trace.
     * @param \Exception|\Error $exception the exception being converted
     * @return string the string representation of the exception.
     *
     * @since 2.0.14
     */
    public static function convertExceptionToVerboseString($exception)
    {
        if ($exception instanceof Exception) {
            $message = "Exception ({$exception->getName()})";
        } elseif ($exception instanceof ErrorException) {
            $message = "{$exception->getName()}";
        } else {
            $message = 'Exception';
        }
        $message .= " '" . get_class($exception) . "' with message '{$exception->getMessage()}' \n\nin "
            . $exception->getFile() . ':' . $exception->getLine() . "\n\n"
            . "Stack trace:\n" . $exception->getTraceAsString();

        return $message;
    }
}
