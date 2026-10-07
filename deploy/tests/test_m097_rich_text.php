<?php
require_once __DIR__.'/../../custom/chinaplanetoperations/class/InternalTaskRichText.class.php';
$n=0;
function check($test,$label){global $n;if(!$test)throw new RuntimeException('FAIL '.$label);echo 'PASS '.(++$n).' '.$label."\n";}
$allowed=fn($id)=>$id===10;
$clean=InternalTaskRichText::clean('<h2>مراجعة الشحنة</h2><p><strong>Packing</strong> <em>dates</em></p><ul><li>Photos</li></ul>',$allowed);
check(strpos($clean,'مراجعة الشحنة')!==false&&strpos($clean,'<strong>Packing</strong>')!==false,'Arabic and formatting preserved');
check(InternalTaskRichText::plain($clean)==="مراجعة الشحنة\nPacking dates\nPhotos",'canonical plaintext follows rich content');
$clean=InternalTaskRichText::clean('<script>alert(1)</script><svg onload="alert(1)"></svg><p onclick="alert(1)" style="color:red">Safe</p><iframe src="https://example.test"></iframe>',$allowed);
check($clean==='<p>Safe</p>','active content and attributes stripped');
$clean=InternalTaskRichText::clean('<img src="asset.php?action=preview&amp;asset_id=10" alt="Photo"><img src="asset.php?action=preview&amp;asset_id=11"><img src="https://example.test/x"><img src="data:image/png;base64,x">',$allowed);
check($clean==='<img src="asset.php?action=preview&amp;asset_id=10" alt="Photo">','only allowed same-task private image retained');
$clean=InternalTaskRichText::clean('<a href="javascript:alert(1)">bad</a><a href="https://example.test">Good</a>',$allowed);
check($clean==='bad<a href="https://example.test" rel="noopener noreferrer">Good</a>','safe links only');
check(InternalTaskRichText::clean('<p>&lt;script&gt; &amp; text</p>',$allowed)==='<p>&lt;script&gt; &amp; text</p>','literal markup remains escaped');
try{InternalTaskRichText::clean(str_repeat('x',60001),$allowed);check(false,'oversized description');}catch(RuntimeException $e){check(true,'oversized description rejected');}
echo "M097_RICH_TEXT=PASS ASSERTIONS=$n\n";
