<?php
require_once('eddit/eddit_framework.php');
require_once('eddit/OrderBy.php');
require_once('meekrodb/db.class-patched.php');
require_once('smarty-4/libs/Smarty.class.php');
require_once('eddit/node.php');
require_once('eddit/object.php');
require_once('eddit/data.php');
require_once('eddit/security.php');
// require_once('ce/caches.php');
use PHPMailer\PHPMailer\PHPMailer;
require_once('PHPMailerNew/vendor/autoload.php');

/**
 * Static class for basic CMS functions
 */
class EDDIT
{
	public static $nodeID;
	public static $languageID;
	protected static $config;
	public static $headers = [];
	public static $URLparams = [];
	public static $cache;
	public static $smarty;
	public static $security;
	public static $assetsDir = '';
	// public static $node;
	// public static $objects;
	protected static $clickEdit = null;
	public static function getEddit()
	{
		$clickEdit = EDDIT::$clickEdit;
		if ($clickEdit === null)
		{
			$clickEdit = EDDIT::$clickEdit = new eddit_framework();
		}
		return $clickEdit;
	}
	public static function __callStatic($method, $args)
	{
		$clickEdit = EDDIT::getEddit();
		if (method_exists($clickEdit,$method))
		{
			if (CE_DEBUG && $method !== 'log' && $method !== 'logger' && $method !== 'error')
			{
				if (sizeof($args) > 0)
					call_user_func_array(array($clickEdit, 'log'), array(array('call'=>$method, 'args'=>$args)));
				else
					call_user_func_array(array($clickEdit, 'log'), array(array('call'=>$method)));
			}
			return call_user_func_array(array($clickEdit, $method), $args);
		}
		else
		{
			EDDIT::error('Invalid call to Eddit::'.$method);
			die();
		}
	}
/**
 * Recursivly decodes JSON data in a given array
 * @param  array $array Multidimensional array with JSON data
 * @return array        Parsed array
 */
	public static function decodeJSON ($array)
	{
		$array = (array) $array;
		foreach ($array as $_id => $_val)
		{
			if (is_array($_val))                                                // mehrdimensionaler array
			{
				$array[$_id] = EDDIT::decodeJSON($_val);
			}
			elseif (is_string($_val) && !empty($_val) && ( $_val[0] == '[' || $_val[0] == '{' )) // JSON als text
			{
				$_decoded = json_decode($_val, true);
				if ($_decoded === null)
				{
					EDDIT::error(__METHOD__.' '.$_val);
					$array[$_id]='** JSON ERROR **';
				}
				// elseif (is_a($_decoded,'stdClass'))
				// {
				//     $array[$_id]=(array)$_decoded;
				// }
				// elseif (is_object($_decoded))
				// {
				//     $array[$_id]=(array)$_decoded;
				// }
				elseif (is_array($_decoded))
				{
					$array[$_id]=$_decoded;
				}
			}
		}
		return $array;
	}
/**
 * Return the specified array index if it exists, else return the default value given
 * @param  string $key     the index to look fo
 * @param  array  $array   the haystack to search in
 * @param  mixed  $default the default value, defaults to NULL
 * @return mixed
 */
	public static function arrayKey($key,$array,$default=null)
	{
		$key = (string) $key;
		if (empty($key) && $key !== 0 && $key !== '0') return FALSE;
		if (isset($array[$key])) return $array[$key];
		elseif ($default !== null) return $default;
		else return FALSE;
	}
/**
 * Return the specified index from $_SESSION or the given default value
 * @param  string $name    Index to Search
 * @param  mixed $default  Value to return in the index is not found, defaults to NULL
 * @return mixed
 */
	public static function sessionParam($name,$default=null)
	{
		if (isset($_SESSION[$name]))
		{
			return $_SESSION[$name];
		}
		else
		{
			return $default;
		}
	}
/**
 * Return the specified index from EDDIT::$config or NULL when not set
 * @param  string $name    Index to Search
 * @return mixed
 */
	public static function config($name,$realm = 'eddit')
	{
		if (isset(EDDIT::$config[$realm][$name]))
		{
			return EDDIT::$config[$realm][$name];
		}
		elseif (isset(EDDIT::$config[$name]) && is_array(EDDIT::$config[$realm]))
		{
			return EDDIT::$config[$name];
		}
		else
		{
			return null;
		}
	}
	// public static function configRealm($realm = 'eddit')
	// {
	// 	if (isset(EDDIT::$config[$realm]) && is_array(EDDIT::$config[$realm]))
	// 	{
	// 		return EDDIT::$config[$realm];
	// 	}
	// 	else
	// 	{
	// 		return [];
	// 	}
	// }
	public static function setConfig($data)
	{
		EDDIT::$config = $data;
	}

/**
 * Return a HTML anchor tag based on specific data
 * @param  string $attributes   the "link" attributes
 * @param  mixed $default       Value to return in the index is not found, defaults to NULL
 * @return mixed
 */
	public static function linktag($params)
	{
		$data = null;
		$class = '';
		$style = '';
		$download = '';
		$onclick = '';
		$object = null;
		$attributes = null;
		$title = '';
		// $debug = false;
		extract($params, EXTR_IF_EXISTS);

		// if (IS_SMESH_IP) var_dump($attributes);

		$type = EDDIT::arrayKey('type',$data);
		$target = EDDIT::arrayKey('target',$data,'_self');

		if ( empty($type) || ($type == 'page' && empty($data['page'])) || ($type == 'file' && empty($data['file'])) )
		{
			return '<a>';
		}

		if ($type == 'page')
		{
			$_linknode = EDDIT::nodes($data['page']);
			if (is_a($_linknode,'eddit_node'))			// checken, ob node existiert!
			{
				$_pageType = $_linknode->attr('type');
				if (isset($_pageType['type']) && $_pageType['type'] == 'forward')
				{
					$href = EDDIT::url(array('pg'=>$_pageType['forward']));
				}
				elseif (isset($_pageType['type']) && $_pageType['type'] == 'external')
				{
					$href = $_pageType['external'];
					$target = '_blank';
				}
				else
				{
					$href = sprintf('{url pg=%d}',$data['page']);
				}
			}
			else
			{
				$href = '#non-existing-node-'.$data['page'];
			}
		}
		elseif ($type == 'file')
			$href = sprintf('/assets/%s',$data['file']);
		elseif ($type == 'external')
			$href = $data['external'];
		else
			$href = '#invalid-link';

		$data = [];
		if (is_a($object,'ce_object'))
		{
			$data[] = sprintf('data-id="%d"',$object->id);
			$data[] = sprintf('data-node="%d"',$object->nodeID);
			$data[] = sprintf('data-type="%s"',$object->templateName);
		}
		if (is_array($attributes))
		{
			foreach($attributes AS $_id => $_attr)
			{
				$data[] = sprintf('%s="%s"',$_id,$_attr);
			}
		}

		return sprintf(
			'<a target="%s" href="%s" %s %s %s %s %s %s>',
			$target, $href,
			empty($class) ? '' : 'class="'.$class.'"',
			empty($style) ? '' : 'style="'.$style.'"',
			empty($download) ? '' : 'download',
			empty($onclick) ? '' : 'onclick="'.$onclick.'"',
			empty($title) ? '' : 'title="'.$title.'"',
			implode(' ',$data)
		);
	}
/**
 * Return a HTML anchor tag based on specific data
 * @param  string $attributes   the "link" attributes
 * @param  mixed $default       Value to return in the index is not found, defaults to NULL
 * @return mixed
 */
	public static function link($params)
	{
		$pg = null;
		$lg = '';
		$target = '';
		$class = '';
		$style = '';
		$download = '';
		$onclick = '';
		$text = '';
		$title = '';
		extract($params, EXTR_IF_EXISTS);

		$pg = intval($pg);
		if ($pg == EDDIT::$nodeID) $class .= ' active';
		$attributes = EDDIT::nodes($pg)->attributes;
		$href = EDDIT::url(['pg'=>$pg,'lg'=>$lg]);
		if ($text == '') $text = $attributes['title'];
		return sprintf(
			'<a target="%s" href="%s" %s %s %s %s %s>%s</a>',
			$target, $href,
			empty($class) ? '' : 'class="'.$class.'"',
			empty($style) ? '' : 'style="'.$style.'"',
			empty($download) ? '' : 'download',
			empty($onclick) ? '' : 'onclick="'.$onclick.'"',
			empty($title) ? '' : 'title="'.$title.'"',
			$text
		);
	}
/**
 * Return the specified index from EDDIT::$URLparams or the given default value
 * @param  string $name    Index to Search
 * @param  mixed $default  Value to return in the index is not found, defaults to NULL
 * @return mixed
 */
	public static function urlParam($name,$default=null)
	{
		if (isset(EDDIT::$URLparams[$name]))
		{
			return EDDIT::$URLparams[$name];
		}
		else
		{
			return $default;
		}
	}
/**
 * Return the specified index from $_POST, $_GET or the given default value
 * @param  string $name    Index to Search
 * @param  mixed $default  Value to return in the index is not found, NULL if not specified
 * @return mixed
 */
	public static function requestVar($name,$default=null)
	{
		if (EDDIT::arrayKey($name,$_POST)!==FALSE) $retVar = EDDIT::arrayKey($name,$_POST);
		elseif (EDDIT::arrayKey($name,$_GET)!==FALSE) $retVar = EDDIT::arrayKey($name,$_GET);
		else $retVar=$default;
		return $retVar;
	}
	public static function checkDir($dir)
	{
		if (!is_dir($dir))
		{
			$done = mkdir($dir);
			if ($done)
			{
				syslog(LOG_INFO,$dir. ' created');
				return $dir;
			}
			else
			{
				syslog(LOG_ERR,'error creating '.$dir);
				return false;
			}
		}
		return $dir;
	}
/**
 * Generates a click+edit URL with language, node and other parameters
 * @param  array $params URL parameters
 * @return string
 */
	public static function url($params)
	{
		$pg = 0;
		$lg = '';
		if ($liveDisplay = EDDIT::urlParam('clickedit'))   // im live modus automatisch den URL param mitnehmen
		{
			$params['clickedit'] = 'live';
		}
		extract($params, EXTR_IF_EXISTS);
		$pg = (int)$pg;
		if ($lg === '') $lg = EDDIT::$languageID;
		if ($pg === 0) $pg = EDDIT::$nodeID;
		$node = EDDIT::nodes($pg);
		if (is_a($node,'eddit_node'))
		{
			// $navName = (empty($node->attributes['navname'])) ? $pg : $node->attributes['navname'];
			$navName = (empty($node->attr_lg($lg,'navname'))) ? $pg : $node->attr_lg($lg,'navname');
			$_url = '/'.$lg.'/'.$navName.'/';
			foreach ($params as $key => $value)
			{
				if ($key == 'lg' || $key == 'pg') continue;
				$_url .= sprintf
				(
					'%s--%s/',
					iconv('UTF-8', 'ASCII//IGNORE', $key),
					iconv('UTF-8', 'ASCII//IGNORE', $value)
				);
			}
			return $_url;
		}
		EDDIT::error(__METHOD__.' Node '.$pg.' not found.');
		return '#invalid_node_'.$pg;
		// return '/'.$lg.'/'.$pg.'/';
	}
/**
 * Generates HTML markup for images
 * @param  array $params Image properties
 * @return string
 */
	public static function image($params)
	{
		$file = null;
		$crop = true;
		$width = null;
		$height = null;
		$class = 'img-responsive';
		$caption = '';
		extract($params, EXTR_IF_EXISTS);
		$_file = EDDIT::arrayKey('file',$file);
		$_offset = EDDIT::arrayKey('offset',$file);
		$_crop = EDDIT::arrayKey('crop',$file);
		$_options = '';
		if (empty($_file)) return '<!-- NO IMAGE PROVIDED! -->';
		if ($crop && is_array($_offset) && is_array($_crop))
		{
			$_options = sprintf('/media/%d/%d/%d/%d/',$_offset[0],$_offset[1],$_crop[0],$_crop[1]);
		}
		else
		{
			$_options = '/assets/';
		}
		return sprintf('<img src="%s%s" width="%s" height="%s" class="%s" alt="%s">', $_options, $_file, $width, $height, $class, $caption);
	}
	public static function image_legacy($params)
	{
		$file = null;
		$width = null;
		$height = null;
		$class = 'img-responsive';
		$caption = '';
		$style = null;
		$usemap = null;
		extract($params, EXTR_IF_EXISTS);
		$_options = '';
		$_attributes = '';
		if (empty($file)) return '<!-- NO IMAGE PROVIDED! -->';

		if (!empty($style)) $_attributes .= sprintf(' style="%s"',$style);
		if (!empty($usemap)) $_attributes .= sprintf(' usemap="%s"',$usemap);

		if ($width > 0 && $height > 0)
		{
			$_options = sprintf('/media/%d/%d/',$width, $height);
		}
		elseif ($width > 0 && $height == null)
		{
			$_options = sprintf('/media/W/%d/',$width);
		}
		elseif ($height > 0 && $width == null)
		{
			$_options = sprintf('/media/H/%d/',$height);
		}
		else
		{
			$_options = '/assets/';
		}
		if (EDDIT::config('AWSbucket'))	// lade bilder von S3 wenn konfiguriert
		{
			if (strpos($file,'layout/')===0)
	        {
	        	$_options = EDDIT::config('AWSbucket');
	        }
		}
		return sprintf('<img src="%s%s" class="%s" alt="%s" %s>', $_options, $file, $class, $caption, $_attributes);
		// return sprintf('<img src="%s%s" width="%s" height="%s" class="%s" alt="%s" usemap="%s">', $_options, $file, $width, $height, $class, $caption, $usemap);
	}
	public static function image_url($params)
	{
		$file = null;
		extract($params, EXTR_IF_EXISTS);
		if (empty($file)) return '<!-- NO IMAGE PROVIDED! -->';
		$_source = '/assets/';
		if (EDDIT::config('AWSbucket'))	// lade bilder von S3 wenn konfiguriert
		{
			if (strpos($file,'layout/')===0)
	        {
	        	$_source = EDDIT::config('AWSbucket');
	        }
		}
		return $_source.$file;
	}
	public static function flagIcon($params)
	{
		$lg = EDDIT::$languageID;
		extract($params, EXTR_IF_EXISTS);
		switch ($lg)
		{
			case 'at':
			case 'de':
			case 'de-at':   $icon = 'at'; break;
			case 'de-de':   $icon = 'de'; break;
			case 'de-ch':
			case 'it-ch':
			case 'fr-ch':      $icon = 'ch'; break;
			case 'fr-be':
			case 'nl-be':      $icon = 'be'; break;
			case 'en':
			case 'en-gb':      $icon = 'gb'; break;
			case 'cs-cz':      $icon = 'cz'; break;
			case 'zh-cn':      $icon = 'cn'; break;
			default:
				$icon = substr($lg,0,2);
		}
		return 'flag-icon-'.$icon;
	}
	public static function smartyI18N($params)
	{
		$prefix = '';
		$id = '';
		$default = '';
		extract($params, EXTR_IF_EXISTS);
		return ($return = EDDIT::$smarty->getConfigVars($prefix.$id)) ? $return : $default;
	}
	public static function linkInfo($params)
	{
		$link = 0;
		extract($params, EXTR_IF_EXISTS);
		$node = EDDIT::nodes($link);
		$removeLink = ''; //'<a class="btn btn-warning" style="float: right; margin-right: 10px" onclick="ce.common.removeLink(this)"><span class="fa fa-close"></span></a>';
		if (is_a($node,'eddit_node'))
		{
			return $removeLink.sprintf(
				'<a class="jstree-anchor"><i class="jstree-icon jstree-themeicon node fa fa-file-text-o jstree-themeicon-custom"></i>%s</a>',
				$node->attr('title')
			);
		}
		else
		{
			return $removeLink.'<a class="jstree-anchor"><i class="jstree-icon jstree-themeicon node fa fa-question-circle-o jstree-themeicon-custom"></i></a>';
		}
	}
	public static function fileInfo($params)
	{
		$file = null;
		extract($params, EXTR_IF_EXISTS);
		$fileExtention = strtolower(substr($file,-3));
		$removeLink = '<a class="btn btn-default" onclick="ce.common.removeFile(this)"><span class="fa fa-close"></span></a>';
		if (empty($file))
		{
			return $removeLink.'<i class="fa fa-question-circle-o"></i><img src="" style="margin-right: 12px"><span class="name" title="">-</span> | <span class="size">-</span> | <span class="date">-</span>';
		}
		elseif (is_file(EDDIT::$assetsDir.$file))
		{
			switch ($fileExtention) {
				case 'pdf':
				case 'png':
				case 'peg':
				case 'jpg':
				case 'tif':
				case 'gif': $fileType = 'file-image-o'; break;
				case 'svg': $fileType = 'file-image-svg'; break;
				case 'mp3': $fileType = 'file-audio-o'; break;
				case 'mov':
				case 'avi':
				case 'peg':
				case 'mpg': $fileType = 'file-video-o'; break;
				case 'htm':
				case 'tml': $fileType = 'file-code-o'; break;
				case 'zip':
				case 'tgz':
				case '.gz': $fileType = 'file-archive-o'; break;
				case 'doc': $fileType = 'file-word-o'; break;
				case 'xls': $fileType = 'file-excel-o'; break;
				// case 'pdf': $fileType = 'file-pdf-o'; break;
				case 'txt': $fileType = 'file-text-o'; break;
				default: $fileType = 'file-o';
			}
			$preview = ($fileType == 'file-image-o') ? sprintf('<img src="/media/W/100/%s" style="margin-right: 12px">', $file) : sprintf('<i class="fa fa-%s"></i>',$fileType);
			$preview = ($fileType == 'file-image-svg') ? sprintf('<img src="/assets/%s" style="max-width: 100px;margin-right: 12px">', $file) : $preview;
			return $preview.$removeLink.sprintf(
				'<a target="_blank" href="/assets/%s"><span class="name">%s</span></a> | <span class="size">%s kB</span> | <span class="date">%s</span>',
				$file, basename($file),
				number_format(filesize(EDDIT::$assetsDir.$file)/1024,0),
				date ("Y.m.d. H:i", filemtime(EDDIT::$assetsDir.$file))
			);
		}
		elseif (is_dir(EDDIT::$assetsDir.$file))
		{
			$removeLink = '<a class="btn btn-default" onclick="ce.common.removeFolder(this)"><span class="fa fa-close"></span></a>';

			return $removeLink.sprintf(
				'<i class="fa fa-folder-o"></i><span class="name">%s</span> | <span class="date">%s</span>',
				$file,
				date ("Y.m.d. H:i", filemtime(EDDIT::$assetsDir.$file))
			);
		}
		else
		{
			return $removeLink.sprintf('<i class="fa fa-chain-broken"></i><img src="" style="margin-right: 12px"><span class="name">%s</span> | <span class="size">-</span> | <span class="date">-</span>',$file);
		}
	}

/**
 * Flattens a multi-dimensional tree recursively
 * @param  array $tree Multi-dimensional array
 * @return array
 */
	public static function flattenTree($tree)
	{
		static $flatTree = [];
		foreach($tree AS $id => $node)
		{
			$flatTree[$id] = $node;
			if (isset($node['children']))
			{
				EDDIT::flattenTree($node['children']);
			}
		}
		return $flatTree;
	}


/**
 * Calls a function based click+edit plugin
 * @param  array $params Plugin properties
 * @param  object $smarty Smarty Object
 * @return void
 */
public static function inline_plugin($params, $smarty)
{
	$pluginName = EDDIT::arrayKey('name',$params,'default');
	$func2call = sprintf('ce_plugin_%s',$pluginName);

	if (CE_DEBUG) EDDIT::log('inline_plugin '.print_r($params,1));

	if ( function_exists($func2call) )
	{
		if (CE_DEBUG) echo '<!-- exec inline_plugin '.$func2call.' -->';
		echo $func2call($params,$smarty);
		return;
	}
	else
	{
		syslog (LOG_INFO,'CE inline_plugin-function nicht gefunden: '.$func2call. ' | ' . $_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']);
		return 'Plugin-Funktion nicht gefunden.';
	}
}

/**
 * Calls a file based click+edit plugin
 * @param  array $params Plugin properties
 * @param  object $smarty Smarty Object
 * @return void
 */
	public static function plugin($params, $smarty)
	{
		$pluginName = EDDIT::arrayKey('name',$params,'default');
		$customFile = sprintf('%s/%s.php',EDDIT::$smarty->plugins_dir[1], $pluginName);
		$defaultFile = sprintf('/www/common/click+edit/plugins/%s.php', $pluginName);
		$func2call = sprintf('ce_plugin_%s',$pluginName);

		// unpack parameter string and add to plugin call
		// $_params = [];
		// $_tmp = explode(';',EDDIT::arrayKey('params',$params,''));
		// foreach ($_tmp AS $_param)
		// {
		// 	$_param = explode('=',$_param);
		// 	if (!empty($_param[0])) $_params[$_param[0]]=$_param[1];
		// }
		// if (sizeof($_params)) $params['params'] = $_params;

		if (CE_DEBUG) EDDIT::log('plugin '.print_r($params,1));
		if (CE_DEBUG) echo '<!-- plugin include '.$func2call.' -->';

		if( is_readable($customFile) && include_once($customFile) )
		{
			if ( function_exists($func2call) )
			{
				if (CE_DEBUG) echo '<!-- exec custom '.$func2call.' -->';
				echo $func2call($params,$smarty);
				return;
			}
			else
			{
				syslog (LOG_INFO,'CE-include custom plugin-function nicht gefunden: '.$func2call. ' in '.$customFile . ' | ' . $_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']);
				return 'Plugin-Funktion nicht gefunden.';
			}
		}
		elseif( is_readable($defaultFile) && include_once($defaultFile) )
		{
			if ( function_exists($func2call) )
			{
				echo '<!-- exec default '.$func2call.' -->';
				echo $func2call($params,$smarty);
				return;
			}
			else
			{
				syslog (LOG_INFO,'CE-include default plugin-function nicht gefunden: '.$func2call. ' in '.$defaultFile . ' | ' . $_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']);
				return 'Plugin-Funktion nicht gefunden.';
			}
		}
		else
		{
			syslog (LOG_INFO,'CE-include plugin-file nicht gefunden: '.$pluginName.'|custom='.$customFile.'|default='.$defaultFile.'|func='.$func2call.'|params='.var_export($params,1));
			return 'Plugin-Datei nicht gefunden.';
		}
	}
	public static function tidyURLstring($string)
	{
		// if (preg_match('/&shy;/',$string))
		// {
		// 	syslog(LOG_INFO,'Fucking shy!');
		// }
		$return = str_replace(
			array('ä', 'ą', '&#228;','ö', 'ó', '&#214;','&#246;','ü', '&#252;','ß', 'ś', '&#223;', '&#8364;', 'ë','è','&#232;','é','&#233;','ê','à','&#224;','á','&#225;','â',' ','&#243;','&#237;','ç','ł','č','š','&#241;',"'",'"',"’",'%',      '<br>','</br>',' - ',' – ','–','+',     '.', '!', '?','&#191;','&#173;','&#186;','&shy;', ',', ' : ', ': ', ':', ' ','&#8217;','&quot;',' & ','&','/','(',')','|','²'),
			array('ae','a', 'ae',    'oe','o', 'oe',    'oe',    'ue','ue',    'ss','s', 'ss',     'euro',    'e','e','e',     'e','e',     'e','a','a',     'a','a',     'a','', 'o',     'i',     'c','l','c','s','n',     '' ,'', '' ,'prozent','-',   '-',    '-',  '-',  '-','plus' , '' , '' , '' ,'',      '',      '',      '',      '' , '-',   '-',  '-', '-','-',      '',      '-',  '-','-','', '' ,'-','2'),
			mb_strtolower(trim($string), 'UTF-8')
		);
		$return = preg_replace( '/--+/','-', $return);	// ersetze mehrfache -- mit -
		$return = preg_replace( '/-+$/','', $return);	// entferne abschliessende -
		return preg_replace('/[\x00-\x1F\x7F]/', '', $return);
	}
	public static function tidyFilename($string)
	{
		$return = str_replace(
			array('ä', 'ą', '&#228;','ö', 'ó', '&#214;','&#246;','ü', '&#252;','ß', 'ś', '&#223;', '&#8364;', 'ë','è','&#232;','é','&#233;','ê','à','&#224;','á','&#225;','â',' ','&#243;','&#237;','ç','ł','č','š','&#241;',"'",'"',"’",'%',      '<br>','</br>',' - ',' – ','–','+',     '!', '?','&#191;','&#173;','&#186;','&shy;', ',', ' : ', ': ', ':', ' ','&#8217;','&quot;',' & ','&','/','(',')','|','²'),
			array('ae','a', 'ae',    'oe','o', 'oe',    'oe',    'ue','ue',    'ss','s', 'ss',     'euro',    'e','e','e',     'e','e',     'e','a','a',     'a','a',     'a','', 'o',     'i',     'c','l','c','s','n',     '' ,'', '' ,'prozent','-',   '-',    '-',  '-',  '-','plus' , '' , '' ,'',      '',      '',      '',      '' , '-',   '-',  '-', '-','-',      '',      '-',  '-','-','', '' ,'-','2'),
			mb_strtolower(trim($string), 'UTF-8')
		);
		$return = preg_replace( '/--+/','-', $return);	// ersetze mehrfache -- mit -
		$return = preg_replace( '/-+$/','', $return);	// entferne abschliessende -
		return preg_replace('/[\x00-\x1F\x7F]/', '', $return);
	}
	public static function addHeader($realm,$name,$content)
	{
		if ($realm == 'title' && empty($content))
		{
			$node = EDDIT::nodes(EDDIT::$nodeID);
			$content = $node->attr('title');
		}
		EDDIT::$headers[$realm][$name] = str_replace('"',"'",$content);
	}
	public static function printHeaders($realm='')
	{
		if ($realm === '')
		{
			$allHeaders = EDDIT::$headers;
			foreach ($allHeaders AS $realm => $headers)
			{
				EDDIT::printHeadersWorker($realm,$headers);
			}
		}
		elseif ($headers = EDDIT::arrayKey($realm,EDDIT::$headers))
		{
				EDDIT::printHeadersWorker($realm,$headers);
		}
		else
		{
			return;
		}
	}
	public static function printHeadersWorker($realm,$headers)
	{
		// if (IS_SMESH_IP) var_dump($headers);
		foreach ($headers AS $key => $value)
		{
			if (empty($value)) continue;
			elseif ($realm == 'link')
				printf("\t".'<link rel="%s" href="%s">'."\n",$key,$value);
			elseif ($realm == 'meta')
				printf("\t".'<meta name="%s" content="%s">'."\n",$key,$value);
			elseif ($realm == 'metaproperty')
				printf("\t".'<meta property="%s" content="%s">'."\n",$key,$value);
			elseif ($realm == 'title')
				printf("\t".'<title>%s</title>'."\n",$value);
		}
	}
	public static function listDirectory($folder, $filter=[], $recursive=false)
	{
		// $folder = dirname(EDDIT::$assetsDir.$folder).'/';
		$folderAbs = EDDIT::$assetsDir.$folder.'/';

		// var_dump('listDirectory',$folderAbs,$filter,$recursive);

		$list = @scandir($folderAbs);

		if(!$list) {
			// EDDIT::error('Could not list path: ' . $folder);
			return [];
		}

		$result = [];
		foreach($list as $item)
		{
			if(preg_match('/^\./',$item) || $item === null) { continue; }
			if(in_array($item,$filter)) { continue; }
			// $tmp = preg_match('([^ a-zа-я-_0-9.]+)ui', $item);
			// if($tmp === false || $tmp === 1) { continue; }
			if(is_dir($folder . DIRECTORY_SEPARATOR . $item))
			{
				$result[] = EDDIT::listDirectory($folder . DIRECTORY_SEPARATOR . $item, $filter);
			}
			else {
				$result[] = $item;
			}
		}

		// var_dump('listDirectory', $result);

		return $result;
	}
	/**
	* Get either a Gravatar URL or complete image tag for a specified email address.
	*
	* @param string $email The email address
	* @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
	* @param string $d Default imageset to use [ 404 | mp | identicon | monsterid | wavatar ]
	* @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
	* @param boole $img True to return a complete IMG tag False for just the URL
	* @param array $atts Optional, additional key/value attributes to include in the IMG tag
	* @return String containing either just a URL or a complete image tag
	* @source https://gravatar.com/site/implement/images/php/
	*/
	public static function getGravatar( $email = null, $s = 150, $d = 'mp', $r = 'g', $img = false, $atts = [] )
	{
		if ($email === null)
		{
			$_user = EDDIT::user('clickedit');
			$email = EDDIT::arrayKey('email',$_user);
		}
		$url = 'https://www.gravatar.com/avatar/';
		$url .= md5( strtolower( trim( $email ) ) );
		$url .= "?s=$s&d=$d&r=$r";
		if ( $img )
		{
			$url = '<img src="' . $url . '"';
			foreach ( $atts as $key => $val )
				$url .= ' ' . $key . '="' . $val . '"';
			$url .= ' />';
		}
		return $url;
	}
	function sendMail($params)
	{
		if (!is_array($params)) return false;
		$to = '';
		$toName = '';
		$from = 'mail.system@blackbox.networx.at';
		$fromName = 'SMESH Mail System';
		$reply = '';
		$replyName = '';
		$cc = '';
		$ccName = '';
		$bcc = 'backup@smesh.studio';
		$subject = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] : $_SERVER['SCRIPT_NAME'];
		$html = '';
		$text = '';
		$charset = 'UTF-8';
		$XMailer = 'SMESH mailer @ '.$subject;
		$attachments = [];
		$headers = [];
		extract($params, EXTR_IF_EXISTS);
	
		if (empty($to) || !$this->checkEmail($to))
		{
			EDDIT::warning(' invalid $to '.$to);
			// syslog(LOG_ERR,__FILE__.' invalid $to '.print_r($params,true));
			return false;
		}
		if (!empty($cc) && !$this->checkEmail($cc))
		{
			EDDIT::warning(' invalid $cc '.$cc);
			return false;
		}
		if (!empty($from) && !$this->checkEmail($from))
		{
			EDDIT::warning(' invalid $from '.$from);
			return false;
		}
		if (!empty($reply) && !$this->checkEmail($reply))
		{
			EDDIT::warning(' invalid $reply '.$reply);
			return false;
		}
		if (!empty($reply) && empty($replyName))
		{
			$replyName = $fromName;
		}
	
		$mail = new PHPMailer();
		$mail->CharSet = $charset;
		$mail->XMailer = $XMailer;
		$mail->setFrom($from,$fromName);
		$mail->addAddress($to, $toName);
		$mail->Subject = $subject;
		$mail->msgHTML($html);
		$mail->AltBody = (empty($text)) ? $mail->html2text($html,true) : $text;
	
		if (!empty($reply)) $mail->addReplyTo($reply, $replyName);
		if (!empty($cc)) $mail->addCC($cc);
		if (!empty($bcc)) $mail->addBCC($bcc);
	
		if (is_array($attachments) && sizeof($attachments) > 0)
		{
			foreach ($attachments AS $file)
			{
				if (is_array($file))
				{
					// syslog(LOG_INFO,__FILE__.' attaching file '.print_r($file,true));
					// using the "Null coalescing operator" ??
					$_path = $file['path'] ?? '';
					$_content = $file['content'] ?? '';
					$_name = $file['name'] ?? '';
					$_encoding = $file['encoding'] ?? 'base64';
					$_type = $file['type'] ?? '';
					$_disposition = $file['disposition'] ?? 'attachment';
					if (is_file($_path))
					{
						// syslog(LOG_INFO,__FILE__.' attaching file '.$_path.' as "'.$_name.'"');
						if (!$mail->addAttachment($_path,$_name,$_encoding,$_type,$_disposition))
						{
							EDDIT::warning(' error attaching file '.print_r($file,true));
						}
					}
					elseif (!empty($_content))
					{
						// syslog(LOG_INFO,__FILE__.' attaching string as "'.$_name.'"');
						if (!$mail->addStringAttachment($_content,$_name,$_encoding,$_type,$_disposition))
						{
							EDDIT::warning(' error attaching string '.$_name);
						}
					}
				}
				elseif (is_file($file))
				{
					// syslog(LOG_INFO,__FILE__.' attaching file '.$file.' as "'.basename($file).'"');
					if (!$mail->addAttachment($file))
					{
						EDDIT::warning(' error attaching file '.$file);
					}
				}
			}
		}
		if (is_array($headers) && sizeof($headers) > 0)
		{
			foreach ($headers AS $header)
			{
				$mail->addCustomHeader($header);
			}
		}
	
		if ((!$mail->send()))
		{
			$errorMsg = $mail->ErrorInfo;
			EDDIT::error(print_r($errorMsg,true));
			$returnCode = false;
		}
		else
		{
			$returnCode = true;
		}
		unset($mail);
		return $returnCode;
	}
	function checkEmail($address)
	{
		return preg_match('/^[\w.+-]{2,}\@[\w.-]{2,}\.[a-zA-Z]{2,10}$/',$address);
	}
}
class My_Security_Policy extends Smarty_Security
{
	// http://www.smarty.net/docs/en/advanced.features.tpl
	public $static_classes = array('EDDIT','DB');
	// remove PHP tags
	// public $php_handling = Smarty::PHP_REMOVE;
	// allow everthing as modifier
	public $modifiers = [];
	// disable all PHP functions
	public $php_functions = array('isset', 'empty', 'count', 'in_array', 'is_array', 'strpos', 'basename', 'dirname');
	// allow everthing as modifier
	public $php_modifiers = array('sprintf', 'print_r', 'basename', 'nl2br', 'number_format', 'implode', 'round', 'substr', 'pathinfo');
	// {php} and {include_php} not allowed
	public $allow_php_tag = false;
	// no super globals can be accessed
	public $allow_super_globals = false;
}
?>
