<?php

/**
 * Deterministic destination-port resolver.
 *
 * Exact curated/database aliases always win. Fuzzy matching is deliberately
 * restricted and never accepts a partial composite.
 */
class PortNameResolver
{
    const DEFAULT_FUZZY_THRESHOLD = 0.90;
    const MIN_FUZZY_LENGTH = 6;
    const MIN_FUZZY_MARGIN = 0.08;
    const MAX_FUZZY_EDITS = 2;

    private $db;
    private $entity;
    private $threshold;
    private $aliases = array();
    private $ambiguous = array();
    private $literalAliases = array();
    private $literalAmbiguous = array();
    private $allowMissingAliasTableLiterals = false;
    private $aliasTableState = 'not_checked';

    public function __construct($db = null, $entity = 1, $threshold = null, $allowMissingAliasTableLiterals = false)
    {
        $this->db = $db;
        $this->entity = (int) $entity;
        if ($threshold === null && isset($GLOBALS['conf']->global->CHINAPLANETSP_PORT_FUZZY_THRESHOLD)) {
            $threshold = (float) $GLOBALS['conf']->global->CHINAPLANETSP_PORT_FUZZY_THRESHOLD;
        }
        $this->threshold = max(0.85, min(1.0, $threshold === null ? self::DEFAULT_FUZZY_THRESHOLD : (float) $threshold));
        $this->allowMissingAliasTableLiterals = $allowMissingAliasTableLiterals === true;
        $this->loadBuiltinCatalog();
        $this->loadLegacyMappings();
        $this->loadAliasTable();
    }

