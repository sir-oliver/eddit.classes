<?php
class eddit_object
{
    public $id = 0;
    public $languageID = '';
    public $parentID = 0;
    public $sequenceID = 0;
    public $nodeID = 0;
    public $templateName = '';
    public $templateID = '';
    public $attributes = array();
    public $validChildren = array();
    protected $objectAttributes = array();
    protected $templateAttributes = array();

    public function __construct($id, $data = null)
    {
        if (CE_DEBUG) EDDIT::log('object #'.$id.' __construct');
        if (!is_array($data))
        {
            $data = $this->loadData($id);   // frisch aus der datenbank holen!
        }
        if (sizeof($data) === 0)
        {
            EDDIT::error(__METHOD__.' Object '.$id.' not found.');
            return false;
        }
        $this->id = (int)$data['IDobjects'];
        $this->parentID = (int)$data['parentID'];
        $this->sequenceID = (int)$data['sequenceID'];
        $this->nodeID = (int)$data['nodeID'];
        $this->templateID = $data['templateID'];
        $this->templateName = EDDIT::i18n($data['templateID'], 'objects');
        $this->languageID = EDDIT::$languageID;

        foreach($data AS $k => $v)
        {
            if (strpos($k,'attr_') === false)
            {
                unset($data[$k]);
            }
        }
        $this->objectAttributes = $data;

        $template = EDDIT::arrayKey($this->templateID, EDDIT::data('templates'));
        if (is_array($template))
        {
            $this->templateAttributes = array_flip($template['attributes']);
            $this->templateAttributes = array_fill_keys($template['attributes'], '');
        }
        $this->attributes = $this->getAttributes();
        $this->validChildren = $template['validChildren'];
    }
    private function getAttributes()
    {
        // $defaultAttributes = $this->objectAttributes['attr_'.EDDIT::$config['defaultLG']];
        $defaultAttributes = $this->objectAttributes['attr_'.EDDIT::config('defaultLG')];
        $objectAttributes = $this->objectAttributes['attr_'.$this->languageID];
        // $this->allAttributes = array(
        //     EDDIT::$config['defaultLG'] => $defaultAttributes,
        //     $this->languageID => $objectAttributes
        // );
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
        // syslog(LOG_INFO,__METHOD__.' $lg='.var_export($this->objectAttributes['attr_'.$lg],1));
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
            return $default;
        }
        if (!isset($this->objectAttributes['attr_'.$lg][$name]))
        {
            return $default;
        }
        return $this->objectAttributes['attr_'.$lg][$name];
    }
    public function display()
    {
        if (!EDDIT::$smarty->templateExists('file:objects/'.$this->templateID.'.tpl'))
        {
            EDDIT::error(__METHOD__.' template "'.$this->templateID.'" NOT FOUND');
            echo '<!-- '.__METHOD__.' template "'.$this->templateID.'" NOT FOUND -->';
            // if (CE_DEBUG) echo __METHOD__.' template "'.$this->templateID.'" NOT FOUND';
            return;
        }
        EDDIT::$smarty->assign('object',$this);
        return EDDIT::$smarty->fetch('file:objects/'.$this->templateID.'.tpl');
    }
    public function headers()
    {
        if (EDDIT::$smarty->templateExists('file:headers/'.$this->templateID.'.tpl'))
        {
            EDDIT::$smarty->assign('object',$this);
            return EDDIT::$smarty->fetch('file:headers/'.$this->templateID.'.tpl');
        }
    }
    public function displayClose()
    {
        if (empty($this->validChildren))    // dieses objekt kann keine "kinder" haben, daher brauchen wir auch nix zumachen
        {
            return;
        }
        if (!EDDIT::$smarty->templateExists('file:objects/'.$this->templateID.'End.tpl'))
        {
            EDDIT::error(__METHOD__.' template "'.$this->templateID.'End" NOT FOUND'.print_r($this->validChildren,1));
            echo '<!-- '.__METHOD__.' template "'.$this->templateID.'End" NOT FOUND -->';
            // if (CE_DEBUG) echo __METHOD__.' template "'.$this->templateID.'End" NOT FOUND';
            return;
        }
        EDDIT::$smarty->assign('object',$this);
        return EDDIT::$smarty->fetch('file:objects/'.$this->templateID.'End.tpl');
    }


    public function export()
    {
        $template = EDDIT::arrayKey($this->templateID, EDDIT::data('templates'));
        $exportAttributes = EDDIT::arrayKey('export', $template);
        if (empty($exportAttributes)) return;

        printf('<object id="%s" type="%s" title="%s">'."\n", $this->id, $this->templateID, $this->templateName);
        foreach ($exportAttributes as $attribute)
        {
            $content = $this->attr($attribute);
            if (empty($content)) continue;
            // printf('<%s translate="yes">'."\n",$attribute);
            printf('<%s>'."\n",$attribute);
            // print( str_replace( array('<br>',' & '),array('<br />',' &amp; '), $content) )."\n";
            printf('<![CDATA[%s]]>'."\n", $content);
            printf('</%s>'."\n",$attribute);
        }
        print('</object>'."\n\n");
    }





    public function form()
    {
        EDDIT::$smarty->assign('object',$this);
        EDDIT::$smarty->display('file:formObjects.tpl');
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
            EDDIT::$smarty->assign('type','objects');
            EDDIT::$smarty->assign('meta',EDDIT::meta($name,$this->templateID,'objects'));
            EDDIT::$smarty->display('file:formField.tpl');
        }

    }
    public function defaultValues()
    {
        // $defaultAttributes = $this->objectAttributes['attr_'.EDDIT::$config['defaultLG']];
        $defaultAttributes = $this->objectAttributes['attr_'.EDDIT::config('defaultLG')];
        $objectAttributes = $this->objectAttributes['attr_'.$this->languageID];
        $defaultValues = [];
        foreach ($this->attributes AS $name => $value)
        {
            $_default = EDDIT::meta($name,$this->templateID,'objects')['default'];
            if ($_default !== '') $defaultValues[$name] = $_default;
        }
        return $defaultValues;
    }
    // public function meta($fieldID)
    // {
    //     if ($_special = EDDIT::arrayKey('objects_'.$this->templateID.'_'.$fieldID,$his->meta,array()))
    //     {
    //         return $_special;
    //     }
    //     elseif ($_general = EDDIT::arrayKey('objects_'.$fieldID,$his->meta,array()))
    //     {
    //         return $_general;
    //     }
    // }
    public function store($data)
    {
        echo $this->templateID.' STORE<hr>';
    }
    private function loadData($IDobject)
    {
        $data = DB::queryFirstRow('SELECT * from objects WHERE IDobjects = %d',$IDobject);
        $data = EDDIT::decodeJSON($data);
        return $data;
    }
    public function parentAttr($name = null, $default = null)
    {
        if ($this->parentID == 0)
        {
            return false;
        }
        $parentObject = EDDIT::objects($this->parentID);
        // var_dump($parentObject);
        return $parentObject->attr($name,$default);
    }
    // public function access($action)
    // {
    //     if (CE_DEBUG) EDDIT::log(__METHOD__ . ' objectID=' . $this->id . ' $action=' . $action);
    //     $group = EDDIT::userGroup('clickedit');
    //
    //     if (is_array($this->access) && is_array($this->access[$group]) && in_array($action, $this->access[$group]))
    //     {
    //         return true;
    //     }
    //     elseif ($this->parentID != 0)
    //     {
    //         $this->parentNode = EDDIT::nodes($this->parentID);
    //         return $this->parentNode->access($action);
    //     }
    //     return false;
    // }
}
?>
