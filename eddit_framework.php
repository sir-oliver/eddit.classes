<?php
class eddit_framework
{
	protected $wwwHome = '/www/';
	protected $ceHome = '/www/common/eddit/';
	protected $ceDir = '';
	protected $ceCompileDir = '';

	protected $serverName = '';
	protected $hostName = '';
	protected $hostDir = '';
	protected $hostCompileDir = '';

	protected $log = array();
	protected $adminMode = false;

	public function __construct()
	{
		if (php_sapi_name() == "cli")
		{
			if (!defined('SERVER_NAME')) die('Please set constant SERVER_NAME before including eddit.php if running from command line!');
			if (CE_DEBUG) $this->log('eddit_framework __construct');
			$this->serverName = SERVER_NAME;
			$this->hostName = SERVER_NAME;
			$this->hostDir = '/www/vhosts/'.SERVER_NAME.'/';
			$this->adminMode = false;
			EDDIT::$assetsDir = $this->hostDir.'htdocs/assets/';
		}
		else
		{
			if (CE_DEBUG) $this->log('eddit_framework __construct');
			$this->serverName = $_SERVER["SERVER_NAME"];
			$this->hostName = (array_key_exists("HTTP_HOST",$_SERVER)) ? $_SERVER["HTTP_HOST"] : $_SERVER["SERVER_NAME"];
			$this->hostDir = (strpos($_SERVER["DOCUMENT_ROOT"],'/htdocs')) ? str_replace('/htdocs','/',$_SERVER["DOCUMENT_ROOT"]) : $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR;
			$this->adminMode = (bool)strpos($_SERVER["SCRIPT_NAME"],'eddit/');
			EDDIT::$assetsDir = $_SERVER["DOCUMENT_ROOT"].'/assets/';
		}
	}
/**
 * initialize the eddit system
 * @return void
 */
	public function init()
	{
		$_config = EDDIT::loadConfig('eddit');
		EDDIT::setConfig($_config);
		DB::$user 		= $_config["database"]["dbUser"];
		DB::$password	= $_config["database"]["dbPass"];
		DB::$dbName		= $_config["database"]["dbName"];
		DB::$host		= $_config["database"]["dbHost"];
		DB::$encoding	= 'utf8';
		DB::debugMode('EDDIT::log');	// callback after each query
		DB::$error_handler = 'EDDIT::error';
		DB::$nonsql_error_handler = 'EDDIT::error';
		// DB::$throw_exception_on_error = true;

		if (!IS_ROBOT)
		{
			// if (CE_DEBUG)
			// syslog(LOG_INFO,__METHOD__.' starting session');
			session_name("eddit");
			session_start();
		}

		// EDDIT::$languageID = EDDIT::requestVar('lg',EDDIT::$config["defaultLG"]);
		EDDIT::$languageID = EDDIT::requestVar('lg',EDDIT::config("defaultLG"));
		$_pg = EDDIT::requestVar('pg');
		if (is_numeric($_pg))       // numerische nodeIDs werden uebernommen
		{
			$_pg = (int)$_pg;
		}
		elseif (is_string($_pg) && !empty($_pg))    // friendly names werden in allen nodes gesucht
		{
			// $_pg = (int)$this->nodesSearchRecursive('navname',$_pg,EDDIT::$config["rootPG"]);
			$_pg = (int)$this->nodesSearchRecursive('navname',$_pg,EDDIT::config("rootPG"));
		}
		else                        // sonst nehmen wir die default nodeID
		{
			// $_pg = (int)EDDIT::$config["defaultPG"];
			$_pg = (int)EDDIT::config("defaultPG");
		}
		EDDIT::$nodeID = $_pg;

		EDDIT::$smarty = new Smarty;
		// if (IS_SMESH_IP)
		// {
		//     EDDIT::$smarty->force_compile = true;
		//     EDDIT::$smarty->compile_check = true;
		//     EDDIT::$smarty->debugging = false;
		// }
		EDDIT::$smarty->caching = false;
		EDDIT::$smarty->use_sub_dirs = true;
		EDDIT::$smarty->enableSecurity('My_Security_Policy');
		$this->ceCompileDir = EDDIT::checkDir($this->wwwHome.'temp/compile/eddit');
		$this->hostCompileDir = EDDIT::checkDir($this->wwwHome.'temp/compile/'.$this->serverName);
		if ($this->adminMode)
		{
			EDDIT::$smarty->template_dir = array
			(
				'templates' => $this->ceHome.'templates',
				'shared' => $this->ceHome.'templates_shared',
				'pages' => $this->hostDir.'templates_pages',
				'site_templates' => $this->hostDir.'templates'
			);
			EDDIT::$smarty->setConfigDir([
				'system' => $this->ceHome.'templates',
				'site' => $this->hostDir.'templates',
			]);
			// EDDIT::$smarty->config_dir = array($this->ceHome.'templates');
			EDDIT::$smarty->compile_dir = $this->ceCompileDir;
			EDDIT::$smarty->cache_dir = EDDIT::checkDir($this->wwwHome.'temp/compile/eddit_cache');
			EDDIT::$smarty->addPluginsDir($this->ceHome .'plugins');
		}
		else
		{
			EDDIT::$smarty->template_dir = array
			(
				'templates' => $this->hostDir.'templates',
				'pages' => $this->hostDir.'templates_pages',
				'shared' => $this->ceHome.'templates_shared'
			);
			EDDIT::$smarty->setConfigDir([
				'site' => $this->hostDir.'templates'
			]);
			// EDDIT::$smarty->config_dir = array($this->hostDir.'templates');
			EDDIT::$smarty->compile_dir = $this->hostCompileDir;
			EDDIT::$smarty->cache_dir = EDDIT::checkDir($this->wwwHome.'temp/compile/'.$this->serverName.'_cache');
			EDDIT::$smarty->addPluginsDir($this->hostDir .'plugins');
		}
		EDDIT::$smarty->registerPlugin('function','linktag', 'EDDIT::linktag');
		EDDIT::$smarty->registerPlugin('function','link', 'EDDIT::link');
		EDDIT::$smarty->registerPlugin('function','url', 'EDDIT::url');
		EDDIT::$smarty->registerPlugin('function','image', 'EDDIT::image');
		EDDIT::$smarty->registerPlugin('function','image_legacy', 'EDDIT::image_legacy');
		EDDIT::$smarty->registerPlugin('function','image_url', 'EDDIT::image_url');
		EDDIT::$smarty->registerPlugin('function','plugin', 'EDDIT::plugin');
		EDDIT::$smarty->registerPlugin('function','inline_plugin', 'EDDIT::inline_plugin');
		EDDIT::$smarty->registerPlugin('function','i18n', 'EDDIT::i18n');
		EDDIT::$smarty->registerPlugin('function','smartyI18N', 'EDDIT::smartyI18N');
		EDDIT::$smarty->registerPlugin('function','flagIcon', 'EDDIT::flagIcon');
		EDDIT::$smarty->registerPlugin('function','linkInfo', 'EDDIT::linkInfo');
		EDDIT::$smarty->registerPlugin('function','fileInfo', 'EDDIT::fileInfo');

		if ( isset($_GET['param']) && is_array($_GET['param']) )
		{
			foreach($_GET['param'] AS $_param)
			{
				if (empty($_param) || !strpos($_param,'--')) continue;
				list($key,$value) = explode('--',$_param);
				EDDIT::$URLparams[$key] = strip_tags($value);
			}
			unset($_GET['param']);
			EDDIT::log('setting EDDIT::$URLparams='.print_r(EDDIT::$URLparams,1));
		}
	}



/**
 * Load the JSON encoded contents of the specified file in the "config" directory
 * @param  string $name    Filename of the configuration file
 * @return mixed
 */
	public function loadConfig($name = 'eddit')
	{
		if ( strpos($name,'.') === false )	// kein punkt im dateinamen
		{
			$filePath = $this->hostDir.'config/'.$name.'.json';
			if ( is_file($filePath) && is_readable($filePath) )
			{
				$fileHandle = fopen($filePath,'r');
				$config = fread($fileHandle,filesize($filePath));
				fclose($fileHandle);
				if ( $config = json_decode($config,true) )
				{
					return $config;
				}
				else
				{
					EDDIT::error([__METHOD__,'invalid json',$filePath]);
				}
			}
			else
			{
				EDDIT::error([__METHOD__,'file does not exists or is not readable',$name]);
			}
		}
		else
		{
			EDDIT::error([__METHOD__,'invalid filename',$name]);
		}
		return [];
	}

/**
 * Return the node object for a given nodeID or an array of ALL nodes if $IDnode = 0
 * @param  integer $IDnode nodeID, defaults to 0
 * @return node
 */
	public function nodes($IDnode = 0)
	{
		static $nodeObjects = array();
		if (sizeof($nodeObjects) == 0)  // caching der datenbank abfragen
		{
			$nodes = DB::query('SELECT * from nodes ORDER BY IDnodes');
			$nodes = EDDIT::decodeJSON($nodes);
			foreach($nodes AS $node)
			{
				$nodeObjects[$node['IDnodes']] = new eddit_node($node['IDnodes'],$node);
			}
		}
		if ($IDnode == 0)
		{
			return $nodeObjects;
		}
		elseif (isset($nodeObjects[$IDnode]))
		{
			return $nodeObjects[$IDnode];
		}
		else
		{
			return null;
		}
	}
	public function nodesSearch($attribute, $value)
	{
		$nodes = $this->nodes();
		foreach($nodes AS $id => $node)
		{
			if ($node->attributes[$attribute] == $value)
			{
				return $id;
			}
		}
		return 0;
	}
	public function nodesSearchRecursive($attribute, $value, $rootNode = 0)
	{
		$nodes = $this->nodesHierarchy($rootNode);
		foreach($nodes AS $id => $node)
		{
			// echo "Checking ID $id\n";
			$thisNode = $this->nodes($id);
			// if ($thisNode->attributes[$attribute] == $value)
			if ($thisNode->attr($attribute) == $value)
			{
				// echo "Returning ID $id\n";
				return $id;
			}
			elseif (isset($node['children']) && is_array($node['children']))
			{
				$foundID = $this->nodesSearchRecursive($attribute, $value, $id);
				if ($foundID !== 0)
				{
					// echo "Returning foundID $foundID\n";
					return $foundID;
				}
			}
		}
		return 0;
	}
/**
 * Build a tree containing ALL nodes of a given root nodeID.
 * @param  integer $IDnode Root of the tree, default to 0
 * @return array
 */
	public function nodesHierarchy($IDnode=0)
	{
		static $decodedData = array();
		if (sizeof($decodedData) == 0)  // caching der datenbank abfragen
		{
			// $data = DB::query('SELECT * from nodes ORDER BY parentID, sequenceID');
			$data = DB::query('SELECT IDnodes, tableID, parentID, sequenceID from nodes ORDER BY parentID, sequenceID');
			$decodedData = EDDIT::decodeJSON($data);
		}
		$data = $this->nodesSort($decodedData, $IDnode);
		return $data;
	}
	private function nodesSort(array $elements, $parentId = 0)
	{
		$branch = array();
		foreach ($elements as $element)
		{
			if ($element['parentID'] == $parentId)
			{
				$children = $this->nodesSort($elements, $element['IDnodes']);
				if ($children)
				{
					$element['children'] = $children;
				}
				$_id = $element['IDnodes'];
				// unset($element['IDnodes']);
				unset($element['sequenceID']);
				unset($element['parentID']);
				unset($element['modified']);
				// unset($element['attr_de']);
				// unset($element['attr_en']);
				$branch[$_id] = $element;
			}
		}
		return $branch;
	}
	public function nodesEnum(array $tree, $depth = 0, $enum = array())
	{
		foreach($tree AS $id => $node)
		{
			$tableID = '';
			$children = array();
			extract($node, EXTR_IF_EXISTS);
			if($tableID != 'objects') continue;    // wir nummerieren nur knoten vom type "objects"
			$attributes = EDDIT::nodes($id)->attributes;
			// if($attributes['online'] == 0) continue;    // unsichtbare nodes und deren kinder brauchen wir nicht ausgeben
			@$enum[$depth]++;
			$tree[$id]['enumString'] = implode($enum,'.');
			$tree[$id]['enum'] = $enum;
			if (isset($node['children']))
			{
				$tree[$id]['children'] = $this->nodesEnum($children, $depth+1, $enum);
			}
		}
		return $tree;
	}
	public function renderNode($IDnode = 0)
	{
		// export as XML wheh urlparam /export--xml/ is set
		// include XSL transformation when urlparam /styled--1/ is set
		if ( EDDIT::urlParam('export') == 'xml' )
		{
			header('Content-Type: text/xml; charset=UTF-8');
			echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
			if (EDDIT::urlParam('styled')) echo '<?xml-stylesheet type="text/xsl" href="'.FQDN.'/eddit/css/export-xml.xsl"?>'."\n";
			echo '<!DOCTYPE edditPage SYSTEM "'.FQDN.'/eddit/css/export-xml.dtd">'."\n";
			echo '<edditPage>'."\n\n";
			EDDIT::exportNode();
			EDDIT::exportObjects();
			echo '</edditPage>'."\n\n";
			return;
		}


		if ($IDnode === 0)
		{
			$IDnode = EDDIT::$nodeID;
		}
		if ($IDnode === 0)
		{
			if (CE_DEBUG) EDDIT::error(__METHOD__.' Node not found.');
			header("HTTP/1.0 404 Not Found");
			header("X-Redirector: ".basename(__FILE__).'::'.__FUNCTION__);
			EDDIT::$smarty->display('file:error.node-not-found.tpl');
			return;
		}

		// EDDIT::login(); // ueberpruefen, ob login noetig ist?

		$node = EDDIT::nodes($IDnode);
		if (is_a($node,'eddit_node'))
		{
			$node->display();
		}
		else
		{
			EDDIT::$smarty->display('file:error.node-not-found.tpl');
			exit();
		}
	}
	public function exportNode($IDnode = 0)
	{
		if ($IDnode === 0)
		{
			$IDnode = EDDIT::$nodeID;
		}
		if ($IDnode === 0)
		{
			// EDDIT::error(__METHOD__.' Node '.$IDnode.' not found.');
			header("HTTP/1.0 404 Not Found");
			header("X-Redirector: ".basename(__FILE__).'::'.__FUNCTION__);
			echo '<object id="0" type="node" title="exportNode"><error>Node not found.</error></object>';
			// EDDIT::$smarty->display('file:error.node-not-found.tpl');
			return;
		}

		$node = EDDIT::nodes($IDnode);
		if (is_a($node,'eddit_node'))
		{
			$executePermission = $node->access('X');
			if (!$executePermission)
			{
				echo '<object id="0" type="error" title="exportNode"><error>Not allowed!</error></object>';
			}
			else
			{
				$node->export();
			}
		}
		else
		{
			// EDDIT::error(__METHOD__.' Node '.$IDnode.' not found.');
			header("HTTP/1.0 404 Not Found");
			header("X-Redirector: ".basename(__FILE__).'::'.__FUNCTION__);
			echo '<object id="0" type="node" title="exportNode"><error>Node not found.</error></object>';
			// EDDIT::$smarty->display('file:error.node-not-found.tpl');
			exit();
		}
	}
	public function redirects()
	{
		$node = EDDIT::nodes(EDDIT::$nodeID);
		if (!is_a($node,'eddit_node'))
		{
			return false;
		}
		$pageType = $node->attr('type');
        if (isset($pageType['type']) && $pageType['type'] == 'forward')
        {
            $href = EDDIT::url(array('pg'=>$pageType['forward']));
            $this->sendRedirectHeaders($href,301);
        }
        elseif (isset($pageType['type']) && $pageType['type'] == 'external')
        {
            $href = $pageType['external'];
            $this->sendRedirectHeaders($href,301);
        }

		$inCMSframe = EDDIT::urlParam('eddit') == 'live';
		if ($inCMSframe)
		{
			return false;
		}

    	// if (!$node->attr('navname'))
    	if (!$node->attr_lg(EDDIT::$languageID,'navname'))
    	{
    		if (CE_DEBUG) syslog(LOG_INFO,__METHOD__.' --> no navname for this page --> no redirection');
    		return false;
    	}

		$pg = EDDIT::requestVar('pg');
		if (is_numeric($pg))       // keine friendly names in der URL
		{
	        $href = EDDIT::url(array('pg'=>EDDIT::$nodeID));
			$this->sendRedirectHeaders($href,301);
		}
	}
	public function sendRedirectHeaders($href,$statuscode=301)
	{
		if (CE_DEBUG) syslog(LOG_INFO,__METHOD__.' --> '.$href);
		header("X-Redirector: ".__METHOD__);
		header("HTTP/1.1 301 Moved Permanently");
		header('Location: '.$href);
		print('Location: '.$href);
		exit();
	}
	public function renderNodeForm($IDnode = 0)
	{
		if ($IDnode === 0) $IDnode = EDDIT::$nodeID;
		$node = EDDIT::nodes($IDnode);
		if ($node->id !== 0)    // node wurde korrekt aus der datenbank geladen
		{
			$node->form();
		}
	}
	public function getNodeParents($IDnode = 0)
	{
		static $parents = array();

		if ($IDnode === 0)  // kein wert uebergeben, also nehmen wir die globalen node-id
		{
			$IDnode = EDDIT::$nodeID;
		}
		if ($IDnode === 0)  // wenn die auch nicht gesetzt ist, dann geben wir nix zurueck
		{
			return array();
		}
		if (!isset($parents[$IDnode]))  // caching der datenbank abfragen
		{
			$node = EDDIT::nodes($IDnode);
			$parents[$IDnode] = $node->getParents();
		}
		return $parents[$IDnode]->getParents();
	}
	public function getNodePath($IDnode = 0)
	{
		static $paths = array();

		if ($IDnode === 0)  // kein wert uebergeben, also nehmen wir die globalen node-id
		{
			$IDnode = EDDIT::$nodeID;
		}
		if ($IDnode === 0)  // wenn die auch nicht gesetzt ist, dann geben wir nix zurueck
		{
			return array();
		}
		if (!isset($paths[$IDnode]))  // caching der pfade abfragen
		{
			$node = $this->getNodeParents($IDnode);
			$paths[$IDnode] = $this->getNodePathWorker($node);
		}
		return $paths[$IDnode];
	}
	private function getNodePathWorker($node)
	{
		static $path = array();
		$path[ $node->id ] = $node->id;
		if (is_a($node->parentNode, 'eddit_node'))
		{
			$this->getNodePathWorker($node->parentNode);
		}
		return $path;
	}






