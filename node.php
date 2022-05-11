<?php
class eddit_node
{
    public $id = 0;
    public $languageID = 'de';
    public $parentID = 0;
    public $parentNode = null;
    public $sequenceID = 0;
    public $tableID = '';
    public $layoutID = '';
    public $templateName = '';
    public $attributes = array();
    protected $access = array();
    protected $modified = '';
    protected $defaultAttributes = array();
    protected $objectAttributes = array();
    protected $templateAttributes = array();

    public function __construct($id, $data = null)
    {
        if (CE_DEBUG) EDDIT::log('node #'.$id.' __construct');
        if (!is_array($data))
        {
            $data = $this->loadData($id);   // frisch aus der datenbank holen!
        }
        if (sizeof($data) === 0)
        {
            EDDIT::error(__METHOD__.' Node '.$id.' not found.');
            return false;
        }
        $this->id = (int)$data['IDnodes'];
        $this->parentID = (int)$data['parentID'];
        $this->sequenceID = (int)$data['sequenceID'];
        $this->tableID = $data['tableID'];
        $this->access = $data['access'];
        $this->modified = $data['modified'];
        $this->templateName = EDDIT::i18n('node', 'nodes');

        $this->languageID = EDDIT::$languageID;

        foreach($data AS $k => $v)
        {
            if (strpos($k,'attr_') === false)
            {
                unset($data[$k]);
            }
        }
        $this->objectAttributes = $data;

        $template = EDDIT::arrayKey('node', EDDIT::data('templates'));
        if (is_array($template))
        {
            $this->templateAttributes = array_flip($template['attributes']);
            $this->templateAttributes = array_fill_keys($template['attributes'], '');
        }
        $this->attributes = $this->getAttributes();
        $this->layoutID = EDDIT::arrayKey('layout', $this->attributes, 'default');
        if (empty($this->layoutID))
        {
            $this->layoutID = 'default';
        }
    }
    private function getAttributes()
    {
        // $defaultAttributes = $this->objectAttributes['attr_'.EDDIT::$config['defaultLG']];
        $defaultAttributes = $this->objectAttributes['attr_'.EDDIT::config('defaultLG')];
        $objectAttributes = (isset($this->objectAttributes['attr_'.$this->languageID])) ? $this->objectAttributes['attr_'.$this->languageID] : null;
        $mergedAttributes = array();
        if (is_array($defaultAttributes) && is_array($objectAttributes))
        {
            $mergedAttributes = array_merge($defaultAttributes,$objectAttributes);
        }
        elseif (is_array($objectAttributes))
        {
            $mergedAttributes = $objectAttributes;
        }
        elseif (is_array($defaultAttributes))
        {
            $mergedAttributes = $defaultAttributes;
        }
        // EDDIT::logger(__METHOD__.' $defaultAttributes', $defaultAttributes);
        // EDDIT::logger(__METHOD__.' $objectAttributes', $objectAttributes);
        // EDDIT::logger(__METHOD__.' $mergedAttributes', $mergedAttributes);
        if (is_array($mergedAttributes))
        {
            $mergedAttributes = array_merge($this->templateAttributes,$mergedAttributes);
        }
        else
        {
            $mergedAttributes = $this->templateAttributes;
        }
        // if ($this->tableID != 'objects') $mergedAttributes['online']=1;
        return $mergedAttributes;
    }
    public function attr($name = null, $default = null)
    {
        // syslog(LOG_INFO,__METHOD__.' $name='.var_export($name,1).' $default='.var_export($default,1));
        if ($name === null)
            return $this->attributes;
        else
        {
            if (isset($this->attributes[$name]) && (!empty($this->attributes[$name]) || $this->attributes[$name] === 0 )) return $this->attributes[$name];
            else return $default;
        }
    }
    public function has_attr($lg = null)
    {
        // syslog(LOG_INFO,__METHOD__.' $lg='.var_export($lg,1));
        if ($lg === null)
        {
            // $lg = EDDIT::$config['defaultLG'];
            $lg = EDDIT::config('defaultLG');
        }
        return (isset($this->objectAttributes['attr_'.$lg]) && sizeof($this->objectAttributes['attr_'.$lg])>0);
    }
    public function attr_lg($lg = null, $name = null, $default = null)
    {
        // syslog(LOG_INFO,__METHOD__.' $lg='.var_export($lg,1).' $name='.var_export($name,1).' $default='.var_export($default,1));
        if ($lg === null)
        {
            // $lg = EDDIT::$config['defaultLG'];
            $lg = EDDIT::config('defaultLG');
        }

        if (!isset($this->objectAttributes['attr_'.$lg]))
        {
            // syslog(LOG_INFO,__METHOD__.' !isset($this->objectAttributes[attr_'.$lg.'])');
            return $default;
        }
        if (!isset($this->objectAttributes['attr_'.$lg][$name]))
        {
            // syslog(LOG_INFO,__METHOD__.' !isset($this->objectAttributes[attr_'.$lg.']['.$name.'])');
            return $default;
        }
        // syslog(LOG_INFO,__METHOD__.' return($this->objectAttributes[attr_'.$lg.']['.$name.'])');
        return $this->objectAttributes['attr_'.$lg][$name];
    }
    public function getObjectAttributes()
    {
        return $this->objectAttributes;
    }
    // private function getAttributes()
    // {
    //     $objectAttributes = $this->objectAttributes['attr_de'];  // multisprache fehlt noch!
    //     if (is_array($objectAttributes))
    //     {
    //         $objectAttributes = array_merge($this->templateAttributes,$objectAttributes);
    //     }
    //     else
    //     {
    //         $objectAttributes = $this->templateAttributes;
    //     }
    //     // if (!isset($objectAttributes['online'])) $objectAttributes['online']=1;
    //     if ($this->tableID != 'objects') $objectAttributes['online']=1;
    //     return $objectAttributes;
    // }
    public function display()
    {
        if ($this->tableID != 'objects')    // nur knoten vom typ "objects" werden dargestellt!
        {
            syslog(LOG_DEBUG,__METHOD__.' -> NO CONTENT TO DISPLAY -> '.$this->tableID);
            header("HTTP/1.0 204 No Content");
            echo 'NO CONTENT TO DISPLAY';
            return;
        }
        echo $this->fetch();
    }
    public function export()
    {
        $template = EDDIT::arrayKey('node', EDDIT::data('templates'));
        $exportAttributes = EDDIT::arrayKey('export', $template);
        if (empty($exportAttributes)) $exportAttributes = ['title','navname'];

        printf('<object id="%s" type="node" tableID="%s" title="%s">'."\n", $this->id, $this->tableID, $this->templateName);
        foreach ($exportAttributes as $attribute)
        {
            $content = $this->attr($attribute);
            if (empty($content)) continue;
            // printf('<%s translate="yes">'."\n",$attribute);
            printf('<%s>'."\n",$attribute);
            printf('<![CDATA[%s]]>'."\n", $content);
            // print( str_replace( array('<br>',' & '),array('<br />',' &amp; '), $content) )."\n";
            printf('</%s>'."\n",$attribute);
        }
        print('<url>'."\n");
        // print('http://'.$_SERVER['HTTP_HOST'].EDDIT::url(['pg'=>$this->id])."\n");
        print(FQDN.EDDIT::url(['pg'=>$this->id])."\n");
        print('</url>'."\n");
        print('</object>'."\n\n");
    }
    public function fetchExtended()
    {
        if (!EDDIT::$smarty->templateExists('file:layout_'.$this->layoutID.'.tpl'))
        {
            EDDIT::error(__METHOD__.' template "layout_'.$this->layoutID.'" NOT FOUND');
            echo '<!-- '.__METHOD__.' template "layout_'.$this->layoutID.'" NOT FOUND -->';
            return;
        }
        EDDIT::$smarty->assign('node',$this);
        $template = sprintf('{extends file="layout_%s.tpl"}',$this->layoutID);
        return $template.EDDIT::$smarty->fetch('file:[pages]page_'.$this->id.'_'.$this->languageID.'.tpl');
    }
    public function fetch()
    {
        $layout_template = sprintf('file:layout_%s.tpl',$this->layoutID);
        if (!EDDIT::$smarty->templateExists($layout_template))
        {
            EDDIT::error(__METHOD__.' template "'.$layout_template.'" NOT FOUND');
            echo '<!-- '.__METHOD__.' template "'.$layout_template.'" NOT FOUND -->';
            return;
        }
        EDDIT::$smarty->assign('node',$this);
        return EDDIT::$smarty->fetch($layout_template);
    }
    public function form()
    {
        EDDIT::$smarty->assign('node',$this);
        EDDIT::$smarty->display('file:formNodes.tpl');
    }
    public function formfields()
    {
        // $defaultAttributes = $this->objectAttributes['attr_'.EDDIT::$config['defaultLG']];
        $defaultAttributes = $this->objectAttributes['attr_'.EDDIT::config('defaultLG')];
        $objectAttributes = $this->objectAttributes['attr_'.$this->languageID];
        // echo '<pre style="font-size: 0.5em">';
        // print_r($defaultAttributes);
        // print_r($objectAttributes);
        // echo '</pre>';
        foreach ($this->attributes AS $name => $value)
        {
            EDDIT::$smarty->assign('name',$name);
            EDDIT::$smarty->assign('inherit',!isset($objectAttributes[$name])); // ist eine eigener wert gespeichert oder fallback auf defaultLG?
            EDDIT::$smarty->assign('inheritValue',isset($defaultAttributes[$name]) ? $defaultAttributes[$name] : null );
            EDDIT::$smarty->assign('value',$value);
            EDDIT::$smarty->assign('default',$value);
            EDDIT::$smarty->assign('type','nodes');
            EDDIT::$smarty->assign('meta',EDDIT::meta($name,$this->tableID,'nodes'));
            EDDIT::$smarty->display('file:formField.tpl');
        }
        $_inherit = (is_array($this->access)) ? count($this->access) == 0 : true;
        EDDIT::$smarty->assign('inherit',$_inherit); // berechtigung gespeichert, sont erben vom parent
        // EDDIT::$smarty->assign('inherit',count($this->access) == 0); // berechtigung gespeichert, sont erben vom parent
        EDDIT::$smarty->assign('inheritValue',$this->accessRights());
        EDDIT::$smarty->assign('value',$this->access);
        EDDIT::$smarty->assign('meta',EDDIT::meta('access',$this->tableID,'nodes'));
        EDDIT::$smarty->display('file:formAccess.tpl');

    }
    public function store($data)
    {
        echo $this->templateID.' STORE<hr>';
    }
    public function getParents()
    {
        if ($this->parentID != 0)
        {
            $this->parentNode = EDDIT::nodes($this->parentID);
            $this->parentNode->getParents();
        }
        return $this;
    }
    private function loadData($IDnode)
    {
        $data = DB::queryFirstRow('SELECT * from nodes WHERE IDnodes = %d',$IDnode);
        $data = EDDIT::decodeJSON($data);
        return $data;
    }
    public function access($action)
    {
        if (CE_DEBUG) EDDIT::log(__METHOD__ . ' nodeID=' . $this->id . ' $action=' . $action);
        // syslog(LOG_INFO, __METHOD__ . ' nodeID=' . $this->id . ' $action=' . $action);
        $group = EDDIT::userGroup('clickedit');

        // if (is_array($this->access) && is_array($this->access[$group]) && in_array($action, $this->access[$group]))
        // if (isset($this->access[$group]) && is_array($this->access[$group]) && in_array($action, $this->access[$group]))
        // {
        //     return true;
        // }
        if (isset($this->access[$group]) && is_array($this->access[$group]))    // wenn die gruppe in den berechtigungen auftaucht, schauen ob action erlaubt ist
        {
            return in_array($action, $this->access[$group]);
        }
        elseif ($this->parentID != 0)                                           // sonst rekursiv die naechst hoehere seite befragen
        {
            $this->parentNode = EDDIT::nodes($this->parentID);
            return $this->parentNode->access($action);
        }
        return false;
    }
    public function accessRights()
    {
        if (CE_DEBUG) EDDIT::log(__METHOD__ . ' nodeID=' . $this->id);

        // if (sizeof($this->access) > 0)
        if (is_array($this->access) && sizeof($this->access) > 0)
        {
            return $this->access;
        }
        elseif ($this->parentID != 0)
        {
            $this->parentNode = EDDIT::nodes($this->parentID);
            return $this->parentNode->accessRights();
        }
        return array();
    }
}
?>
