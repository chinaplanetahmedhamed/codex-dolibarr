<?php
require_once __DIR__.'/NewCatalogSchedule.class.php';
/**
 * M102 batch coordinator. No WooSync connection or credentials live here.
 * The adapter must create product + remote link atomically, refuse SKU/native
 * collisions, skip linked IDs without updating them, and verify persistence.
 */
interface NewCatalogImportAdapter
{
    /** Throw unless bound to site 1/entity 1 and reviewed native dependencies. */
    public function assertTarget();
    /** Return items [{remote_id:string}], next_cursor, complete. Read-only. */
    public function discover($cursor, $limit);
    /** Return new, linked, or conflict. Must not change any record. */
    public function classify($remoteId);
    /** Recheck under transaction/lock; return created or linked only after proof. */
    public function createIfMissing($remoteId);
}

/** Only these categories may cross the adapter/logging boundary. */
final class NewCatalogImportFailure extends RuntimeException
{
    public function __construct($reason)
    {
        parent::__construct(in_array($reason, array('TARGET_FAILED', 'SCHEMA_FAILED',
            'NATIVE_PRODUCT_COLLISION', 'REMOTE_READ_FAILED', 'PERSISTENCE_NOT_CONFIRMED',
            'DISCOVERY_INVALID', 'DISCOVERY_NO_PROGRESS', 'REMOTE_ID_INVALID',
            'CLASSIFICATION_INVALID', 'CATEGORY_MAPPING_MISSING'), true) ? $reason : 'BATCH_FAILED');
    }
}

final class NewCatalogImportRunner
{
    private $directory;

    public function __construct($directory)
    {
        if (!is_dir($directory) || is_link($directory) || !is_writable($directory)) {
            throw new RuntimeException('STATE_DIRECTORY_INVALID');
        }
        // Private state outside the web root, provisioned by the operator.
        if ((fileperms($directory) & 0077) !== 0) {
            throw new RuntimeException('STATE_DIRECTORY_NOT_PRIVATE');
        }
        $this->directory = rtrim($directory, '/');
    }