	public function objects($IDobject = 0, $IDnode = 0)
	{
		static $objectsObjects = array();
		if ($IDnode === 0)  // kein wert uebergeben, also nehmen wir die globalen node-id
		{
			$IDnode = EDDIT::$nodeID;
		}
		if ($IDnode === 0)  // wenn die auch nicht gesetzt ist, dann geben wir nix zurueck
		{
			EDDIT::error(__METHOD__.' Node '.$IDnode.' not found.');
			return;
		}
		if (!isset($objectsObjects[$IDnode]) || sizeof($objectsObjects[$IDnode]) == 0)  // caching der datenbank abfragen
		{
			$objects = DB::query('SELECT * from objects WHERE nodeID = %d ORDER BY parentID, sequenceID', $IDnode);
			$objects = EDDIT::decodeJSON($objects);
			foreach($objects AS $object)
			{
				$objectsObjects[$IDnode][$object['IDobjects']] = new eddit_object($object['IDobjects'],$object);
			}
		}
		if ($IDobject == 0)
		{
			return $objectsObjects[$IDnode];
		}
		else
		{
			return $objectsObjects[$IDnode][$IDobject];
		}
	}
	public function objectsHierarchy($IDnode)
	{
		static $decodedData = array();
		if (!isset($decodedData[$IDnode]))  // caching der datenbank abfragen
		{
			$data = DB::query('SELECT * from objects WHERE nodeID = %d ORDER BY parentID, sequenceID', $IDnode);
			$decodedData[$IDnode] = EDDIT::decodeJSON($data);
		}
		$data = $this->objectsSort($decodedData[$IDnode]);
		return $data;
	}
	public function objectsSearch($attribute, $value)
	{
		$objects = $this->objects();
		foreach($objects AS $id => $object)
		{
			if ($object->attributes[$attribute] == $value)
			{
				return $id;
			}
		}
		return 0;
	}
	private function objectsSort(array $elements, $parentId = 0)
	{
		$branch = array();
		foreach ($elements as $element)
		{
			if ($element['parentID'] == $parentId)
			{
				$children = $this->objectsSort($elements, $element['IDobjects']);
				if ($children)
				{
					$element['children'] = $children;
				}
				$_id = $element['IDobjects'];
				// unset($element['IDobjects']);
				unset($element['sequenceID']);
				unset($element['nodeID']);
				// unset($element['parentID']);
				unset($element['modified']);
				$branch[$_id] = $element;
			}
		}
		return $branch;
	}
	public function renderObjects($IDnode = 0)
	{
		if ($IDnode === 0)
		{
			$IDnode = EDDIT::$nodeID;
		}
		if ($IDnode === 0)
		{
			EDDIT::error(__METHOD__.' Node '.$IDnode.' not found.');
			// EDDIT::$smarty->display('file:error-node-not-found.tpl');
			return;
		}
		$node = EDDIT::nodes($IDnode);
		if ($node->tableID != 'objects')    // nur "objects" werden oeffentlich dargestellt
		{
			$return = '<p class="container">Knoten ist nicht darstellbar.</p>';
			if (IS_SMESH_IP)
			{
				$return = '<p class="container">Knoten ist vom Typ <b>'.$node->tableID.'</b> und kann nicht mit <b>'.__METHOD__.'</b> angezeigt werden.</p>';
				// $data = EDDIT::data($node->tableID,$IDnode);
				// $return .= '<pre style="font-size:0.5em">';
				// $return .= print_r($data,1);
				// $return .= '</pre>';
			}
			return $return;
		}

		$liveDisplay = EDDIT::urlParam('eddit'); // seite im vorschaumodus anzeigen?
		if ($liveDisplay)
		{
			EDDIT::$smarty->force_compile = true;
		}
		else
		{
			$fileName = sprintf('page_%d_%s.tpl',$IDnode, EDDIT::$languageID);
			if (EDDIT::$smarty->templateExists('file:[pages]'.$fileName))   // bereits abgespeicherter seiteninhalt
			{
				EDDIT::$smarty->display('file:[pages]'.$fileName,'','page|'.$IDnode);
				return;
			}
		}

		$objects = $this->objectsHierarchy($IDnode);
		$pageContent = $this->renderObjectsWorker($objects,$IDnode);

		if ($liveDisplay)
		{
			EDDIT::log(__METHOD__.' displayed from string');
			EDDIT::$smarty->display('eval:'.$pageContent);
		}
		else // write template data to pages file
		{
			$pagesDir = EDDIT::$smarty->getTemplateDir('pages');
			$done = file_put_contents($pagesDir.$fileName, $pageContent, LOCK_EX);
			EDDIT::log(__METHOD__.' written to file '.$pagesDir.$fileName);
			EDDIT::$smarty->display('file:[pages]'.$fileName,'','page|'.$IDnode);
		}
	}
	private function renderObjectsWorker($tree, $IDnode = 0)
	{
		$tree = (array)$tree;
		static $content = '';
		foreach($tree AS $id => $obj)
		{
			$object = EDDIT::objects($id, $IDnode);

			if(@$object->attributes['online'] == 0)
			{
				// syslog(LOG_INFO,'renderObjectsWorker invisible '.$id);
				continue;    // unsichtbare objekte und deren kinder brauchen wir nicht ausgeben
			}

			$content .= $object->display();
			if (isset($obj['children']))
			{
				$this->renderObjectsWorker($obj['children'], $IDnode);
			}
			$content .= $object->displayClose();
		}
		return $content;
	}
	public function renderObjectForm($IDobject, $IDnode=0)
	{
		$object = EDDIT::objects($IDobject, $IDnode);
		if (is_a($object,'eddit_object'))
		{
			EDDIT::objects($IDobject, $IDnode)->form();
		}
		elseif (CE_DEBUG)
		{
			EDDIT::log(__METHOD__.' object #'.$IDobject.', on node #'.$IDnode.' not found');
			var_dump($IDobject, $IDnode); return;
		}
	}



