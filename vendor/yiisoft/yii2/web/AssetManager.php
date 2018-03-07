<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\helpers\FileHelper;
use yii\helpers\Url;

/**
 * AssetManager管理资源包的配置和加载
 * AssetManager manages asset bundle configuration and loading.
 *
 * AssetManager is configured as an application component in [[\yii\web\Application]] by default.
 * You can access that instance via `Yii::$app->assetManager`.
 *
 * You can modify its configuration by adding an array to your application config under `components`
 * as shown in the following example:
 *
 * ```php
 * 'assetManager' => [
 *     'bundles' => [
 *         // you can override AssetBundle configs here
 *     ],
 * ]
 * ```
 *
 * @property AssetConverterInterface $converter The asset converter. Note that the type of this property
 * differs in getter and setter. See [[getConverter()]] and [[setConverter()]] for details.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AssetManager extends Component
{
    /**
     * 资源包配置列表，此属性提供自定义资产包。
     * 当一个资源包 被[[getBundle()]]加载时，如果它在这里指定了一个相应的配置，那么这个配置将被应用到这个包中。
     * @var array|boolean list of asset bundle configurations. This property is provided to customize asset bundles.
     * When a bundle is being loaded by [[getBundle()]], if it has a corresponding configuration specified here, the configuration will be applied to the bundle.
     *
     * 数组键是资产包的名称，通常是资产包类名，没有开头反斜杠。
     * 数组值是相应的配置。
     * 如果值是 false，则意味着相应的资产包被禁用，getBundle() 将返回 null
     * The array keys are the asset bundle names, which typically are asset bundle class names without leading backslash.
     * The array values are the corresponding configurations.
     * If a value is false, it means the corresponding asset bundle is disabled and [[getBundle()]] should return null.
     *
     * If this property is false, it means the whole asset bundle feature is disabled and [[getBundle()]] will always return null.
     *
     * The following example shows how to disable the bootstrap css file used by Bootstrap widgets
     * (because you want to use your own styles):
     *
     * 下面的示例展示了如何禁用Bootstrap小部件使用的Bootstrap css文件（因为你想用自己的css）
     * ```php
     * [
     *     'yii\bootstrap\BootstrapAsset' => [
     *         'css' => [],
     *     ],
     * ]
     * ```
     */
    public $bundles = [];
    /**
     * 存储已发布资产文件的根目录
     * @var string the root directory storing the published asset files.
     */
    public $basePath = '@webroot/assets';
    /**
     * 可以访问已发布的资产文件的基本URL
     * @var string the base URL through which the published asset files can be accessed.
     */
    public $baseUrl = '@web/assets';
    /**
     * 资源部署。
     * 有时你想"修复" 多个资源包中资源文件的错误/不兼容，例如包A使用1.11.1版本的jquery.min.js，
     * 包B使用2.1.1版本的jquery.js，可自定义每个包来解决这个问题， 更好的方式是使用资源部署特性来部署不正确的资源为想要的，
     * 为此，配置yii\web\AssetManager::$assetMap属性，如下所示：
     * ```
     *  'components' => [
            'assetManager' => [
                'assetMap' => [
                    'jquery.js' => '//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js',
                ],
            ],
        ],
     * ```
     * assetMap的键为你想要修复的资源名，值为你想要使用的资源路径， 当视图注册资源包，
     * 在css 和 js 数组中每个相关资源文件会和该部署进行对比， 如果数组任何键对比为资源文件的最后文件名
     * （如果有的话前缀为 yii\web\AssetBundle::$sourcePath），对应的值为替换原来的资源。
     * 例如，资源文件my/path/to/jquery.js 匹配键 jquery.js
     *
     * Note: 只有相对相对路径指定的资源对应到资源部署，替换的资源路径可以为绝对路径，也可为和yii\web\AssetManager::$basePath相关的路径。
     *
     * @var array mapping from source asset files (keys) to target asset files (values).
     *
     * This property is provided to support fixing incorrect asset file paths in some asset bundles.
     * When an asset bundle is registered with a view, each relative asset file in its [[AssetBundle::css|css]]
     * and [[AssetBundle::js|js]] arrays will be examined against this map. If any of the keys is found
     * to be the last part of an asset file (which is prefixed with [[AssetBundle::sourcePath]] if available),
     * the corresponding value will replace the asset and be registered with the view.
     * For example, an asset file `my/path/to/jquery.js` matches a key `jquery.js`.
     *
     * Note that the target asset files should be absolute URLs, domain relative URLs (starting from '/') or paths
     * relative to [[baseUrl]] and [[basePath]].
     *
     * In the following example, any assets ending with `jquery.min.js` will be replaced with `jquery/dist/jquery.js`
     * which is relative to [[baseUrl]] and [[basePath]].
     *
     * ```php
     * [
     *     'jquery.min.js' => 'jquery/dist/jquery.js',
     * ]
     * ```
     *
     * You may also use aliases while specifying map value, for example:
     *
     * ```php
     * [
     *     'jquery.min.js' => '@web/js/jquery/jquery.js',
     * ]
     * ```
     */
    public $assetMap = [];
    /**
     * 是否使用符号链接来发布资产文件。
     * 默认为false，即资产文件被复制到[[basePath]]。
     * 使用符号链接的好处是，已发布的资产将始终与源资产保持一致，并且不需要复制操作。
     * 这在开发过程中特别有用。
     * @var boolean whether to use symbolic link to publish asset files.
     * Defaults to false, meaning asset files are copied to [[basePath]].
     * Using symbolic links has the benefit that the published assets will always be consistent with the source assets and there is no copy operation required.
     * This is especially useful during development.
     *
     * 但是，为了使用符号链接，对虚拟主机环境有特殊的要求。
     * 尤其，符号链接只支持linux/unix，以及Windows vista/2008或更高版本。
     * However, there are special requirements for hosting environments in order to use symbolic links.
     * In particular, symbolic links are supported only on Linux/Unix, and Windows Vista/2008 or greater.
     *
     * 此外，一些Web服务器也需要适当地配置，以便Web用户可以访问相关的资产。
     * 例如，对于Apache Web服务器，应该为Web文件夹添加以下配置指令：
     * Moreover, some Web servers need to be properly configured so that the linked assets are accessible to Web users.
     * For example, for Apache Web server, the following configuration directive should be added for the Web folder:
     *
     * ```apache
     * Options FollowSymLinks
     * ```
     */
    public $linkAssets = false;
    /**
     * 为新发布的资产文件设置的权限。
     * 这个值将被PHP chmod()函数使用。不会应用 umask。
     * 如果不设置，则权限将由当前环境决定。
     *
     * @var integer the permission to be set for newly published asset files.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * If not set, the permission will be determined by the current environment.
     */
    public $fileMode;
    /**
     * @var integer the permission to be set for newly generated asset directories.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * Defaults to 0775, meaning the directory is read-writable by owner and group,
     * but read-only for other users.
     */
    public $dirMode = 0775;
    /**
     * 在复制每个子目录或文件之前调用的一个PHP回调。
     * 该选项仅在发布目录时使用。
     * 如果回调返回false，子目录或文件的复制操作将被取消。
     * 回调的参数应该是:`function ($from, $to)`，$from 是要复制的子目录或文件，而 $to 是复制目标目录或文件。
     * @var callback a PHP callback that is called before copying each sub-directory or file.
     * This option is used only when publishing a directory.
     * If the callback returns false, the copy operation for the sub-directory or file will be cancelled.
     *
     * The signature of the callback should be: `function ($from, $to)`, where `$from` is the sub-directory or file to be copied from, while `$to` is the copy target.
     *
     * 这个属性将作为`beforeCopy`参数传递给[[\yii\helpers\FileHelper::copyDirectory()]]。
     * This is passed as a parameter `beforeCopy` to [[\yii\helpers\FileHelper::copyDirectory()]].
     */
    public $beforeCopy;
    /**
     * @var callback a PHP callback that is called after a sub-directory or file is successfully copied.
     * This option is used only when publishing a directory. The signature of the callback is the same as
     * for [[beforeCopy]].
     * This is passed as a parameter `afterCopy` to [[\yii\helpers\FileHelper::copyDirectory()]].
     */
    public $afterCopy;
    /**
     * 是否复制所发布的目录，即使在目标目录中已经存在。
     * 该选项仅在发布目录时才使用。
     * 您可能希望在开发环境将其设置为true，以确保发布的目录总是最新的。
     * 不要在生产服务器上将其设置为true，因为这会显著降低性能。
     * @var boolean whether the directory being published should be copied even if it is found in the target directory.
     * This option is used only when publishing a directory.
     * You may want to set this to be `true` during the development stage to make sure the published directory is always up-to-date.
     * Do not set this to true on production servers as it will significantly degrade the performance.
     */
    public $forceCopy = false;
    /**
     * 是否在每个已发布资产的URL后附加时间戳。
     * 当这是 true 时，已发布的资产的URL可能看起来像`/path/to/asset?v=timestamp`，
     * 时间戳是已发布的资产文件最后一次修改的时间。
     * 通常，当您为源启用HTTP缓存时，您应将该属性设置为true，这时将会在资源更新时清除该文件的缓存。
     * @var boolean whether to append a timestamp to the URL of every published asset.
     * When this is true, the URL of a published asset may look like `/path/to/asset?v=timestamp`,
     * where `timestamp` is the last modification time of the published asset file.
     * You normally would want to set this property to true when you have enabled HTTP caching for assets,
     * because it allows you to bust caching when the assets are updated.
     * @since 2.0.3
     */
    public $appendTimestamp = false;
    /**
     * 用来生成资源目录散列的PHP可调用函数
     * @var callable a callback that will be called to produce hash for asset directory generation.
     * The signature of the callback should be as follows:
     * 回调的参数应该如下所列
     *
     * ```
     * function ($path)
     * ```
     * `$path`是资源路径。
     * 注意，$path可以是资产文件所在的目录，也可以是单个文件。
     * 对于在`url()`中使用相对路径的CSS文件，散列实现应该使用文件的目录路径，而不是文件路径，以便在复制中包含相关的资产文件。
     * where `$path` is the asset path.
     * Note that the `$path` can be either directory where the asset files reside or a single file.
     * For a CSS file that uses relative path in `url()`, the hash implementation should use the directory path of the file instead of the file path to include the relative asset files in the copying.
     *
     * If this is not set, the asset manager will use the default CRC32 and filemtime in the `hash` method.
     * 如果没有设置，资产管理器将在`hash`方法中使用默认的CRC32和filemtime
     * Example of an implementation using MD4 hash:
     * 使用MD4散列的实现的例子:
     * ```php
     * function ($path) {
     *     return hash('md4', $path);
     * }
     * ```
     *
     * @since 2.0.6
     */
    public $hashCallback;

    private $_dummyBundles = [];


    /**
     * 初始化资源管理组件
     * Initializes the component.
     * @throws InvalidConfigException if [[basePath]] is invalid
     */
    public function init()
    {
        parent::init();
        // 获取 存储已发布资产文件的根目录
        $this->basePath = Yii::getAlias($this->basePath);
        if (!is_dir($this->basePath)) {
            // 若根目录不存在，则抛异常
            throw new InvalidConfigException("The directory does not exist: {$this->basePath}");
        } elseif (!is_writable($this->basePath)) {
            // 若目录不可写，则抛异常
            throw new InvalidConfigException("The directory is not writable by the Web process: {$this->basePath}");
        } else {
            // 返回规范化的绝对路径名，该函数删除所有符号连接（比如 '/./', '/../' 以及多余的 '/'），返回绝对路径名。
            $this->basePath = realpath($this->basePath);
        }
        // 初始化基础url,去掉最右侧的 '/'
        $this->baseUrl = rtrim(Yii::getAlias($this->baseUrl), '/');
    }

    /**
     * 返回已命名的资源包
     * Returns the named asset bundle.
     *
     * 该方法将首先查找[[bundles]]中的包。
     * 如果没找到，它将把 $name 作为资源包的类名，创建一个新的实例。
     * This method will first look for the bundle in [[bundles]]. If not found,
     * it will treat `$name` as the class of the asset bundle and create a new instance of it.
     *
     * 资产包的类名(没有开头的反斜杠)
     * @param string $name the class name of the asset bundle (without the leading backslash)
     * 是否在返回资产包之前发布资产文件。
     * 如果设置为false，则必须手动调用`AssetBundle::publish()`发布资产文件。
     * @param boolean $publish whether to publish the asset files in the asset bundle before it is returned.
     * If you set this false, you must manually call `AssetBundle::publish()` to publish the asset files.
     * 返回资产包实例
     * @return AssetBundle the asset bundle instance
     * @throws InvalidConfigException if $name does not refer to a valid asset bundle
     */
    public function getBundle($name, $publish = true)
    {
        // 如果bundles 值是 false，则意味着相应的资产包被禁用，getBundle() 将返回 null
        if ($this->bundles === false) {
            return $this->loadDummyBundle($name);
        } elseif (!isset($this->bundles[$name])) {
            // 将指定名称的资源包类配置实例化为资源包对象,并返回
            return $this->bundles[$name] = $this->loadBundle($name, [], $publish);
        } elseif ($this->bundles[$name] instanceof AssetBundle) {
            return $this->bundles[$name];
        } elseif (is_array($this->bundles[$name])) {
            // $this->bundles[$name] 是配置数组，则将指定名称的资源包类配置实例化为资源包对象,并返回
            return $this->bundles[$name] = $this->loadBundle($name, $this->bundles[$name], $publish);
        } elseif ($this->bundles[$name] === false) {
            // 如果bundles 值是 false，则意味着相应的资产包被禁用，getBundle() 将返回 null
            return $this->loadDummyBundle($name);
        } else {
            throw new InvalidConfigException("Invalid asset bundle configuration: $name");
        }
    }

    /**
     * 将指定名称的资源包类配置实例化为资源包对象
     * Loads asset bundle class by name
     *
     * @param string $name bundle name
     * @param array $config bundle object configuration
     * @param boolean $publish if bundle should be published
     * @return AssetBundle
     * @throws InvalidConfigException if configuration isn't valid
     */
    protected function loadBundle($name, $config = [], $publish = true)
    {
        // 如果配置文件中不存在 'class' 元素，则将 $name 作为 资源包类名
        if (!isset($config['class'])) {
            $config['class'] = $name;
        }
        // 创建资源包对象
        /* @var $bundle AssetBundle */
        $bundle = Yii::createObject($config);
        if ($publish) {
            // 发布资源包,它还会尝试将非CSS或JS文件(e.g. LESS, Sass)使用 [[AssetManager::converter|asset converter]] 转换成相应的CSS或JS文件
            $bundle->publish($this);
        }
        return $bundle;
    }

    /**
     * 按名称装入虚拟包（空包）
     * Loads dummy bundle by name
     *
     * @param string $name
     * @return AssetBundle
     */
    protected function loadDummyBundle($name)
    {
        if (!isset($this->_dummyBundles[$name])) {
            // 为指定的资源包名称实例化一个空的资源包对象
            $this->_dummyBundles[$name] = $this->loadBundle($name, [
                'sourcePath' => null,
                'js' => [],
                'css' => [],
                'depends' => [],
            ]);
        }
        return $this->_dummyBundles[$name];
    }

    /**
     * 返回指资源的实际URL。
     * 实际的URL是通过对 baseUrl 或 AssetManager::baseUrl 到给定的资源文件的路径获得的。
     * Returns the actual URL for the specified asset.
     * The actual URL is obtained by prepending either [[baseUrl]] or [[AssetManager::baseUrl]] to the given asset path.
     * @param AssetBundle $bundle the asset bundle which the asset file belongs to
     * @param string $asset the asset path. This should be one of the assets listed in [[js]] or [[css]].
     * @return string the actual URL for the specified asset.
     */
    public function getAssetUrl($bundle, $asset)
    {
        if (($actualAsset = $this->resolveAsset($bundle, $asset)) !== false) {
            if (strncmp($actualAsset, '@web/', 5) === 0) {
                $asset = substr($actualAsset, 5);
                $basePath = Yii::getAlias('@webroot');
                $baseUrl = Yii::getAlias('@web');
            } else {
                $asset = Yii::getAlias($actualAsset);
                $basePath = $this->basePath;
                $baseUrl = $this->baseUrl;
            }
        } else {
            $basePath = $bundle->basePath;
            $baseUrl = $bundle->baseUrl;
        }

        if (!Url::isRelative($asset) || strncmp($asset, '/', 1) === 0) {
            return $asset;
        }

        if ($this->appendTimestamp && ($timestamp = @filemtime("$basePath/$asset")) > 0) {
            return "$baseUrl/$asset?v=$timestamp";
        } else {
            return "$baseUrl/$asset";
        }
    }

    /**
     * Returns the actual file path for the specified asset.
     * @param AssetBundle $bundle the asset bundle which the asset file belongs to
     * @param string $asset the asset path. This should be one of the assets listed in [[js]] or [[css]].
     * @return string|boolean the actual file path, or false if the asset is specified as an absolute URL
     */
    public function getAssetPath($bundle, $asset)
    {
        if (($actualAsset = $this->resolveAsset($bundle, $asset)) !== false) {
            return Url::isRelative($actualAsset) ? $this->basePath . '/' . $actualAsset : false;
        } else {
            return Url::isRelative($asset) ? $bundle->basePath . '/' . $asset : false;
        }
    }

    /**
     * @param AssetBundle $bundle
     * @param string $asset
     * @return string|boolean
     */
    protected function resolveAsset($bundle, $asset)
    {
        if (isset($this->assetMap[$asset])) {
            return $this->assetMap[$asset];
        }
        if ($bundle->sourcePath !== null && Url::isRelative($asset)) {
            $asset = $bundle->sourcePath . '/' . $asset;
        }

        $n = mb_strlen($asset, Yii::$app->charset);
        foreach ($this->assetMap as $from => $to) {
            $n2 = mb_strlen($from, Yii::$app->charset);
            if ($n2 <= $n && substr_compare($asset, $from, $n - $n2, $n2) === 0) {
                return $to;
            }
        }

        return false;
    }

    private $_converter;

    /**
     * 获取资源转换器
     * Returns the asset converter.
     * @return AssetConverterInterface the asset converter.
     */
    public function getConverter()
    {
        // 若没有设置资源转换器
        if ($this->_converter === null) {
            // 创建资源转换器对象
            $this->_converter = Yii::createObject(AssetConverter::className());
        } elseif (is_array($this->_converter) || is_string($this->_converter)) {
            // 若设置了资源转换器
            if (is_array($this->_converter) && !isset($this->_converter['class'])) {
                // 若配置数组中没有'class',则设置默认的'class'
                $this->_converter['class'] = AssetConverter::className();
            }
            // 使用配置数组创建资源转换器对象
            $this->_converter = Yii::createObject($this->_converter);
        }
        // 返回资源转换器对象
        return $this->_converter;
    }

    /**
     * 设置资源转换器
     * Sets the asset converter.
     * 这可以是实现[[AssetConverterInterface]]的对象，也可以是可以用来创建资产转换器对象的配置数组。
     * @param array|AssetConverterInterface $value the asset converter.
     * This can be either an object implementing the [[AssetConverterInterface]], or a configuration array that can be used to create the asset converter object.
     */
    public function setConverter($value)
    {
        $this->_converter = $value;
    }

    /**
     * 已经发布的资源的列表
     * @var array published assets
     */
    private $_published = [];

    /**
     * 发布一个文件或一个目录
     * Publishes a file or a directory.
     *
     * 该方法将把指定的文件或目录复制到 basePath，以便通过Web服务器访问它
     * This method will copy the specified file or directory to [[basePath]] so that it can be accessed via the Web server.
     *
     * 如果资产是一个文件，那么将检查它的修改时间，以避免不必要的文件复制。
     * If the asset is a file, its file modification time will be checked to avoid unnecessary file copying.
     *
     * 如果资产是一个目录，那么它下面的所有文件和子目录将被递归地发布。
     * 注意，如果$forceCopy 是 false，方法只检查目标目录的存在，以避免重复的复制(这是非常耗费资源的)。
     * If the asset is a directory, all files and subdirectories under it will be published recursively.
     * Note, in case $forceCopy is false the method only checks the existence of the target directory to avoid repetitive copying (which is very expensive).
     *
     * 默认情况下，当发布目录、子目录和文件时，若其名称以“点”开头，则不会发布。
     * 如果您想要改变这种行为，您可以在 $options 参数中指定“beforeCopy”选项。
     * By default, when publishing a directory, subdirectories and files whose name starts with a dot "." will NOT be published.
     * If you want to change this behavior, you may specify the "beforeCopy" option as explained in the `$options` parameter.
     *
     * Note: On rare scenario, a race condition can develop that will lead to a one-time-manifestation of a non-critical problem in the creation of the directory that holds the published assets.
     * This problem can be avoided altogether by 'requesting' in advance all the resources that are supposed to trigger a 'publish()' call, and doing
     * that in the application deployment phase, before system goes live.
     * See more in the following discussion: http://code.google.com/p/yii/issues/detail?id=2579
     *
     * 要发布的资源(文件或目录)
     * @param string $path the asset (file or directory) to be published
     * 在发布目录时要应用的选项。
     * 支持下面的选项：
     *
     * - only: array, 要复制的文件或目录应该匹配的模式列表。
     * - except: array, 不想要复制的文件或目录应该匹配的模式列表。
     * - caseSensitive: boolean, 在"only" or "except"中指定的模式是否应该是区分大小写的。默认是 true.
     * - beforeCopy: callback, 在复制每个子目录或文件之前调用的一个PHP回调。如果设置了这个参数，覆盖[[beforeCopy]]属性。
     * - afterCopy: callback, 一个在子目录或文件被成功地复制之后被调用的PHP回调。如果设置了这个参数，覆盖[[afterCopy]]属性。
     * - forceCopy: boolean, 即使在目标目录中已经存在，也强制复制所发布的目录。该选项仅在发布目录时才使用。如果设置了这个参数，覆盖[[forceCopy]]属性。
     *
     * @param array $options the options to be applied when publishing a directory.
     * The following options are supported:
     *
     * - only: array, list of patterns that the file paths should match if they want to be copied.
     * - except: array, list of patterns that the files or directories should match if they want to be excluded from being copied.
     * - caseSensitive: boolean, whether patterns specified at "only" or "except" should be case sensitive. Defaults to true.
     * - beforeCopy: callback, a PHP callback that is called before copying each sub-directory or file.
     *   This overrides [[beforeCopy]] if set.
     * - afterCopy: callback, a PHP callback that is called after a sub-directory or file is successfully copied.
     *   This overrides [[afterCopy]] if set.
     * - forceCopy: boolean, whether the directory being published should be copied even if
     *   it is found in the target directory. This option is used only when publishing a directory.
     *   This overrides [[forceCopy]] if set.
     *
     * 返回 路径(目录或文件路径)和资产发布的URL。
     * @return array the path (directory or file path) and the URL that the asset is published as.
     * @throws InvalidParamException if the asset to be published does not exist.
     */
    public function publish($path, $options = [])
    {
        // 获取要发布的资源的路径
        $path = Yii::getAlias($path);
        // 如果该资源已经发布，则直接返回数据
        if (isset($this->_published[$path])) {
            return $this->_published[$path];
        }

        /**
         * @see http://www.w3school.com.cn/php/func_filesystem_realpath.asp
         * realpath($path) : 返回绝对路径名。若失败，则返回 false。比如说文件不存在的话。
         *
         * 若 $path 不是字符串或者,或者文件不存在，则抛异常
         */
        if (!is_string($path) || ($src = realpath($path)) === false) {
            throw new InvalidParamException("The file or directory to be published does not exist: $path");
        }

        if (is_file($src)) {
            // todo here
            // 若 $src 是文件，发布该资源文件，并返回被发布资源的路径和URL。
            return $this->_published[$path] = $this->publishFile($src);
        } else {
            // 若 $src 是目录，发布该资源目录，并返回被发布资源的路径和URL。
            return $this->_published[$path] = $this->publishDirectory($src, $options);
        }
    }

    /**
     * 发布一个文件
     * Publishes a file.
     *
     * 要发布的资产文件
     * @param string $src the asset file to be published
     * 被发布资产的路径和URL。
     * @return array the path and the URL that the asset is published as.
     * @throws InvalidParamException if the asset to be published does not exist.
     */
    protected function publishFile($src)
    {
        // 根据文件信息生成散列字符串作为目录名称
        $dir = $this->hash($src);
        // 获取文件名
        $fileName = basename($src);
        // 要创建的文件所在目录
        $dstDir = $this->basePath . DIRECTORY_SEPARATOR . $dir;
        // 要创建的文件完整路径
        $dstFile = $dstDir . DIRECTORY_SEPARATOR . $fileName;

        if (!is_dir($dstDir)) {
            // 若目录不存在，则递归地创建目录
            FileHelper::createDirectory($dstDir, $this->dirMode, true);
        }

        // 是否使用符号链接来发布资产文件。
        if ($this->linkAssets) {
            if (!is_file($dstFile)) {
                /**
                 * @see http://php.net/manual/zh/function.symlink.php
                 * 建立符号连接
                 */
                symlink($src, $dstFile);
            }
        // 若 $dstFile 不存在 或者 $dstFile 最后修改时间小于源文件最后修改时间
        } elseif (@filemtime($dstFile) < @filemtime($src)) {
            // 复制 $src 到 $dstFile;
            copy($src, $dstFile);
            // 若设置了默认的新文件权限
            if ($this->fileMode !== null) {
                // 修改文件权限
                @chmod($dstFile, $this->fileMode);
            }
        }
        // 返回 被发布资产的路径和URL。
        return [$dstFile, $this->baseUrl . "/$dir/$fileName"];
    }

    /**
     * 发布一个目录。
     * Publishes a directory.
     * 要发布的资产目录
     * @param string $src the asset directory to be published
     * @param array $options the options to be applied when publishing a directory.
     *
     * 在发布目录时要应用的选项。
     * 支持下面的选项：
     *
     * - only: array, 要复制的文件或目录应该匹配的模式列表。
     * - except: array, 不想要复制的文件或目录应该匹配的模式列表。
     * - caseSensitive: boolean, 在"only" or "except"中指定的模式是否应该是区分大小写的。默认是 true.
     * - beforeCopy: callback, 在复制每个子目录或文件之前调用的一个PHP回调。如果设置了这个参数，覆盖[[beforeCopy]]属性。
     * - afterCopy: callback, 一个在子目录或文件被成功地复制之后被调用的PHP回调。如果设置了这个参数，覆盖[[afterCopy]]属性。
     * - forceCopy: boolean, 即使在目标目录中已经存在，也强制复制所发布的目录。该选项仅在发布目录时才使用。如果设置了这个参数，覆盖[[forceCopy]]属性。
     *
     * The following options are supported:
     *
     * - only: array, list of patterns that the file paths should match if they want to be copied.
     * - except: array, list of patterns that the files or directories should match if they want to be excluded from being copied.
     * - caseSensitive: boolean, whether patterns specified at "only" or "except" should be case sensitive. Defaults to true.
     * - beforeCopy: callback, a PHP callback that is called before copying each sub-directory or file.
     *   This overrides [[beforeCopy]] if set.
     * - afterCopy: callback, a PHP callback that is called after a sub-directory or file is successfully copied.
     *   This overrides [[afterCopy]] if set.
     * - forceCopy: boolean, whether the directory being published should be copied even if
     *   it is found in the target directory. This option is used only when publishing a directory.
     *   This overrides [[forceCopy]] if set.
     *
     * 返回被发布资产的路径和URL。
     * @return array the path directory and the URL that the asset is published as.
     * @throws InvalidParamException if the asset to be published does not exist.
     */
    protected function publishDirectory($src, $options)
    {
        // 根据文件信息生成散列字符串作为目录名称
        $dir = $this->hash($src);
        // 要发布的目录
        $dstDir = $this->basePath . DIRECTORY_SEPARATOR . $dir;

        // 是否使用符号链接来发布资产文件。
        if ($this->linkAssets) {
            if (!is_dir($dstDir)) {
                // 若目录不存在，则递归地创建目录
                FileHelper::createDirectory(dirname($dstDir), $this->dirMode, true);
                /**
                 * @see http://php.net/manual/zh/function.symlink.php
                 * 建立符号连接
                 */
                symlink($src, $dstDir);
            }
        // $dstDir 目录不存在或者 强制重新复制资源目录
        } elseif (!empty($options['forceCopy']) || ($this->forceCopy && !isset($options['forceCopy'])) || !is_dir($dstDir)) {
            // 合并各路配置数组
            $opts = array_merge(
                $options,
                [
                    'dirMode' => $this->dirMode,
                    'fileMode' => $this->fileMode,
                ]
            );
            // beforeCopy: callback, 在复制每个子目录或文件之前调用的一个PHP回调。如果设置了这个参数，覆盖[[beforeCopy]]属性。
            if (!isset($opts['beforeCopy'])) {
                // 若没有$opts['beforeCopy']，则设置$opts['beforeCopy']

                if ($this->beforeCopy !== null) {
                    $opts['beforeCopy'] = $this->beforeCopy;
                } else {
                    // 默认的 $opts['beforeCopy']，即，不复制以 '.' 开头的文件
                    $opts['beforeCopy'] = function ($from, $to) {
                        return strncmp(basename($from), '.', 1) !== 0;
                    };
                }
            }
            // 若没有$opts['afterCopy']，则设置$opts['afterCopy']
            if (!isset($opts['afterCopy']) && $this->afterCopy !== null) {
                $opts['afterCopy'] = $this->afterCopy;
            }
            // 将整个目录复制为另一个目录，文件和子目录也将被复制
            FileHelper::copyDirectory($src, $dstDir, $opts);
        }
        // 返回 被发布资产的路径和URL。
        return [$dstDir, $this->baseUrl . '/' . $dir];
    }

    /**
     * Returns the published path of a file path.
     * This method does not perform any publishing. It merely tells you
     * if the file or directory is published, where it will go.
     * @param string $path directory or file path being published
     * @return string|false string the published file path. False if the file or directory does not exist
     */
    public function getPublishedPath($path)
    {
        $path = Yii::getAlias($path);

        if (isset($this->_published[$path])) {
            return $this->_published[$path][0];
        }
        if (is_string($path) && ($path = realpath($path)) !== false) {
            return $this->basePath . DIRECTORY_SEPARATOR . $this->hash($path) . (is_file($path) ? DIRECTORY_SEPARATOR . basename($path) : '');
        } else {
            return false;
        }
    }

    /**
     * Returns the URL of a published file path.
     * This method does not perform any publishing. It merely tells you
     * if the file path is published, what the URL will be to access it.
     * @param string $path directory or file path being published
     * @return string|false string the published URL for the file or directory. False if the file or directory does not exist.
     */
    public function getPublishedUrl($path)
    {
        $path = Yii::getAlias($path);

        if (isset($this->_published[$path])) {
            return $this->_published[$path][1];
        }
        if (is_string($path) && ($path = realpath($path)) !== false) {
            return $this->baseUrl . '/' . $this->hash($path) . (is_file($path) ? '/' . basename($path) : '');
        } else {
            return false;
        }
    }

    /**
     * 为目录路径生成一个CRC32散列。
     * 冲突比MD5要高，但会生成一个小得多的散列字符串。
     * Generate a CRC32 hash for the directory path.
     * Collisions are higher than MD5 but generates a much smaller hash string.
     * @param string $path string to be hashed.
     * @return string hashed string.
     */
    protected function hash($path)
    {
        // 若设置了自定义的散列算法，则使用自定义方法计算
        if (is_callable($this->hashCallback)) {
            return call_user_func($this->hashCallback, $path);
        }
        // 文件： 所在目录名 + 文件最后修改时间
        // 目录： 目录名 + 目录最后修改时间
        $path = (is_file($path) ? dirname($path) : $path) . filemtime($path);
        // 生成散列字符串
        return sprintf('%x', crc32($path . Yii::getVersion()));
    }
}
