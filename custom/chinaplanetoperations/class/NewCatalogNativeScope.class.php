<?php
/** Preserve native error severity/handlers without emitting raw vendor requests,
 * authentication headers, source product payloads or SQL values. Process-local
 * changes are always restored, including when the vendor throws. No DB settings.
 */
final class NewCatalogNativeLogHandler
{
    public $code;
    private $inner;
    public function __construct($inner) { $this->inner=$inner; $this->code=$inner->code; }
    public function setIdent($value) { return $this->inner->setIdent($value); }
    public function export($data, $suffix = '')
    {
        $data['message']='M102 native operation; diagnostic details redacted';
        return $this->inner->export($data, $suffix);
    }
}
final class NewCatalogNativeScope
{
    public static function call(callable $operation)
    {
        global $conf, $debugbar;
        $names=array('SYSLOG_LEVEL','MAIN_ENABLE_LOG_TO_HTML','MAIN_ENABLE_LOG_INLINE_HTML','ECOMMERCE_DISABLED_SEND_DATA_TO_API');
        $saved=array();
        foreach ($names as $name) $saved[$name]=array(property_exists($conf->global,$name),$conf->global->$name ?? null);
        $handlers=$conf->loghandlers ?? array();
        $oldDebugbar=$debugbar;
        $oldRequest=$_REQUEST;
        $level=(int)($conf->global->SYSLOG_LEVEL ?? LOG_WARNING);
        $conf->global->SYSLOG_LEVEL=min($level,LOG_WARNING);
        $conf->global->MAIN_ENABLE_LOG_TO_HTML=0;
        $conf->global->MAIN_ENABLE_LOG_INLINE_HTML=0;
        $conf->global->ECOMMERCE_DISABLED_SEND_DATA_TO_API=1;
        $conf->loghandlers=array_map(function ($handler) { return new NewCatalogNativeLogHandler($handler); },$handlers);
        $debugbar=null;
        unset($_REQUEST['logtohtml'],$_REQUEST['log']);
        $depth=ob_get_level();
        ob_start(function ($buffer) { return ''; });
        try { return $operation(); }
        finally {
            while (ob_get_level()>$depth) ob_end_clean();
            $conf->loghandlers=$handlers;
            foreach ($saved as $name=>$value) {
                if ($value[0]) $conf->global->$name=$value[1];
                else unset($conf->global->$name);
            }
            $debugbar=$oldDebugbar;
            $_REQUEST=$oldRequest;
        }
    }
}