	public function renderHeaders($IDnode = 0)
	{
		if ($IDnode === 0)
		{
			$IDnode = EDDIT::$nodeID;
		}
		if ($IDnode === 0)
		{
			EDDIT::error(__METHOD__.' Node '.$IDnode.' not found.');
			// EDDIT::$smarty->display('file:error-node-not-found.tpl');
			return;
		}
		$node = EDDIT::nodes($IDnode);
		if ($node->tableID != 'objects')    // nur "objects" werden oeffentlich dargestellt
		{
			return '';
			// $return = 'Knoten ist vom Typ <b>'.$node->tableID.'</b> und sollte nicht mit <b>'.__METHOD__.'</b> angezeigt werden';
			// if (IS_SMESH_IP)
			// {
			//     $data = EDDIT::data($node->tableID,$IDnode);
			//     $return .= '<pre style="font-size:0.5em">';
			//     $return .= print_r($data,1);
			//     $return .= '</pre>';
			// }
			// return $return;
		}

		$fileName = sprintf('headers_%d_%s.tpl',$IDnode, EDDIT::$languageID);
		// $fileName = 'page_'.$IDnode.'_'.EDDIT::$languageID.'.tpl';
		if (EDDIT::$smarty->templateExists('file:[pages]'.$fileName))   // bereits abgespeicherter seiteninhalt
		{
			EDDIT::$smarty->display('file:[pages]'.$fileName,'','page|'.$IDnode);
			return;
		}

		$objects = $this->objectsHierarchy($IDnode);
		$pageContent = $this->renderHeadersWorker($objects);

		// write template data to pages file
		$pagesDir = EDDIT::$smarty->getTemplateDir('pages');
		$done = file_put_contents($pagesDir.$fileName, $pageContent, LOCK_EX);
		EDDIT::log(LOG_INFO,__METHOD__.' written to file '.$pagesDir.$fileName);

		EDDIT::$smarty->display('file:[pages]'.$fileName,'','page|'.$IDnode);
		// $this->renderHeaders($IDnode);    // ruft sich selber nochmal auf um das template darzustellen
	}
	private function renderHeadersWorker($tree)
	{
		$tree = (array)$tree;
		static $content = '';
		foreach($tree AS $id => $obj)
		{
			$object = EDDIT::objects($id);

			if($object->attributes['online'] == 0) continue;    // unsichtbare objekte und deren kinder brauchen wir nicht ausgeben

			$content .= $object->headers();
			if (isset($obj['children']))
			{
				$this->renderHeadersWorker($obj['children']);
			}
		}
		return $content;
	}





