<?php
class eddit_data extends eddit_object
{
    public $tableID = '';
    public $id = '';

    public function __construct($tableID, $id)
    {
        if (CE_DEBUG) EDDIT::log('object #'.$id.' __construct');
        $this->tableID = $tableID;
        $this->id = $id;
        $this->languageID = EDDIT::$languageID;
        if (is_numeric($id) && $id == 0)   // neues objekt - hole felder aus meta tabelle
        {
            $tableFields = DB::queryFirstColumn('
                SELECT COLUMN_NAME AS "column"
                FROM `information_schema`.`COLUMNS`
                WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
                EDDIT::config('dbName'),
                $tableID
            );
            $_attributes = array_fill_keys($tableFields,'');
            // $this->objectAttributes[EDDIT::$config['defaultLG']] = $_attributes;
            $this->objectAttributes[EDDIT::config('defaultLG')] = $_attributes;
            $this->attributes = $_attributes;
            return;
        }
        $data = $this->loadData();
        if (sizeof($data) === 0)
        {
            EDDIT::error(__METHOD__.' Data-Object '.$tableID.' / '.$id.' not found.');
            return false;
        }
        $this->objectAttributes = $data;
        $this->attributes = $this->getAttributes();
        // $template = EDDIT::arrayKey($this->tableID, EDDIT::data('templates'));
        // if (is_array($template))
        // {
        //     $this->templateAttributes = array_flip($template['attributes']);
        //     $this->templateAttributes = array_fill_keys($template['attributes'], '');
        // }

    }
    private function getAttributes()
    {
        // $defaultAttributes = (isset($this->objectAttributes[EDDIT::$config['defaultLG']])) ? $this->objectAttributes[EDDIT::$config['defaultLG']] : null;
        $defaultAttributes = (isset($this->objectAttributes[EDDIT::config('defaultLG')])) ? $this->objectAttributes[EDDIT::config('defaultLG')] : null;
        $objectAttributes = (isset($this->objectAttributes[$this->languageID])) ? $this->objectAttributes[$this->languageID] : null;
        // $this->allAttributes = array(
        //     EDDIT::$config['defaultLG'] => $defaultAttributes,
        //     $this->languageID => $objectAttributes
        // );
        if (is_array($objectAttributes))
        {
            foreach ($objectAttributes as $key => $value)
            {
                // felder deren inhalt mit @@ beginnt (oder wenn das feld "online" == -1)
                // werden entfernt und spaeter aus der defaultsprache geholt!
                if( (is_string($value) && strpos($value,'@@') === 0) || ($key == 'online' && $value === '-1') )
                    unset($objectAttributes[$key]);
                elseif( (is_string($value) && $value === '0000-00-00'))
                    unset($objectAttributes[$key]);
                elseif( (is_numeric($value) && $value === -1))
                    unset($objectAttributes[$key]);
            }
        }
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
    public function display()
    {
        if (!EDDIT::$smarty->templateExists('file:objects/data_'.$this->tableID.'.tpl'))
        {
            EDDIT::error(__METHOD__.' template "objects/data_'.$this->tableID.'.tpl" NOT FOUND');
            if (CE_DEBUG) echo __METHOD__.' template "objects/data_'.$this->tableID.'.tpl" NOT FOUND';
            return;
        }
        EDDIT::$smarty->assign('data',$this);
        return EDDIT::$smarty->fetch('file:objects/data_'.$this->tableID.'.tpl');
    }
    public function headers()
    {
        return;
    }
    public function form()
    {
        EDDIT::$smarty->assign('data',$this);
        EDDIT::$smarty->display('file:formData.tpl');
    }
    public function formfields()
    {
        // $defaultAttributes = (isset($this->objectAttributes[EDDIT::$config['defaultLG']])) ? $this->objectAttributes[EDDIT::$config['defaultLG']] : null;
        $defaultAttributes = (isset($this->objectAttributes[EDDIT::config('defaultLG')])) ? $this->objectAttributes[EDDIT::config('defaultLG')] : null;
        $objectAttributes = (isset($this->objectAttributes[$this->languageID])) ? $this->objectAttributes[$this->languageID] : null;
        // echo '<pre style="font-size: 0.5em">';
        // print_r($defaultAttributes);
        // print_r($objectAttributes);
        // echo '</pre>';
        foreach ($this->attributes AS $name => $value)
        {
            EDDIT::$smarty->assign('name',$name);
            EDDIT::$smarty->assign('inherit',
                !isset($objectAttributes[$name])
                ||
                (
                    isset($objectAttributes[$name])
                    &&
                    (
                        (is_string($objectAttributes[$name]) && strpos($objectAttributes[$name],'@@') === 0)
                        ||
                        $objectAttributes[$name] === '0000-00-00'
                        ||
                        $objectAttributes[$name] === '-1'
                    )
                )
            ); // ist eine eigener wert gespeichert oder fallback auf defaultLG?
            EDDIT::$smarty->assign('inheritValue',isset($defaultAttributes[$name]) ? $defaultAttributes[$name] : null );
            EDDIT::$smarty->assign('value',$value);
            EDDIT::$smarty->assign('type',$this->tableID);
            EDDIT::$smarty->assign('meta',EDDIT::meta($name,'',$this->tableID));
            EDDIT::$smarty->display('file:formField.tpl');
        }

    }
    public function has_attr($lg = null)
    {
        if ($lg === null)
        {
            // $lg = EDDIT::$config['defaultLG'];
            $lg = EDDIT::config('defaultLG');
        }
        return (isset($this->objectAttributes[$lg]) && sizeof($this->objectAttributes[$lg])>0);
    }
    public function store($data)
    {
        echo $this->tableID.' STORE<hr>';
    }
    private function loadData()
    {
        $where = new WhereClause('and');
        $where->add('%b = %s', 'ID'.$this->tableID, $this->id);
        $data = DB::query('SELECT * from %b WHERE %l', $this->tableID, $where);
        if (sizeof($data) == 0) return array();
        $first = reset($data);
        if (isset($first['IDlang']))      // vorbereitung fuer mehrer sprachen
        {
            $data = DBHelper::reIndex($data, 'IDlang');
        }
        else
        {
            $data = array();
            $data[EDDIT::$languageID] = $first;
        }
        $data = EDDIT::decodeJSON($data);
        return $data;
    }
}
?>
