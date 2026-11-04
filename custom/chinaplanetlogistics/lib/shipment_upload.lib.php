<?php
/**
 * Store a complete legacy shipment upload under a name that cannot replace existing bytes.
 * Caller owns actor/entity checks, MIME validation and shipment-root confinement.
 * Suffixes are storage names; shared-document revision IDs remain separate.
 * Requires same-filesystem hard links; unsupported storage fails closed.
 *
 * @return string Stored basename, returned only after all bytes are written and linked.
 */
function chinaplanetlogistics_store_new_upload($directory, $shipmentId, $safeName, $content)
{
    if ((int) $shipmentId <= 0 || $safeName === '' || $safeName === '.' || $safeName === '..'
        || basename($safeName) !== $safeName || strpos($safeName, '\\') !== false
        || strpos($safeName, "\0") !== false || $content === '') {
        throw new InvalidArgumentException('Invalid shipment upload.');
    }
    $root = realpath($directory);
    if ($root === false || !is_dir($root)) throw new RuntimeException('Shipment upload storage unavailable.');
    $temporary = @tempnam($root, '.cpl-upload-');
    if ($temporary === false) throw new RuntimeException('Shipment upload storage unavailable.');
    $handle = null;
    try {
        if (dirname($temporary) !== $root) throw new RuntimeException('Shipment upload storage unavailable.');
        $handle = @fopen($temporary, 'wb');
        if ($handle === false) throw new RuntimeException('Shipment upload storage unavailable.');
        $offset = 0;
        $size = strlen($content);
        while ($offset < $size) {
            $written = fwrite($handle, substr($content, $offset, 1048576));
            if ($written === false || $written === 0) throw new RuntimeException('Shipment upload write failed.');
            $offset += $written;
        }
        if (!fflush($handle) || !fclose($handle)) throw new RuntimeException('Shipment upload write failed.');
        if (!chmod($temporary, 0640)) throw new RuntimeException('Shipment upload permissions unavailable.');
        $parts = pathinfo($safeName);
        $base = !empty($parts['filename']) ? $parts['filename'] : $safeName;
        $extension = !empty($parts['extension']) ? '.'.$parts['extension'] : '';
        $prefix = 'SHIP-'.(int) $shipmentId.'-';
        for ($version = 1; $version <= 10000; $version++) {
            $stored = $version === 1 ? $prefix.$safeName : $prefix.$base.'-'.$version.$extension;
            $destination = $root.'/'.$stored;
            // link() atomically refuses every occupied name, including dangling symlinks.
            // rename() can replace bytes; PHP fopen('x') may resolve a dangling link first.
            if (@link($temporary, $destination)) return $stored;
            clearstatcache(true, $destination);
            if (file_exists($destination) || is_link($destination)) continue;
            throw new RuntimeException('Shipment upload storage unavailable.');
        }
        throw new RuntimeException('Shipment upload version limit reached.');
    } finally {
        if (is_resource($handle)) fclose($handle);
        // Cleanup only this upload's staging name, never an occupied final name.
        @unlink($temporary);
    }
}
