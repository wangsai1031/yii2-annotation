<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/**
 * AssetBundle 表示一组资源文件，如CSS、JS、图像
 * AssetBundle represents a collection of asset files, such as CSS, JS, images.
 *
 * 每个资源包都有一个惟一的名称，我们可以从应用程序中使用的所有资源包中识别它
 * Each asset bundle has a unique name that globally identifies it among all asset bundles used in an application.
 * The name is the [fully qualified class name](http://php.net/manual/en/language.namespaces.rules.php)
 * of the class representing it.
 *
 * An asset bundle can depend on other asset bundles. When registering an asset bundle
 * with a view, all its dependent asset bundles will be automatically registered.
 *
 * For more details and usage information on AssetBundle, see the [guide article on assets](guide:structure-assets).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AssetBundle extends BaseObject
{
    /**
     *  资源位置
        资源根据它们的位置可以分为：

        - 源资源: 资源文件和PHP源代码放在一起，不能被Web直接访问， 为了使用这些源资源，
     *    它们要拷贝到一个可Web访问的Web目录中 成为发布的资源，这个过程称为发布资源，随后会详细介绍。
        - 发布资源: 资源文件放在可通过Web直接访问的Web目录中；
        - 外部资源: 资源文件放在与你的Web应用不同的 Web服务器上；
        - 当定义资源包类时候，如果你指定了sourcePath 属性， 就表示任何使用相对路径的资源会被当作源资源；
     *    如果没有指定该属性， 就表示这些资源为发布资源（因此应指定basePath 和 baseUrl 让Yii知道它们的位置）。

        推荐将资源文件放到Web目录以避免不必要的发布资源过程， 这就是之前的例子：指定 basePath 而不是 sourcePath.

        对于 扩展来说， 由于它们的资源和源代码都在不能Web访问的目录下， 在定义资源包类时必须指定sourcePath属性。

        Note: $sourcePath 属性不要用@webroot/assets，该路径默认为 asset manager资源管理器将源资源发布后存储资源的路径， 该路径的所有内容会认为是临时文件， 可能会被删除。
     *
     *
     * 包含该资源包的资源文件的目录。
     * 资源文件是您的Web应用程序源代码库的一部分。
     * 如果包含资源文件的目录不能被Web直接访问，那么您必须设置该属性。
     * 否则，应设置 basePath 属性和baseUrl。 路径别名 可在此处使用。
     *
     * @var string the directory that contains the source asset files for this asset bundle.
     * A source asset file is a file that is part of your source code repository of your Web application.
     *
     * You must set this property if the directory containing the source asset files is not Web accessible.
     * By setting this property, [[AssetManager]] will publish the source asset files
     * to a Web-accessible directory automatically when the asset bundle is registered on a page.
     *
     * If you do not set this property, it means the source asset files are located under [[basePath]].
     *
     * You can use either a directory or an alias of the directory.
     * @see $publishOptions
     */
    public $sourcePath;
    /**
     * 包含该包中的资源文件的可以被web访问目录
     * @var string the Web-accessible directory that contains the asset files in this bundle.
     *
     * 当指定sourcePath 属性， 资源管理器 会发布包的资源到一个可Web访问并覆盖该属性，
     * 如果你的资源文件在一个Web可访问目录下，应设置该属性，这样就不用再发布了。
     *
     * 路径别名 可在此处使用。
     * If [[sourcePath]] is set, this property will be *overwritten* by [[AssetManager]]
     * when it publishes the asset files from [[sourcePath]].
     *
     * You can use either a directory or an alias of the directory.
     */
    public $basePath;
    /**
     * 在js和css中列出的相对资源文件的基本URL。
     * @var string the base URL for the relative asset files listed in [[js]] and [[css]].
     *
     * 和 basePath 类似，如果你指定 sourcePath 属性， 资源管理器 会发布这些资源并覆盖该属性，
     * 路径别名 可在此处使用。
     *
     * If [[sourcePath]] is set, this property will be *overwritten* by [[AssetManager]]
     * when it publishes the asset files from [[sourcePath]].
     *
     * You can use either a URL or an alias of the URL.
     */
    public $baseUrl;
    /**
     * 资源依赖
     * 当Web页面包含多个CSS或JavaScript文件时， 它们有一定的先后顺序以避免属性覆盖，
     * 例如，Web页面在使用jQuery UI小部件前必须确保jQuery JavaScript文件已经被包含了， 我们称这种资源先后次序称为资源依赖。
     *
     * 资源依赖主要通过yii\web\AssetBundle::$depends 属性来指定， 在AppAsset 示例中，
     * 资源包依赖其他两个资源包： yii\web\YiiAsset 和 yii\bootstrap\BootstrapAsset
     * 也就是该资源包的CSS和JavaScript文件要在这两个依赖包的文件包含 之后 才包含。
     *
     * 资源依赖关系是可传递，也就是人说A依赖B，B依赖C，那么A也依赖C。
     *
     * 这个资源包依赖的包类名称列表。
     * @var array list of bundle class names that this bundle depends on.
     *
     * For example:
     *
     * ```php
     * public $depends = [
     *    'yii\web\YiiAsset',
     *    'yii\bootstrap\BootstrapAsset',
     * ];
     * ```
     */
    public $depends = [];
    /**
     * 这个资源包包含的JavaScript文件列表。
     * 每个JavaScript文件都应该用以下格式指定
     *
     * - 相对路径表示为本地JavaScript文件 (如 js/main.js)， 文件实际的路径在该相对路径前加上
     *   yii\web\AssetManager::$basePath，文件实际的URL在该路径前加上yii\web\AssetManager::$baseUrl。
     * - 绝对URL地址表示为外部JavaScript文件，
     *   如 http://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js
     *   或 //ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js.
     * - 数组，第一个元素是前面描述的绝对URL或相对路径，
     *   后面的元素为键值对，将用于覆盖该条目的[[jsOptions]]设置。
     *
     * @var array list of JavaScript files that this bundle contains. Each JavaScript file can be
     * specified in one of the following formats:
     *
     * - an absolute URL representing an external asset. For example,
     *   `http://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js` or
     *   `//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js`.
     * - a relative path representing a local asset (e.g. `js/main.js`). The actual file path of a local
     *   asset can be determined by prefixing [[basePath]] to the relative path, and the actual URL
     *   of the asset can be determined by prefixing [[baseUrl]] to the relative path.
     * - an array, with the first entry being the URL or relative path as described before, and a list of key => value pairs
     *   that will be used to overwrite [[jsOptions]] settings for this entry.
     *   This functionality is available since version 2.0.7.
     *
     * 注意：应使用正斜杠"/" 作为目录分隔符
     * Note that only a forward slash "/" should be used as directory separator.
     */
    public $js = [];
    /**
     * 这个资源包包含的CSS文件列表。
     * 该数组格式和 js 相同
     * @var array list of CSS files that this bundle contains. Each CSS file can be specified
     * in one of the three formats as explained in [[js]].
     *
     * Note that only a forward slash "/" should be used as directory separator.
     */
    public $css = [];
    /**
     * 当调用yii\web\View::registerJsFile()注册该包 每个 JavaScript文件时， 指定传递到该方法的选项。
     * @var array the options that will be passed to [[View::registerJsFile()]]
     * when registering the JS files in this bundle.
     */
    public $jsOptions = [];
    /**
     * 当调用yii\web\View::registerCssFile()注册该包 每个 css文件时， 指定传递到该方法的选项。
     * @var array the options that will be passed to [[View::registerCssFile()]]
     * when registering the CSS files in this bundle.
     */
    public $cssOptions = [];
    /**
     * 指定当资源包发布时，传递到[[AssetManager::publish()]]方法的选项，仅在指定了sourcePath属性时使用。
     * @var array the options to be passed to [[AssetManager::publish()]] when the asset bundle
     * is being published. This property is used only when [[sourcePath]] is set.
     */
    public $publishOptions = [];


    /**
     * 为给定的视图注册这个资源包。
     * Registers this asset bundle with a view.
     * @param View $view the view to be registered with
     * @return static the registered asset bundle instance
     */
    public static function register($view)
    {
        // 注册资源包
        return $view->registerAssetBundle(get_called_class());
    }

    /**
     * 初始化资源包。
     * 如果您覆盖了这个方法，请确保您在最后调用了父实现。
     * Initializes the bundle.
     * If you override this method, make sure you call the parent implementation in the last.
     */
    public function init()
    {
        if ($this->sourcePath !== null) {
            // 去掉 sourcePath 结尾的 '/' 和 '\'
            $this->sourcePath = rtrim(Yii::getAlias($this->sourcePath), '/\\');
        }
        if ($this->basePath !== null) {
            // 去掉 basePath 结尾的 '/' 和 '\'
            $this->basePath = rtrim(Yii::getAlias($this->basePath), '/\\');
        }
        if ($this->baseUrl !== null) {
            // 去掉 baseUrl 结尾的 '/'
            $this->baseUrl = rtrim(Yii::getAlias($this->baseUrl), '/');
        }
    }

    /**
     * 为给定的视图注册CSS和JS文件
     * Registers the CSS and JS files with the given view.
     * @param \yii\web\View $view the view that the asset files are to be registered with.
     */
    public function registerAssetFiles($view)
    {
        $manager = $view->getAssetManager();
        foreach ($this->js as $js) {
            if (is_array($js)) {
                $file = array_shift($js);
                $options = ArrayHelper::merge($this->jsOptions, $js);
                $view->registerJsFile($manager->getAssetUrl($this, $file), $options);
            } else {
                if ($js !== null) {
                    $view->registerJsFile($manager->getAssetUrl($this, $js), $this->jsOptions);
                }
            }
        }
        foreach ($this->css as $css) {
            if (is_array($css)) {
                $file = array_shift($css);
                $options = ArrayHelper::merge($this->cssOptions, $css);
                $view->registerCssFile($manager->getAssetUrl($this, $file), $options);
            } else {
                if ($css !== null) {
                    $view->registerCssFile($manager->getAssetUrl($this, $css), $this->cssOptions);
                }
            }
        }
    }

    /**
     * 如果它的源代码没有在web可访问的目录下，就发布资产包。
     * 它还会尝试将非CSS或JS文件(e.g. LESS, Sass)使用 [[AssetManager::converter|asset converter]] 转换成相应的CSS或JS文件
     * Publishes the asset bundle if its source code is not under Web-accessible directory.
     * It will also try to convert non-CSS or JS files (e.g. LESS, Sass) into the corresponding
     * CSS or JS files using [[AssetManager::converter|asset converter]].
     * @param AssetManager $am the asset manager to perform the asset publishing
     */
    public function publish($am)
    {
        // 若 sourcePath 不为null ，并且 $this->basePath, $this->baseUrl 都为 null
        if ($this->sourcePath !== null && !isset($this->basePath, $this->baseUrl)) {
            // 发布一个文件或一个目录，并获取该发布后资源的文件路径和Url
            list($this->basePath, $this->baseUrl) = $am->publish($this->sourcePath, $this->publishOptions);
        }

        // $this->basePath, $this->baseUrl 存在，则资源转换器对象不为空
        if (isset($this->basePath, $this->baseUrl) && ($converter = $am->getConverter()) !== null) {
            // 遍历 js 文件
            foreach ($this->js as $i => $js) {
                // 若$js是数组，第一个元素是js文件的绝对URL或相对路径，
                // 后面的元素为键值对，将用于覆盖该条目的[[jsOptions]]设置。
                if (is_array($js)) {
                    // 删除数组 $js 中的第一个元素，并返回删除的元素的值
                    $file = array_shift($js);
                    // 检查js文件的URL是否是相对路径
                    if (Url::isRelative($file)) {
                        // 合并 js 的两个配置数组，当包含相同元素时后者覆盖前者
                        $js = ArrayHelper::merge($this->jsOptions, $js);
                        // $converter->convert($file, $this->basePath) 将一个给定的资产文件转换为一个CSS或JS文件。并将其放回 $js 数组开头
                        array_unshift($js, $converter->convert($file, $this->basePath));
                        $this->js[$i] = $js;
                    }
                } elseif (Url::isRelative($js)) {
                    // $converter->convert($file, $this->basePath) 将一个给定的资产文件转换为一个CSS或JS文件。
                    $this->js[$i] = $converter->convert($js, $this->basePath);
                }
            }
            // 遍历css文件
            foreach ($this->css as $i => $css) {
                // 若$css是数组，第一个元素是js文件的绝对URL或相对路径，
                // 后面的元素为键值对，将用于覆盖该条目的[[cssOptions]]设置。
                if (is_array($css)) {
                    // 删除数组 $css 中的第一个元素，并返回删除的元素的值
                    $file = array_shift($css);
                    // 检查css文件的URL是否是相对路径
                    if (Url::isRelative($file)) {
                        // 合并 css 的两个配置数组，当包含相同元素时后者覆盖前者
                        $css = ArrayHelper::merge($this->cssOptions, $css);
                        // $converter->convert($file, $this->basePath) 将一个给定的资产文件转换为一个CSS或JS文件。并将其放回 $css 数组开头
                        array_unshift($css, $converter->convert($file, $this->basePath));
                        $this->css[$i] = $css;
                    }
                } elseif (Url::isRelative($css)) {
                    // $converter->convert($file, $this->basePath) 将一个给定的资产文件转换为一个CSS或JS文件。
                    $this->css[$i] = $converter->convert($css, $this->basePath);
                }
            }
        }
    }
}
