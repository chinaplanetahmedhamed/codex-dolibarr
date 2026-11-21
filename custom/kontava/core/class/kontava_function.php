<?php

/**
	 *  Return Id of widget as defined in llx_boxes_def
	 *
	 *  @param	string		$fileName		Nom du fichier php du widget
	 *  @param	int			$zone			Name or area (0 for Homepage, 99 for Kontava)
	 *  @param  int         $fk_user	  	Id of user
	 *  @return bool       	                État du widget (1 = déplié, 0 = replié)
	 */
    function getWidgetId($fileName) {
        global $db;
        $fullName = $fileName."@kontava";
        $sql = "SELECT d.rowid as box_id, d.file";
        $sql .= " FROM ".$db->prefix()."boxes_def as d";
        $sql .= " WHERE d.file = '".$fullName."'";
    
        $resql = $db->query($sql);
        if ($resql) {
            
                $obj = $db->fetch_object($resql);
                dol_syslog("L'ID du widget est : ".$obj->box_id, LOG_DEBUG);
                return $obj->box_id;
        }
        dol_syslog("Le widget n'existe pas dans la table llx_boxes_def", LOG_ERR);
    
        return NULL; // Par défaut, affiché
    }
   

/**
	 *  Return state of 0=fold / 1=unfold of a widget for a given user on a given screen (Home or Kontava)
	 *
	 *  @param	int		    $box_id			id du widget
	 *  @param	int			$zone			Name or area (0 for Homepage, 99 for Kontava)
	 *  @param  int         $fk_user	  	Id of user
	 *  @return bool       	                État du widget (1 = déplié, 0 = replié)
	 */
function getWidgetState($box_id, $zone, $fk_user) {
    global $db, $user;

    $entity = $user->entity;
    $sql = "SELECT unfold FROM ".MAIN_DB_PREFIX."kontava_boxes 
            WHERE box_id = ".(int)$box_id."
            AND zone = ".(int)$zone." 
            AND fk_user = ".(int)$fk_user." 
            AND entity = ".(int)$entity;

    $resql = $db->query($sql);
    if ($resql) {
        $num = $db->num_rows($resql);
        if ($num>0) {
            $obj = $db->fetch_object($resql);
            dol_syslog("L'état unfold est  : ".$obj->unfold, LOG_DEBUG);
            return $obj->unfold;
        }
    }
    dol_syslog("L'état unfold n'existe pas, on la met par défaut à 1", LOG_DEBUG);

    return 1; // Par défaut, affiché
}

/**
	 *  Return 1 ou 0 s'il a bien pu écrire ou mettre à jour la db
	 *
	 *  @param	int		    $box_id			id du widget
	 *  @param	int			$zone			Name or area (0 for Homepage, 99 for Kontava)
	 *  @param  int         $fk_user	  	Id of user
     *  @param  bool        $unfold         État du widget (1 = déplié, 0 = replié)
	 *  @return bool       	                
	 */
function updateWidgetState($box_id, $zone, $fk_user, $unfold) {
    global $db, $user;

    $entity = $user->entity;  // Récupère l'ID de l'entreprise

    // Vérifier si une entrée existe déjà pour cet utilisateur et ce widget
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."kontava_boxes 
            WHERE box_id = ".(int)$box_id." 
            AND zone = ".(int)$zone." 
            AND fk_user = ".(int)$fk_user." 
            AND entity = ".(int)$entity;

    $resql = $db->query($sql);

    if ($resql && $db->num_rows($resql) > 0) {
        // Mise à jour de l'état existant
        $sql = "UPDATE ".MAIN_DB_PREFIX."kontava_boxes 
                SET unfold = ".(int)$unfold." 
                WHERE box_id = ".(int)$box_id."
                AND zone = ".(int)$zone."
                AND fk_user = ".(int)$fk_user."
                AND entity = ".(int)$entity;
    } else {
        // Insertion d'un nouvel état
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."kontava_boxes (entity, box_id, zone, fk_user, unfold) 
                VALUES (".(int)$entity.", ".(int)$box_id.", ".(int)$zone.", ".(int)$fk_user.", ".(int)$unfold.")";
    }

    if ($db->query($sql)) {
        return true;
    } else {
        dol_syslog("Erreur SQL : " . $db->lasterror(), LOG_ERR);
        return false;
    }
}

/**
 * Extrait la valeur d'une constante Dolibarr depuis un dump SQL.
 *
 * @param string $sqlContent Contenu du fichier SQL (ex: dump Dolibarr)
 * @param string $constName Nom exact de la constante à rechercher
 * @return string|null Valeur trouvée, ou null si absente
 */
function extractDolibarrConstFromSQL($sqlContent, $constName)
{
    $constName = strtoupper(trim($constName));

    if (!preg_match_all('/INSERT\s+INTO\s+[`"]?llx_const[`"]?.*?VALUES\s*(.*?);/is', $sqlContent, $blocks)) {
        return null;
    }

    $normalize = function($f) {
        $f = trim($f);
        if (preg_match("/^'(.*)'$/s", $f, $m)) {
            return str_replace("''", "'", $m[1]);
        } elseif (preg_match('/^"(.*)"$/s', $f, $m2)) {
            return str_replace('""', '"', $m2[1]);
        }
        return $f;
    };

    foreach ($blocks[1] as $valuesText) {
        $txt = trim($valuesText);
        $tuples = preg_split("/\\),\\s*\\(/", $txt);
        if (!$tuples) continue;

        $tuples[0] = preg_replace('/^\s*\(/', '', $tuples[0]);
        $tuples[count($tuples) - 1] = preg_replace('/\)\s*$/', '', $tuples[count($tuples) - 1]);

        foreach ($tuples as $tuple) {
            $fields = [];
            $cur = '';
            $inQuote = false;
            $quoteChar = '';
            $len = strlen($tuple);

            for ($i = 0; $i < $len; $i++) {
                $c = $tuple[$i];
                if (($c === "'" || $c === '"')) {
                    if (!$inQuote) {
                        $inQuote = true;
                        $quoteChar = $c;
                    } else if ($quoteChar === $c) {
                        $next = ($i + 1 < $len) ? $tuple[$i + 1] : '';
                        if ($next === $quoteChar) {
                            $cur .= $quoteChar;
                            $i++;
                            continue;
                        } else {
                            $inQuote = false;
                        }
                    }
                } elseif ($c === ',' && !$inQuote) {
                    $fields[] = trim($cur);
                    $cur = '';
                    continue;
                }
                $cur .= $c;
            }
            if ($cur !== '') $fields[] = trim($cur);

            if (isset($fields[1])) {
                $name = strtoupper($normalize($fields[1]));
                if ($name === $constName) {
                    if (isset($fields[3])) {
                        return $normalize($fields[3]);
                    } else {
                        foreach ($fields as $f) {
                            $nf = $normalize($f);
                            if (preg_match('/[A-Za-z0-9._-]+/', $nf)) return $nf;
                        }
                    }
                }
            }
        }
    }

    if (preg_match(
        "/\(\s*\d+\s*,\s*'".preg_quote($constName, '/')."'\s*,\s*\d+\s*,\s*'([^']+)'/i",
        $sqlContent, $m2
    )) {
        return $m2[1];
    }

    return null;
}

?>