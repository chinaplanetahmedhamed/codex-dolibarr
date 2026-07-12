<?php

function cpui_escape($value)
{
    return function_exists('dol_escape_htmltag') ? dol_escape_htmltag((string) $value) : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cpui_dashboard_shell_open($title, $subtitle = '', $attributes = array())
{
    $attrs = '';
    foreach ($attributes as $name => $value) {
        if (!preg_match('/^(data-[a-z0-9-]+|aria-[a-z0-9-]+)$/', $name)) continue;
        $attrs .= ' '.cpui_escape($name).'="'.cpui_escape($value).'"';
    }
    return '<main class="cpui-shell"'.$attrs.'><header class="cpui-shell__header"><div><h1>'.cpui_escape($title).'</h1>'
        .($subtitle !== '' ? '<p>'.cpui_escape($subtitle).'</p>' : '').'</div></header><div class="cpui-dashboard-grid">';
}

function cpui_dashboard_shell_close()
{
    return '</div></main>';
}

function cpui_badge($label, $status = 'neutral')
{
    $allowed = array('neutral', 'info', 'success', 'warning', 'critical', 'waiting', 'unknown');
    if (!in_array($status, $allowed, true)) $status = 'unknown';
    $icons = array('neutral' => '●', 'info' => 'ℹ', 'success' => '✓', 'warning' => '▲', 'critical' => '!', 'waiting' => '◷', 'unknown' => '?');
    return '<span class="cpui-badge cpui-badge--'.$status.'"><span aria-hidden="true">'.$icons[$status].'</span> '.cpui_escape($label).'</span>';
}

function cpui_kpi_card($label, $value, $options = array())
{
    $status = isset($options['status']) ? $options['status'] : 'neutral';
    $unit = isset($options['unit']) ? $options['unit'] : '';
    $meta = isset($options['meta']) ? $options['meta'] : '';
    $badge = isset($options['badge']) ? cpui_badge($options['badge'], $status) : '';
    return '<section class="cpui-card cpui-kpi" aria-label="'.cpui_escape($label).'">'
        .'<div class="cpui-kpi__top"><span class="cpui-kpi__label">'.cpui_escape($label).'</span>'.$badge.'</div>'
        .'<div class="cpui-kpi__value"><bdi>'.cpui_escape($value).'</bdi>'.($unit !== '' ? ' <small>'.cpui_escape($unit).'</small>' : '').'</div>'
        .($meta !== '' ? '<p class="cpui-kpi__meta">'.cpui_escape($meta).'</p>' : '').'</section>';
}

function cpui_empty_state($title, $message = '')
{
    return '<section class="cpui-state cpui-state--empty"><span aria-hidden="true">◇</span><h2>'.cpui_escape($title).'</h2>'
        .($message !== '' ? '<p>'.cpui_escape($message).'</p>' : '').'</section>';
}

function cpui_partial_state($title, $message, $correlationId = '')
{
    return '<section class="cpui-state cpui-state--partial" role="status">'.cpui_badge($title, 'warning')
        .'<p>'.cpui_escape($message).'</p>'
        .($correlationId !== '' ? '<small>'.cpui_escape($correlationId).'</small>' : '').'</section>';
}

function cpui_freshness($label, $isoTimestamp, $state = 'info')
{
    $safe = cpui_escape($isoTimestamp);
    return '<span class="cpui-freshness">'.cpui_badge($label, $state).' <time datetime="'.$safe.'">'.$safe.'</time></span>';
}

