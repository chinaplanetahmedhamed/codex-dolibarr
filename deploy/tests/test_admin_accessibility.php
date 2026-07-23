<?php
function channel($value)
{
    $value /= 255;
    return $value <= 0.04045 ? $value / 12.92 : pow(($value + 0.055) / 1.055, 2.4);
}

function luminance($hex)
{
    $hex = ltrim($hex, '#');
    $rgb = array_map('hexdec', str_split($hex, 2));
    return 0.2126 * channel($rgb[0]) + 0.7152 * channel($rgb[1]) + 0.0722 * channel($rgb[2]);
}

function contrast($foreground, $background)
{
    $a = luminance($foreground);
    $b = luminance($background);
    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
}

function accessibilityAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL '.$message.PHP_EOL);
        exit(1);
    }
    print 'PASS '.$message.PHP_EOL;
}

$pairs = array(
    'dark header secondary text'=>array('#82a9c5', '#041126'),
    'section subtitle'=>array('#b9d9ec', '#093050'),
    'light panel body text'=>array('#183147', '#edf7fd'),
    'light panel secondary text'=>array('#52697d', '#edf7fd'),
    'light panel links'=>array('#07579a', '#edf7fd'),
    'input text'=>array('#172c3d', '#ffffff'),
    'input placeholder'=>array('#596d80', '#ffffff'),
    'active navigation worst gradient point'=>array('#ffffff', '#08699a'),
    'keyboard focus indicator'=>array('#ffbf47', '#041126'),
	'dark hero heading'=>array('#ffffff', '#174e66'),
	'dark hero supporting text'=>array('#d7e9ef', '#174e66'),
	'dark hero eyebrow'=>array('#f2cc72', '#174e66'),
);
foreach ($pairs as $label=>$colors) {
    $ratio = contrast($colors[0], $colors[1]);
    accessibilityAssert($ratio >= 4.5, $label.' contrast '.number_format($ratio, 2).':1 meets WCAG AA');
}

$css = file_get_contents(dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/css/admin_dashboard.css');
accessibilityAssert(strpos($css, 'display:flow-root') !== false, 'legacy floating Dolibarr forms remain inside the light panel');
accessibilityAssert(strpos($css, ':focus-visible') !== false, 'keyboard focus indicator is defined');
accessibilityAssert(strpos($css, 'prefers-reduced-motion:reduce') !== false, 'reduced-motion preference is supported');
accessibilityAssert(strpos($css, 'Semantic contrast contract') !== false, 'dark-surface heading colors are centrally scoped');
accessibilityAssert(strpos($css, ':is(.cpcc-hero,.cpcc-page-head,.cpl-detail-hero,.cpl-command-hero)') !== false, 'dark-surface rules do not affect headings on white panels');

$finance = file_get_contents(dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/admin/financial.php');
accessibilityAssert(substr_count($finance, 'aria-label=') >= 20, 'financial form controls expose accessible names');

print 'Admin accessibility tests passed'.PHP_EOL;
