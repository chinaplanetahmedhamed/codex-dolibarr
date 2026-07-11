<?php

/**
 * Idempotent forward-only schema migration for REL-2026-07-12-CUSTOM-MODULES.
 *
 * This library intentionally contains no rollback or data-changing statements.
 */
function rel20260712Identifier($value)
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        throw new RuntimeException('Unsafe SQL identifier');
    }
    return '`'.$value.'`';
}

function rel20260712ColumnExists(mysqli $db, $schema, $table, $column)
{
    $stmt = $db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->bind_param('sss', $schema, $table, $column);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function rel20260712TableExists(mysqli $db, $schema, $table)
{
    $stmt = $db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    $stmt->bind_param('ss', $schema, $table);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function rel20260712IndexExists(mysqli $db, $schema, $table, $index)
{
    $stmt = $db->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->bind_param('sss', $schema, $table, $index);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function rel20260712Exec(mysqli $db, $sql, array &$actions, $label)
{
    if (!$db->query($sql)) {
        throw new RuntimeException($label.': '.$db->error);
    }
    $actions[] = $label;
}

function rel20260712Apply(mysqli $db, $schema, $prefix)
{
    $actions = array();
    $logistics = $prefix.'chinaplanetlogistics';
    $card = $prefix.'cpkanban_card';
    $asset = $prefix.'cpkanban_asset';

    if (!rel20260712TableExists($db, $schema, $logistics)) {
        throw new RuntimeException('Required existing logistics table is missing: '.$logistics);
    }
    if (!rel20260712TableExists($db, $schema, $card)) {
        throw new RuntimeException('Required existing Kanban card table is missing: '.$card);
    }

    $columns = array(
        'fk_project' => 'integer DEFAULT NULL AFTER `fk_soc`',
        'freetime_days' => 'integer DEFAULT NULL AFTER `date_ata`',
        'customer_note' => 'text DEFAULT NULL AFTER `notes`',
    );
    foreach ($columns as $name => $definition) {
        if (!rel20260712ColumnExists($db, $schema, $logistics, $name)) {
            rel20260712Exec($db, 'ALTER TABLE '.rel20260712Identifier($logistics).' ADD COLUMN '.rel20260712Identifier($name).' '.$definition, $actions, 'add '.$logistics.'.'.$name);
        }
    }
    if (!rel20260712IndexExists($db, $schema, $logistics, 'idx_chinaplanetlogistics_fk_project')) {
        rel20260712Exec($db, 'ALTER TABLE '.rel20260712Identifier($logistics).' ADD INDEX `idx_chinaplanetlogistics_fk_project` (`fk_project`)', $actions, 'add logistics project index');
    }

    if (!rel20260712TableExists($db, $schema, $asset)) {
        $sql = 'CREATE TABLE '.rel20260712Identifier($asset).' ('
            .'`rowid` integer AUTO_INCREMENT PRIMARY KEY,'
            .'`entity` integer DEFAULT 1 NOT NULL,'
            .'`fk_soc` integer NOT NULL,'
            .'`fk_card` integer NOT NULL,'
            .'`fk_shipment` integer DEFAULT NULL,'
            .'`parent_asset_id` integer DEFAULT NULL,'
            .'`asset_type` varchar(32) NOT NULL,'
            .'`document_type` varchar(64) DEFAULT NULL,'
            .'`original_name` varchar(255) NOT NULL,'
            .'`stored_name` varchar(255) NOT NULL,'
            .'`mime_type` varchar(128) NOT NULL,'
            .'`file_size` bigint DEFAULT 0 NOT NULL,'
            .'`caption` varchar(255) DEFAULT NULL,'
            .'`milestone` varchar(64) DEFAULT NULL,'
            .'`version_no` integer DEFAULT 1 NOT NULL,'
            ."`review_status` varchar(24) DEFAULT 'under_review' NOT NULL,"
            .'`customer_visible` tinyint DEFAULT 0 NOT NULL,'
            .'`is_cover` tinyint DEFAULT 0 NOT NULL,'
            .'`status` tinyint DEFAULT 1 NOT NULL,'
            .'`fk_user_creat` integer NOT NULL,'
            .'`fk_user_modif` integer DEFAULT NULL,'
            .'`date_creation` datetime NOT NULL,'
            .'`tms` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            .') ENGINE=InnoDB';
        rel20260712Exec($db, $sql, $actions, 'create '.$asset);
    }

    $requiredAssetColumns = array('rowid', 'entity', 'fk_soc', 'fk_card', 'fk_shipment', 'parent_asset_id', 'asset_type', 'document_type', 'original_name', 'stored_name', 'mime_type', 'file_size', 'caption', 'milestone', 'version_no', 'review_status', 'customer_visible', 'is_cover', 'status', 'fk_user_creat', 'fk_user_modif', 'date_creation', 'tms');
    foreach ($requiredAssetColumns as $column) {
        if (!rel20260712ColumnExists($db, $schema, $asset, $column)) {
            throw new RuntimeException('Existing asset table has an unsupported partial schema; missing '.$column);
        }
    }

    $indexes = array(
        'idx_cpkasset_owner' => '(`entity`,`fk_soc`,`fk_card`,`status`)',
        'idx_cpkasset_shipment' => '(`entity`,`fk_soc`,`fk_shipment`)',
        'idx_cpkasset_public' => '(`entity`,`fk_soc`,`fk_card`,`customer_visible`,`review_status`,`status`)',
        'idx_cpkasset_cover' => '(`entity`,`fk_soc`,`fk_card`,`is_cover`,`status`)',
        'idx_cpkasset_parent' => '(`parent_asset_id`)',
    );
    foreach ($indexes as $name => $definition) {
        if (!rel20260712IndexExists($db, $schema, $asset, $name)) {
            rel20260712Exec($db, 'ALTER TABLE '.rel20260712Identifier($asset).' ADD INDEX '.rel20260712Identifier($name).' '.$definition, $actions, 'add '.$asset.'.'.$name);
        }
    }

    return $actions;
}
