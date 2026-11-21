<?php
require_once __DIR__.'/NewCatalogNativeAdapter.class.php';
require_once __DIR__.'/NewCatalogSchedule.class.php';

/** Native Dolibarr cron entry: run(1) is read-only; run(0) requires an explicit
 * enabled flag plus an operator-provisioned private state directory. Register
 * at one-minute intervals: daily03 kickoff, bounded continuations, then idle.
 */
class NewCatalogImportCron
{
    public $output='';
    public $error='';
    private $db;
    public function __construct($db) { $this->db=$db; }

    public function run($dryRun=1)
    {
        global $conf,$user;
        if (!in_array($dryRun,array(0,1,'0','1'),true)) { $this->error='MODE_INVALID';return -1; }
        $dryRun=(bool)$dryRun;
        try {
            return NewCatalogNativeScope::call(function () use ($dryRun,$conf,$user) {
                if (!$dryRun && !getDolGlobalInt('CPO_CMS_NEW_PRODUCT_IMPORT_ENABLED')) {
                    $this->output='M102 disabled';return 0;
                }
                dol_include_once('/ecommerceng/lib/eCommerce.lib.php');
                dol_include_once('/ecommerceng/class/data/eCommerceSite.class.php');
                dol_include_once('/ecommerceng/class/data/eCommerceProduct.class.php');
                dol_include_once('/ecommerceng/class/data/eCommerceCategory.class.php');
                dol_include_once('/ecommerceng/class/data/eCommerceRemoteAccess.class.php');
                dol_include_once('/ecommerceng/class/data/woocommerce/eCommerceRemoteAccessWoocommerce.class.php');
                dol_include_once('/ecommerceng/class/business/eCommerceSynchro.class.php');
                require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
                $site=new eCommerceSite($this->db);
                if ($site->fetch(1)<=0) throw new NewCatalogImportFailure('TARGET_FAILED');
                $directory=DOL_DATA_ROOT.'/chinaplanetoperations/m102-site1';
                $runner=new NewCatalogImportRunner($directory);
                $adapter=new NewCatalogNativeAdapter($this->db,$site,$user);
                $result=$runner->run($adapter,10,$dryRun,null,!$dryRun);
                $this->output=json_encode($result,JSON_UNESCAPED_SLASHES);
                if (in_array($result['status'],array('error','backoff'),true)) { $this->error=$result['reason'];return -1; }
                return 0;
            });
        } catch(Throwable $e) {
            $safe=array('TARGET_FAILED','SCHEMA_FAILED','STATE_INVALID','STATE_DIRECTORY_INVALID','STATE_DIRECTORY_NOT_PRIVATE','STATE_SYMLINK_REFUSED');
            $this->error=in_array($e->getMessage(),$safe,true) ? $e->getMessage() : 'M102_RUN_FAILED';
            $this->output='M102 halted; '.$this->error;
            return -1;
        }
    }
}
