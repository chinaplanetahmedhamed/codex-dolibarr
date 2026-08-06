<?php

require_once __DIR__.'/ShippingNoteTranslationService.class.php';

class ShippingPriceImporter
{
    private $db;
    private $entity;
    private $user;
    private $countryMap;
    private $lastImportResults = array();
    private $translator;
    private $translationCache = array();
    private $translationStartedAt = 0.0;
    private $translationCalls = 0;
    private $translationCircuitOpen = false;

    public function __construct($db, $user, $entity = null, $translator = null)
    {
        $this->db = $db;
        $this->user = $user;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
        $this->translator = $translator ?: new ShippingNoteTranslationService();
        $this->countryMap = $this->defaultCountryMap();
        $this->loadSavedCountryMap();
    }

    public function importFile($path, $originalName, array $options)
    {
        $this->lastImportResults = array();
        $this->translationCache = array();
        $this->translationStartedAt = microtime(true);
        $this->translationCalls = 0;
        $this->translationCircuitOpen = false;
        require_once DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
        require_once DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';

        $mode = ($options['mode'] ?? 'current') === 'history' ? 'history' : 'current';
        $replacementScope = ($options['replacement_scope'] ?? 'agent') === 'all' ? 'all' : 'agent';
        $sourceName = trim((string) ($options['source_name'] ?? 'Shipping agent'));
        if ($sourceName === '') $sourceName = 'Shipping agent';
        $ignoreBelow = max(0, (float) ($options['ignore_below'] ?? 1000));
        $currency = strtoupper(trim((string) ($options['currency'] ?? 'USD')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'USD';
        $storedFile = (string) ($options['stored_file'] ?? '');
        $hash = hash_file('sha256', $path);

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $recognized = array();
        foreach ($book->getWorksheetIterator() as $sheet) {
            $meta = $this->inspectSheet($sheet);
            if ($meta !== null) $recognized[] = array('sheet' => $sheet, 'meta' => $meta);
        }
        if (!$recognized) throw new RuntimeException('No shipping tariff sheet with POD and TARIFF/COST headers was found.');

        if ($mode === 'current') {
            usort($recognized, function ($a, $b) {
                $aVisible = $a['sheet']->getSheetState() === \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VISIBLE ? 1 : 0;
                $bVisible = $b['sheet']->getSheetState() === \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VISIBLE ? 1 : 0;
                if ($aVisible !== $bVisible) return $bVisible - $aVisible;
                return strcmp($b['meta']['effective_date'], $a['meta']['effective_date']);
            });
            $recognized = array($recognized[0]);
        } else {
            usort($recognized, function ($a, $b) { return strcmp($a['meta']['effective_date'], $b['meta']['effective_date']); });
        }

        $batchIds = array();
        foreach ($recognized as $item) {
            $sheet = $item['sheet']; $meta = $item['meta'];
            $duplicate = $this->findDuplicate($hash, $sheet->getTitle(), $sourceName, $ignoreBelow, $currency, $mode, $replacementScope);
            if ($duplicate > 0) {
                $this->initializeDuplicateDraftTranslations($duplicate);
                $batchIds[] = $duplicate;
                $this->lastImportResults[] = array(
                    'batch_id' => $duplicate,
                    'duplicate' => true,
                    'publication' => $this->getBatchPublicationState($duplicate),
                );
                continue;
            }
            $parsed = $this->parseSheet($sheet, $meta, $ignoreBelow, $currency);
            $batchId = $this->storeBatch($parsed, array(
                'source_name' => $sourceName,
                'original_name' => $originalName,
                'stored_file' => $storedFile,
                'file_hash' => $hash,
                'mode' => $mode,
                'replacement_scope' => $replacementScope,
                'ignore_below' => $ignoreBelow,
                'currency' => $currency,
            ));
            $batchIds[] = $batchId;
            $this->lastImportResults[] = array(
                'batch_id' => $batchId,
                'duplicate' => false,
                'publication' => $this->getBatchPublicationState($batchId),
            );
        }
        $book->disconnectWorksheets();
        return $batchIds;
    }

    public function getLastImportResults()
    {
        return $this->lastImportResults;
    }

    public function getBatchPublicationState($batchId)
    {
        $batchId = (int) $batchId;
        $threshold = max(0, (float) ($GLOBALS['conf']->global->CHINAPLANETSP_IGNORE_BELOW ?? 1000));
        $sql = "SELECT b.rowid,b.status,b.source_name,b.effective_date,b.replacement_scope,b.accepted_rows,";
        $sql .= " COALESCE(rs.total_rates,0) total_rates,COALESCE(rs.published_rates,0) published_rates,";
        $sql .= " COALESCE(rs.current_rates,0) current_rates,COALESCE(rs.searchable_rates,0) searchable_rates";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch b";
        $sql .= " LEFT JOIN (SELECT fk_batch,entity,COUNT(rowid) total_rates,";
        $sql .= " SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) published_rates,";
        $sql .= " SUM(CASE WHEN status=1 AND is_current=1 THEN 1 ELSE 0 END) current_rates,";
        $sql .= " SUM(CASE WHEN status=1 AND is_current=1 AND public_visible=1 AND cost_price>=".price2num($threshold)." THEN 1 ELSE 0 END) searchable_rates";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate GROUP BY fk_batch,entity) rs ON rs.fk_batch=b.rowid AND rs.entity=b.entity";
        $sql .= " WHERE b.rowid=".$batchId." AND b.entity=".$this->entity;
        $res = $this->db->query($sql);
        $row = $res ? $this->db->fetch_object($res) : null;
        if (!$row) throw new RuntimeException('Import batch not found.');

        $scope = $row->replacement_scope === 'all'
            ? "b.source_name<>'Manual entry'"
            : "(b.source_name='".$this->db->escape($row->source_name)."' OR b.replacement_scope='all')";
        $latestSql = "SELECT rowid,effective_date FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch b WHERE b.entity=".$this->entity;
        $latestSql .= " AND b.status=2 AND ".$scope." ORDER BY b.effective_date DESC,b.rowid DESC LIMIT 1";
        $latestRes = $this->db->query($latestSql);
        $latest = $latestRes ? $this->db->fetch_object($latestRes) : null;
        $isLatest = !$latest || (int) $latest->rowid === $batchId;
        $isPublished = (int) $row->status === 2;
        $isCurrent = $isPublished && (int) $row->current_rates > 0;

        return array(
            'batch_id' => $batchId,
            'status' => (int) $row->status,
            'is_published' => $isPublished,
            'is_current' => $isCurrent,
            'is_latest_relevant' => $isLatest,
            'can_reactivate' => $isPublished && !$isCurrent && $isLatest && (int) $row->accepted_rows > 0,
            'total_rates' => (int) $row->total_rates,
            'published_rates' => (int) $row->published_rates,
            'current_rates' => (int) $row->current_rates,
            'searchable_rates' => (int) $row->searchable_rates,
        );
    }

    public function publishBatch($batchId, $forceCurrent = false)
    {
        $batchId = (int) $batchId;
        $this->db->begin();
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE rowid=".$batchId." AND entity=".$this->entity." FOR UPDATE";
        $res = $this->db->query($sql);
        $batch = $res ? $this->db->fetch_object($res) : null;
        if (!$batch) { $this->db->rollback(); throw new RuntimeException('Import batch not found.'); }
        if ((int) $batch->status === 2 && !$forceCurrent) {
            $this->db->commit();
            return $this->getBatchPublicationState($batchId);
        }
        if ((int) $batch->status === 2 && $forceCurrent) {
            $this->db->commit();
            $state = $this->getBatchPublicationState($batchId);
            if (empty($state['can_reactivate'])) {
                throw new RuntimeException('This published batch cannot be reactivated because it is already current or a newer relevant batch exists.');
            }
            $this->db->begin();
            $res = $this->db->query($sql);
            $batch = $res ? $this->db->fetch_object($res) : null;
            if (!$batch) return $this->rollbackError();
        }

        $translationSql = "SELECT COUNT(*) pending FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".$this->entity." AND fk_batch=".$batchId;
        $translationSql .= " AND source_notes IS NOT NULL AND source_notes<>'' AND translation_status<>'approved'";
        $translationRes = $this->db->query($translationSql);
        $translationRow = $translationRes ? $this->db->fetch_object($translationRes) : null;
        if (!$translationRow) return $this->rollbackError();
        if ((int) $translationRow->pending > 0) {
            $this->db->rollback();
            throw new RuntimeException('Approve every imported carrier/source note before publishing this batch.');
        }
        $sql = "UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_rate SET status=1,is_current=0 WHERE fk_batch=".$batchId." AND entity=".$this->entity;
        if (!$this->db->query($sql)) return $this->rollbackError();

        $sql = "SELECT MAX(b.effective_date) latest_date FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch b";
        $sql .= " WHERE b.entity=".$this->entity." AND b.status=2 AND b.source_name='".$this->db->escape($batch->source_name)."'";
        $res = $this->db->query($sql); $latest = $res ? $this->db->fetch_object($res) : null;
        $makeCurrent = $forceCurrent || !$latest || empty($latest->latest_date) || $batch->effective_date >= $latest->latest_date;
        if ($makeCurrent) {
            $sql = "UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_rate r INNER JOIN ".MAIN_DB_PREFIX."chinaplanetsp_batch b ON b.rowid=r.fk_batch";
            $sql .= " SET r.is_current=0 WHERE r.entity=".$this->entity." AND b.source_name<>'Manual entry'";
            if ($batch->replacement_scope !== 'all') $sql .= " AND b.source_name='".$this->db->escape($batch->source_name)."'";
            if (!$this->db->query($sql)) return $this->rollbackError();
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_rate SET is_current=1 WHERE fk_batch=".$batchId." AND status=1")) return $this->rollbackError();
        }
        $sql = "UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_batch SET status=2,fk_user_modif=".(int) $this->user->id." WHERE rowid=".$batchId." AND entity=".$this->entity;
        if (!$this->db->query($sql)) return $this->rollbackError();
        $this->db->commit();
        return $this->getBatchPublicationState($batchId);
    }

    private function inspectSheet($sheet)
    {
        $maxRow = min(20, $sheet->getHighestDataRow());
        $maxCol = min(30, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        $headerRow = 0; $podCol = 0; $tariffStart = 0; $costStart = 0; $notesCol = 0;
        for ($r = 1; $r <= $maxRow; $r++) {
            $labels = array();
            for ($c = 1; $c <= $maxCol; $c++) $labels[$c] = strtoupper(trim((string) $this->cellValue($sheet, $c, $r)));
            foreach ($labels as $c => $label) if ($label === 'POD') { $headerRow = $r; $podCol = $c; }
            if ($headerRow === $r) {
                foreach ($labels as $c => $label) {
                    if (strpos($label, 'TARIFF') !== false) $tariffStart = $c;
                    if ($label === 'COST' || strpos($label, 'BASE RATE') !== false) $costStart = $c;
                    if (strpos($label, 'NOTE') !== false || strpos($label, 'REMARK') !== false || strpos($label, '运价适用') !== false) $notesCol = $c;
                }
            }
            if ($headerRow && ($tariffStart || $costStart)) break;
        }
        if (!$headerRow || !$podCol || (!$tariffStart && !$costStart)) return null;

        $containerRow = $headerRow + 1;
        $priceStart = $tariffStart ?: $costStart;
        $priceGroup = $tariffStart ? 'tariff' : 'cost';
        $priceCols = $this->containerColumns($sheet, $priceStart, $containerRow, $maxCol);
        if ($tariffStart && $costStart && !$this->hasNumericPrices($sheet, $priceCols, $containerRow + 1, min($sheet->getHighestDataRow(), $containerRow + 80))) {
            $priceStart = $costStart;
            $priceGroup = 'cost_fallback';
            $priceCols = $this->containerColumns($sheet, $priceStart, $containerRow, $maxCol);
        }
        if (!$priceCols) return null;

        $effectiveDate = $this->sheetEffectiveDate($sheet);
        return array(
            'header_row' => $headerRow, 'container_row' => $containerRow, 'pod_col' => $podCol,
            'origin_col' => 1, 'route_col' => $podCol + 1, 'price_cols' => $priceCols,
            'notes_col' => $notesCol ?: max(array_keys($priceCols)) + 1, 'price_group' => $priceGroup,
            'effective_date' => $effectiveDate,
        );
    }

    private function parseSheet($sheet, array $meta, $ignoreBelow, $currency)
    {
        $rows = array(); $ignored = array(); $warnings = array(); $lastOrigin = '';
        $highest = $sheet->getHighestDataRow();
        for ($r = $meta['container_row'] + 1; $r <= $highest; $r++) {
            $origin = trim((string) $this->cellValue($sheet, $meta['origin_col'], $r));
            if ($origin !== '') $lastOrigin = $origin; else $origin = $lastOrigin;
            $podRaw = trim((string) $this->cellValue($sheet, $meta['pod_col'], $r));
            if ($podRaw === '' || $origin === '') continue;
            $pod = $this->cleanPod($podRaw);
            if ($pod === '') continue;
            $route = trim((string) $this->cellValue($sheet, $meta['route_col'], $r));
            $originData = $this->parseOrigin($origin);
            $country = $this->countryForPod($pod);
            if ($country === '') $warnings['country:'.$pod] = 'Unmapped destination country: '.$pod;
            if ($originData['line'] === 'UNKNOWN') $warnings['line:'.$origin] = 'Unmapped shipping line: '.$origin;
            $foundPrice = false; $acceptedPrices = array();
            foreach ($meta['price_cols'] as $col => $container) {
                $cell = $sheet->getCellByColumnAndRow($col, $r);
                $value = $cell->getValue();
                if (!is_numeric($value)) {
                    if ($value !== null && trim((string) $value) !== '' && trim((string) $value) !== '-') {
                        $ignored[] = array('row' => $r, 'cell' => $cell->getCoordinate(), 'reason' => 'non_numeric', 'value' => mb_substr((string) $value, 0, 80));
                    }
                    continue;
                }
                $foundPrice = true; $cost = (float) $value;
                if ($cost < $ignoreBelow) {
                    $ignored[] = array('row' => $r, 'cell' => $cell->getCoordinate(), 'reason' => 'below_threshold', 'value' => $cost);
                    continue;
                }
                $margin = $this->containerMargin($container);
                $acceptedPrices[] = array('cell'=>$cell, 'container'=>$container, 'cost'=>$cost, 'sale'=>$cost + $margin);
            }
            if (!$foundPrice) $ignored[] = array('row' => $r, 'cell' => '', 'reason' => 'no_usable_price', 'value' => $pod);
            if (!$acceptedPrices) continue;
            $notesRaw = (string) $this->cellValue($sheet, $meta['notes_col'], $r);
            $notes = ShippingNoteTranslationService::normalizeSource($notesRaw);
            $translation = $this->translationForNote($notes);
            foreach ($acceptedPrices as $acceptedPrice) {
                $rows[] = array(
                    'source_row' => $r, 'source_cell' => $acceptedPrice['cell']->getCoordinate(), 'region' => $originData['region'],
                    'pol' => $originData['pol'], 'country' => $country, 'pod' => $pod, 'line' => $originData['line'],
                    'service' => $this->serviceType($route), 'route' => $route, 'container' => $acceptedPrice['container'],
                    'cost' => $acceptedPrice['cost'], 'sale' => $acceptedPrice['sale'], 'currency' => $currency,
                    'public_visible' => ($country !== '' && $originData['pol'] !== 'UNKNOWN' && $originData['line'] !== 'UNKNOWN') ? 1 : 0,
                    'source_notes_raw' => $notesRaw,
                    'source_notes' => $notes,
                    'notes_en' => $translation['notes_en'],
                    'translation_status' => $translation['status'],
                    'translation_source_hash' => $translation['source_hash'],
                    'translation_provider' => $translation['provider'],
                    'translation_model' => $translation['model'],
                    'translation_error' => $translation['error'],
                    'public_notes' => $translation['status'] === 'approved' ? $translation['notes_en'] : '',
                    'internal_notes' => $notes,
                );
            }
        }
        return array(
            'sheet' => $sheet->getTitle(), 'effective_date' => $meta['effective_date'], 'price_group' => $meta['price_group'],
            'rows' => $rows, 'ignored' => $ignored, 'warnings' => array_values($warnings),
            'total_rows' => max(0, $highest - $meta['container_row']),
        );
    }

    private function storeBatch(array $parsed, array $context)
    {
        $signature=$context['file_hash'].'|'.$parsed['sheet'].'|'.$context['source_name'].'|'.$context['ignore_below'].'|'.$context['currency'].'|'.$context['mode'].'|'.$context['replacement_scope'];
        $ref = 'SPB-'.str_replace('-', '', $parsed['effective_date']).'-'.substr(hash('sha256', $signature), 0, 8);
        $report = array(
            'price_group' => $parsed['price_group'], 'ignored' => array_slice($parsed['ignored'], 0, 1000),
            'warnings' => $parsed['warnings'], 'truncated_ignored' => count($parsed['ignored']) > 1000,
        );
        $this->db->begin();
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_batch(entity,ref,source_name,original_name,stored_file,file_hash,source_sheet,effective_date,import_mode,replacement_scope,ignore_below,currency_code,total_rows,accepted_rows,ignored_rows,warning_count,status,report_json,fk_user_creat,date_creation) VALUES(";
        $sql .= $this->entity.",'".$this->db->escape($ref)."','".$this->db->escape($context['source_name'])."','".$this->db->escape($context['original_name'])."','".$this->db->escape($context['stored_file'])."','".$this->db->escape($context['file_hash'])."','".$this->db->escape($parsed['sheet'])."','".$this->db->escape($parsed['effective_date'])."','".$this->db->escape($context['mode'])."','".$this->db->escape($context['replacement_scope'])."',".price2num($context['ignore_below']).",'".$this->db->escape($context['currency'])."',".(int) $parsed['total_rows'].",".count($parsed['rows']).",".count($parsed['ignored']).",".count($parsed['warnings']).",0,'".$this->db->escape(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))."',".(int) $this->user->id.",'".$this->db->idate(dol_now())."')";
        if (!$this->db->query($sql)) throw new RuntimeException($this->db->lasterror());
        $batchId = $this->db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetsp_batch');
        foreach ($parsed['rows'] as $row) {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_rate(entity,fk_batch,source_sheet,source_row,source_cell,region_label,pol,destination_country,pod,shipping_line,service_type,route_detail,container_type,cost_price,sale_price,currency_code,valid_from,source_notes_raw,source_notes,notes_en,translation_status,translation_source_hash,translation_provider,translation_model,translation_error,public_notes,internal_notes,public_visible,is_current,status,fk_user_creat,date_creation) VALUES(";
            $sql .= $this->entity.",".$batchId.",'".$this->db->escape($parsed['sheet'])."',".(int) $row['source_row'].",'".$this->db->escape($row['source_cell'])."','".$this->db->escape($row['region'])."','".$this->db->escape($row['pol'])."',".($row['country'] !== '' ? "'".$this->db->escape($row['country'])."'" : 'NULL').",'".$this->db->escape($row['pod'])."','".$this->db->escape($row['line'])."','".$this->db->escape($row['service'])."','".$this->db->escape($row['route'])."','".$this->db->escape($row['container'])."',".price2num($row['cost']).",".price2num($row['sale']).",'".$this->db->escape($row['currency'])."','".$this->db->escape($parsed['effective_date'])."',".$this->nullable($row['source_notes_raw']).",".$this->nullable($row['source_notes']).",".$this->nullable($row['notes_en']).",'".$this->db->escape($row['translation_status'])."',".$this->nullable($row['translation_source_hash']).",".$this->nullable($row['translation_provider']).",".$this->nullable($row['translation_model']).",".$this->nullable($row['translation_error']).",".$this->nullable($row['public_notes']).",".$this->nullable($row['internal_notes']).",".(int)$row['public_visible'].",0,0,".(int) $this->user->id.",'".$this->db->idate(dol_now())."')";
            if (!$this->db->query($sql)) { $this->db->rollback(); throw new RuntimeException($this->db->lasterror()); }
        }
        $this->db->commit();
        return (int) $batchId;
    }

    private function translationForNote($notes)
    {
        $notes = ShippingNoteTranslationService::normalizeSource($notes);
        $hash = $notes === '' ? '' : hash('sha256', $notes);
        if (isset($this->translationCache[$hash])) return $this->translationCache[$hash];
        if ($notes === '') {
            return $this->translationCache[$hash] = array('status'=>'not_needed','notes_en'=>'','source_hash'=>'','provider'=>'','model'=>'','error'=>'','attempted'=>false);
        }
        if (ShippingNoteTranslationService::containsHan($notes)) {
            $elapsed = microtime(true) - $this->translationStartedAt;
            if ($this->translationCircuitOpen || $this->translationCalls >= 10 || $elapsed >= 19.75) {
                return $this->translationCache[$hash] = array(
                    'status'=>'pending','notes_en'=>'','source_hash'=>$hash,'provider'=>'','model'=>'',
                    'error'=>$this->translationCircuitOpen ? 'provider_circuit_open' : 'translation_budget_exhausted','attempted'=>false,
                );
            }
        }
        $remainingSeconds = max(0.001, 20.0 - (microtime(true) - $this->translationStartedAt));
        $result = $this->translator->translate($notes, $remainingSeconds);
        if (!empty($result['attempted'])) $this->translationCalls++;
        if (in_array($result['status'], array('pending','failed'), true) && ShippingNoteTranslationService::containsHan($notes)) {
            $this->translationCircuitOpen = true;
        }
        if ((microtime(true) - $this->translationStartedAt) >= 20.0) $this->translationCircuitOpen = true;
        return $this->translationCache[$hash] = $result;
    }

    /** Initialize a reused legacy draft without modifying published/history price data. */
    private function initializeDuplicateDraftTranslations($batchId)
    {
        $batchId = (int) $batchId;
        $sql = "SELECT status FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE rowid=".$batchId." AND entity=".$this->entity;
        $res = $this->db->query($sql); $batch = $res ? $this->db->fetch_object($res) : null;
        if (!$batch || (int) $batch->status !== 0) return;
        $sql = "SELECT source_row,MIN(internal_notes) source_note FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate";
        $sql .= " WHERE entity=".$this->entity." AND fk_batch=".$batchId." AND source_notes IS NULL AND internal_notes IS NOT NULL AND internal_notes<>''";
        $sql .= " GROUP BY source_row ORDER BY source_row";
        $res = $this->db->query($sql); $groups = array();
        if ($res) while ($row = $this->db->fetch_object($res)) $groups[] = $row;
        foreach ($groups as $group) {
            $raw = (string) $group->source_note;
            $normalized = ShippingNoteTranslationService::normalizeSource($raw);
            if ($normalized === '') continue;
            $translation = $this->translationForNote($normalized);
            $this->db->begin();
            $lockSql = "SELECT status FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE rowid=".$batchId." AND entity=".$this->entity." FOR UPDATE";
            $lockRes = $this->db->query($lockSql); $locked = $lockRes ? $this->db->fetch_object($lockRes) : null;
            if (!$locked || (int) $locked->status !== 0) { $this->db->rollback(); return; }
            $update = "UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_rate SET source_notes_raw=".$this->nullable($raw).",source_notes=".$this->nullable($normalized).",notes_en=".$this->nullable($translation['notes_en']).",";
            $update .= "translation_status='".$this->db->escape($translation['status'])."',translation_source_hash=".$this->nullable($translation['source_hash']).",translation_provider=".$this->nullable($translation['provider']).",";
            $update .= "translation_model=".$this->nullable($translation['model']).",translation_error=".$this->nullable($translation['error']).",public_notes=".$this->nullable($translation['status'] === 'approved' ? $translation['notes_en'] : '')."";
            $update .= " WHERE entity=".$this->entity." AND fk_batch=".$batchId." AND source_row=".(int) $group->source_row." AND source_notes IS NULL";
            if (!$this->db->query($update)) return $this->rollbackError();
            $this->db->commit();
        }
    }

    private function nullable($value)
    {
        $value = (string) $value;
        return $value === '' ? 'NULL' : "'".$this->db->escape($value)."'";
    }

    private function sheetEffectiveDate($sheet)
    {
        for ($r = 1; $r <= min(12, $sheet->getHighestDataRow()); $r++) {
            for ($c = 1; $c <= min(8, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn())); $c++) {
                $value = (string) $this->cellValue($sheet, $c, $r);
                if (preg_match('/(20\d{2})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $value, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
            }
        }
        if (preg_match('/^(\d{2})-(\d{2})$/', $sheet->getTitle(), $m)) {
            $year = ((int) $m[1] >= 9) ? 2025 : 2026;
            return sprintf('%04d-%02d-%02d', $year, $m[1], $m[2]);
        }
        return dol_print_date(dol_now(), '%Y-%m-%d');
    }

    private function normalizeContainer($value)
    {
        $v = strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
        $map = array('20GP' => '20GP', '20DC' => '20GP', '20' => '20GP', '40GP' => '40GP', '40DC' => '40GP', '40' => '40GP', '40HQ' => '40HQ', '40HC' => '40HQ', '45HQ' => '45HQ', '45HC' => '45HQ');
        return $map[$v] ?? '';
    }

    private function containerColumns($sheet, $start, $row, $maxCol)
    {
        $cols = array();
        for ($c = $start; $c <= $maxCol; $c++) {
            $container = $this->normalizeContainer($this->cellValue($sheet, $c, $row));
            if ($container !== '') $cols[$c] = $container;
            elseif ($c > $start && $cols) break;
        }
        return $cols;
    }

    private function hasNumericPrices($sheet, array $cols, $startRow, $endRow)
    {
        foreach (array_keys($cols) as $col) for ($row = $startRow; $row <= $endRow; $row++) {
            if (is_numeric($this->cellValue($sheet, $col, $row))) return true;
        }
        return false;
    }

    private function cellValue($sheet, $column, $row)
    {
        $value = $sheet->getCellByColumnAndRow($column, $row)->getValue();
        if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) return $value->getPlainText();
        return $value;
    }

    private function parseOrigin($raw)
    {
        $upper = strtoupper($raw); $line = 'UNKNOWN';
        $lines = array('PEAK SHIPPING'=>'PEAK SHIPPING','WHITE LINE'=>'WHITE LINE','SEA LEGEND'=>'SEA LEGEND','YANG MING'=>'YANG MING','HAPAG-LLOYD'=>'HAPAG-LLOYD','HYUNDAI'=>'HMM','EVERGREEN'=>'EVERGREEN','WANHAI'=>'WAN HAI','WAN HAI'=>'WAN HAI','SEALEAD'=>'SEALEAD','MAERSK'=>'MAERSK','AKKON'=>'AKKON','COSCO'=>'COSCO','VOLTA'=>'VOLTA','OOCL'=>'OOCL','CMA'=>'CMA','MSC'=>'MSC','RCL'=>'RCL','TSL'=>'TSL','ONE'=>'ONE','YML'=>'YANG MING','ZIM'=>'ZIM','PIL'=>'PIL','CUL'=>'CUL','ESL'=>'ESL','OSL'=>'OSL','OVP'=>'OVP','SLG'=>'SLG','SLS'=>'SLS','HMM'=>'HMM','EMC'=>'EVERGREEN','JHS'=>'JHS','中谷'=>'CUL','吉航'=>'JHS');
        foreach ($lines as $needle=>$canonical) if (strpos($upper,$needle)!==false || strpos($raw,$needle)!==false) { $line=$canonical; break; }
        $pol = 'UNKNOWN';
        $ports = array('SHENZHEN'=>'Shenzhen','SHEKOU'=>'Shenzhen','YANTIAN'=>'Shenzhen','DACHAN'=>'Shenzhen','GUANGZHOU'=>'Guangzhou','NANSHA'=>'Guangzhou');
        foreach ($ports as $needle => $name) if (strpos($upper, $needle) !== false) { $pol = $name; break; }
        if ($pol === 'UNKNOWN') {
            foreach (array('深圳'=>'Shenzhen','蛇口'=>'Shenzhen','盐田'=>'Shenzhen','大铲湾'=>'Shenzhen','广州'=>'Guangzhou','南沙'=>'Guangzhou') as $needle=>$name) {
                if (strpos($raw, $needle) !== false) { $pol=$name; break; }
            }
        }
        if ($pol === 'UNKNOWN') {
            $defaultPol = trim((string) ($GLOBALS['conf']->global->CHINAPLANETSP_DEFAULT_POL ?? 'Guangzhou'));
            if ($defaultPol !== '') $pol = $defaultPol;
        }
        $lane='';foreach(array('地中海'=>'Mediterranean','MEDITERRANEAN'=>'Mediterranean','中东'=>'Middle East','MIDDLE EAST'=>'Middle East','北非'=>'North Africa','NORTH AFRICA'=>'North Africa','红海'=>'Red Sea','RED SEA'=>'Red Sea')as$needle=>$english)if(strpos($upper,$needle)!==false||strpos($raw,$needle)!==false){$lane=$english;break;}
        return array('pol' => $pol, 'line' => $line, 'region' => $lane);
    }

    private function cleanPod($pod)
    {
        $pod = preg_replace('/\s+/', ' ', trim($pod));
        if (preg_match('/^SOKHNA\s*[\(（]/iu', $pod)) $pod = 'SOKHNA';
        return mb_substr($pod, 0, 160);
    }

    private function serviceType($route)
    {
        $lower = mb_strtolower($route);
        if (strpos($lower, 'transit') !== false || strpos($route, '中转') !== false || preg_match('/\bvia\b/i', $route)) return 'transit';
        if (strpos($lower, 'direct') !== false || strpos($route, '直航') !== false) return 'direct';
        return 'unknown';
    }

    private function containerMargin($container)
    {
        $key = 'CHINAPLANETSP_MARGIN_'.$container;
        return isset($GLOBALS['conf']->global->$key) ? (float) $GLOBALS['conf']->global->$key : ($container === '20GP' ? 200 : 400);
    }

    private function countryForPod($pod)
    {
        $normalized = strtoupper(trim(preg_replace('/\s*\(.*/', '', $pod)));
        foreach ($this->countryMap as $needle => $country) if (strpos($normalized, $needle) === 0) return $country;
        return '';
    }

    /** Saved mappings override and extend the built-in fallback map. */
    private function loadSavedCountryMap()
    {
        $sql = "SELECT port_name,aliases,country_code FROM ".MAIN_DB_PREFIX."chinaplanetsp_port WHERE entity=".$this->entity." AND status=1";
        $res = $this->db->query($sql);
        if (!$res) return;
        while ($row = $this->db->fetch_object($res)) {
            $names = array($row->port_name);
            foreach (preg_split('/[,\r\n]+/', (string) $row->aliases) as $alias) if (trim($alias) !== '') $names[] = trim($alias);
            foreach ($names as $name) {
                $normalized = strtoupper(trim(preg_replace('/\s*\(.*/', '', $name)));
                if ($normalized !== '') $this->countryMap[$normalized] = strtoupper($row->country_code);
            }
        }
        uksort($this->countryMap, function ($a, $b) { return strlen($b) - strlen($a); });
    }

    private function defaultCountryMap()
    {
        return array(
            'SOKHNA'=>'EG','AIN SOKHNA'=>'EG','ALEXANDRIA'=>'EG','DEKHEILA'=>'EG','DAMIETTA'=>'EG','PORT SAID'=>'EG',
            'JEDDAH'=>'SA','DAMMAM'=>'SA','RIYADH'=>'SA','JUBAIL'=>'SA','JEBEL ALI'=>'AE','ABU DHABI'=>'AE','SHARJAH'=>'AE','AJMAN'=>'AE','KHOR FAKKAN'=>'AE','FUJAIRAH'=>'AE','UMM AL QAIWAIN'=>'AE','RAS AL KHAIMAH'=>'AE',
            'HAMAD'=>'QA','DOHA'=>'QA','AQABA'=>'JO','ADEN'=>'YE','HODEIDAH'=>'YE','PORT SUDAN'=>'SD','DJIBOUTI'=>'DJ','BERBERA'=>'SO','MOGADISHU'=>'SO',
            'UMM QASR'=>'IQ','SHUWAIKH'=>'KW','SHUAIBA'=>'KW','KUWAIT'=>'KW','BAHRAIN'=>'BH','SOHAR'=>'OM','SALALAH'=>'OM','MUSCAT'=>'OM',
            'ASHDOD'=>'PS','HAIFA'=>'PS','MISURATA'=>'LY','BENGHAZI'=>'LY','TRIPOLI'=>'LY','LATTAKIA'=>'SY','BEIRUT'=>'LB','MERSIN'=>'TR','ISTANBUL'=>'TR',
        );
    }

    private function findDuplicate($hash, $sheet, $sourceName, $ignoreBelow, $currency, $mode, $replacementScope)
    {
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE entity=".$this->entity." AND file_hash='".$this->db->escape($hash)."' AND source_sheet='".$this->db->escape($sheet)."'";
        $sql .= " AND source_name='".$this->db->escape($sourceName)."' AND ignore_below=".price2num($ignoreBelow)." AND currency_code='".$this->db->escape($currency)."'";
        $sql .= " AND import_mode='".$this->db->escape($mode)."' AND replacement_scope='".$this->db->escape($replacementScope)."' AND status<>9 ORDER BY rowid DESC LIMIT 1";
        $res = $this->db->query($sql); $obj = $res ? $this->db->fetch_object($res) : null;
        return $obj ? (int) $obj->rowid : 0;
    }

    private function rollbackError()
    {
        $error = $this->db->lasterror(); $this->db->rollback(); throw new RuntimeException($error);
    }
}