	public function exportObjects($IDnode = 0)
	{
		if ($IDnode === 0)
		{
			$IDnode = EDDIT::$nodeID;
		}
		if ($IDnode === 0)
		{
			// EDDIT::error(__METHOD__.' Node '.$IDnode.' not found.');
			header("HTTP/1.0 404 Not Found");
			header("X-Redirector: ".basename(__FILE__).'::'.__FUNCTION__);
			echo '<object id="0" type="node" title="exportObjects"><error>Node not found.</error></object>';
			// EDDIT::$smarty->display('file:error-node-not-found.tpl');
			return;
		}
		$node = EDDIT::nodes($IDnode);

		$executePermission = $node->access('X');
		if (!$executePermission)
		{
			echo '<object id="0" type="error" title="exportObjects"><error>Not allowed!</error></object>';
			return;
		}

		if ('objects' == $node->tableID)
		{
            $objects = $this->objectsHierarchy($IDnode);
            // print_r($objects);
            $this->exportObjectsWorker($objects, $IDnode);
		}
		else
		{
            $template = EDDIT::arrayKey($node->tableID, EDDIT::data('templates'));
            $i18n = EDDIT::arrayKey('i18n', $template);
            if (empty($i18n)) {
                return;
            }

			// $data = $this->data($node->tableID, $IDnode);
			// $data = $this->dataQueryNewer($node->tableID,$where);
			$where = new WhereClause('and');
			$where->add('`nodeID` = %d',EDDIT::$nodeID);
			$data = $this->dataQuery($node->tableID,$where);
            foreach ($data as $line) {
                printf('<object id="%s" type="%s">'."\n", $line['ID'.$node->tableID], $node->tableID);
                foreach ($i18n as $attribute) {
                    if ($value = EDDIT::arrayKey($attribute, $line)) {
                        printf('<%s>'."\n", $attribute);
                        printf('<![CDATA[%s]]>'."\n", $value);
                        printf('</%s>'."\n", $attribute);
                    }
                }
                echo '</object>'."\n\n";
            }
        }
	}
	private function exportObjectsWorker($tree, $IDnode = 0)
	{
		$tree = (array)$tree;
		static $content = '';
		foreach($tree AS $id => $obj)
		{
			$object = EDDIT::objects($id, $IDnode);

			if(@$object->attributes['online'] == 0)
			{
				// syslog(LOG_INFO,'renderObjectsWorker invisible '.$id);
				continue;    // unsichtbare objekte und deren kinder brauchen wir nicht ausgeben
			}
			// print_r($object);
			$object->export();
			if (isset($obj['children']))
			{
				$this->exportObjectsWorker($obj['children'], $IDnode);
			}
			// $object->exportClose();
		}
		return $content;
	}






