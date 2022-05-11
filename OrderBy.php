<?php
class OrderBy {
    public $orders = array();

    function __toString()
    {
        if (sizeof($this->orders) == 0) return '\'none\'';
        $order = array();
        foreach ($this->orders AS $o)
        {
            if (isset($o['raw']))
                $order[] = $o['raw'].' '.$o['direction'];
            else
                $order[] = '`'.$o['field'].'` '.$o['direction'];
        }
        return implode(', ',$order);
    }
    function add($field,$direction)
    {
        $this->orders[] = array('field' => $field, 'direction' => $direction);
    }
    function addRaw($raw,$direction)
    {
        $this->orders[] = array('raw' => $raw, 'direction' => $direction);
    }
}
?>