    /** Normalize for identity, not for display. */
    public static function normalize($value)
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(array("\xC2\xA0", '（', '）', '，', '：', '／', '＆', '—', '–', '‑', '−'), array(' ', '(', ')', ',', ':', '/', '&', '-', '-', '-', '-'), $value);
        if (function_exists('transliterator_transliterate')) {
            $converted = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
            if ($converted !== false) $value = $converted;
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false && $converted !== '') $value = $converted;
        }
        $value = mb_strtoupper($value, 'UTF-8');
        $value = preg_replace('/[^A-Z0-9\p{L}\p{N}]+/u', ' ', $value);
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    /** Normalize an exact literal alias while preserving delimiter meaning. */
    private static function literalSignature($value)
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(array("\xC2\xA0", '（', '）', '，', '：', '／', '＆', '—', '–', '‑', '−'), array(' ', '(', ')', ',', ':', '/', '&', '-', '-', '-', '-'), $value);
        if (function_exists('transliterator_transliterate')) {
            $converted = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
            if ($converted !== false) $value = $converted;
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false && $converted !== '') $value = $converted;
        }
        $value = mb_strtoupper($value, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        return preg_replace('/\s*([(),\/&;+])\s*/u', '$1', $value);
    }

    /** @return array<int,array<string,mixed>> */
    public function resolveList($raw, $allowFuzzy = true)
    {
        $raw = trim((string) $raw);
        if ($raw === '') return array('status'=>'unmapped', 'raw'=>$raw, 'matches'=>array(), 'reason'=>'empty');

        $destination = $this->removeOriginPrefix($raw);

        // A delimiter inside parentheses is terminal/berth metadata, while a
        // top-level delimiter separates destinations. Preserve explicitly
        // curated literal aliases such as "KHALIFA PORT, ABU DHABI" without
        // allowing punctuation-insensitive aliases to collapse composites.
        $parts = $this->splitTopLevelComposite($destination);
        if (count($parts) > 1) {
            $literal = $this->resolveLiteralCandidate($destination);
            if ($literal['status'] === 'matched' || $literal['status'] === 'ambiguous') {
                $literal['raw'] = $raw;
                $literal['origin_removed'] = $destination !== $raw;
                return $literal;
            }

            $matches = array(); $seen = array();
            foreach ($parts as $part) {
                $part = $this->stripQualifier($part);
                $resolved = $this->resolveCandidate($part, $allowFuzzy);
                if ($resolved['status'] !== 'matched' || count($resolved['matches']) !== 1) {
                    return array('status'=>$resolved['status'] === 'ambiguous' ? 'ambiguous' : 'unmapped', 'raw'=>$raw, 'matches'=>array(), 'reason'=>'unresolved_composite_part', 'part'=>trim($part));
                }
                $match = $resolved['matches'][0];
                $key = $match['country_code'].'|'.$match['port_name'];
                if (!isset($seen[$key])) { $seen[$key] = true; $matches[] = $match; }
            }
            return array('status'=>'matched', 'raw'=>$raw, 'matches'=>$matches, 'method'=>'composite', 'score'=>1.0, 'origin_removed'=>$destination !== $raw);
        }

        $whole = $this->resolveCandidate($destination, false);
        if ($whole['status'] === 'matched') {
            $whole['raw'] = $raw;
            $whole['origin_removed'] = $destination !== $raw;
            return $whole;
        }

        $qualified = $this->stripQualifier($destination);
        if ($qualified !== $destination) {
            $qualifiedMatch = $this->resolveCandidate($qualified, false);
            if ($qualifiedMatch['status'] === 'matched') {
                $qualifiedMatch['raw'] = $raw;
                $qualifiedMatch['origin_removed'] = $destination !== $raw;
                $qualifiedMatch['qualifier_removed'] = true;
                return $qualifiedMatch;
            }
            $destination = $qualified;
        }

        $resolved = $this->resolveCandidate($destination, $allowFuzzy);
        $resolved['raw'] = $raw;
        $resolved['origin_removed'] = $destination !== $raw;
        return $resolved;
    }

    /** @return array<int,string> */
    private function splitTopLevelComposite($value)
    {
        $characters = preg_split('//u', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $parts = array(); $buffer = ''; $depth = 0;
        foreach ($characters as $character) {
            if ($character === '(' || $character === '（') {
                $depth++;
                $buffer .= $character;
                continue;
            }
            if ($character === ')' || $character === '）') {
                if ($depth > 0) $depth--;
                $buffer .= $character;
                continue;
            }
            if ($depth === 0 && in_array($character, array('/', '／', '&', '＆', ',', '，', ';', '+'), true)) {
                if (trim($buffer) !== '') $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $character;
        }
        if (trim($buffer) !== '') $parts[] = trim($buffer);
        return $parts;
    }

    private function resolveLiteralCandidate($value)
    {
        $signature = self::literalSignature($value);
        if ($signature === '') return array('status'=>'unmapped', 'matches'=>array(), 'reason'=>'empty_literal');
        if (isset($this->literalAmbiguous[$signature])) return array('status'=>'ambiguous', 'matches'=>array(), 'reason'=>'literal_alias_conflict');
        if (!isset($this->literalAliases[$signature])) return array('status'=>'unmapped', 'matches'=>array(), 'reason'=>'no_literal_alias');
        // The literal catalog is an immutable allowlist of delimiter-bearing
        // aliases, not the final port record. Resolve the normalized alias
        // through the authoritative map so a database-backed resolver retains
        // fk_port and the canonical code. Fail closed if mutable data tries to
        // move the reviewed literal alias to a different port or country.
        $expected = $this->literalAliases[$signature];
        $normalized = self::normalize($value);
        if ($normalized === '' || isset($this->ambiguous[$normalized]) || !isset($this->aliases[$normalized])) {
            return array('status'=>'ambiguous', 'matches'=>array(), 'reason'=>'literal_alias_owner_unavailable');
        }
        $match = $this->aliases[$normalized];
        if ($match['port_name'] !== $expected['port_name'] || $match['country_code'] !== $expected['country_code']) {
            return array('status'=>'ambiguous', 'matches'=>array(), 'reason'=>'literal_alias_owner_mismatch');
        }
        if ($this->db && !($this->allowMissingAliasTableLiterals && $this->aliasTableState === 'missing')) {
            if ($this->aliasTableState !== 'loaded' || ($match['source'] ?? '') !== 'database' || (int)($match['fk_port'] ?? 0) <= 0) {
                return array('status'=>'ambiguous', 'matches'=>array(), 'reason'=>'literal_alias_owner_unavailable');
            }
        }
        $match['method'] = 'exact'; $match['score'] = 1.0; $match['matched_alias'] = self::normalize($value);
        return array('status'=>'matched', 'matches'=>array($match), 'method'=>'exact', 'score'=>1.0);
    }

    private function resolveCandidate($value, $allowFuzzy)
    {
        $normalized = self::normalize($value);
        if ($normalized === '') return array('status'=>'unmapped', 'matches'=>array(), 'reason'=>'empty_normalized');
        if (isset($this->ambiguous[$normalized])) return array('status'=>'ambiguous', 'matches'=>array(), 'reason'=>'alias_conflict');
        if (isset($this->aliases[$normalized])) {
            $match = $this->aliases[$normalized];
            $match['method'] = 'exact'; $match['score'] = 1.0; $match['matched_alias'] = $normalized;
            return array('status'=>'matched', 'matches'=>array($match), 'method'=>'exact', 'score'=>1.0);
        }
        if (!$allowFuzzy || strlen($normalized) < self::MIN_FUZZY_LENGTH) return array('status'=>'unmapped', 'matches'=>array(), 'reason'=>'no_exact_alias');

        $candidates = array();
        foreach ($this->aliases as $alias => $candidate) {
            if (strlen($alias) < self::MIN_FUZZY_LENGTH || $alias[0] !== $normalized[0]) continue;
            $distance = levenshtein($normalized, $alias);
            if ($distance > self::MAX_FUZZY_EDITS) continue;
            $score = 1.0 - ($distance / max(strlen($normalized), strlen($alias)));
            $owner = $candidate['country_code'].'|'.$candidate['port_name'];
            if (!isset($candidates[$owner]) || $score > $candidates[$owner]['score']) $candidates[$owner] = array('alias'=>$alias, 'candidate'=>$candidate, 'score'=>$score, 'distance'=>$distance);
        }
        usort($candidates, function ($a, $b) { return $a['score'] === $b['score'] ? strcmp($a['alias'], $b['alias']) : ($a['score'] > $b['score'] ? -1 : 1); });
        $best = $candidates[0] ?? null;
        $secondScore = isset($candidates[1]) ? $candidates[1]['score'] : 0.0;
        if ($best === null || $best['score'] < $this->threshold || ($best['score'] - $secondScore) < self::MIN_FUZZY_MARGIN) {
            return array('status'=>'unmapped', 'matches'=>array(), 'reason'=>'fuzzy_below_safety_threshold', 'best_score'=>$best === null ? 0.0 : $best['score']);
        }
        $match = $best['candidate'];
        $match['method'] = 'fuzzy'; $match['score'] = $best['score']; $match['matched_alias'] = $best['alias'];
        return array('status'=>'matched', 'matches'=>array($match), 'method'=>'fuzzy', 'score'=>$best['score']);
    }

    private function removeOriginPrefix($value)
    {
        $value = trim($value);
        if (preg_match('/^(.+?)(?:--|—|–)\s*(.+)$/u', $value, $matches)) {
            $origin = self::normalize($matches[1]);
            $knownOrigins = array('SHEKOU','SHE KOU','NANSHA','NAN SHA','SHENZHEN','SHEN ZHEN','GUANGZHOU','GUANG ZHOU','HUANGPU','HUANG PU','HUMEN','HU MEN','YANTIAN','YAN TIAN');
            if (in_array($origin, $knownOrigins, true)) return trim($matches[2]);
        }
        return $value;
    }

    private function stripQualifier($value)
    {
        return trim(preg_replace_callback('/\s*[\(（]([^\(\)（）]*)[\)）]\s*/u', function ($match) {
            $qualifier = trim($match[1]);
            $known = preg_match('/\b(?:SEA\s*PORT|DRY\s*PORT|CALLING\s+(?:AICT|ACCHC))\b/iu', $qualifier)
                || preg_match('/^\d+(?:\.\d+)?#?$/u', $qualifier);
            return $known ? ' ' : $match[0];
        }, trim($value)));
    }

    private function addAlias($alias, array $port, $authoritative = false)
    {
        $normalized = self::normalize($alias);
        if ($normalized === '') return;
        $port['port_name'] = trim($port['port_name']);
        $port['country_code'] = strtoupper(trim($port['country_code']));
        // Only the reviewed built-in catalog may declare that a top-level
        // delimiter belongs to one literal alias. Mutable legacy/database
        // aliases must never bypass composite splitting.
        if (($port['source'] ?? '') === 'builtin') {
            $literal = self::literalSignature($alias);
            $literalExisting = isset($this->literalAliases[$literal]) ? $this->literalAliases[$literal] : null;
            if ($literalExisting !== null && ($literalExisting['port_name'] !== $port['port_name'] || $literalExisting['country_code'] !== $port['country_code'])) {
                unset($this->literalAliases[$literal]);
                $this->literalAmbiguous[$literal] = true;
            } elseif (!isset($this->literalAmbiguous[$literal])) {
                $this->literalAliases[$literal] = $port;
            }
        }
        $existing = isset($this->aliases[$normalized]) ? $this->aliases[$normalized] : null;
        if ($existing !== null && ($existing['port_name'] !== $port['port_name'] || $existing['country_code'] !== $port['country_code'])) {
            if ($authoritative) {
                $this->aliases[$normalized] = $port;
                unset($this->ambiguous[$normalized]);
            } elseif (($existing['source'] ?? '') !== 'builtin') {
                unset($this->aliases[$normalized]);
                $this->ambiguous[$normalized] = true;
            }
            return;
        }
        $this->aliases[$normalized] = $port;
    }

    private function loadBuiltinCatalog()
    {
        foreach (self::catalog() as $definition) {
            $port = array('port_name'=>$definition['port_name'], 'country_code'=>$definition['country_code'], 'port_code'=>$definition['port_code'], 'fk_port'=>0, 'source'=>'builtin');
            $this->addAlias($definition['port_name'], $port, true);
            foreach ($definition['aliases'] as $alias) $this->addAlias($alias, $port, true);
        }
    }

    private function loadLegacyMappings()
    {
        if (!$this->db) return;
        $sql = "SELECT rowid,port_name,port_code,country_code,aliases FROM ".MAIN_DB_PREFIX."chinaplanetsp_port WHERE entity=".$this->entity." AND status=1";
        $res = $this->db->query($sql);
        if (!$res) return;
        while ($row = $this->db->fetch_object($res)) {
            $port = array('port_name'=>$row->port_name, 'country_code'=>$row->country_code, 'port_code'=>$row->port_code, 'fk_port'=>(int)$row->rowid, 'source'=>'legacy');
            $this->addAlias($row->port_name, $port, false);
            foreach (preg_split('/[,\r\n]+/', (string) $row->aliases) as $alias) if (trim($alias) !== '') $this->addAlias($alias, $port, false);
        }
    }

    private function loadAliasTable()
    {
        if (!$this->db) return;
        $existsSql = "SELECT COUNT(*) total FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='".MAIN_DB_PREFIX."chinaplanetsp_port_alias'";
        $existsResult = $this->db->query($existsSql);
        if (!$existsResult) { $this->aliasTableState = 'failed'; return; }
        $exists = $this->db->fetch_object($existsResult);
        if (!$exists || (int)$exists->total !== 1) { $this->aliasTableState = 'missing'; return; }
        $sql = "SELECT a.alias_name,a.normalized_alias,p.rowid,p.port_name,p.port_code,p.country_code";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_port_alias a INNER JOIN ".MAIN_DB_PREFIX."chinaplanetsp_port p ON p.rowid=a.fk_port AND p.entity=a.entity";
        $sql .= " WHERE a.entity=".$this->entity." AND a.status=1 AND p.status=1";
        $res = $this->db->query($sql);
        if (!$res) { $this->aliasTableState = 'failed'; return; }
        $this->aliasTableState = 'loaded';
        while ($row = $this->db->fetch_object($res)) {
            $port = array('port_name'=>$row->port_name, 'country_code'=>$row->country_code, 'port_code'=>$row->port_code, 'fk_port'=>(int)$row->rowid, 'source'=>'database');
            $this->addAlias($row->alias_name ?: $row->normalized_alias, $port, true);
        }
    }

    /** Curated operational catalog. Codes are UN/LOCODE when supplied. */
    public static function catalog()
    {
        return array(
            array('port_name'=>'SOKHNA','country_code'=>'EG','port_code'=>'EGSOK','aliases'=>array('AIN SOKHNA','EL SOKHNA')),
            array('port_name'=>'ALEXANDRIA','country_code'=>'EG','port_code'=>'EGALY','aliases'=>array('ALEXANDIRA')),
            array('port_name'=>'ALEXANDRIA OLD PORT','country_code'=>'EG','port_code'=>'EGALY','aliases'=>array('ALEXANDIRA OLD PORT')),
            array('port_name'=>'ALEXANDRIA TMT','country_code'=>'EG','port_code'=>'EGALY','aliases'=>array('ALEXANDRIA (TMT)')),
            array('port_name'=>'DEKHEILA','country_code'=>'EG','port_code'=>'EGEDK','aliases'=>array('EL DEKHEILA')),
            array('port_name'=>'DAMIETTA','country_code'=>'EG','port_code'=>'EGDAM','aliases'=>array()),
            array('port_name'=>'PORT SAID','country_code'=>'EG','port_code'=>'EGPSD','aliases'=>array()),
            array('port_name'=>'PORT SAID WEST','country_code'=>'EG','port_code'=>'EGPSD','aliases'=>array('HP-SZ-PORT SAID WEST')),
            array('port_name'=>'JEDDAH','country_code'=>'SA','port_code'=>'SAJED','aliases'=>array('JEDDAH (REDSEA GATEWAY)','JEDDAH (REASEA GATEWAY)','JEDDAH (SOUTH)','JEDDAH (DPW/南港)')),
            array('port_name'=>'DAMMAM','country_code'=>'SA','port_code'=>'SADMM','aliases'=>array()),
            array('port_name'=>'RIYADH DRY PORT','country_code'=>'SA','port_code'=>'SARYP','aliases'=>array('RIYADH')),
            array('port_name'=>'JUBAIL','country_code'=>'SA','port_code'=>'SAJUB','aliases'=>array()),
            array('port_name'=>'JIZAN','country_code'=>'SA','port_code'=>'SAGIZ','aliases'=>array('JAZAN')),
            array('port_name'=>'JEBEL ALI','country_code'=>'AE','port_code'=>'AEJEA','aliases'=>array()),
            array('port_name'=>'ABU DHABI','country_code'=>'AE','port_code'=>'AEAUH','aliases'=>array()),
            array('port_name'=>'KHALIFA PORT','country_code'=>'AE','port_code'=>'AEKHL','aliases'=>array('KHALIFA','KHALIFA PORT, ABU DHABI')),
            array('port_name'=>'SHARJAH','country_code'=>'AE','port_code'=>'AESHJ','aliases'=>array('SHARJAH ICD')),
            array('port_name'=>'AJMAN','country_code'=>'AE','port_code'=>'AEAJM','aliases'=>array()),
            array('port_name'=>'KHOR FAKKAN','country_code'=>'AE','port_code'=>'AEKLF','aliases'=>array('KHORFAKKAN','KHOR FAKKAN (ICD)')),
            array('port_name'=>'FUJAIRAH','country_code'=>'AE','port_code'=>'AEFJR','aliases'=>array()),
            array('port_name'=>'UMM AL QUWAIN','country_code'=>'AE','port_code'=>'AEQIW','aliases'=>array('UMM AL QAWAIN','UMM AL QAIWAIN','UMM AL QUWAIN PORT')),
            array('port_name'=>'RAS AL KHAIMAH','country_code'=>'AE','port_code'=>'AERKT','aliases'=>array()),
            array('port_name'=>'HAMAD','country_code'=>'QA','port_code'=>'QAHMD','aliases'=>array('HAMAD PORT')),
            array('port_name'=>'DOHA','country_code'=>'QA','port_code'=>'QADOH','aliases'=>array()),
            array('port_name'=>'AQABA','country_code'=>'JO','port_code'=>'JOAQJ','aliases'=>array()),
            array('port_name'=>'ADEN','country_code'=>'YE','port_code'=>'YEADE','aliases'=>array()),
            array('port_name'=>'HODEIDAH','country_code'=>'YE','port_code'=>'YEHOD','aliases'=>array('HODEIDA')),
            array('port_name'=>'PORT SUDAN','country_code'=>'SD','port_code'=>'SDPZU','aliases'=>array('SUDAN')),
            array('port_name'=>'DJIBOUTI','country_code'=>'DJ','port_code'=>'DJJIB','aliases'=>array()),
            array('port_name'=>'BERBERA','country_code'=>'SO','port_code'=>'SOBBO','aliases'=>array()),
            array('port_name'=>'MOGADISHU','country_code'=>'SO','port_code'=>'SOMGQ','aliases'=>array()),
            array('port_name'=>'UMM QASR','country_code'=>'IQ','port_code'=>'IQUQR','aliases'=>array('UMM北','UMM QASR (北港，靠14 或15号码头)','UMM QASR (南港)','UMM QASR (北港)','UMM QASR (北港，靠20号码头)','UMM QASR (北港，靠27号码头)','UMM QASR 北港20号码头','UMM QASR北港（11号码头）')),
            array('port_name'=>'SHUWAIKH','country_code'=>'KW','port_code'=>'KWSWK','aliases'=>array()),
            array('port_name'=>'SHUAIBA','country_code'=>'KW','port_code'=>'KWSAA','aliases'=>array('KUWAIT (SHUAIBA)')),
            array('port_name'=>'KUWAIT','country_code'=>'KW','port_code'=>'KWKWI','aliases'=>array()),
            array('port_name'=>'BAHRAIN','country_code'=>'BH','port_code'=>'BHBAH','aliases'=>array()),
            array('port_name'=>'SOHAR','country_code'=>'OM','port_code'=>'OMSOH','aliases'=>array()),
            array('port_name'=>'SALALAH','country_code'=>'OM','port_code'=>'OMSLL','aliases'=>array()),
            array('port_name'=>'MUSCAT','country_code'=>'OM','port_code'=>'OMMCT','aliases'=>array()),
            array('port_name'=>'ASHDOD','country_code'=>'PS','port_code'=>null,'aliases'=>array()),
            array('port_name'=>'HAIFA','country_code'=>'PS','port_code'=>null,'aliases'=>array()),
            array('port_name'=>'ALGIERS','country_code'=>'DZ','port_code'=>'DZALG','aliases'=>array('ALGER')),
            array('port_name'=>'ANNABA','country_code'=>'DZ','port_code'=>'DZAAE','aliases'=>array()),
            array('port_name'=>'BEJAIA','country_code'=>'DZ','port_code'=>'DZBJA','aliases'=>array('BEJAIA','BEJAÏA','BOUGIE')),
            array('port_name'=>'DJEN-DJEN','country_code'=>'DZ','port_code'=>'DZDJE','aliases'=>array('DJEN DJEN','DJENDJEN')),
            array('port_name'=>'GHAZAOUET','country_code'=>'DZ','port_code'=>'DZGHZ','aliases'=>array('GHAZAQUET')),
            array('port_name'=>'MOSTAGANEM','country_code'=>'DZ','port_code'=>'DZMOS','aliases'=>array()),
            array('port_name'=>'ORAN','country_code'=>'DZ','port_code'=>'DZORN','aliases'=>array('ORAN CONTAINER TERMINAL')),
            array('port_name'=>'SKIKDA','country_code'=>'DZ','port_code'=>'DZSKI','aliases'=>array()),
            array('port_name'=>'AGADIR','country_code'=>'MA','port_code'=>'MAAGA','aliases'=>array('AGADIR MOROCCO','AGADIR, MOROCCO')),
            array('port_name'=>'CASABLANCA','country_code'=>'MA','port_code'=>'MACAS','aliases'=>array()),
            array('port_name'=>'NADOR','country_code'=>'MA','port_code'=>'MANDR','aliases'=>array()),
            array('port_name'=>'TANGIER','country_code'=>'MA','port_code'=>'MATNG','aliases'=>array('TANGER','TANGIER TANGER MOROCCO')),
            array('port_name'=>'AL KHUMS','country_code'=>'LY','port_code'=>'LYKHO','aliases'=>array('AL KHOMS','EL KHOMS','KHOMS','EL KHUMS')),
            array('port_name'=>'MISURATA','country_code'=>'LY','port_code'=>'LYMRA','aliases'=>array('MSIURATA')),
            array('port_name'=>'BENGHAZI','country_code'=>'LY','port_code'=>'LYBEN','aliases'=>array('BINGAZI')),
            array('port_name'=>'TRIPOLI','country_code'=>'LY','port_code'=>'LYTIP','aliases'=>array()),
            array('port_name'=>'BIZERTE','country_code'=>'TN','port_code'=>'TNBIZ','aliases'=>array()),
            array('port_name'=>'RADES','country_code'=>'TN','port_code'=>'TNRDS','aliases'=>array('RADÈS')),
            array('port_name'=>'SFAX','country_code'=>'TN','port_code'=>'TNSFA','aliases'=>array()),
            array('port_name'=>'SOUSSE','country_code'=>'TN','port_code'=>'TNSUS','aliases'=>array()),
            array('port_name'=>'TUNIS','country_code'=>'TN','port_code'=>'TNTUN','aliases'=>array()),
            array('port_name'=>'CONAKRY','country_code'=>'GN','port_code'=>'GNCKY','aliases'=>array()),
            array('port_name'=>'BANDAR ABBAS','country_code'=>'IR','port_code'=>'IRBND','aliases'=>array()),
            array('port_name'=>'LATTAKIA','country_code'=>'SY','port_code'=>'SYLTK','aliases'=>array('LATAKIA')),
            array('port_name'=>'BEIRUT','country_code'=>'LB','port_code'=>'LBBEY','aliases'=>array()),
            array('port_name'=>'MERSIN','country_code'=>'TR','port_code'=>'TRMER','aliases'=>array()),
            array('port_name'=>'ISTANBUL','country_code'=>'TR','port_code'=>'TRIST','aliases'=>array('ISTANBUL (MARDAS)')),
        );
    }

    /**
     * Return an exact, reviewed built-in alias ownership transition.
     *
     * The migration uses this allowlist to avoid stealing manual or otherwise
     * unexpected aliases while correcting the prior generic Alexandria model.
     *
     * @return array<string,string>|null
     */
    public static function catalogAliasTransition($alias)
    {
        $transitions = array(
            'ALEXANDIRA OLD PORT'=>array('from_port'=>'ALEXANDRIA','from_country'=>'EG','to_port'=>'ALEXANDRIA OLD PORT','to_country'=>'EG'),
            'ALEXANDRIA OLD PORT'=>array('from_port'=>'ALEXANDRIA','from_country'=>'EG','to_port'=>'ALEXANDRIA OLD PORT','to_country'=>'EG'),
            'ALEXANDRIA TMT'=>array('from_port'=>'ALEXANDRIA','from_country'=>'EG','to_port'=>'ALEXANDRIA TMT','to_country'=>'EG'),
        );
        $normalized = self::normalize($alias);
        return isset($transitions[$normalized]) ? $transitions[$normalized] : null;
    }

    /** Return a catalog transition only for a one-destination exact match. */
    public static function exactCatalogAliasTransition($resolution)
    {
        if (!is_array($resolution) || ($resolution['status'] ?? '') !== 'matched' || count($resolution['matches'] ?? array()) !== 1) return null;
        $match = $resolution['matches'][0];
        $method = $match['method'] ?? ($resolution['method'] ?? '');
        if ($method !== 'exact') return null;
        return self::catalogAliasTransition($match['matched_alias'] ?? '');
    }
}
