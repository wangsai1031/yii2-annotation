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
 * yii.sample = (function($) {
 *     var pub = {
 *         // whether this module is currently active. If false, init() will not be called for this module
 *         // it will also not be called for all its child modules. If this property is undefined, it means true.
 *         isActive: true,
 *         init: function() {
 *             // ... module initialization code go here ...
 *         },
 *
 *         // ... other public functions and properties go here ...
 *     };
 *
 *     // ... private functions and properties go here ...
 *
 *     return pub;
 * })(jQuery);
 * ```
 *
 * Using this structure, you can define public and private functions/properties for a module.
 * Private functions/properties are only visible within the module, while public functions/properties
 * may be accessed outside of the module. For example, you can access "yii.sample.isActive".
 *
 * You must call "yii.initModule()" once for the root module of all your modules.
 */
yii = (function ($) {
    var pub = {
        /**
         * 可以通过AJAX请求多次加载的JS或CSS url列表。
         * 每个脚本都可以表示为一个绝对URL或一个相对的URL。
         * List of JS or CSS URLs that can be loaded multiple times via AJAX requests.
         * Each script can be represented as either an absolute URL or a relative one.
         */
        reloadableScripts: [],
        /**
         * 可点击元素的选择器，它需要支持确认和表单提交。
         * The selector for clickable elements that need to support confirmation and form submission.
         */
        clickableSelector: 'a, button, input[type="submit"], input[type="button"], input[type="reset"], input[type="image"]',
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
            if (confirm(message)) {
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
         * This method recognizes the `data-method` attribute of the element.
         * If the attribute exists, the method will submit the form containing this element.
         * If there is no containing form, a form will be created and submitted using the method given by this attribute value (e.g. "post", "put").
         * For hyperlinks, the form action will take the value of the "href" attribute of the link.
         * For other elements, either the containing form action or the current page URL will be used as the form action URL.
         *
         * 如果没有定义`data-method`属性，那么元素的href属性(如果有的话)将被分配给window.location。
         * If the `data-method` attribute is not defined, the `href` attribute (if any) of the element will be assigned to `window.location`.
         *
         * 从2.0.3开始，当您指定数据方法时，数据params属性也会被识别。
         * `data-params`的值应该是由JSON表示的数据(键值对)，并作为隐藏输入提交。
         * 例如，您可以使用以下代码生成这样的链接:
         * Starting from version 2.0.3, the `data-params` attribute is also recognized when you specify `data-method`.
         * The value of `data-params` should be a JSON representation of the data (name-value pairs) that should be submitted as hidden inputs.
         * For example, you may use the following code to generate such a link:
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
         * ];
         * ```
         *
         * @param $e the jQuery representation of the element
         */
        handleAction: function ($e, event) {
            // 若当前元素存在 data-form 属性，则将该属性作为ID查找表单。否则获取祖先元素中的第一个 form
            var $form = $e.attr('data-form') ? $('#' + $e.attr('data-form')) : $e.closest('form'),
                // 若 当前元素不存在 data-method 属性，且 表单存在，则使用表单的 data-method 属性，否则使用 当前元素的 data-method 属性
                method = !$e.data('method') && $form ? $form.attr('method') : $e.data('method'),
                // 获取当前元素的 href 属性
                action = $e.attr('href'),
                // 获取当前元素的 data-params 属性
                params = $e.data('params'),
                // 获取当前元素的 data-pjax 属性
                pjax = $e.data('pjax'),
                // 等效于pjaxPushState = $e.data('pjax-push-state')||false;
                // pushState 和 replaceState 都可以不刷新页面而改变浏览器地址栏的地址
                // pushState()可以创建历史，可以配合popstate事件，而replaceState()则是替换掉当前的URL，不会产生历史。
                pjaxPushState = !!$e.data('pjax-push-state'),
                pjaxReplaceState = !!$e.data('pjax-replace-state'),
                // pjax 超时时间
                pjaxTimeout = $e.data('pjax-timeout'),
                // pjax 重新加载页面后的定位
                pjaxScrollTo = $e.data('pjax-scrollto'),
                pjaxPushRedirect = $e.data('pjax-push-redirect'),
                pjaxReplaceRedirect = $e.data('pjax-replace-redirect'),
                pjaxSkipOuterContainers = $e.data('pjax-skip-outer-containers'),
                pjaxContainer,
                pjaxOptions = {};

            // 设置了pjax，并且浏览器支持pjax
            if (pjax !== undefined && $.support.pjax) {
                // 若当前元素有 data-pjax-container 属性，则直接使用该属性，否则获取祖先元素中的第一个 data-pjax-container
                if ($e.data('pjax-container')) {
                    pjaxContainer = $e.data('pjax-container');
                } else {
                    pjaxContainer = $e.closest('[data-pjax-container=""]');
                }
                // default to body if pjax container not found
                // 如果没有找到pjax容器，那么默认为body
                if (!pjaxContainer.length) {
                    pjaxContainer = $('body');
                }
                // pjax 配置属性
                pjaxOptions = {
                    container: pjaxContainer,
                    push: pjaxPushState,
                    replace: pjaxReplaceState,
                    scrollTo: pjaxScrollTo,
                    pushRedirect: pjaxPushRedirect,
                    replaceRedirect: pjaxReplaceRedirect,
                    pjaxSkipOuterContainers: pjaxSkipOuterContainers,
                    timeout: pjaxTimeout,
                    originalEvent: event,
                    originalTarget: $e
                }
            }

            // 若没有定义提交方法
            if (method === undefined) {
                // 由于 action = $e.attr('href')。即判断点击a 标签跳转
                if (action && action != '#') {
                    if (pjax !== undefined && $.support.pjax) {
                        $.pjax.click(event, pjaxOptions);
                    } else {
                        window.location = action;
                    }
                } else if ($e.is(':submit') && $form.length) {
                    if (pjax !== undefined && $.support.pjax) {
                        $form.on('submit',function(e){
                            $.pjax.submit(e, pjaxOptions);
                        })
                    }
                    $form.trigger('submit');
                }
                return;
            }

            var newForm = !$form.length;
            if (newForm) {
                if (!action || !action.match(/(^\/|:\/\/)/)) {
                    action = window.location.href;
                }
                $form = $('<form/>', {method: method, action: action});
                var target = $e.attr('target');
                if (target) {
                    $form.attr('target', target);
                }
                if (!method.match(/(get|post)/i)) {
                    $form.append($('<input/>', {name: '_method', value: method, type: 'hidden'}));
                    method = 'POST';
                }
                if (!method.match(/(get|head|options)/i)) {
                    var csrfParam = pub.getCsrfParam();
                    if (csrfParam) {
                        $form.append($('<input/>', {name: csrfParam, value: pub.getCsrfToken(), type: 'hidden'}));
                    }
                }
                $form.hide().appendTo('body');
            }

            var activeFormData = $form.data('yiiActiveForm');
            if (activeFormData) {
                // remember who triggers the form submission. This is used by yii.activeForm.js
                activeFormData.submitObject = $e;
            }

            // temporarily add hidden inputs according to data-params
            if (params && $.isPlainObject(params)) {
                $.each(params, function (idx, obj) {
                    $form.append($('<input/>').attr({name: idx, value: obj, type: 'hidden'}));
                });
            }

            var oldMethod = $form.attr('method');
            $form.attr('method', method);
            var oldAction = null;
            if (action && action != '#') {
                oldAction = $form.attr('action');
                $form.attr('action', action);
            }
            if (pjax !== undefined && $.support.pjax) {
                $form.on('submit',function(e){
                    $.pjax.submit(e, pjaxOptions);
                })
            }
            $form.trigger('submit');
            $.when($form.data('yiiSubmitFinalizePromise')).then(
                function () {
                    if (oldAction != null) {
                        $form.attr('action', oldAction);
                    }
                    $form.attr('method', oldMethod);

                    // remove the temporarily added hidden inputs
                    if (params && $.isPlainObject(params)) {
                        $.each(params, function (idx, obj) {
                            $('input[name="' + idx + '"]', $form).remove();
                        });
                    }

                    if (newForm) {
                        $form.remove();
                    }
                }
            );
        },

        // 获取查询参数
        getQueryParams: function (url) {
            // 在url中查找 ?,若没有，则没有参数。
            var pos = url.indexOf('?');
            if (pos < 0) {
                return {};
            }
            // 截取 ? 之后到第一个 # 之前的部分，再将该部分以 '&' 为分隔符，切分成数组。
            var pairs = url.substring(pos + 1).split('#')[0].split('&'),
                params = {},
                pair,
                i;
            // 遍历 pairs
            for (i = 0; i < pairs.length; i++) {
                // 将参数 a=1 的key 和 val 分开
                pair = pairs[i].split('=');
                // decodeURIComponent() 函数可对 encodeURIComponent() 函数编码的 URI 进行解码。
                var name = decodeURIComponent(pair[0]);
                var value = decodeURIComponent(pair[1]);
                // 若 name 不为 空
                if (name.length) {
                    // 若 params[name] 已经存在
                    if (params[name] !== undefined) {
                        // params[name] 不是数组，则将其转换成一个数组
                        if (!$.isArray(params[name])) {
                            params[name] = [params[name]];
                        }
                        // 将新值添加到数组后。
                        params[name].push(value || '');
                    } else {
                        // 若 params[name] 不存在，则直接赋值
                        params[name] = value || '';
                    }
                }
            }
            // 返回 kv数组
            return params;
        },

        // 初始化模块
        initModule: function (module) {
            if (module.isActive === undefined || module.isActive) {
                // 判断 module 是否存在 init 函数
                if ($.isFunction(module.init)) {
                    module.init();
                }
                $.each(module, function () {
                    if ($.isPlainObject(this)) {
                        pub.initModule(this);
                    }
                });
            }
        },

        init: function () {
            // 初始化 Csrf 令牌
            initCsrfHandler();
            // 重定向处理器
            initRedirectHandler();
            // 脚本过滤器
            initScriptFilter();
            initDataMethods();
        }
    };

    /**
     * 重定向处理器
     */
    function initRedirectHandler() {
        // handle AJAX redirection
        // 处理AJAX重定向
        // $(document).ajaxComplete 为 ajax 执行完成后调用的方法
        $(document).ajaxComplete(function (event, xhr, settings) {
            // 从 header 中获取 X-Redirect 参数
            var url = xhr && xhr.getResponseHeader('X-Redirect');
            if (url) {
                // 若存在，则直接重定向
                window.location = url;
            }
        });
    }

    /**
     * Csrf 处理器
     */
    function initCsrfHandler() {
        // automatically send CSRF token for all AJAX requests
        // 自动为所有AJAX请求发送CSRF令牌
        // jQuery.ajaxPrefilter()函数用于指定预先处理Ajax参数选项的回调函数。
        $.ajaxPrefilter(function (options, originalOptions, xhr) {
            // options.crossDomain 跨域请求
            // 非跨域，并且有 getCsrfParam()
            if (!options.crossDomain && pub.getCsrfParam()) {
                // 在header中添加 'X-CSRF-Token'
                xhr.setRequestHeader('X-CSRF-Token', pub.getCsrfToken());
            }
        });
        // 将所有表单的 CSRF 输入字段 填充最新的令牌。
        pub.refreshCsrfToken();
    }

    function initDataMethods() {
        var handler = function (event) {
            var $this = $(this),
                // 获取 method ： POST GET
                method = $this.data('method'),
                // 获取 confirm
                message = $this.data('confirm'),
                form = $this.data('form');

            // 若三个都不存在，则直接返回
            if (method === undefined && message === undefined && form === undefined) {
                return true;
            }

            // 若message 存在
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

        // 处理 clickable 和 changeable 元素的 data-confirm and data-method
        // handle data-confirm and data-method for clickable and changeable elements
        $(document).on('click.yii', pub.clickableSelector, handler)
            .on('change.yii', pub.changeableSelector, handler);
    }

    /**
     * 脚本过滤器
     */
    function initScriptFilter() {
        // 获取当前域名
        var hostInfo = location.protocol + '//' + location.host;

        // map() 把每个元素通过函数传递到当前匹配集合中，生成包含返回值的新的 jQuery 对象。
        // 选中所有包含src属性的script标签，遍历，
        // 将  ["/assets/345a7110/jquery.js", "/assets/10cc81ed/yii.js", "/assets/a08dd668/js/bootstrap.js"]
        // 转换为  ["http://tran/assets/345a7110/jquery.js", "http://tran/assets/10cc81ed/yii.js", "http://tran/assets/a08dd668/js/bootstrap.js"]
        var loadedScripts = $('script[src]').map(function () {
            // 若src是以'/'开头，则在前面加上域名
            return this.src.charAt(0) === '/' ? hostInfo + this.src : this.src;
        }).toArray();
        // jQuery.ajaxPrefilter()函数用于指定预先处理Ajax参数选项的回调函数。
        // 过滤 通过ajax 请求脚本
        $.ajaxPrefilter('script', function (options, originalOptions, xhr) {
            // 如果是 jsonp，则跳过
            if (options.dataType == 'jsonp') {
                return;
            }
            // 若url的第一个字符是'/',则在前面补上完整域名
            var url = options.url.charAt(0) === '/' ? hostInfo + options.url : options.url;
            // $.inArray() 在数组中查找指定值并返回它的索引值（如果没有找到，则返回-1）
            if ($.inArray(url, loadedScripts) === -1) {
                // 若url 不在 loadedScripts 中，则将该url 添加到 loadedScripts 数组末尾
                loadedScripts.push(url);
            } else {
                // 判断 url 是否可以多次加载
                var isReloadable = $.inArray(url, $.map(pub.reloadableScripts, function (script) {
                        return script.charAt(0) === '/' ? hostInfo + script : script;
                    })) !== -1;
                if (!isReloadable) {
                    // 如果不能多次加载，则终止请求
                    xhr.abort();
                }
            }
        });
        // ajax 请求完成之后执行
        $(document).ajaxComplete(function (event, xhr, settings) {
            var styleSheets = [];
            // 遍历所有外部CSS链接标签
            $('link[rel=stylesheet]').each(function () {
                // 判断 href 是否可以多次加载
                if ($.inArray(this.href, pub.reloadableScripts) !== -1) {
                    return;
                }
                if ($.inArray(this.href, styleSheets) == -1) {
                    styleSheets.push(this.href)
                } else {
                    $(this).remove();
                }
            })
        });
    }

    return pub;
})(jQuery);

jQuery(function () {
    yii.initModule(yii);
});

