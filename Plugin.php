<?php
/**
 * 把外部链接转换为 your_blog_path/go/key/  <br>
 * 通过菜单“创建->短链接”设置 <br>
 * 自定义短链功能来自<a href="http://defe.me/prg/429.html">golinks</a> | 感谢：<a href="http://forum.typecho.org/viewtopic.php?t=5576">小咪兔</a>
 *
 * @package ShortLinks
 * @author Ryan
 * @version 1.0.4
 * @link http://blog.iplayloli.com/typecho-plugin-shortlinks.html
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
		if("Pdo_SQLite" === $adapter || "SQLite" === $adapter){
		   $db->query(" CREATE TABLE IF NOT EXISTS ". $shortlinks ." (
			   id INTEGER PRIMARY KEY, 
			   key TEXT,
			   target TEXT,
			   count NUMERIC)");
		}
		if("Pdo_Mysql" === $adapter || "Mysql" === $adapter){
			$db->query("CREATE TABLE IF NOT EXISTS ". $shortlinks ." (
				  `id` int(8) NOT NULL AUTO_INCREMENT,
				  `key` varchar(64) NOT NULL,
				  `target` varchar(10000) NOT NULL,
				  `count` int(8) DEFAULT '0',
				  PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
		}
		Helper::addAction('shortlinks', 'ShortLinks_Action');
		Helper::addRoute('go', '/go/[key]/', 'ShortLinks_Action', 'shortlink');
		Helper::addPanel(2, 'ShortLinks/panel.php', '短链接', '短链接管理',   'administrator');
		Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('ShortLinks_Plugin','replace');
		Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('ShortLinks_Plugin','replace');
		Typecho_Plugin::factory('Widget_Abstract_Comments')->filter = array('ShortLinks_Plugin','replace');
		return('数据表 '.$shortlinks.' 创建成功, 插件已经成功激活!');
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
		return('短链接插件已被禁用，但是数据表并没有被删除');
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
		$convert =  new Typecho_Widget_Helper_Form_Element_Radio('convert' , array('1'=>_t('开启'),'0'=>_t('关闭')),'1',_t('外链转内链'),_t('开启后会帮你把外链转换成内链'));
		$form->addInput($convert);
		$refererList =  new Typecho_Widget_Helper_Form_Element_Textarea('refererList', NULL, NULL, _t('referer 白名单'), _t('在这里设置 referer 白名单(正则表达式)'));
		$form->addInput($refererList);
		$nonConvertList =  new Typecho_Widget_Helper_Form_Element_Textarea('nonConvertList', NULL, _t('/(b0\.upaiyun\.com|glb\.clouddn\.com|qbox\.me|qnssl\.com)/'), _t('外链转换白名单'), _t('在这里设置外链转换白名单(正则表达式)'));
		$form->addInput($nonConvertList);
	}
	/**
	 * 个人用户的配置面板
	 *
	 * @access public
	 * @param Typecho_Widget_Helper_Form $form
	 * @return void
	 */
	public static function personalConfig(Typecho_Widget_Helper_Form $form){}
	/**
	 * 外链转内链
	 *
	 * @access public
	 * @param $content
	 * @param $class
	 * @return $content
	 */
	public static function replace($text, $widget, $lastResult) {
		$pOption = Typecho_Widget::widget('Widget_Options')->Plugin('ShortLinks');
		if($pOption->convert == 1)  {
			$text = empty($lastResult) ? $text : $lastResult;
			if (($widget instanceof Widget_Archive)||($widget instanceof Widget_Abstract_Comments)) {
				$options = Typecho_Widget::widget('Widget_Options');
				preg_match_all('/<a(.*?)href="(.*?)"(.*?)>/',$text,$matches);
				if($matches){
					foreach($matches[2] as $val){
						if(strpos($val,'://')!==false && strpos($val,rtrim($options->siteUrl, '/'))===false && !preg_match('/\.(jpg|jepg|png|ico|bmp|gif|tiff)/i',$val) && !preg_match($pOption->nonConvertList,$val)){
							$text=str_replace("href=\"$val\"", "target=\"_blank\"href=\"".$options->siteUrl."go/".str_replace("/","|",base64_encode(htmlspecialchars_decode($val)))."\" ",$text);
						}
					}
				}
			}
			if ($widget instanceof Widget_Abstract_Comments) {
				$url = $text['url'];
				if(strpos($url,'://')!==false && strpos($val,rtrim($options->siteUrl, '/'))===false) {
					$text['url'] = $options->siteUrl."go/".str_replace("/","|",base64_encode(htmlspecialchars_decode($url))).'" target="_blank';
				}
			}
		}
		return $text;
	}
 }