	public function data($tableID, $IDnode = 0)
	{
		static $decodedData = array();
		if (!isset($decodedData[$tableID][$IDnode]))  // caching der datenbank abfragen
		{
			if ($IDnode == 0)
			{
				$data = DB::query('SELECT * from %b', $tableID);
			}
			else
			{
				$data = DB::query('SELECT * from %b WHERE nodeID = %d', $tableID, $IDnode);
			}
			$data = DBHelper::reIndex($data, 'ID'.$tableID);
			$decodedData[$tableID][$IDnode] = EDDIT::decodeJSON($data);
		}
		$data = $decodedData[$tableID][$IDnode];
		return $data;
	}
	public function dataSearch($tableID, $where, $order = null)
	{
		if (!is_a($where, 'WhereClause'))
		{
			EDDIT::error('dataSearch NO where clause');
			return false;
		}
		if (is_null($order))
		{
			$data = DB::query('SELECT * from %b WHERE %l', $tableID, $where);
		}
		else
		{
			$data = DB::query('SELECT * from %b WHERE %l ORDER BY %l', $tableID, $where, $order);
		}
		$data = DBHelper::reIndex($data, 'ID'.$tableID);
		$decodedData = EDDIT::decodeJSON($data);
		return $decodedData;
	}
	public function dataQueryOld($tableID, $where = null, $order = null, $limitStart=0, $limitCount=0)
	{
		$tableFields = DB::queryFirstColumn('
			SELECT COLUMN_NAME AS "column"
			FROM `information_schema`.`COLUMNS`
			WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
			EDDIT::config('dbName'),
			$tableID
		);
		if (!is_a($where, 'WhereClause'))
		{
			$where = new WhereClause('AND');
		}
		if (!is_a($order, 'OrderBy'))
		{
			$order = new OrderBy();
		}
		if ($limitCount > 0)
		{
			$limit = sprintf('LIMIT %d,%d', $limitStart, $limitCount);
		}
		else
		{
			$limit = '';
		}
		if (in_array('IDlang', $tableFields))  // tabelle unterstuetzt mehrere sprachen
		{
			// if (EDDIT::$config['defaultLG'] == EDDIT::$languageID)
			if (EDDIT::config('defaultLG') == EDDIT::$languageID)
			{
				$where->add('IDlang = %s', EDDIT::$languageID);
				// optimierung wenn defaultsprache = abfragesprache
				$data = DB::query('SELECT %l from `%l` WHERE %l ORDER BY %l %l', implode($tableFields,','), $tableID, $where, $order, $limit);
			}
			else
			{
				// zuerst eine allgemeine abfrage aufbauen, die werte aus CURRENT und DEFAULT language holt
				$fields = [];
				foreach ($tableFields as $field)
				{
					$placeholder = ($field == 'online') ? '-1' : '@@';
					$fields[] = sprintf(
						'IF(cur.%1$s="%2$s", def.%1$s, cur.%1$s) AS "%1$s"', $field, $placeholder
					);
				}
				$sql = 'SELECT '.implode($fields,',');
				$sql .= sprintf(
					' FROM `%1$s` def LEFT JOIN `%1$s` cur ON def.ID%1$s = cur.ID%1$s', $tableID
				);
				$sql .= sprintf(
					' WHERE def.`IDlang` = "%s" AND (cur.`IDlang` = "%s" OR cur.`IDlang` = "")',
					// EDDIT::$config['defaultLG'],
					EDDIT::config('defaultLG'),
					EDDIT::$languageID
				);
				// dann die eigentliche abfrage auf das obige ergebnis als sub-query
				$data = DB::query('SELECT * from ('.$sql.') AS `inline` WHERE %l ORDER BY %l %l', $where, $order, $limit);
			}
		}
		else // tabelle ist "einsprachig"
		{
			$data = DB::query('SELECT * from %b WHERE %l ORDER BY %l %l', $tableID, $where, $order, $limit);
		}
		$data = DBHelper::reIndex($data, 'ID'.$tableID);
		$decodedData = EDDIT::decodeJSON($data);
		return $decodedData;
	}



	public function dataQuery($tableID, $where = null, $order = null, $limitStart=0, $limitCount=0)
	{
		$tableFields = DB::query('
			SELECT COLUMN_NAME AS "column", DATA_TYPE AS "type"
			FROM `information_schema`.`COLUMNS`
			WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
			EDDIT::config('dbName'),
			$tableID
		);
		$tableFields = DBHelper::verticalSlice($tableFields, 'type', 'column');
		if (!is_a($where, 'WhereClause'))
		{
			$where = new WhereClause('AND');
		}
		if (!is_a($order, 'OrderBy'))
		{
			$order = new OrderBy();
		}
		if ($limitCount > 0)
		{
			$limit = sprintf('LIMIT %d,%d', $limitStart, $limitCount);
		}
		else
		{
			$limit = '';
		}
		if (array_key_exists('IDlang', $tableFields))  // tabelle unterstuetzt mehrere sprachen
		{
			// if (EDDIT::$config['defaultLG'] == EDDIT::$languageID)
			if (EDDIT::config('defaultLG') == EDDIT::$languageID)
			{
				$where->add('IDlang = %s', EDDIT::$languageID);
				// optimierung wenn defaultsprache = abfragesprache
				$data = DB::query('SELECT %l from `%l` WHERE %l ORDER BY %l %l', implode(array_keys($tableFields),','), $tableID, $where, $order, $limit);
			}
			else
			{
				// zuerst eine allgemeine abfrage aufbauen, die werte aus CURRENT und DEFAULT language holt
				$fields = [];
				foreach ($tableFields as $field => $fieldtype)	// der platzhalter fuers vererben ist je nach datentyp ein anderer
				{
					switch($fieldtype)
					{
						case 'int':
						case 'tinyint':
							$placeholder = -1; break;
						case 'date':
							$placeholder = '0000-00-00'; break;
						default:
							$placeholder = '@@';
					}

					if (is_numeric($placeholder))
					{
						$fields[] = sprintf(
							'IF(cur.%1$s=%2$d, def.%1$s, cur.%1$s) AS "%1$s"', $field, $placeholder
						);
					}
					else
					{
						$fields[] = sprintf(
							'IF(cur.%1$s="%2$s", def.%1$s, cur.%1$s) AS "%1$s"', $field, $placeholder
						);
					}
				}
				$sql = 'SELECT '.implode($fields,',');
				$sql .= sprintf(
					' FROM `%1$s` def LEFT JOIN `%1$s` cur ON def.ID%1$s = cur.ID%1$s', $tableID
				);
				$sql .= sprintf(
					' WHERE def.`IDlang` = "%s" AND (cur.`IDlang` = "%s" OR cur.`IDlang` = "")',
					// EDDIT::$config['defaultLG'],
					EDDIT::config('defaultLG'),
					EDDIT::$languageID
				);

				// dann die eigentliche abfrage auf das obige ergebnis als sub-query
				$data = DB::query('SELECT * from ('.$sql.') AS `inline` WHERE %l ORDER BY %l %l', $where, $order, $limit);
			}
		}
		else // tabelle ist "einsprachig"
		{
			$data = DB::query('SELECT * from %b WHERE %l ORDER BY %l %l', $tableID, $where, $order, $limit);
		}
		$data = DBHelper::reIndex($data, 'ID'.$tableID);
		$decodedData = EDDIT::decodeJSON($data);
		return $decodedData;
	}




