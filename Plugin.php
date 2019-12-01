<?php

/**
 * 把外部链接转换为指定内部链接
 *
 * @package ShortLinks
 * @author Ryan
 * @version 1.1.0 a1
 * @link https://github.com/benzBrake/ShortLinks
 */
class ShortLinks_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return String
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        $db = Typecho_Db::get();
        $shortlinks = $db->getPrefix() . 'shortlinks';
        $adapter = $db->getAdapterName();
        if ("Pdo_SQLite" === $adapter || "SQLite" === $adapter) {
            $db->query(" CREATE TABLE IF NOT EXISTS " . $shortlinks . " (
			   id INTEGER PRIMARY KEY, 
			   key TEXT,
			   target TEXT,
			   count NUMERIC)");
        }
        if ("Pdo_Mysql" === $adapter || "Mysql" === $adapter) {
            $db->query("CREATE TABLE IF NOT EXISTS " . $shortlinks . " (
				  `id` int(8) NOT NULL AUTO_INCREMENT,
				  `key` varchar(64) NOT NULL,
				  `target` varchar(10000) NOT NULL,
				  `count` int(8) DEFAULT '0',
				  PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
        }
        Helper::addAction('shortlinks', 'ShortLinks_Action');
        Helper::addRoute('go', '/go/[key]/', 'ShortLinks_Action', 'shortlink');
        Helper::addPanel(2, 'ShortLinks/panel.php', '短链管理', '短链接管理', 'administrator');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('ShortLinks_Plugin', 'replace');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('ShortLinks_Plugin', 'replace');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->filter = array('ShortLinks_Plugin', 'replace');
        Typecho_Plugin::factory('Widget_Abstract_Comments')->filter = array('ShortLinks_Plugin', 'replace');
        Typecho_Plugin::factory('Widget_Archive')->singleHandle = array('ShortLinks_Plugin', 'replace');
        return ('数据表 ' . $shortlinks . ' 创建成功, 插件已经成功激活!');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return String
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removeRoute('go');
        Helper::removeAction('shortlinks');
        Helper::removePanel(2, 'ShortLinks/panel.php');
        return ('短链接插件已被禁用，但是其表（_shortlinks）并没有被删除！');
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $radio = new Typecho_Widget_Helper_Form_Element_Radio('convert', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('外链转内链'), _t('开启后会帮你把外链转换成内链'));
        $form->addInput($radio);
        $radio = new Typecho_Widget_Helper_Form_Element_Radio('convert_comment_link', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('转换评论者链接'), _t('开启后会帮你把评论者链接转换成内链'));
        $form->addInput($radio);
        $template_files = scandir(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates');
        $go_templates = array('NULL' => '不使用');
        foreach ($template_files as $item) {
            if (PATH_SEPARATOR !== ':')
                $item = mb_convert_encoding($item, "UTF-8", "GBK");
            $name = mb_split("\.", $item)[0];
            if (empty($name)) continue;
            $go_templates[$name] = $name;
        }
        $edit = new Typecho_Widget_Helper_Form_Element_Select('go_template', $go_templates, '默认模板', _t('跳转页面模板'));
        $form->addInput($edit);
        $edit = new Typecho_Widget_Helper_Form_Element_Text('go_delay', NULL, _t('3'), _t('跳转延时'), _t('跳转页面停留时间'));
        $form->addInput($edit);
        $radio = new Typecho_Widget_Helper_Form_Element_Radio('target', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('新窗口打开文章中的链接'), _t('开启后会帮你文章中的链接新增target属性'));
        $form->addInput($radio);
        $textarea = new Typecho_Widget_Helper_Form_Element_Textarea('convert_custom_field', NULL, NULL, _t('需要处理的自定义字段'), _t('在这里设置需要处理的自定义字段，一行一个(实验性功能)'));
        $form->addInput($textarea);
        $radio = new Typecho_Widget_Helper_Form_Element_Radio('null_referer', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('空Referer开关'), _t('开启后会允许空Referer'));
        $form->addInput($radio);
        $referer_list = new Typecho_Widget_Helper_Form_Element_Textarea('referer_list', NULL, NULL, _t('referer 白名单'), _t('在这里设置 referer 白名单，一行一个'));
        $form->addInput($referer_list);
        $nonConvertList = new Typecho_Widget_Helper_Form_Element_Textarea('nonConvertList', NULL, _t("b0.upaiyun.com" . PHP_EOL . "glb.clouddn.com" . PHP_EOL . "qbox.me" . PHP_EOL . "qnssl.com"), _t('外链转换白名单'), _t('在这里设置外链转换白名单(评论者链接不生效)'));
        $form->addInput($nonConvertList);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 外链转内链
     *
     * @access public
     * @param $content
     * @param $class
     * @return $content
     */
    public static function replace($text, $widget, $lastResult)
    {
        $text = empty($lastResult) ? $text : $lastResult;
        $pluginOption = Typecho_Widget::widget('Widget_Options')->Plugin('ShortLinks'); // 插件选项
        $siteUrl = Helper::options()->siteUrl;
        $target = ($pluginOption->target) ? ' target="_blank" ' : ''; // 新窗口打开
        if ($pluginOption->convert == 1) {
            if (!is_string($text) && $text instanceof Widget_Archive) {
                // 自定义字段处理
                $fieldsList = self::textareaToArr($pluginOption->convert_custom_field);
                if ($fieldsList) {
                    foreach ($fieldsList as $field) {
                        if (isset($text->fields[$field])) {
                            @preg_match_all('/<a(.*?)href="(.*?)"(.*?)>/', $text->fields[$field], $matches);
                            if ($matches) {
                                foreach ($matches[2] as $link) {
                                    $text->fields[$field] = str_replace("href=\"$link\"", "href=\"" . self::convertLink($link) . "\"", $text->fields[$field]);
                                }
                            }
                        }
                    }
                }
            }
            if (($widget instanceof Widget_Archive) || ($widget instanceof Widget_Abstract_Comments)) {
                $fields = unserialize($widget->fields);
                if (is_array($fields) && array_key_exists("noshort", $fields))
                    return $text;
                // 文章内容和评论内容处理
                @preg_match_all('/<a(.*?)href="(.*?)"(.*?)>/', $text, $matches);
                if ($matches) {
                    foreach ($matches[2] as $link) {
                        $text = str_replace("href=\"$link\"", "href=\"" . self::convertLink($link) . "\"" . $target, $text);
                    }
                }
            }
            if ($pluginOption->convert_comment_link == 1 && $widget instanceof Widget_Abstract_Comments) {
                // 评论者链接处理
                $url = $text['url'];
                if (strpos($url, '://') !== false && strpos($url, rtrim($siteUrl, '/')) === false) {
                    $text['url'] = self::convertLink($url, false);
                }
            }
        }
        return $text;
    }

    /**
     * 转换链接形式
     *
     * @access public
     * @param $link
     * @return $string
     */
    public static function convertLink($link, $check = true)
    {
        $rewrite = (Helper::options()->rewrite) ? '' : 'index.php/'; // 伪静态处理
        $pluginOption = Typecho_Widget::widget('Widget_Options')->Plugin('ShortLinks'); // 插件选项
        $linkBase = ltrim(rtrim(Typecho_Router::get('go')['url'], '/'), '/'); // 防止链接形式修改后不能用
        $siteUrl = Helper::options()->siteUrl;
        $target = ($pluginOption->target) ? ' target="_blank" ' : ''; // 新窗口打开
        $nonConvertList = self::textareaToArr($pluginOption->nonConvertList); // 不转换列表
        if ($check) {
            if (strpos($link, '://') !== false && strpos($link, rtrim($siteUrl, '/')) !== false) return $link; //本站链接不处理
            if (self::checkDomain($link, $nonConvertList)) return $link; // 不转换列表中的不处理
            if (preg_match('/\.(jpg|jepg|png|ico|bmp|gif|tiff)/i', $link)) return $link; // 图片不处理
        }
        return $siteUrl . $rewrite . str_replace('[key]', str_replace("/", "|", base64_encode(htmlspecialchars_decode($link))), $linkBase);
    }

    /**
     * 检查域名是否在数组中存在
     *
     * @access public
     * @param $url $arr
     * @param $class
     * @return boolean
     */
    public static function checkDomain($url, $arr)
    {
        if ($arr === null) return false;
        if (count($arr) === 0) return false;
        foreach ($arr as $a) {
            if (strpos($url, $a) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 一行一个文本框转数组
     *
     * @access public
     * @param $textarea
     * @param $class
     * @return $arr
     */
    public static function textareaToArr($textarea)
    {
        $str = str_replace(array("\r\n", "\r", "\n"), "|", $textarea);
        if ($str == "") return null;
        return explode("|", $str);
    }
}