    public function run(NewCatalogImportAdapter $adapter, $limit = 10, $dryRun = true, $now = null, $daily = false)
    {
        $now = $now === null ? time() : (int) $now;
        if (!is_int($limit) || $limit < 1 || $limit > 50) {
            throw new InvalidArgumentException('BATCH_LIMIT_INVALID');
        }
        foreach (array('lock', 'state.json') as $name) {
            if (is_link($this->directory.'/'.$name)) {
                throw new RuntimeException('STATE_SYMLINK_REFUSED');
            }
        }
        $lock = fopen($this->directory.'/lock', 'c');
        if (!$lock) throw new RuntimeException('LOCK_OPEN_FAILED');
        chmod($this->directory.'/lock', 0600);
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return array('status'=>'busy', 'created'=>0);
        }
        try {
            $adapter->assertTarget();
            $state = $this->loadState();
            if ($daily && !$dryRun && !NewCatalogSchedule::due($state['last_scan_success'], $now)) {
                return array('status'=>'idle', 'created'=>0);
            }
            if (!$dryRun && $state['retry_after'] > $now) {
                return array('status'=>'backoff', 'created'=>0, 'retry_after'=>$state['retry_after'], 'reason'=>$state['last_error']);
            }
            $counts = array('created'=>0, 'linked'=>0, 'new'=>0);
            try {
                $page = $adapter->discover($state['cursor'], $limit);
                $this->validatePage($page, $limit, $state['cursor']);
                foreach ($page['items'] as $item) {
                    $id = $item['remote_id'];
                    $classification = $adapter->classify($id);
                    if ($classification === 'linked') {
                        $counts['linked']++;
                    } elseif ($classification === 'conflict') {
                        throw new NewCatalogImportFailure('NATIVE_PRODUCT_COLLISION');
                    } elseif ($classification === 'new') {
                        $counts['new']++;
                        if (!$dryRun) {
                            $result = $adapter->createIfMissing($id);
                            if (!in_array($result, array('created', 'linked'), true)) {
                                throw new NewCatalogImportFailure('PERSISTENCE_NOT_CONFIRMED');
                            }
                            $counts[$result]++;
                        }
                    } else {
                        throw new NewCatalogImportFailure('CLASSIFICATION_INVALID');
                    }
                }
                if (!$dryRun) {
                    $state['cursor'] = $page['complete'] ? null : $page['next_cursor'];
                    $state['failures'] = 0;
                    $state['retry_after'] = 0;
                    $state['last_error'] = null;
                    $state['last_batch_success'] = $now;
                    if ($page['complete']) $state['last_scan_success'] = $now;
                    $this->saveState($state);
                }
                return array_merge($counts, array('status'=>$dryRun ? 'dry_run' : ($page['complete'] ? 'complete' : 'pending')));
            } catch (Throwable $e) {
                // Replay this page: already committed remote links must skip.
                // Never checkpoint past a failed item or log raw vendor errors.
                $reason = $e instanceof NewCatalogImportFailure ? $e->getMessage() : 'BATCH_FAILED';
                if (!$dryRun) {
                    $state['failures'] = min(10, $state['failures'] + 1);
                    $state['retry_after'] = $now + min(3600, 60 * (2 ** ($state['failures'] - 1)));
                    $state['last_error'] = $reason;
                    $state['last_failure'] = $now;
                    $this->saveState($state);
                }
                return array_merge($counts, array('status'=>'error',
                    'reason'=>$reason));
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function validatePage($page, $limit, $cursor)
    {
        if (!is_array($page) || !isset($page['items']) || !is_array($page['items'])
            || count($page['items']) > $limit || !array_key_exists('next_cursor', $page)
            || !isset($page['complete']) || !is_bool($page['complete'])) {
            throw new NewCatalogImportFailure('DISCOVERY_INVALID');
        }
        if (!$page['complete'] && ($page['next_cursor'] === $cursor || $page['next_cursor'] === null)) {
            throw new NewCatalogImportFailure('DISCOVERY_NO_PROGRESS');
        }
        $seen = array();
        foreach ($page['items'] as $item) {
            if (!is_array($item) || !isset($item['remote_id']) || !is_string($item['remote_id'])
                || !preg_match('/^[1-9][0-9]*(\|[1-9][0-9]*)?$/D', $item['remote_id'])
                || isset($seen[$item['remote_id']])) {
                throw new NewCatalogImportFailure('REMOTE_ID_INVALID');
            }
            $seen[$item['remote_id']] = true;
        }
    }

    private function loadState()
    {
        $default = array('version'=>1, 'site'=>1, 'entity'=>1, 'cursor'=>null,
            'failures'=>0, 'retry_after'=>0, 'last_error'=>null, 'last_failure'=>null,
            'last_batch_success'=>null, 'last_scan_success'=>null);
        $path = $this->directory.'/state.json';
        if (!file_exists($path)) return $default;
        $raw = file_get_contents($path);
        if ($raw === false || strlen($raw) > 65536) throw new RuntimeException('STATE_INVALID');
        $state = json_decode($raw, true);
        if (!is_array($state) || array_diff_key($default, $state)
            || $state['version'] !== 1 || $state['site'] !== 1 || $state['entity'] !== 1
            || !is_int($state['failures']) || $state['failures'] < 0
            || !is_int($state['retry_after'])) throw new RuntimeException('STATE_INVALID');
        return $state;
    }

    private function saveState($state)
    {
        $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        $temporary = tempnam($this->directory, '.checkpoint-');
        if (!$temporary) throw new RuntimeException('CHECKPOINT_CREATE_FAILED');
        try {
            chmod($temporary, 0600);
            if (file_put_contents($temporary, $json) !== strlen($json)
                || !rename($temporary, $this->directory.'/state.json')) {
                throw new RuntimeException('CHECKPOINT_WRITE_FAILED');
            }
        } finally {
            if (file_exists($temporary)) unlink($temporary);
        }
    }
}