	public function dataQueryNew($tableID, $where = null, $order = null, $limitStart=0, $limitCount=0)
	{
		$tableFields = DB::queryFirstColumn('
			SELECT COLUMN_NAME AS "column"
			FROM `information_schema`.`COLUMNS`
			WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
			EDDIT::config('dbName'),
			$tableID
		);
		if (!is_a($where, 'WhereClause'))
		{
			$where = new WhereClause('AND');
		}
		if (!is_a($order, 'OrderBy'))
		{
			$order = new OrderBy();
		}
		if ($limitCount > 0)
		{
			$limit = sprintf('LIMIT %d,%d', $limitStart, $limitCount);
		}
		else
		{
			$limit = '';
		}
		if (in_array('IDlang', $tableFields))  // tabelle unterstuetzt mehrere sprachen
		{
			// if (EDDIT::$config['defaultLG'] == EDDIT::$languageID)
			if (EDDIT::config('defaultLG') == EDDIT::$languageID)
			{
				$where->add('IDlang = %s', EDDIT::$languageID);
				// optimierung wenn defaultsprache = abfragesprache
				$data = DB::query('SELECT %l from `%l` WHERE %l ORDER BY %l %l', implode($tableFields,','), $tableID, $where, $order, $limit);
			}
			else
			{
				// zuerst eine allgemeine abfrage aufbauen, die werte aus CURRENT und DEFAULT language holt
				$fields = [];
				foreach ($tableFields as $field)
				{
					$placeholder = ($field == 'online') ? '-1' : '@@';
					$fields[] = sprintf(
						'IF(cur.%1$s="%2$s" OR cur.%1$s IS NULL, def.%1$s, cur.%1$s) AS "%1$s"', $field, $placeholder
					);
				}
				$sql = 'SELECT '.implode($fields,',');
				$sql .= sprintf(
					' FROM `%1$s` def LEFT JOIN (SELECT * FROM `%1$s` WHERE `IDlang` = "%2$s") cur ON def.ID%1$s = cur.ID%1$s', $tableID, EDDIT::$languageID
				);
				$sql .= sprintf(
					// ' WHERE def.`IDlang` = "%s"', EDDIT::$config['defaultLG']
					' WHERE def.`IDlang` = "%s"', EDDIT::config('defaultLG')
				);
				// dann die eigentliche abfrage auf das obige ergebnis als sub-query
				$data = DB::query('SELECT * from ('.$sql.') AS `inline` WHERE %l ORDER BY %l %l', $where, $order, $limit);
			}
		}
		else // tabelle ist "einsprachig"
		{
			$data = DB::query('SELECT * from %b WHERE %l ORDER BY %l %l', $tableID, $where, $order, $limit);
		}
		$data = DBHelper::reIndex($data, 'ID'.$tableID);
		$decodedData = EDDIT::decodeJSON($data);
		return $decodedData;
	}
	public function dataQueryNewer($tableID, $where = null, $order = null, $limitStart=0, $limitCount=0)
	{
		if (!is_a($where, 'WhereClause'))
		{
			$where = new WhereClause('AND');
		}
		if (!is_a($order, 'OrderBy'))
		{
			$order = new OrderBy();
		}
		if ($limitCount > 0)
		{
			$limit = sprintf('LIMIT %d,%d', $limitStart, $limitCount);
		}
		else
		{
			$limit = '';
		}
		$data = DB::query('SELECT * from %b WHERE %l ORDER BY %l %l', $tableID, $where, $order, $limit);
		$data = DBHelper::reIndex($data, 'ID'.$tableID);
		$decodedData = EDDIT::decodeJSON($data);
		return $decodedData;
	}
	public function dataQueryBuilder($queryType, $what, $tableID, $where = null, $order = null, $limitStart=0, $limitCount=0)
	{
		$tableFields = DB::queryFirstColumn('
			SELECT COLUMN_NAME AS "column"
			FROM `information_schema`.`COLUMNS`
			WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
			EDDIT::config('dbName'),
			$tableID
		);
		if (!is_a($where, 'WhereClause'))
		{
			$where = new WhereClause('AND');
		}
		if (!is_a($order, 'OrderBy'))
		{
			$order = new OrderBy();
		}
		if ($limitCount > 0)
		{
			$limit = sprintf('LIMIT %d,%d', $limitStart, $limitCount);
		}
		else
		{
			$limit = '';
		}

		if (in_array('IDlang', $tableFields))  // tabelle unterstuetzt mehrere sprachen
		{
			// zuerst eine allgemeine abfrage aufbauen, die werte aus CURRENT und DEFAULT language holt
			$fields = array();
			foreach ($tableFields as $field)
			{
				$placeholder = ($field == 'online') ? '-1' : '@@';
				$fields[] = sprintf(
					'IF(cur.%1$s="%2$s", def.%1$s, cur.%1$s) AS "%1$s"', $field, $placeholder
				);
			}
			$sql = 'SELECT '.implode($fields,',');
			$sql .= sprintf(
				' FROM `%1$s` def LEFT JOIN `%1$s` cur ON def.ID%1$s = cur.ID%1$s', $tableID
			);
			$sql .= sprintf(
				' WHERE def.`IDlang` = "%s" AND (cur.`IDlang` = "%s" OR cur.`IDlang` = "")',
				// EDDIT::$config['defaultLG'],
				EDDIT::config('defaultLG'),
				EDDIT::$languageID
			);
			// dann die eigentliche abfrage auf das obige ergebnis als sub-query
			$data = forward_static_call(array('DB',$queryType), 'SELECT '.$what.' from ('.$sql.') AS `inline` WHERE %l ORDER BY %l %l', $where, $order, $limit);
		}
		else // tabelle ist "einsprachig"
		{
			$data = forward_static_call(array('DB',$queryType), 'SELECT '.$what.' from %b WHERE %l ORDER BY %l %l', $tableID, $where, $order, $limit);
		}
		$decodedData = EDDIT::decodeJSON($data);
		return $decodedData;
	}
	public function renderDataForm($tableID, $dataID)
	{
		$where = new WhereClause('and');
		$where->add('%b = %s', 'ID'.$tableID, $dataID);

		$data = EDDIT::dataSearch($tableID,$where);
		EDDIT::$smarty->assign('title',$tableID);
		EDDIT::$smarty->assign('data',$data);
		EDDIT::$smarty->display('file:formData.tpl');
	}






	public function i18n($placeholder='', $realmID='')
	{
		if (empty($placeholder))
		{
			return '';
		}
		static $decodedData = [];
		if (sizeof($decodedData) == 0)  // caching der datenbank abfragen
		{
			$translations = DB::query('SELECT * from translations ORDER BY realmID');
			foreach($translations AS $t)
			{
				$decodedData[ $t['realmID'] ][ $t['placeholder'] ] = $t;
			}
		}
		
		$IDlanguage = EDDIT::$languageID;
		if (isset($decodedData[$realmID][$placeholder]))
		{
			$i18n = $decodedData[$realmID][$placeholder];
			return isset($i18n['attr_'.$IDlanguage]) ? $i18n['attr_'.$IDlanguage] : $i18n['attr_'.EDDIT::config('defaultLG')];
		}
		else
		{
			EDDIT::log(__METHOD__.' not found '.$realmID.'_'.$placeholder);
			return $realmID.'_'.$placeholder;
		}
	}





/**
 * Returns META information form creating formfields
 * @param  string $fieldID Name of the database field
 * @param  string $realm   Name of the object table
 * @param  string $tableID "nodes" / "objects", defaults to "objects"
 * @return array
 */
	public function meta($fieldID='',$realm='',$tableID='objects')
	{
		static $decodedData = array();
		if (sizeof($decodedData) == 0)  // caching der datenbank abfragen
		{
			$data = DB::query('SELECT IF(realmID = "", CONCAT(tableID,"_",fieldID), CONCAT(tableID,"_",realmID,"_",fieldID)) AS `id`, `formtype`, `sqltype`, `options`, `default`, `overview` from metadata ORDER BY tableID, fieldID');
			$data = DBHelper::reIndex($data, 'id');
			$decodedData = EDDIT::decodeJSON($data);
		}
		$data = $decodedData;
		if ($_special = EDDIT::arrayKey($tableID.'_'.$realm.'_'.$fieldID,$data))
		{
			return $_special;
		}
		elseif ($_general = EDDIT::arrayKey($tableID.'_'.$fieldID,$data))
		{
			return $_general;
		}
		elseif (!empty($fieldID))
		{
			// echo 'da ';
			return array();
		}
		return $data;
	}
/**
 * Returns "options" array for select and radio buttons
 * @param  mixed $options   Array or SQL Statement
 * @return array
 */
	public function options($options)
	{
		if (is_array($options))
		{
			return $options;
		}
		elseif (is_string($options) && strpos($options,'SELECT') === 0)
		{
			$_options = array();
			// $_options = DB::query($options);

			if ( preg_match('/\%[1,2]\$[d,s]/',$options) )	// %1$d / %2$s variables in SQL
			{
				$_sql = sprintf($options,EDDIT::$nodeID,EDDIT::$languageID);
				// syslog(LOG_DEBUG,$_sql);
				$_options = DB::query($_sql);
			}
			else
			{
				$_options = DB::query($options);
			}

			$_options = DBHelper::verticalSlice($_options, 'value', 'key');
			return $_options;
		}
		return array();
	}





