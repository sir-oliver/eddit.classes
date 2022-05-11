<?php
class eddit_security
{
    public function __construct()
    {
        if (CE_DEBUG) EDDIT::log(__METHOD__);

    }
    public function access($attributes, $realm, $action)
    {
        if (CE_DEBUG) EDDIT::log(__METHOD__);
        return true;
    }
}
?>
