<?php
require_once __DIR__.'/NewCatalogImportRunner.class.php';

/** Read-only WooCommerce discovery. Never uses WooSync's broken page_num path.
 * The injected reader must use the existing authenticated GET client only.
 * Page size is fixed, independently of execution batch size. Queue contains IDs,
 * never source payloads, credentials, customer fields or mutable product values.
 */
final class NewCatalogDiscovery
{
    const PAGE_SIZE = 20;
    private $readPage;

    public function __construct(callable $readPage)
    {
        $this->readPage = $readPage;
    }

    public function discover($cursor, $limit)
    {
        if (!is_int($limit) || $limit < 1 || $limit > 50) {
            throw new NewCatalogImportFailure('DISCOVERY_INVALID');
        }
        if ($cursor === null) $cursor = array('page'=>1, 'queue'=>array(), 'end'=>false, 'last_id'=>0);
        if (!is_array($cursor) || array_keys($cursor) !== array('page', 'queue', 'end', 'last_id')
            || !is_int($cursor['page']) || $cursor['page'] < 1
            || !is_array($cursor['queue']) || !is_bool($cursor['end'])
            || !isset($cursor['last_id']) || !is_int($cursor['last_id']) || $cursor['last_id'] < 0
            || count($cursor['queue']) > 2000) {
            throw new NewCatalogImportFailure('DISCOVERY_INVALID');
        }
        foreach ($cursor['queue'] as $id) $this->assertId($id);
        // At most one parent page per invocation. A parent with many variations
        // is split across invocations without requesting the first page again.
        if (!$cursor['queue'] && !$cursor['end']) {
            $rows = call_user_func($this->readPage, array('page'=>$cursor['page'],
                'per_page'=>self::PAGE_SIZE, 'orderby'=>'id', 'order'=>'asc', 'status'=>'publish'));
            if (!is_array($rows) || count($rows) > self::PAGE_SIZE) {
                throw new NewCatalogImportFailure('REMOTE_READ_FAILED');
            }
            $seen = array();
            $lastId = $cursor['last_id'];
            foreach ($rows as $row) {
                if (!is_array($row) || !isset($row['id'])
                    || !ctype_digit((string)$row['id']) || (int)$row['id'] <= $lastId
                    || !isset($row['status']) || $row['status'] !== 'publish') {
                    throw new NewCatalogImportFailure('DISCOVERY_INVALID');
                }
                $lastId = (int)$row['id'];
                $parent = (string)$lastId;
                if (!empty($row['variations'])) {
                    if (!is_array($row['variations'])) throw new NewCatalogImportFailure('DISCOVERY_INVALID');
                    foreach ($row['variations'] as $variation) {
                        $id = $parent.'|'.(string)$variation;
                        $this->assertId($id);
                        if (isset($seen[$id])) throw new NewCatalogImportFailure('DISCOVERY_INVALID');
                        $seen[$id] = true;
                        $cursor['queue'][] = $id;
                    }
                } else {
                    // An empty variable parent is kept visible as a failure for
                    // the native adapter; never silently certify it imported.
                    $cursor['queue'][] = $parent;
                }
                if (count($cursor['queue']) > 2000) throw new NewCatalogImportFailure('DISCOVERY_INVALID');
            }
            $cursor['last_id'] = $lastId;
            $cursor['page']++;
            $cursor['end'] = count($rows) < self::PAGE_SIZE;
        }
        $ids = array_splice($cursor['queue'], 0, $limit);
        return array('items'=>array_map(function ($id) { return array('remote_id'=>$id); }, $ids),
            'next_cursor'=>$cursor, 'complete'=>$cursor['end'] && !$cursor['queue']);
    }

    private function assertId($id)
    {
        if (!is_string($id) || !preg_match('/^[1-9][0-9]*(\|[1-9][0-9]*)?$/D', $id)) {
            throw new NewCatalogImportFailure('REMOTE_ID_INVALID');
        }
    }
}