	public function log($params)
	{
		// syslog(LOG_INFO,'log() '.print_r($params,1));
		$this->log[] = $params;
	}
	public function logger($caller, $params)
	{
		// syslog(LOG_INFO,'log() '.print_r($params,1));
		$this->log[] = array('logger'=>$caller, 'args'=>$params);
	}
	public function error($params)
	{
		$this->log[] = $params;
		if (isset($params['query']))
		{
			$query = $params['query'];
			unset($params['query']);
			syslog(LOG_ERR,'ERROR '.print_r($params,1));
			syslog(LOG_ERR,'ERROR query = '.$query);
			// mail('admin@smesh.studio',CE_HOSTNAME,print_r($_REQUEST,1).print_r($params,1).$query);
			return;
		}
		syslog(LOG_ERR,'ERROR '.print_r($params,1));
	}
	public function warning($params)
	{
		$this->log[] = $params;
		if (isset($params['query']))
		{
			$query = $params['query'];
			unset($params['query']);
			syslog(LOG_ERR,'ERROR '.print_r($params,1));
			syslog(LOG_ERR,'ERROR query = '.$query);
			// mail('admin@smesh.studio',CE_HOSTNAME,print_r($_REQUEST,1).print_r($params,1).$query);
			return;
		}
		syslog(LOG_WARNING,'WARNING '.print_r($params,1));
	}
	public function notice($params)
	{
		$this->log[] = $params;
		if (isset($params['query']))
		{
			$query = $params['query'];
			unset($params['query']);
			syslog(LOG_ERR,'ERROR '.print_r($params,1));
			syslog(LOG_ERR,'ERROR query = '.$query);
			// mail('admin@smesh.studio',CE_HOSTNAME,print_r($_REQUEST,1).print_r($params,1).$query);
			return;
		}
		syslog(LOG_NOTICE,'NOTICE '.print_r($params,1));
	}
	public function dumpLogger()
	{
		return $this->log;
	}
	public function dumpLog()
	{
		$log = $this->log;
		echo '<div class="panel panel-default">'.PHP_EOL;
		echo '<div class="panel-heading">EDDIT::dumpLog</div>'.PHP_EOL;
		echo '<ul class="list-group">'.PHP_EOL;
		$_dbQueries = 0;
		$_dbTime = 0.0;
		foreach ($log as $_log)
		{
			if (is_string($_log))
			{
				printf('<li class="list-group-item"><b>%s</b></li>'.PHP_EOL, $_log);
			}
			elseif (is_array($_log))                                                // mehrdimensionaler array
			{
				// list($_type, $_cmd) = each($_log);
				$_type = key($_log);
				$_cmd = current($_log);
				// var_dump($_type);
				if ($_type == 'logger')
				{
					printf('<li class="list-group-item"><b>%s</b>'.PHP_EOL, $_cmd);
					if (isset($_log['args']))
					{
						echo '<pre style="border:0; margin:0; padding:0; font-size: 0.8em; background: transparent">';
						print_r($_log['args']);
						echo '</pre>';
					}
					echo '</li>'.PHP_EOL;
				}
				elseif ($_type == 'call')
				{
					printf('<li class="list-group-item"><b>%s</b>'.PHP_EOL, $_cmd);
					if (isset($_log['args']))
					{
						$_args = array();
						foreach ($_log['args'] as $_arg)
						{
							if (is_array($_arg))
								$_args[] = 'Array';
							elseif (is_object($_arg))
								$_args[] = 'Object';
							else
								$_args[] = $_arg;
						}
						echo implode(' , ',$_args);
					}
					echo '</li>'.PHP_EOL;
				}
				elseif ($_type == 'query')
				{
					$_dbQueries++;
					$_dbTime += $_log['runtime']/100;
					echo '<li class="list-group-item"><b>query</b>';
					printf('<span class="badge">%s</span>'.PHP_EOL,$_log['affected']);
					printf('@ <b>%8.4f sec.</b>', $_log['runtime']/100);
					echo '<br>'.$_log['query'];
					echo '</li>'.PHP_EOL;
				}
				elseif ($_type == 'type')   // DB error!
				{
					$_dbQueries++;
					echo '<li class="list-group-item"><b>error</b>';
					printf('<span class="badge">%s</span>'.PHP_EOL,$_log['code']);
					echo $_log['error'];
					echo '</li>'.PHP_EOL;
				}
			}
		}
		echo '<li class="list-group-item">';
		if (defined('CE_TIME'))
			printf('PHP Execution Time <b>%8.4f sec.</b>'.PHP_EOL, CE_TIME);
		printf(' / <b>%d</b> DB Queries @ <b>%8.4f sec.</b>'.PHP_EOL, $_dbQueries, $_dbTime);
		if ( defined('CE_MEM_USAGE') )
		{
			printf(' / Memory Usage <b>%s</b>'.PHP_EOL, str_pad( number_format( CE_MEM_USAGE/1024, 2, ',', '.').' kB', 23));
		}
		echo '</li>';
		echo '</ul>'.PHP_EOL;
		echo '</div>'.PHP_EOL;
	}






	public function setupCMS()
	{
		if (!isset($_SESSION['eddit'])) $_SESSION['eddit'] = true;
		// EDDIT::$security = new security();
	}
	public function renderCMS($IDnode = 0)
	{
		if ($IDnode === 0)
		{
			$IDnode = EDDIT::$nodeID;
		}
		if ($IDnode === 0)
		{
			EDDIT::error(__METHOD__.' Node '.$IDnode.' not found.');
			EDDIT::$smarty->display('file:error-node-not-found.tpl');
			return;
		}
		// $screen = EDDIT::requestVar('screen','dashboard');
		// EDDIT::$smarty->assign('screen',$screen);
		EDDIT::$smarty->display('file:eddit.tpl');
	}
	public function renderScreen($screen = '')
	{
		if ($screen === '')
		{
			$screen = EDDIT::requestVar('screen','dashboard');
		}
		$fileName = sprintf('screen_%s.tpl',$screen);
		if (EDDIT::$smarty->templateExists('file:[templates]'.$fileName))   // bereits abgespeicherter seiteninhalt
		{
			EDDIT::$smarty->display('file:[templates]'.$fileName);
		}
		else
		{
			EDDIT::error(__METHOD__.' template '.$fileName.' not found');
			EDDIT::$smarty->display('file:[templates]screen_notfound.tpl');
		}
	}






