/**
 * Yii JavaScript module.
 *
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */

/**
 * yii is the root module for all Yii JavaScript modules.
 * It implements a mechanism of organizing JavaScript code in modules through the function "yii.initModule()".
 *
 * Each module should be named as "x.y.z", where "x" stands for the root module (for the Yii core code, this is "yii").
 *
 * A module may be structured as follows:
 *
 * ```javascript
 * window.yii.sample = (function($) {
 *     var pub = {
 *         // whether this module is currently active. If false, init() will not be called for this module
 *         // it will also not be called for all its child modules. If this property is undefined, it means true.
 *         isActive: true,
 *         init: function() {
 *             // ... module initialization code goes here ...
 *         },
 *
 *         // ... other public functions and properties go here ...
 *     };
 *
 *     // ... private functions and properties go here ...
 *
 *     return pub;
 * })(window.jQuery);
 * ```
 *
 * Using this structure, you can define public and private functions/properties for a module.
 * Private functions/properties are only visible within the module, while public functions/properties
 * may be accessed outside of the module. For example, you can access "yii.sample.isActive".
 *
 * You must call "yii.initModule()" once for the root module of all your modules.
 */
window.yii = (function ($) {
    var pub = {
        /**
         * 可以通过AJAX请求多次加载的JS或CSS url列表。
         * 每个脚本都可以表示为一个绝对URL或一个相对的URL。
         * List of JS or CSS URLs that can be loaded multiple times via AJAX requests.
         * Each item may be represented as either an absolute URL or a relative one.
         * Each item may contain a wildcard matching character `*`, that means one or more
         * any characters on the position. For example:
         *  - `/css/*.css` will match any file ending with `.css` in the `css` directory of the current web site
         *  - `http*://cdn.example.com/*` will match any files on domain `cdn.example.com`, loaded with HTTP or HTTPS
         *  - `/js/myCustomScript.js?realm=*` will match file `/js/myCustomScript.js` with defined `realm` parameter
         */
        reloadableScripts: [],
        /**
         * 可点击元素的选择器，它需要支持确认和表单提交。
         * The selector for clickable elements that need to support confirmation and form submission.
         */
        clickableSelector: 'a, button, input[type="submit"], input[type="button"], input[type="reset"], ' +
        'input[type="image"]',
        /**
         * 可改变元素的选择器，它需要支持确认和表单提交。
         * The selector for changeable elements that need to support confirmation and form submission.
         */
        changeableSelector: 'select, input, textarea',

        /**
         * CSRF参数名称。如果不启用CSRF验证，则返回Undefined
         * @return string|undefined the CSRF parameter name. Undefined is returned if CSRF validation is not enabled.
         */
        getCsrfParam: function () {
            // 从meta标签中获取 CSRF 参数名
            return $('meta[name=csrf-param]').attr('content');
        },

        /**
         * CSRF token。如果不启用CSRF验证，则返回Undefined
         * @return string|undefined the CSRF token. Undefined is returned if CSRF validation is not enabled.
         */
        getCsrfToken: function () {
            // 从meta标签中获取 CSRF Token
            return $('meta[name=csrf-token]').attr('content');
        },

        /**
         * Sets the CSRF token in the meta elements.
         * This method is provided so that you can update the CSRF token with the latest one you obtain from the server.
         * @param name the CSRF token name
         * @param value the CSRF token value
         */
        setCsrfToken: function (name, value) {
            $('meta[name=csrf-param]').attr('content', name);
            $('meta[name=csrf-token]').attr('content', value);
        },

        /**
         * 将所有表单的 CSRF 输入字段 填充最新的令牌。
         * 此方法提供了避免缓存表单包含过时的CSRF令牌。
         * Updates all form CSRF input fields with the latest CSRF token.
         * This method is provided to avoid cached forms containing outdated CSRF tokens.
         */
        refreshCsrfToken: function () {
            // 获取csrf Token
            var token = pub.getCsrfToken();
            // 若token存在，则在表单中填充 csrf Token
            if (token) {
                $('form input[name="' + pub.getCsrfParam() + '"]').val(token);
            }
        },

        /**
         * 显示一个确认对话框
         * Displays a confirmation dialog.
         * 默认的实现只是显示一个js确认对话框。
         * The default implementation simply displays a js confirmation dialog.
         * 您可以通过设置`yii.confirm`来覆盖此方法。
         * You may override this by setting `yii.confirm`.
         * @param message the confirmation message.
         * 当用户确认消息时，将调用一个回调
         * @param ok a callback to be called when the user confirms the message
         * 当用户取消确认时，将调用一个回调
         * @param cancel a callback to be called when the user cancels the confirmation
         */
        confirm: function (message, ok, cancel) {
            if (window.confirm(message)) {
                // 若ok存在，则调用ok
                !ok || ok();
            } else {
                // 若cancel存在，则调用cancel
                !cancel || cancel();
            }
        },

        /**
         * 处理由用户触发的操作。
         * 该方法识别元素的`data-method`属性。
         * 如果该属性存在，则该方法将提交包含该元素的表单。
         * 如果没有包含表单，则将使用该属性值所提供的方法创建并提交表单(例如: "post", "put")。
         * 对于超链接，表单操作将获取链接的“href”属性的值。
         * 对于其他元素，包含表单操作或当前页面URL将用作表单动作URL。
         * Handles the action triggered by user.
         * This method recognizes the `data-method` attribute of the element. If the attribute exists,
         * the method will submit the form containing this element. If there is no containing form, a form
         * will be created and submitted using the method given by this attribute value (e.g. "post", "put").
         * For hyperlinks, the form action will take the value of the "href" attribute of the link.
         * For other elements, either the containing form action or the current page URL will be used
         * as the form action URL.
         *
         * 如果没有定义`data-method`属性，那么元素的href属性(如果有的话)将被分配给window.location。
         * If the `data-method` attribute is not defined, the `href` attribute (if any) of the element
         * will be assigned to `window.location`.
         *
         * 从2.0.3开始，当您指定数据方法时，数据params属性也会被识别。
         * `data-params`的值应该是由JSON表示的数据(键值对)，并作为隐藏输入提交。
         * 例如，您可以使用以下代码生成这样的链接:
         * Starting from version 2.0.3, the `data-params` attribute is also recognized when you specify
         * `data-method`. The value of `data-params` should be a JSON representation of the data (name-value pairs)
         * that should be submitted as hidden inputs. For example, you may use the following code to generate
         * such a link:
         *
         * ```php
         * use yii\helpers\Html;
         * use yii\helpers\Json;
         *
         * echo Html::a('submit', ['site/foobar'], [
         *     'data' => [
         *         'method' => 'post',
         *         'params' => [
         *             'name1' => 'value1',
         *             'name2' => 'value2',
         *         ],
         *     ],
         * ]);
         * ```
         *
         * @param $e the jQuery representation of the element
         * @param event Related event
         */
        handleAction: function ($e, event) {
            // 若当前元素存在 data-form 属性，则将该属性作为ID查找表单。否则获取祖先元素中的第一个 form
            var $form = $e.attr('data-form') ? $('#' + $e.attr('data-form')) : $e.closest('form'),
                // 若 当前元素不存在 data-method 属性，且 表单存在，则使用表单的 data-method 属性，否则使用 当前元素的 data-method 属性
                method = !$e.data('method') && $form ? $form.attr('method') : $e.data('method'),
                // 获取当前元素的 href 属性
                action = $e.attr('href'),
                // 判断action 是否可用，由于 action = $e.attr('href')。即判断点击a 标签跳转
                isValidAction = action && action !== '#',
                // 获取当前元素的 data-params 属性
                params = $e.data('params'),
                areValidParams = params && $.isPlainObject(params),
                // 获取当前元素的 data-pjax 属性
                pjax = $e.data('pjax'),
                // 设置了pjax，并且浏览器支持pjax
                usePjax = pjax !== undefined && pjax !== 0 && $.support.pjax,
                pjaxContainer,
                pjaxOptions = {};

            if (usePjax) {
                // 若当前元素有 data-pjax-container 属性，则直接使用该属性，否则获取祖先元素中的第一个 data-pjax-container
                pjaxContainer = $e.data('pjax-container');
                if (pjaxContainer === undefined || !pjaxContainer.length) {
                    pjaxContainer = $e.closest('[data-pjax-container]').attr('id')
                        ? ('#' + $e.closest('[data-pjax-container]').attr('id'))
                        : '';
                }
                // default to body if pjax container not found
                // 如果没有找到pjax容器，那么默认为body
                if (!pjaxContainer.length) {
                    pjaxContainer = 'body';
                }
                // pjax 配置属性
                pjaxOptions = {
                    container: pjaxContainer,
                    push: !!$e.data('pjax-push-state'),
                    replace: !!$e.data('pjax-replace-state'),
                    scrollTo: $e.data('pjax-scrollto'),
                    pushRedirect: $e.data('pjax-push-redirect'),
                    replaceRedirect: $e.data('pjax-replace-redirect'),
                    skipOuterContainers: $e.data('pjax-skip-outer-containers'),
                    timeout: $e.data('pjax-timeout'),
                    originalEvent: event,
                    originalTarget: $e
                };
            }

            // 若没有定义提交方法
            if (method === undefined) {
                if (isValidAction) {
                    usePjax ? $.pjax.click(event, pjaxOptions) : window.location.assign(action);
                } else if ($e.is(':submit') && $form.length) {
                    if (usePjax) {
                        $form.on('submit', function (e) {
                            $.pjax.submit(e, pjaxOptions);
                        });
                    }
                    $form.trigger('submit');
                }
                return;
            }

            var oldMethod,
                oldAction,
                newForm = !$form.length;
            if (!newForm) {
                oldMethod = $form.attr('method');
                $form.attr('method', method);
                if (isValidAction) {
                    oldAction = $form.attr('action');
                    $form.attr('action', action);
                }
            } else {
                if (!isValidAction) {
                    action = pub.getCurrentUrl();
                }
                $form = $('<form/>', {method: method, action: action});
                var target = $e.attr('target');
                if (target) {
                    $form.attr('target', target);
                }
                if (!/(get|post)/i.test(method)) {
                    $form.append($('<input/>', {name: '_method', value: method, type: 'hidden'}));
                    method = 'post';
                    $form.attr('method', method);
                }
                if (/post/i.test(method)) {
                    var csrfParam = pub.getCsrfParam();
                    if (csrfParam) {
                        $form.append($('<input/>', {name: csrfParam, value: pub.getCsrfToken(), type: 'hidden'}));
                    }
                }
                $form.hide().appendTo('body');
            }

            var activeFormData = $form.data('yiiActiveForm');
            if (activeFormData) {
                // Remember the element triggered the form submission. This is used by yii.activeForm.js.
                activeFormData.submitObject = $e;
            }

            if (areValidParams) {
                $.each(params, function (name, value) {
                    $form.append($('<input/>').attr({name: name, value: value, type: 'hidden'}));
                });
            }

            if (usePjax) {
                $form.on('submit', function (e) {
                    $.pjax.submit(e, pjaxOptions);
                });
            }

            $form.trigger('submit');

            $.when($form.data('yiiSubmitFinalizePromise')).done(function () {
                if (newForm) {
                    $form.remove();
                    return;
                }

                if (oldAction !== undefined) {
                    $form.attr('action', oldAction);
                }
                $form.attr('method', oldMethod);

                if (areValidParams) {
                    $.each(params, function (name) {
                        $('input[name="' + name + '"]', $form).remove();
                    });
                }
            });
        },

        // 获取查询参数
        getQueryParams: function (url) {
            // 在url中查找 ?,若没有，则没有参数。
            var pos = url.indexOf('?');
            if (pos < 0) {
                return {};
            }

            // 截取 ? 之后到第一个 # 之前的部分，再将该部分以 '&' 为分隔符，切分成数组。
            var pairs = $.grep(url.substring(pos + 1).split('#')[0].split('&'), function (value) {
                return value !== '';
            });
            var params = {};

            // 遍历 pairs
            for (var i = 0, len = pairs.length; i < len; i++) {
                // 将参数 a=1 的key 和 val 分开
                var pair = pairs[i].split('=');
                // decodeURIComponent() 函数可对 encodeURIComponent() 函数编码的 URI 进行解码。
                var name = decodeURIComponent(pair[0].replace(/\+/g, '%20'));
                var value = decodeURIComponent(pair[1].replace(/\+/g, '%20'));
                // 若 name 为 空
                if (!name.length) {
                    continue;
                }
                // 若 params[name] 已经存在
                if (params[name] === undefined) {
                    // 若 params[name] 不存在，则直接赋值
                    params[name] = value || '';
                } else {
                    // params[name] 不是数组，则将其转换成一个数组
                    if (!$.isArray(params[name])) {
                        params[name] = [params[name]];
                    }
                    // 将新值添加到数组后。
                    params[name].push(value || '');
                }
            }

            // 返回 kv数组
            return params;
        },

        // 初始化模块
        initModule: function (module) {
            if (module.isActive !== undefined && !module.isActive) {
                return;
            }
            // 判断 module 是否存在 init 函数
            if ($.isFunction(module.init)) {
                module.init();
            }
            $.each(module, function () {
                if ($.isPlainObject(this)) {
                    pub.initModule(this);
                }
            });
        },

        init: function () {
            // 初始化 Csrf 令牌
            initCsrfHandler();
            // 重定向处理器
            initRedirectHandler();
            // 资源文件过滤器
            initAssetFilters();
            initDataMethods();
        },

        /**
         * 获取当前域名
         * Returns the URL of the current page without params and trailing slash. Separated and made public for testing.
         * @returns {string}
         */
        getBaseCurrentUrl: function () {
            return window.location.protocol + '//' + window.location.host;
        },

        /**
         * Returns the URL of the current page. Used for testing, you can always call `window.location.href` manually
         * instead.
         * @returns {string}
         */
        getCurrentUrl: function () {
            return window.location.href;
        }
    };

    /**
     * Csrf 处理器
     */
    function initCsrfHandler() {
        // automatically send CSRF token for all AJAX requests
        $.ajaxPrefilter(function (options, originalOptions, xhr) {
            if (!options.crossDomain && pub.getCsrfParam()) {
                xhr.setRequestHeader('X-CSRF-Token', pub.getCsrfToken());
            }
        });
        pub.refreshCsrfToken();
    }

    /**
     * 重定向处理器
     */
    function initRedirectHandler() {
        // handle AJAX redirection
        $(document).ajaxComplete(function (event, xhr) {
            var url = xhr && xhr.getResponseHeader('X-Redirect');
            if (url) {
                window.location.assign(url);
            }
        });
    }

    /**
     * 资源文件过滤器
     */
    function initAssetFilters() {
        /**
         * Used for storing loaded scripts and information about loading each script if it's in the process of loading.
         * A single script can have one of the following values:
         *
         * - `undefined` - script was not loaded at all before or was loaded with error last time.
         * - `true` (boolean) -  script was successfully loaded.
         * - object - script is currently loading.
         *
         * In case of a value being an object the properties are:
         * - `xhrList` - represents a queue of XHR requests sent to the same URL (related with this script) in the same
         * small period of time.
         * - `xhrDone` - boolean, acts like a locking mechanism. When one of the XHR requests in the queue is
         * successfully completed, it will abort the rest of concurrent requests to the same URL until cleanup is done
         * to prevent possible errors and race conditions.
         * @type {{}}
         */
        var loadedScripts = {};

        // 选中所有包含src属性的script标签，遍历，
        // 将资源的相对路径转换为绝对路径
        $('script[src]').each(function () {
            var url = getAbsoluteUrl(this.src);
            loadedScripts[url] = true;
        });

        // jQuery.ajaxPrefilter()函数用于指定预先处理Ajax参数选项的回调函数。
        // 过滤 通过ajax 请求脚本
        $.ajaxPrefilter('script', function (options, originalOptions, xhr) {
            // 如果是 jsonp，则跳过
            if (options.dataType == 'jsonp') {
                return;
            }

            // 获取Url绝对路径
            var url = getAbsoluteUrl(options.url),
                // 若url已经加载，并且不可以多次加载
                forbiddenRepeatedLoad = loadedScripts[url] === true && !isReloadableAsset(url),
                cleanupRunning = loadedScripts[url] !== undefined && loadedScripts[url]['xhrDone'] === true;

            if (forbiddenRepeatedLoad || cleanupRunning) {
                // 如果不能多次加载，则终止请求
                xhr.abort();
                return;
            }

            if (loadedScripts[url] === undefined || loadedScripts[url] === true) {
                loadedScripts[url] = {
                    xhrList: [],
                    xhrDone: false
                };
            }

            xhr.done(function (data, textStatus, jqXHR) {
                // If multiple requests were successfully loaded, perform cleanup only once
                if (loadedScripts[jqXHR.yiiUrl]['xhrDone'] === true) {
                    return;
                }

                loadedScripts[jqXHR.yiiUrl]['xhrDone'] = true;

                for (var i = 0, len = loadedScripts[jqXHR.yiiUrl]['xhrList'].length; i < len; i++) {
                    var singleXhr = loadedScripts[jqXHR.yiiUrl]['xhrList'][i];
                    if (singleXhr && singleXhr.readyState !== XMLHttpRequest.DONE) {
                        singleXhr.abort();
                    }
                }

                loadedScripts[jqXHR.yiiUrl] = true;
            }).fail(function (jqXHR, textStatus) {
                if (textStatus === 'abort') {
                    return;
                }

                delete loadedScripts[jqXHR.yiiUrl]['xhrList'][jqXHR.yiiIndex];

                var allFailed = true;
                for (var i = 0, len = loadedScripts[jqXHR.yiiUrl]['xhrList'].length; i < len; i++) {
                    if (loadedScripts[jqXHR.yiiUrl]['xhrList'][i]) {
                        allFailed = false;
                    }
                }

                if (allFailed) {
                    delete loadedScripts[jqXHR.yiiUrl];
                }
            });
            // Use prefix for custom XHR properties to avoid possible conflicts with existing properties
            xhr.yiiIndex = loadedScripts[url]['xhrList'].length;
            xhr.yiiUrl = url;

            loadedScripts[url]['xhrList'][xhr.yiiIndex] = xhr;
        });

        // ajax 请求完成之后执行
        $(document).ajaxComplete(function () {
            var styleSheets = [];
            // 遍历所有外部CSS链接标签
            $('link[rel=stylesheet]').each(function () {
                var url = getAbsoluteUrl(this.href);
                // 判断 href 是否可以多次加载
                if (isReloadableAsset(url)) {
                    return;
                }

                $.inArray(url, styleSheets) === -1 ? styleSheets.push(url) : $(this).remove();
            });
        });
    }

    function initDataMethods() {
        var handler = function (event) {
            var $this = $(this),
                method = $this.data('method'),
                message = $this.data('confirm'),
                form = $this.data('form');

            if (method === undefined && message === undefined && form === undefined) {
                return true;
            }

            if (message !== undefined) {
                $.proxy(pub.confirm, this)(message, function () {
                    pub.handleAction($this, event);
                });
            } else {
                pub.handleAction($this, event);
            }
            event.stopImmediatePropagation();
            return false;
        };

        // handle data-confirm and data-method for clickable and changeable elements
        $(document).on('click.yii', pub.clickableSelector, handler)
            .on('change.yii', pub.changeableSelector, handler);
    }

    // 判断资源是否可以多次加载
    function isReloadableAsset(url) {
        for (var i = 0; i < pub.reloadableScripts.length; i++) {
            var rule = getAbsoluteUrl(pub.reloadableScripts[i]);
            var match = new RegExp("^" + escapeRegExp(rule).split('\\*').join('.+') + "$").test(url);
            if (match === true) {
                return true;
            }
        }

        return false;
    }

    // http://stackoverflow.com/questions/3446170/escape-string-for-use-in-javascript-regex
    function escapeRegExp(str) {
        return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
    }

    /**
     * 获取资源文件的绝对路径
     * 将  ["/assets/345a7110/jquery.js", "/assets/10cc81ed/yii.js", "/assets/a08dd668/js/bootstrap.js"]
     * 转换为  ["http://tran/assets/345a7110/jquery.js", "http://tran/assets/10cc81ed/yii.js", "http://tran/assets/a08dd668/js/bootstrap.js"]
     var loadedScripts = $('script[src]').map(function () {
     * Returns absolute URL based on the given URL
     * @param {string} url Initial URL
     * @returns {string}
     */
    function getAbsoluteUrl(url) {
        // 若src是以'/'开头，则在前面加上域名
        return url.charAt(0) === '/' ? pub.getBaseCurrentUrl() + url : url;
    }

    return pub;
})(window.jQuery);

window.jQuery(function () {
    window.yii.initModule(window.yii);
});
