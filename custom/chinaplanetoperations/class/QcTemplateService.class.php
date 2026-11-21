<?php

/** Hash-pinned, administrator-approved QC checkpoint templates. */
class QcTemplateService
{
    private $templates = array();

    public static function configured()
    {
        return !empty($GLOBALS['conf']->global->CHINAPLANETOPERATIONS_QC_TEMPLATE_PATH) && !empty($GLOBALS['conf']->global->CHINAPLANETOPERATIONS_QC_TEMPLATE_SHA256);
    }

    public function __construct($path = null, $expectedHash = null)
    {
        if ($path === null) $path = self::configured() ? (string)$GLOBALS['conf']->global->CHINAPLANETOPERATIONS_QC_TEMPLATE_PATH : '';
        if ($expectedHash === null) $expectedHash = self::configured() ? strtolower((string)$GLOBALS['conf']->global->CHINAPLANETOPERATIONS_QC_TEMPLATE_SHA256) : '';
        if ($path === '' || $path[0] !== '/' || !is_file($path) || is_link($path) || !preg_match('/^[a-f0-9]{64}$/', (string)$expectedHash) || !hash_equals((string)$expectedHash, (string)hash_file('sha256', $path))) throw new RuntimeException('Validated QC template dataset is not configured.');
        $data=json_decode((string)file_get_contents($path),true);if(!is_array($data)||($data['schema_version']??null)!==1||!isset($data['templates'])||!is_array($data['templates']))throw new RuntimeException('QC template dataset is invalid.');
        foreach($data['templates']as$template){$id=strtolower(trim((string)($template['id']??'')));if(!preg_match('/^[a-z0-9_-]{1,64}$/',$id)||trim((string)($template['label']??''))===''||!isset($template['checkpoints'])||!is_array($template['checkpoints']))throw new RuntimeException('QC template contract is invalid.');$this->templates[$id]=$template;}
    }

    public function listTemplates(){return array_values(array_map(function($template){return array('id'=>(string)$template['id'],'label'=>(string)$template['label'],'checkpoint_count'=>count($template['checkpoints']));},$this->templates));}
    public function template($id){$id=strtolower(trim((string)$id));if(!isset($this->templates[$id]))throw new RuntimeException('Approved QC template was not found.');return$this->templates[$id];}
}
