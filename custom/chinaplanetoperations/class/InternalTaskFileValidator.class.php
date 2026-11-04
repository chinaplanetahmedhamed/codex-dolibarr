<?php

/** Bounded-memory validation for internal task PDF attachments. */
class InternalTaskFileValidator
{
    private const ACTIVE = '#(<\?php|<script|<html|javascript:|/JavaScript|/JS\b|/Launch|/EmbeddedFile|/OpenAction|/AA\b|/RichMedia|/SubmitForm|/ImportData)#i';

    private static function names($bytes)
    {
        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', function ($m) {return chr(hexdec($m[1]));}, $bytes);
    }

    public static function officeXml($path, $name, $extension)
    {
        $reader = new XMLReader();
        $previous = libxml_use_internal_errors(true);
        $rootSeen = false; $override = false;
        $requiredPart = $extension === 'docx' ? '/word/document.xml' : '/xl/workbook.xml';
        $requiredType = $extension === 'docx' ? 'wordprocessingml.document.main+xml' : 'spreadsheetml.sheet.main+xml';
        try {
            if (!$reader->open('zip://'.$path.'#'.$name, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) throw new RuntimeException('Invalid Office XML.');
            $reader->setParserProperty(XMLReader::LOADDTD, false);
            $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE || $reader->nodeType === XMLReader::ENTITY_REF) throw new RuntimeException('Unsafe Office XML.');
                if ($reader->nodeType !== XMLReader::ELEMENT) continue;
                if (!$rootSeen) {
                    $rootSeen = true;
                    if ($name === '[Content_Types].xml' && ($reader->localName !== 'Types' || $reader->namespaceURI !== 'http://schemas.openxmlformats.org/package/2006/content-types')) throw new RuntimeException('Office content type mismatch.');
                    if ($name === substr($requiredPart, 1)) {
                        $expectedRoot = $extension === 'docx' ? 'document' : 'workbook';
                        $expectedNamespace = $extension === 'docx' ? 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' : 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
                        if ($reader->localName !== $expectedRoot || $reader->namespaceURI !== $expectedNamespace) throw new RuntimeException('Office root part mismatch.');
                    }
                }
                if ($name === '[Content_Types].xml' && $reader->localName === 'Override' && $reader->namespaceURI === 'http://schemas.openxmlformats.org/package/2006/content-types' && $reader->getAttribute('PartName') === $requiredPart && strpos((string)$reader->getAttribute('ContentType'), $requiredType) !== false) $override = true;
                if (substr($name, -5) === '.rels' && $reader->localName === 'Relationship') {
                    if (strtolower((string)$reader->getAttribute('TargetMode')) === 'external') throw new RuntimeException('External Office relationships are not allowed.');
                    $type = strtolower((string)$reader->getAttribute('Type'));
                    // Core properties use the OPC "package" namespace too. They
                    // are ordinary document metadata, not an embedded package.
                    $coreProperties = $type === 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
                    if (strpos($type, 'oleobject') !== false || (strpos($type, 'package') !== false && !$coreProperties) || strpos($type, 'attachedtemplate') !== false) throw new RuntimeException('Embedded Office relationships are not allowed.');
                }
            }
            if (!$rootSeen || libxml_get_errors()) throw new RuntimeException('Invalid Office XML.');
            if ($name === '[Content_Types].xml' && !$override) throw new RuntimeException('Office content type mismatch.');
        } finally {
            $reader->close(); libxml_clear_errors(); libxml_use_internal_errors($previous);
        }
    }

    /** Only known validation failures may become user-facing messages. */
    public static function errorTranslationKey(Throwable $error)
    {
        $keys = array(
            'External Office relationships are not allowed.' => 'InternalTaskOfficeExternalLinks',
            'Embedded Office relationships are not allowed.' => 'InternalTaskOfficeEmbeddedContent',
            'Active Office content is not allowed.' => 'InternalTaskOfficeEmbeddedContent',
            'Unexpected Office package part.' => 'InternalTaskOfficeUnsupportedContent',
            'Invalid Office XML.' => 'InternalTaskOfficeInvalid',
            'Invalid Office file.' => 'InternalTaskOfficeInvalid',
            'Invalid Office package prefix.' => 'InternalTaskOfficeInvalid',
            'Invalid Office package ending.' => 'InternalTaskOfficeInvalid',
            'Office content type mismatch.' => 'InternalTaskOfficeInvalid',
            'Office root part mismatch.' => 'InternalTaskOfficeInvalid',
            'Office archive is too large.' => 'InternalTaskOfficeExpandedLimit',
            'Unsafe Office archive.' => 'InternalTaskOfficeArchiveUnsafe',
            'Double or hidden file extensions are not allowed.' => 'InternalTaskFileNameInvalid',
            'Unsafe file name.' => 'InternalTaskFileNameInvalid',
            'Task file quota exceeded.' => 'InternalTaskFileQuota',
        );
        return $keys[$error->getMessage()] ?? 'InternalTaskValidationError';
    }

    public static function pdf($path, $size)
    {
        $head = file_get_contents($path, false, null, 0, 1024);
        $tail = file_get_contents($path, false, null, max(0, $size - 2048), 2048);
        if (!preg_match('/^%PDF-1\.[0-7][\r\n]/', $head) || !preg_match('/startxref\s+\d+\s+%%EOF\s*$/s', $tail)) throw new RuntimeException('Invalid PDF content.');
        $stream = fopen($path, 'rb');
        if (!$stream) throw new RuntimeException('Unable to inspect PDF.');
        try {
            $carry = ''; $object = false; $endObject = false; $xref = false; $trailer = false; $xrefStream = false; $filters = 0;
            // Scan raw bytes including binary streams, retaining overlap for split names/tokens.
            $filterPattern = '#/(?:F|\x2346)(?:i|\x2369)(?:l|\x236[cC])(?:t|\x2374)(?:e|\x2365)(?:r|\x2372)\b#';
            while (!feof($stream)) {
                $block = fread($stream, 65536);
                if ($block === false) throw new RuntimeException('Unable to inspect PDF.');
                $bytes = $carry.$block;
                $cut = feof($stream) ? strlen($bytes) : max(0, strlen($bytes) - 256);
                $names = self::names($bytes);
                if (preg_match(self::ACTIVE, $names)) throw new RuntimeException('Active PDF content is not allowed.');
                $object = $object || preg_match('/\d+\s+\d+\s+obj\b/', $bytes);
                $endObject = $endObject || preg_match('/\bendobj\b/', $bytes);
                $xref = $xref || preg_match('/\bxref\s+\d+\s+\d+\s+/', $bytes);
                $trailer = $trailer || preg_match('/\btrailer\s*<</', $bytes);
                $xrefStream = $xrefStream || preg_match('#/Type\s*/XRef\b#', $names);
                preg_match_all($filterPattern, $bytes, $matches, PREG_OFFSET_CAPTURE);
                foreach ($matches[0] as $match) if ($match[1] < $cut) $filters++;
                $carry = substr($bytes, $cut);
            }
            if (!$object || !$endObject || (!($xref && $trailer) && !$xrefStream)) throw new RuntimeException('Invalid PDF content.');
            rewind($stream);
            $buffer = ''; $parsed = 0;
            while (true) {
                if (!preg_match('/(?<![A-Za-z])stream(?:\r\n|\n|\r)/', $buffer, $start, PREG_OFFSET_CAPTURE)) {
                    if (feof($stream)) break;
                    // Only the dictionary immediately before a stream is needed.
                    $buffer = substr($buffer, -131072).fread($stream, 65536);
                    continue;
                }
                $header = self::names(substr($buffer, 0, $start[0][1]));
                $buffer = substr($buffer, $start[0][1] + strlen($start[0][0]));
                $objectPos = strrpos($header, 'endobj');
                if ($objectPos !== false) $header = substr($header, $objectPos + 6);
                $filter = '';
                if (preg_match('#/Filter\b#', $header)) {
                    if (preg_match_all('#/Filter\s*(/[A-Za-z0-9]+|\[[^\]]+\])#s', $header, $fm) !== 1) throw new RuntimeException('Unsupported PDF filter reference.');
                    preg_match_all('#/([A-Za-z0-9]+)#', $fm[1][0], $names);
                    if (count($names[1]) !== 1) throw new RuntimeException('Chained PDF stream filters are not allowed.');
                    $filter = $names[1][0]; $parsed++;
                    if (!in_array($filter, array('FlateDecode','DCTDecode','JPXDecode','CCITTFaxDecode','JBIG2Decode'), true)) throw new RuntimeException('Unsupported PDF stream filter.');
                }
                $compressed = ''; $bodySize = 0;
                while (true) {
                    $ended = preg_match('/(?:\r\n|\n|\r)endstream\b/', $buffer, $end, PREG_OFFSET_CAPTURE);
                    if ($ended) {
                        $body = substr($buffer, 0, $end[0][1]);
                        $ending = substr($end[0][0], 0, -9);
                        $buffer = substr($buffer, $end[0][1] + strlen($end[0][0]));
                    } else {
                        if (feof($stream)) throw new RuntimeException('Unterminated PDF stream.');
                        $take = max(0, strlen($buffer) - 32);
                        $body = substr($buffer, 0, $take); $buffer = substr($buffer, $take);
                    }
                    $bodySize += strlen($body);
                    if ($filter === 'FlateDecode') {
                        if ($bodySize > 11 * 1024 * 1024) throw new RuntimeException('Compressed PDF stream exceeds validation limit.');
                        $compressed .= $body;
                    }
                    if ($ended) break;
                    $buffer .= fread($stream, 65536);
                }
                if ($filter === 'FlateDecode') {
                    // The final EOL can belong to a compressed stream's declared length.
                    if (preg_match('#/Length\s+(\d+)\s*(/|>>)#', $header, $length)) {
                        $declared = (int)$length[1];
                        if ($declared > $bodySize && $declared - $bodySize <= strlen($ending)) $compressed .= substr($ending, 0, $declared - $bodySize);
                        elseif ($declared < $bodySize) {
                            if (trim(substr($compressed, $declared), "\r\n\t ") !== '') throw new RuntimeException('PDF stream length mismatch.');
                            $compressed = substr($compressed, 0, $declared);
                        } elseif ($declared > $bodySize) throw new RuntimeException('PDF stream length mismatch.');
                    }
                    $inflated = @gzuncompress($compressed, 10 * 1024 * 1024);
                    if ($inflated === false) $inflated = @gzinflate($compressed, 10 * 1024 * 1024);
                    if ($inflated === false) throw new RuntimeException('Unsupported compressed PDF stream.');
                    if (preg_match(self::ACTIVE, self::names($inflated))) throw new RuntimeException('Active compressed PDF content is not allowed.');
                    unset($inflated, $compressed);
                }
            }
            if ($filters !== $parsed) throw new RuntimeException('Unsupported PDF filter reference.');
        } finally {fclose($stream);}
    }
}
