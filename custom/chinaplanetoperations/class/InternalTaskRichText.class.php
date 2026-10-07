<?php
/** Small HTML allowlist for card descriptions. Images must be private task assets. */
class InternalTaskRichText
{
    public static function clean($html, callable $imageAllowed)
    {
        if (!is_string($html) || strlen($html)>60000) throw new RuntimeException('Invalid description.');
        if ($html==='') return '';
        $dom=new DOMDocument('1.0','UTF-8');
        $previous=libxml_use_internal_errors(true);
        try {$dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
        finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
        $render=function($node) use (&$render,$imageAllowed) {
            if ($node instanceof DOMText) return htmlspecialchars($node->nodeValue,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            if (!($node instanceof DOMElement)) return '';
            $tag=strtolower($node->tagName);
            if (in_array($tag,array('script','style','iframe','object','embed','svg','math','form','input','button','textarea','select','template'),true)) return '';
            if ($tag==='img') {
                $src=$node->getAttribute('src');
                if (!preg_match('/^asset\.php\?action=preview&asset_id=([1-9][0-9]*)$/D',$src,$match) || !$imageAllowed((int)$match[1])) return '';
                return '<img src="asset.php?action=preview&amp;asset_id='.(int)$match[1].'" alt="'.htmlspecialchars(mb_substr($node->getAttribute('alt'),0,255),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">';
            }
            $children='';foreach($node->childNodes as $child)$children.=$render($child);
            if ($tag==='a') {
                $url=trim($node->getAttribute('href'));
                if (!preg_match('/^(https?:\/\/|mailto:)/i',$url)||preg_match('/[\x00-\x20]/',$url)) return $children;
                return '<a href="'.htmlspecialchars($url,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" rel="noopener noreferrer">'.$children.'</a>';
            }
            if (!in_array($tag,array('p','br','strong','b','em','i','u','s','ul','ol','li','blockquote','h2','h3','pre','code','hr'),true)) return $children;
            if ($tag==='br'||$tag==='hr') return '<'.$tag.'>';
            return '<'.$tag.'>'.$children.'</'.$tag.'>';
        };
        $out='';$body=$dom->getElementsByTagName('body')->item(0);if($body)foreach($body->childNodes as $node)$out.=$render($node);
        return $out;
    }
    public static function plain($html)
    {
        return trim(html_entity_decode(strip_tags(preg_replace('/<(\/p|\/li|br|\/h[23]|\/blockquote)[^>]*>/i',"\n",$html)),ENT_QUOTES|ENT_HTML5,'UTF-8'));
    }
}
