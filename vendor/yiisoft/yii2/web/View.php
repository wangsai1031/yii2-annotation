<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\base\InvalidConfigException;

/**
 * View 表示MVC模式中的视图对象
 * View represents a view object in the MVC pattern.
 *
 * 视图提供了一组方法(例如 [[render()]])用于呈现视图的目的。
 * View provides a set of methods (e.g. [[render()]]) for rendering purpose.
 *
 * 默认情况下，View 配置为一个应用程序组件 [[\yii\base\Application]]。
 * 你可以通过`Yii::$app->view`来访问View实例。
 * View is configured as an application component in [[\yii\base\Application]] by default.
 * You can access that instance via `Yii::$app->view`.
 *
 * You can modify its configuration by adding an array to your application config under `components` as it is shown in the following example:
 * 您可以通过在 应用程序组件 `components` 配置中添加一个数组来修改它的配置,示例如下
 *
 * ```php
 * 'view' => [
 *     'theme' => 'app\themes\MyTheme',
 *     'renderers' => [
 *         // you may add Smarty or Twig renderer here
 *     ]
 *     // ...
 * ]
 * ```
 *
 * @property \yii\web\AssetManager $assetManager The asset manager. Defaults to the "assetManager" application
 * component.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class View extends \yii\base\View
{
    /**
     * 调用 [[beginBody()]] 时触发的事件
     * @event Event an event that is triggered by [[beginBody()]].
     */
    const EVENT_BEGIN_BODY = 'beginBody';
    /**
     * 调用 [[endBody()]] 时触发的事件
     * @event Event an event that is triggered by [[endBody()]].
     */
    const EVENT_END_BODY = 'endBody';
    /**
     * 注册的JavaScript代码块或文件的位置。
     * 这个选项是将 JavaScript 代码放在 head 标签内
     * The location of registered JavaScript code block or files.
     * This means the location is in the head section.
     */
    const POS_HEAD = 1;
    /**
     * 注册的JavaScript代码块或文件的位置。
     * 这个选项是将 JavaScript 代码放在 body 标签的开始部分
     * The location of registered JavaScript code block or files.
     * This means the location is at the beginning of the body section.
     */
    const POS_BEGIN = 2;
    /**
     * 注册的JavaScript代码块或文件的位置。
     * 这个选项是将 JavaScript 代码放在  body 标签的结束部分
     * The location of registered JavaScript code block or files.
     * This means the location is at the end of the body section.
     */
    const POS_END = 3;

    /**
     * `jQuery(document).ready()` 和 `jQuery(window).load()` 区别：
     *
     * 1. `jQuery(window).load()` 必须等到页面内包括图片的所有元素加载完毕后才能执行。
     * `jQuery(document).ready()` 是DOM结构绘制完毕后就执行，不必等到加载完毕。
     *
     * 2. `jQuery(window).load()` 不能同时编写多个，如果有多个，只会执行一个(最后一个)。
     * `jQuery(document).ready()` 可以同时编写多个，并且都可以得到执行。
     */

    /**
     * 注册的JavaScript代码块或文件的位置。
     * 这个选项是将 JavaScript 代码放在 `jQuery(document).ready()` 内
     * The location of registered JavaScript code block.
     * This means the JavaScript code block will be enclosed within `jQuery(document).ready()`.
     */
    const POS_READY = 4;
    /**
     * 注册的JavaScript代码块或文件的位置。
     * 这个选项是将 JavaScript 代码放在 `jQuery(window).load()` 内
     * The location of registered JavaScript code block.
     * This means the JavaScript code block will be enclosed within `jQuery(window).load()`.
     */
    const POS_LOAD = 5;
    /**
     * 它在内部被用作占位符，用于获取 head 部分注册的内容
     * This is internally used as the placeholder for receiving the content registered for the head section.
     */
    const PH_HEAD = '<![CDATA[YII-BLOCK-HEAD]]>';
    /**
     * 它在内部被用作占位符，用于获取在 body 开始部分注册的内容。
     * This is internally used as the placeholder for receiving the content registered for the beginning of the body section.
     */
    const PH_BODY_BEGIN = '<![CDATA[YII-BLOCK-BODY-BEGIN]]>';
    /**
     * 它在内部被用作占位符，用于获取在 body 结束部分注册的内容。
     * This is internally used as the placeholder for receiving the content registered for the end of the body section.
     */
    const PH_BODY_END = '<![CDATA[YII-BLOCK-BODY-END]]>';

    /**
     * 已经注册的资源包列表。键是包名，值是已注册的 [[AssetBundle]] 对象。
     * @var AssetBundle[] list of the registered asset bundles.
     * The keys are the bundle names, and the values are the registered [[AssetBundle]] objects.
     * @see registerAssetBundle()
     */
    public $assetBundles = [];
    /**
     * 页面标题
     * @var string the page title
     */
    public $title;
    /**
     * 已经注册的 meta 标签
     * @var array the registered meta tags.
     * @see registerMetaTag()
     */
    public $metaTags;
    /**
     * 已经注册的link标签
     * @var array the registered link tags.
     * @see registerLinkTag()
     */
    public $linkTags;
    /**
     * 已经注册的 CSS 代码块
     * @var array the registered CSS code blocks.
     * @see registerCss()
     */
    public $css;
    /**
     * 已经注册的 CSS 文件
     * @var array the registered CSS files.
     * @see registerCssFile()
     */
    public $cssFiles;
    /**
     * 已经注册的 JS 代码块
     * @var array the registered JS code blocks
     * @see registerJs()
     */
    public $js;
    /**
     * 已经注册的 JS 文件
     * @var array the registered JS files.
     * @see registerJsFile()
     */
    public $jsFiles;

    private $_assetManager;


    /**
     * 标记 HTML head 部分的位置
     * Marks the position of an HTML head section.
     */
    public function head()
    {
        echo self::PH_HEAD;
    }

    /**
     * 标志HTML body 部分的开始
     * Marks the beginning of an HTML body section.
     */
    public function beginBody()
    {
        echo self::PH_BODY_BEGIN;
        // 触发 beginBody 事件
        $this->trigger(self::EVENT_BEGIN_BODY);
    }

    /**
     * 标记HTML body部分的结束
     * Marks the ending of an HTML body section.
     */
    public function endBody()
    {
        // 触发 endBody 事件
        $this->trigger(self::EVENT_END_BODY);
        echo self::PH_BODY_END;

        // 遍历资源包列表数组的键（即资源包名），挨个注册
        foreach (array_keys($this->assetBundles) as $bundle) {
            $this->registerAssetFiles($bundle);
        }
    }

    /**
     * 标记一个HTML页面的结束。
     * Marks the ending of an HTML page.
     * 视图是否以AJAX模式呈现。
     * 如果是true，那么在 [[POS_READY]] and [[POS_LOAD]]位置上注册的JS脚本将会像普通脚本一样被呈现在视图的最后。
     * @param boolean $ajaxMode whether the view is rendering in AJAX mode.
     * If true, the JS scripts registered at [[POS_READY]] and [[POS_LOAD]] positions will be rendered at the end of the view like normal scripts.
     */
    public function endPage($ajaxMode = false)
    {
        // 触发 endPage 事件
        $this->trigger(self::EVENT_END_PAGE);

        // 获取当前的缓冲区内容并删除当前的输出缓冲区
        $content = ob_get_clean();

        // 替换各部分的占位符
        echo strtr($content, [
            // 生成插入到头部部分的内容。内容包含注册的meta标签、link标签、css/js代码块和文件。
            self::PH_HEAD => $this->renderHeadHtml(),
            // 获取插入到 'body' 开始部分的内容。内容包含已注册的JS代码块和文件。
            self::PH_BODY_BEGIN => $this->renderBodyBeginHtml(),
            // 获取在body末尾部分插入的内容。内容包含已注册的JS代码块和文件。
            self::PH_BODY_END => $this->renderBodyEndHtml($ajaxMode),
        ]);

        // 清除已注册的meta标签、link标签、css/js脚本和文件。
        $this->clear();
    }

    /**
     * 在响应AJAX请求时渲染视图
     * Renders a view in response to an AJAX request.
     *
     * 这个方法类似于[[render()]]，除了它渲染的视图被 [[beginPage()]], [[head()]], [[beginBody()]], [[endBody()]] and [[endPage()]]方法包围。
     * 通过这样做，该方法能够将js/css文件注入到呈现结果中。
     * This method is similar to [[render()]] except that it will surround the view being rendered with the calls of
     * [[beginPage()]], [[head()]], [[beginBody()]], [[endBody()]] and [[endPage()]].
     * By doing so, the method is able to inject into the rendering result with JS/CSS scripts and files that are registered with the view.
     *
     * @param string $view the view name. Please refer to [[render()]] on how to specify this parameter.
     * @param array $params the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @param object $context the context that the view should use for rendering the view. If null, existing [[context]] will be used.
     * @return string the rendering result
     * @see render()
     */
    public function renderAjax($view, $params = [], $context = null)
    {
        // 根据给定的视图名称找视图文件。
        $viewFile = $this->findViewFile($view, $context);

        ob_start();
        /**
         * @see http://php.net/manual/zh/function.ob-implicit-flush.php
         * ob_implicit_flush — 打开/关闭绝对刷送。
         * 绝对（隐式）刷送将导致在每次输出调用后有一次刷送操作，以便不再需要对 flush() 的显式调用。
         *
         * 关闭绝对（隐式）刷送。
         */
        ob_implicit_flush(false);

        $this->beginPage();
        $this->head();
        $this->beginBody();
        // 渲染一个视图文件。
        echo $this->renderFile($viewFile, $params, $context);
        $this->endBody();
        $this->endPage(true);

        // 得到当前缓冲区的内容并删除当前输出缓。
        return ob_get_clean();
    }

    /**
     * 注册该视图对象使用的资源管理器
     * Registers the asset manager being used by this view object.
     * @return \yii\web\AssetManager the asset manager. Defaults to the "assetManager" application component.
     */
    public function getAssetManager()
    {
        // 默认使用 "assetManager" 应用程序组件
        return $this->_assetManager ?: Yii::$app->getAssetManager();
    }

    /**
     * 设置该视图对象使用的资源管理器
     * Sets the asset manager.
     * @param \yii\web\AssetManager $value the asset manager
     */
    public function setAssetManager($value)
    {
        $this->_assetManager = $value;
    }

    /**
     * 清除已注册的meta标签、link标签、css/js脚本和文件。
     * Clears up the registered meta tags, link tags, css/js scripts and files.
     */
    public function clear()
    {
        $this->metaTags = null;
        $this->linkTags = null;
        $this->css = null;
        $this->cssFiles = null;
        $this->js = null;
        $this->jsFiles = null;
        $this->assetBundles = [];
    }

    /**
     * 注册一个资源包所提供的所有文件，包括依赖包文件。
     * 一旦文件被注册，就从 [[assetBundles]] 中删除一个 资源包。
     * Registers all files provided by an asset bundle including depending bundles files.
     * Removes a bundle from [[assetBundles]] once files are registered.
     * @param string $name name of the bundle to register
     */
    protected function registerAssetFiles($name)
    {
        // 若不存在这个资源包，或资源包还未注册，则跳过
        if (!isset($this->assetBundles[$name])) {
            return;
        }
        // 获取资源包对象
        $bundle = $this->assetBundles[$name];
        // 若资源包对象不为空
        if ($bundle) {
            // 遍历资源包的依赖，递归注册
            foreach ($bundle->depends as $dep) {
                $this->registerAssetFiles($dep);
            }
            // 为当前视图注册这个资源包
            $bundle->registerAssetFiles($this);
        }
        unset($this->assetBundles[$name]);
    }

    /**
     * 注册命名的资源包。
     * 所有相关依赖的资源包都将被注册。
     * Registers the named asset bundle.
     * All dependent asset bundles will be registered.
     *
     * 资源包的类名(没有开头的反斜杠)
     * @param string $name the class name of the asset bundle (without the leading backslash)
     *
     * 如果设置，这个值将决定javascript文件 值最小的位置。
     * 这将根据资产的javascript文件位置进行调整，如果不能满足需求将会失败。
     * 如果设置null，资源包的位置设置将不会被更改。
     * 了解关于javascript位置的更多细节，请参阅[[registerJsFile]]。
     * @param integer|null $position if set, this forces a minimum position for javascript files.
     * This will adjust depending assets javascript file position or fail if requirement can not be met.
     * If this is null, asset bundles position settings will not be changed.
     * See [[registerJsFile]] for more details on javascript position.
     *
     * 返回注册的资源包实例。
     * @return AssetBundle the registered asset bundle instance
     * @throws InvalidConfigException if the asset bundle does not exist or a circular dependency is detected
     */
    public function registerAssetBundle($name, $position = null)
    {
        // 若资源包还未注册
        if (!isset($this->assetBundles[$name])) {
            // 获取该视图对象的资源包管理器对象
            $am = $this->getAssetManager();
            // 获取已命名的资源包
            $bundle = $am->getBundle($name);
            $this->assetBundles[$name] = false;
            // register dependencies
            // 注册依赖项
            $pos = isset($bundle->jsOptions['position']) ? $bundle->jsOptions['position'] : null;
            // 遍历依赖资源包，递归地进行注册
            foreach ($bundle->depends as $dep) {
                $this->registerAssetBundle($dep, $pos);
            }
            // 将资源包对象放入已经注册的资源包列表。
            $this->assetBundles[$name] = $bundle;
        // 若注册的资源包为false,则抛异常
        } elseif ($this->assetBundles[$name] === false) {
            throw new InvalidConfigException("A circular dependency is detected for bundle '$name'.");
        } else {
            // 资源包已经注册了， 则直接获取该资源包对象。
            $bundle = $this->assetBundles[$name];
        }

        // 若设置了文件位置
        if ($position !== null) {
            // 获取资源包内部配置的位置信息
            $pos = isset($bundle->jsOptions['position']) ? $bundle->jsOptions['position'] : null;
            if ($pos === null) {
                // 资源包内部没有配置位置信息，则使用该位置
                $bundle->jsOptions['position'] = $pos = $position;
            } elseif ($pos > $position) {
                // 若资源被内部配置的位置大于 $position，则抛异常
                throw new InvalidConfigException("An asset bundle that depends on '$name' has a higher javascript file position configured than '$name'.");
            }
            // update position for all dependencies
            // 更新所有依赖的位置
            foreach ($bundle->depends as $dep) {
                $this->registerAssetBundle($dep, $pos);
            }
        }

        return $bundle;
    }

    /**
     * 注册meta标记。
     * Registers a meta tag.
     *
     * For example, a description meta tag can be added like the following:
     *
     * ```php
     * $view->registerMetaTag([
     *     'name' => 'description',
     *     'content' => 'This website is about funny raccoons.'
     * ]);
     * ```
     *
     * will result in the meta tag `<meta name="description" content="This website is about funny raccoons.">`.
     *
     * @param array $options the HTML attributes for the meta tag.
     * @param string $key the key that identifies the meta tag. If two meta tags are registered
     * with the same key, the latter will overwrite the former. If this is null, the new meta tag
     * will be appended to the existing ones.
     */
    public function registerMetaTag($options, $key = null)
    {
        if ($key === null) {
            $this->metaTags[] = Html::tag('meta', '', $options);
        } else {
            $this->metaTags[$key] = Html::tag('meta', '', $options);
        }
    }

    /**
     * 注册link标签
     * Registers a link tag.
     *
     * For example, a link tag for a custom [favicon](http://www.w3.org/2005/10/howto-favicon)
     * can be added like the following:
     *
     * ```php
     * $view->registerLinkTag(['rel' => 'icon', 'type' => 'image/png', 'href' => '/myicon.png']);
     * ```
     *
     * which will result in the following HTML: `<link rel="icon" type="image/png" href="/myicon.png">`.
     *
     * **Note:** To register link tags for CSS stylesheets, use [[registerCssFile()]] instead, which
     * has more options for this kind of link tag.
     *
     * @param array $options the HTML attributes for the link tag.
     * @param string $key the key that identifies the link tag. If two link tags are registered
     * with the same key, the latter will overwrite the former. If this is null, the new link tag
     * will be appended to the existing ones.
     */
    public function registerLinkTag($options, $key = null)
    {
        if ($key === null) {
            $this->linkTags[] = Html::tag('link', '', $options);
        } else {
            $this->linkTags[$key] = Html::tag('link', '', $options);
        }
    }

    /**
     * 注册Css代码块
     * Registers a CSS code block.
     * @param string $css the content of the CSS code block to be registered
     * @param array $options the HTML attributes for the `<style>`-tag.
     * @param string $key the key that identifies the CSS code block. If null, it will use
     * $css as the key. If two CSS code blocks are registered with the same key, the latter
     * will overwrite the former.
     */
    public function registerCss($css, $options = [], $key = null)
    {
        $key = $key ?: md5($css);
        $this->css[$key] = Html::style($css, $options);
    }

    /**
     * 注册Css文件
     * Registers a CSS file.
     * @param string $url the CSS file to be registered.
     * @param array $options the HTML attributes for the link tag. Please refer to [[Html::cssFile()]] for
     * the supported options. The following options are specially handled and are not treated as HTML attributes:
     *
     * - `depends`: array, specifies the names of the asset bundles that this CSS file depends on.
     *
     * @param string $key the key that identifies the CSS script file. If null, it will use
     * $url as the key. If two CSS files are registered with the same key, the latter
     * will overwrite the former.
     */
    public function registerCssFile($url, $options = [], $key = null)
    {
        $url = Yii::getAlias($url);
        $key = $key ?: $url;

        $depends = ArrayHelper::remove($options, 'depends', []);

        $webAlias = Yii::getAlias('@web');
        if ($webAlias !== '' && strpos($url, $webAlias) === 0) {
            $url = substr($url, strlen($webAlias));
        }

        $url = strncmp($url, '//', 2) === 0 ? $url : ltrim($url, '/');

        /** @var AssetBundle $bundle */
        $bundle = Yii::createObject([
            'class' => AssetBundle::className(),
            'baseUrl' => '@web',
            'basePath' => '@webroot',
            'css' => (array)$url,
            'cssOptions' => $options,
            'depends' => (array)$depends,
        ]);

        if (empty($depends)) {
            $url = $this->getAssetManager()->getAssetUrl($bundle, $url);
            $this->cssFiles[$key] = Html::cssFile($url, $options);
        } else {
            $this->getAssetManager()->bundles[$key] = $bundle;
            $this->registerAssetBundle($key);
        }
    }

    /**
     * Registers a JS code block.
     * @param string $js the JS code block to be registered
     * @param integer $position the position at which the JS script tag should be inserted
     * in a page. The possible values are:
     *
     * - [[POS_HEAD]]: in the head section
     * - [[POS_BEGIN]]: at the beginning of the body section
     * - [[POS_END]]: at the end of the body section
     * - [[POS_LOAD]]: enclosed within jQuery(window).load().
     *   Note that by using this position, the method will automatically register the jQuery js file.
     * - [[POS_READY]]: enclosed within jQuery(document).ready(). This is the default value.
     *   Note that by using this position, the method will automatically register the jQuery js file.
     *
     * @param string $key the key that identifies the JS code block. If null, it will use
     * $js as the key. If two JS code blocks are registered with the same key, the latter
     * will overwrite the former.
     */
    public function registerJs($js, $position = self::POS_READY, $key = null)
    {
        $key = $key ?: md5($js);
        $this->js[$position][$key] = $js;
        if ($position === self::POS_READY || $position === self::POS_LOAD) {
            JqueryAsset::register($this);
        }
    }

    /**
     * Registers a JS file.
     * @param string $url the JS file to be registered.
     * @param array $options the HTML attributes for the script tag. The following options are specially handled
     * and are not treated as HTML attributes:
     *
     * - `depends`: array, specifies the names of the asset bundles that this JS file depends on.
     * - `position`: specifies where the JS script tag should be inserted in a page. The possible values are:
     *     * [[POS_HEAD]]: in the head section
     *     * [[POS_BEGIN]]: at the beginning of the body section
     *     * [[POS_END]]: at the end of the body section. This is the default value.
     *
     * Please refer to [[Html::jsFile()]] for other supported options.
     *
     * @param string $key the key that identifies the JS script file. If null, it will use
     * $url as the key. If two JS files are registered with the same key at the same position, the latter
     * will overwrite the former. Note that position option takes precedence, thus files registered with the same key,
     * but different position option will not override each other.
     */
    public function registerJsFile($url, $options = [], $key = null)
    {
        $url = Yii::getAlias($url);
        $key = $key ?: $url;

        $depends = ArrayHelper::remove($options, 'depends', []);

        $webAlias = Yii::getAlias('@web');
        if ($webAlias !== '' && strpos($url, $webAlias) === 0) {
            $url = substr($url, strlen($webAlias));
        }

        $url = strncmp($url, '//', 2) === 0 ? $url : ltrim($url, '/');

        /** @var AssetBundle $bundle */
        $bundle = Yii::createObject([
            'class' => AssetBundle::className(),
            'baseUrl' => '@web',
            'basePath' => '@webroot',
            'js' => (array)$url,
            'jsOptions' => $options,
            'depends' => (array)$depends,
        ]);

        if (empty($depends)) {
            $url = $this->getAssetManager()->getAssetUrl($bundle, $url);
            $position = ArrayHelper::remove($options, 'position', self::POS_END);
            $this->jsFiles[$position][$key] = Html::jsFile($url, $options);
        } else {
            $this->getAssetManager()->bundles[$key] = $bundle;
            $this->registerAssetBundle($key);
        }
    }

    /**
     * 生成插入到头部部分的内容。
     * 内容包含注册的meta标签、link标签、css/js代码块和文件。
     * Renders the content to be inserted in the head section.
     * The content is rendered using the registered meta tags, link tags, CSS/JS code blocks and files.
     * @return string the rendered content
     */
    protected function renderHeadHtml()
    {
        $lines = [];
        // meta 元标签
        if (!empty($this->metaTags)) {
            $lines[] = implode("\n", $this->metaTags);
        }
        // link 标签
        if (!empty($this->linkTags)) {
            $lines[] = implode("\n", $this->linkTags);
        }
        // css 文件
        if (!empty($this->cssFiles)) {
            $lines[] = implode("\n", $this->cssFiles);
        }
        // css 代码块
        if (!empty($this->css)) {
            $lines[] = implode("\n", $this->css);
        }
        // 定位在头部的js文件
        if (!empty($this->jsFiles[self::POS_HEAD])) {
            $lines[] = implode("\n", $this->jsFiles[self::POS_HEAD]);
        }
        // 定位在头部的js代码块
        if (!empty($this->js[self::POS_HEAD])) {
            $lines[] = Html::script(implode("\n", $this->js[self::POS_HEAD]), ['type' => 'text/javascript']);
        }

        // 返回转换后的字符串
        return empty($lines) ? '' : implode("\n", $lines);
    }

    /**
     * 获取插入到 'body' 开始部分的内容。
     * 内容包含已注册的JS代码块和文件。
     * Renders the content to be inserted at the beginning of the body section.
     * The content is rendered using the registered JS code blocks and files.
     * @return string the rendered content
     */
    protected function renderBodyBeginHtml()
    {
        $lines = [];
        // 定位在body开始部分的js文件
        if (!empty($this->jsFiles[self::POS_BEGIN])) {
            $lines[] = implode("\n", $this->jsFiles[self::POS_BEGIN]);
        }
        // 定位在body开始部分的js代码块
        if (!empty($this->js[self::POS_BEGIN])) {
            $lines[] = Html::script(implode("\n", $this->js[self::POS_BEGIN]), ['type' => 'text/javascript']);
        }
        // 返回转换后的字符串
        return empty($lines) ? '' : implode("\n", $lines);
    }

    /**
     * 获取在body末尾部分插入的内容。
     * 内容包含已注册的JS代码块和文件。
     * Renders the content to be inserted at the end of the body section.
     * The content is rendered using the registered JS code blocks and files.
     *
     * 视图是否以AJAX模式呈现.
     * 如果是true，那么在 [[POS_READY]] and [[POS_LOAD]]位置上注册的JS脚本将会像普通脚本一样被呈现在视图的最后。
     * @param boolean $ajaxMode whether the view is rendering in AJAX mode.
     * If true, the JS scripts registered at [[POS_READY]] and [[POS_LOAD]] positions
     * will be rendered at the end of the view like normal scripts.
     * @return string the rendered content
     */
    protected function renderBodyEndHtml($ajaxMode)
    {
        $lines = [];

        // 定位在 body 末尾部分的js文件
        if (!empty($this->jsFiles[self::POS_END])) {
            $lines[] = implode("\n", $this->jsFiles[self::POS_END]);
        }

        if ($ajaxMode) {
            // 视图以AJAX模式呈现.
            $scripts = [];
            // 定位在 body 末尾部分的js代码块
            if (!empty($this->js[self::POS_END])) {
                $scripts[] = implode("\n", $this->js[self::POS_END]);
            }
            // 原本定位在 jQuery(document).ready() 方法中的js代码块，也直接被放在视图最后
            if (!empty($this->js[self::POS_READY])) {
                $scripts[] = implode("\n", $this->js[self::POS_READY]);
            }
            // 原本定位在 jQuery(window).on('load', function (){}) 方法中的js代码块，也直接被放在视图最后
            if (!empty($this->js[self::POS_LOAD])) {
                $scripts[] = implode("\n", $this->js[self::POS_LOAD]);
            }
            if (!empty($scripts)) {
                // 将以上js代码块全部放在同一个 <script> 标签内
                $lines[] = Html::script(implode("\n", $scripts), ['type' => 'text/javascript']);
            }
        } else {
            // 定位在 body 末尾部分的js代码块
            if (!empty($this->js[self::POS_END])) {
                $lines[] = Html::script(implode("\n", $this->js[self::POS_END]), ['type' => 'text/javascript']);
            }
            // 定位 jQuery(document).ready() 方法中的js代码块
            if (!empty($this->js[self::POS_READY])) {
                $js = "jQuery(document).ready(function () {\n" . implode("\n", $this->js[self::POS_READY]) . "\n});";
                $lines[] = Html::script($js, ['type' => 'text/javascript']);
            }
            // 定位在 jQuery(window).on('load', function (){}) 方法中的js代码块
            if (!empty($this->js[self::POS_LOAD])) {
                $js = "jQuery(window).on('load', function () {\n" . implode("\n", $this->js[self::POS_LOAD]) . "\n});";
                $lines[] = Html::script($js, ['type' => 'text/javascript']);
            }
        }

        // 返回转换后的字符串
        return empty($lines) ? '' : implode("\n", $lines);
    }
}