	public function login($realm = 'public')
	{
		EDDIT::$smarty->assign('msg','welcome');
		if ( $login = EDDIT::requestVar('login') )
		{
			$passWd = EDDIT::requestVar('pass');
			/*
				pruefe ob logindaten korrekt sind, dann hole alle infos aus der DB und speichere in session
			*/
			// $where = new WhereClause('and');
			// $where->add('email = %s', $login);
			// $where->add('password = %s', $passWd);
			// $_user = EDDIT::dataSearch('persons',$where);
			$_user = DB::queryFirstRow('SELECT * FROM users WHERE username=%s AND password=%s', $login, $passWd);
			// $_user = $passWd == 'lskdjf' ? array('IDpersons'=>0,'groupID'=>0,'roleID'=>0) : false;

			if ( is_array($_user) )
			{
				unset($_user['password']);
				$_SESSION['LOGIN'][$realm] = $_user;
				$code = $login.md5($realm.$login);
				syslog(LOG_INFO,'erfolgreich angemeldet: realm '.$realm.' '.$login.' from IP "'.$_SERVER['REMOTE_ADDR'].'"');
				// syslog(LOG_INFO, '$_COOKIE["AUTO_LOGIN_'.$realm.'"]="'.base64_encode($code).'"');
				// syslog(LOG_INFO, '$code="'.$code.'" ---- $login="'.$login.'" ---- realm="'.$realm.'" ---- md5($realm.$login)="'.md5($realm.$login).'"');
				// setcookie('AUTO_LOGIN_'.$realm,base64_encode($login.md5($realm.$login.$_SERVER['REMOTE_ADDR'])), time()+60*60*24*30,'/');
				setcookie('AUTO_LOGIN_'.$realm,base64_encode($code), time()+60*60*24*30,'/');
				return;
			}
			else
			{
				EDDIT::$smarty->assign('msg','failed');
				syslog(LOG_INFO,'Login failed: realm '.$realm.' '.$login.'/'.$passWd.' from IP '.$_SERVER['REMOTE_ADDR'].'"');
			}
		}
		elseif (EDDIT::requestVar('logout') )
		{
			if (isset($_SESSION['LOGIN'][$realm]))
			{
				EDDIT::$smarty->assign('msg','logout');
				syslog(LOG_INFO,'logout realm '.$realm.' '.$_SESSION['LOGIN'][$realm]['email']);
				unset($_SESSION['LOGIN'][$realm]);
				setcookie('AUTO_LOGIN_'.$realm,'', 0,'/');
			}
		}
		elseif // schon logindaten in der session??
		(
			isset($_SESSION['LOGIN'][$realm]) &&
			is_array($_SESSION['LOGIN'][$realm])
		)
		{
			return;
		}
		elseif // auto-login mit dauer-cookie??
		(
			array_key_exists('AUTO_LOGIN_'.$realm,$_COOKIE) &&
			$code = base64_decode($_COOKIE['AUTO_LOGIN_'.$realm])
		)
		{
			$login = substr($code,0,-32);
			$security_hash = substr($code,-32);
			// syslog(LOG_INFO, '$_COOKIE["AUTO_LOGIN_'.$realm.'"]="'.$_COOKIE['AUTO_LOGIN_'.$realm].'"');
			// syslog(LOG_INFO, '$code="'.$code.'" ---- $login="'.$login.'" ---- $security_hash="'.$security_hash.'" ---- realm="'.$realm.'" ---- md5($realm.$login)="'.md5($realm.$login).'"');
			// if ($security_hash == md5($realm.$login.$_SERVER['REMOTE_ADDR']))	// checke security-hash aus IP und Kundennummer
			if ($security_hash == md5($realm.$login))	// checke security-hash
			{
				$_user = DB::queryFirstRow('SELECT * FROM users WHERE username=%s', $login);
				// $_user = $login == 'lskdjf' ? array('IDpersons'=>0,'groupID'=>0,'roleID'=>0) : false;

				if ( is_array($_user) )
				{
					unset($_user['password']);
					$_SESSION['LOGIN'][$realm] = $_user;

					syslog(LOG_INFO,'erfolgreich mit Cookie angemeldet: realm '.$realm.' '.$login.' from IP "'.$_SERVER['REMOTE_ADDR'].'"');
					// setcookie('AUTO_LOGIN_'.$realm,base64_encode($login.md5($realm.$login.$_SERVER['REMOTE_ADDR'])), time()+60*60*24*30,'/');
					setcookie('AUTO_LOGIN_'.$realm,base64_encode($login.md5($realm.$login)), time()+60*60*24*30,'/');
					return;
				}
				else
				{
					syslog(LOG_INFO,'Login via Cookie failed: realm '.$realm.' '.$login.' from IP '.$_SERVER['REMOTE_ADDR'].'"');
				}
			}
			else
			{
				syslog(LOG_INFO,'Illegal Login via Cookie for: realm '.$realm.' '.$login.' from IP '.$_SERVER['REMOTE_ADDR'].'"');
			}
		}

		header("HTTP/1.0 401 Unauthorized");
		header("X-Redirector: ".basename(__METHOD__).' #'.__LINE__);
		EDDIT::$smarty->display('file:login.tpl');
		// session_write_close();
		require_once('eddit/footer.php');
		exit();
	}
	public function user($realm = 'public')
	{
		if (isset($_SESSION['LOGIN'][$realm]) && is_array($_SESSION['LOGIN'][$realm]))
		{
			return $_SESSION['LOGIN'][$realm];
		}
	}
	/**
	 * Return the nodeID of the currently logged in in user
	 * @param  string $realm realm of the login (public/eddit)
	 * @return int
	 */
	public function userID($realm = 'public')
	{
		if (isset($_SESSION['LOGIN'][$realm]) && is_array($_SESSION['LOGIN'][$realm]))
		{
			return (int)$_SESSION['LOGIN'][$realm]['IDpersons'];
		}
		else
		{
			return 0;
		}
	}
	/**
	 * Return the nodeID (groupID) of the currently logged in in user
	 * @param  string $realm realm of the login (public/eddit)
	 * @return int
	 */
	public function userGroup($realm = 'public')
	{
		if (isset($_SESSION['LOGIN'][$realm]) && is_array($_SESSION['LOGIN'][$realm]))
		{
			return (int)$_SESSION['LOGIN'][$realm]['nodeID'];
		}
		else
		{
			return 0;
		}
	}
	/**
	 * Return the active languages of the currently logged in in user
	 * @param  string $realm realm of the login (public/eddit)
	 * @return int
	 */
	public function userLanguages($realm = 'public')
	{
		if (isset($_SESSION['LOGIN'][$realm]['languages']))
		{
			return current(EDDIT::decodeJSON($_SESSION['LOGIN'][$realm]['languages']));
		}
		else
		{
			return null;
		}
	}
	/**
	 * Return the merged groupIDs of the currently logged in in user
	 * @param  string $realm realm of the login (public/eddit)
	 * @return array
	 */
	public function userGroups($realm = 'public')
	{
		if (isset($_SESSION['LOGIN'][$realm]) && is_array($_SESSION['LOGIN'][$realm]))
		{
			$user = $_SESSION['LOGIN'][$realm];
			$groupPrimary = $user['nodeID'];
			if (isset($user['groupIDs']) && is_array($user['groupIDs']))
			{
				return $groupPrimary + $user['groupIDs'];
			}
			else
			{
				return $groupPrimary;
			}
		}
	}
	/**
	 * Archives a template file including header and clear the compiled data
	 * @param  int $IDnode  ID of the node
	 * @param  string $IDlanguage  languageID
	 * @return bool
	 */
	public function clearTemplate($IDnode,$IDlanguage)
	{
		$filePath = EDDIT::$smarty->template_dir['pages'];
		$done = true;
		$headerFileName = sprintf('headers_%d_%s.tpl',$IDnode, $IDlanguage);
		if (is_readable($filePath.$headerFileName))
		{
			$fileNameNew = str_replace('.tpl','_'.date('Y-m-d_H-i',time()).'.html',$headerFileName);
			$done = rename($filePath.$headerFileName, $filePath.$fileNameNew);
			// unlink($filePath.$headerFileName);
		}
		$fileName = sprintf('page_%d_%s.tpl',$IDnode, $IDlanguage);
		if (is_readable($filePath.$fileName))
		{
			$fileNameNew = str_replace('.tpl','_'.date('Y-m-d_H-i',time()).'.html',$fileName);
			$done = rename($filePath.$fileName, $filePath.$fileNameNew);
		}
		EDDIT::$smarty->compile_dir = $this->hostCompileDir;
		EDDIT::$smarty->clearCompiledTemplate(null, 'page|'.$IDnode);
		EDDIT::$smarty->compile_dir = $this->ceCompileDir;
		EDDIT::log('clearTemplate '.$filePath.$fileName);
		return $done ? EDDIT::i18n('success','ce') : EDDIT::i18n('failed','ce');
	}
}
?>
