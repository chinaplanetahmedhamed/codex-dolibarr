<?php

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

/**
 * REST API for China Planet Logistics shipments and shipment document repository.
 *
 * @access protected
 * @class DolibarrApiAccess {@requires user,external}
 */
class Chinaplanetlogistics extends DolibarrApi
{
    const MAX_UPLOAD_BYTES = 209715200; // 200 MB matching staff upload policy

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * List and search logistics shipments.
     *
     * @param string $query Optional search filter (ref, container_no, booking_no, vessel_name, customer name)
     * @param int    $socid Optional filter by customer thirdparty ID
     * @param int    $limit Maximum records to return (1..100, default 50)
     * @return array List of shipment records
     *
     * @url GET shipments
     * @url GET /
     */
    public function listShipments($query = '', $socid = 0, $limit = 50)
    {
        $user = DolibarrApiAccess::$user;
        if (!$user || !empty($user->socid) || (!$user->hasRight('chinaplanetlogistics', 'read') && empty($user->admin))) {
            throw new RestException(403, 'Permission denied: read access required for logistics shipments');
        }

        $limit = min(max((int) $limit, 1), 100);
        global $conf;
        $entity = !empty($conf->entity) ? (int) $conf->entity : 0;
        if ($entity <= 0) {
            throw new RestException(500, 'Active entity is unavailable');
        }

        $sql = "SELECT s.rowid AS id, s.ref, s.fk_soc, c.nom AS socname, s.booking_no, s.voyage_no, s.vessel_name,";
        $sql .= " s.port_loading AS origin_port, s.port_destination AS destination_port, s.container_no,";
        $sql .= " s.container_type, s.nb_containers, s.fk_statut AS status, s.date_eta, s.date_etd";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetlogistics s";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe c ON c.rowid = s.fk_soc";
        $sql .= " WHERE s.entity = ".(int) $entity;

        $socid = (int) $socid;
        if ($socid > 0) {
            $sql .= " AND s.fk_soc = ".$socid;
        }

        $query = trim((string) $query);
        if ($query !== '') {
            $clean = $this->db->escape($query);
            $sql .= " AND (s.ref LIKE '%".$clean."%' OR s.booking_no LIKE '%".$clean."%' OR s.container_no LIKE '%".$clean."%' OR s.vessel_name LIKE '%".$clean."%' OR c.nom LIKE '%".$clean."%')";
        }

        $sql .= " ORDER BY s.rowid DESC LIMIT ".(int) $limit;

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog('ChinaPlanetLogistics API shipment list query failed: '.$this->db->lasterror(), LOG_ERR);
            throw new RestException(500, 'Could not load shipments');
        }

        $items = array();
        while ($row = $this->db->fetch_object($resql)) {
            $items[] = array(
                'id' => (string) $row->id,
                'ref' => (string) $row->ref,
                'fk_soc' => (string) $row->fk_soc,
                'socname' => (string) ($row->socname ?? ''),
                'booking_no' => (string) ($row->booking_no ?? ''),
                'voyage_no' => (string) ($row->voyage_no ?? ''),
                'vessel_name' => (string) ($row->vessel_name ?? ''),
                'origin_port' => (string) ($row->origin_port ?? ''),
                'destination_port' => (string) ($row->destination_port ?? ''),
                'container_no' => (string) ($row->container_no ?? ''),
                'container_type' => (string) ($row->container_type ?? ''),
                'nb_containers' => (string) ($row->nb_containers ?? ''),
                'status' => (string) ($row->status ?? ''),
                'date_eta' => $row->date_eta ? $this->db->jdate($row->date_eta) : null,
                'date_etd' => $row->date_etd ? $this->db->jdate($row->date_etd) : null,
            );
        }

        return $items;
    }

    /**
     * Fetch a single logistics shipment by ID or reference.
     *
     * @param string $id Shipment numeric ID or reference string (e.g. 'VS-20260920CG')
     * @return array Shipment details
     *
     * @url GET shipments/{id}
     */
    public function getShipment($id)
    {
        $user = DolibarrApiAccess::$user;
        if (!$user || !empty($user->socid) || (!$user->hasRight('chinaplanetlogistics', 'read') && empty($user->admin))) {
            throw new RestException(403, 'Permission denied: read access required for logistics shipments');
        }

        $shipment = new ChinaPlanetLogistics($this->db);
        $result = is_numeric($id) ? $shipment->fetch((int) $id) : $shipment->fetch(0, $id);
        if ($result <= 0 || empty($shipment->id)) {
            throw new RestException(404, "Shipment '".$id."' was not found");
        }

        return array(
            'id' => (string) $shipment->id,
            'ref' => (string) $shipment->ref,
            'fk_soc' => (string) $shipment->fk_soc,
            'container_no' => (string) $shipment->container_no,
            'container_type' => (string) $shipment->container_type,
            'nb_containers' => (string) $shipment->nb_containers,
            'booking_no' => (string) $shipment->booking_no,
            'bl_no' => (string) $shipment->bl_no,
            'shipping_line' => (string) $shipment->shipping_line,
            'vessel_name' => (string) $shipment->vessel_name,
            'voyage_no' => (string) $shipment->voyage_no,
            'port_loading' => (string) $shipment->port_loading,
            'port_destination' => (string) $shipment->port_destination,
            'date_etd' => $shipment->date_etd,
            'date_atd' => $shipment->date_atd,
            'date_eta' => $shipment->date_eta,
            'date_ata' => $shipment->date_ata,
            'status' => (string) $shipment->fk_statut,
            'notes' => (string) $shipment->notes,
            'customer_note' => (string) $shipment->customer_note,
        );
    }

    /**
     * Upload and attach a document directly to a logistics shipment.
     * Stored in the shipment documents directory: /documents/chinaplanetlogistics/ship-{$id}/
     *
     * @param string $id                  Shipment rowid or reference
     * @param string $filename            Target file name
     * @param string $filecontent         Base64 encoded file content
     * @param string $fileencoding        Encoding (must be 'base64')
     * @param string $ref                 Optional reference fallback if id is not passed in URL
     * @param int    $publish_to_customer Whether to publish the document to the customer portal (default 0)
     * @param int    $overwrite           Overwrite existing file with same name (default 1)
     * @return array Upload result
     *
     * @url POST shipments/{id}/documents
     * @url POST upload
     */
    public function uploadDocument($id = '', $filename = '', $filecontent = '', $fileencoding = 'base64', $ref = '', $publish_to_customer = 0, $overwrite = 1)
    {
        $user = DolibarrApiAccess::$user;
        if (!$user || !empty($user->socid) || (!$user->hasRight('chinaplanetlogistics', 'write') && empty($user->admin))) {
            throw new RestException(403, 'Permission denied: write access required to upload shipment documents');
        }

        // Parse JSON body for parameters that Restler may not have bound from php://input
        $rawInput = file_get_contents('php://input');
        $body = !empty($rawInput) ? json_decode($rawInput, true) : null;
        if (is_array($body)) {
            if (empty($id) && !empty($body['id'])) $id = (string) $body['id'];
            if (empty($ref) && !empty($body['ref'])) $ref = (string) $body['ref'];
            if (empty($filename) && !empty($body['filename'])) $filename = (string) $body['filename'];
            if (empty($filecontent) && !empty($body['filecontent'])) $filecontent = (string) $body['filecontent'];
            if (isset($body['publish_to_customer'])) $publish_to_customer = !empty($body['publish_to_customer']) ? 1 : 0;
            if (isset($body['overwrite'])) $overwrite = !empty($body['overwrite']) ? 1 : 0;
        }

        $target = !empty($id) ? $id : $ref;
        if (empty($target)) {
            throw new RestException(400, 'Shipment reference or ID is required');
        }

        if (empty($filename)) {
            throw new RestException(400, 'Filename is required');
        }

        if (empty($filecontent)) {
            throw new RestException(400, 'File content is required');
        }

        // Fetch shipment
        $shipment = new ChinaPlanetLogistics($this->db);
        $fetchRes = is_numeric($target) ? $shipment->fetch((int) $target) : $shipment->fetch(0, $target);
        if ($fetchRes <= 0 || empty($shipment->id)) {
            throw new RestException(404, "Shipment '".$target."' was not found");
        }

        // Validate safe filename & extension policy
        $safeName = cpcc_portal_validate_shipment_filename($filename);
        if ($safeName === '') {
            throw new RestException(400, "Invalid filename or unsupported extension: '".$filename."'");
        }

        // Decode base64
        $content = base64_decode($filecontent, true);
        if ($content === false || strlen($content) === 0) {
            throw new RestException(400, 'Invalid base64 file content');
        }

        $actualSize = strlen($content);
        if ($actualSize > self::MAX_UPLOAD_BYTES) {
            throw new RestException(413, 'File size exceeds maximum staff allowance (200 MB)');
        }

        $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $policy = cpcc_portal_shipment_file_policy();
        $mime = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->buffer($content));
        $allowedMimes = isset($policy[$extension]) ? array_map('strtolower', $policy[$extension]) : array();
        if ($mime === '' || !in_array($mime, $allowedMimes, true)) {
            throw new RestException(400, 'File content does not match its type');
        }

        // Target storage directory: DOL_DATA_ROOT/chinaplanetlogistics/ship-{$id}
        $moduleRoot = DOL_DATA_ROOT.'/chinaplanetlogistics';
        if (!is_dir($moduleRoot) && !dol_mkdir($moduleRoot)) {
            throw new RestException(500, 'Could not create logistics document root');
        }

        $uploadDir = cpcc_portal_shipment_doc_root($shipment->id);
        if (!is_dir($uploadDir) && !dol_mkdir($uploadDir)) {
            throw new RestException(500, 'Could not create shipment document directory');
        }

        if (!cpcc_portal_path_is_within($uploadDir, $moduleRoot)) {
            throw new RestException(400, 'Storage path rejected');
        }

        // Stored name pattern: SHIP-{$id}-{$safeName} matching manual staff upload
        $shipmentPrefix = 'SHIP-'.$shipment->id;
        $storedName = $shipmentPrefix.'-'.$safeName;

        if (empty($overwrite)) {
            $pathinfo = pathinfo($safeName);
            $baseName = !empty($pathinfo['filename']) ? $pathinfo['filename'] : $safeName;
            $ext = !empty($pathinfo['extension']) ? '.'.$pathinfo['extension'] : '';
            $counter = 2;
            while (file_exists($uploadDir.'/'.$storedName)) {
                $storedName = $shipmentPrefix.'-'.$baseName.'-'.$counter.$ext;
                $counter++;
            }
        }

        $destination = $uploadDir.'/'.$storedName;
        $written = file_put_contents($destination, $content);
        if ($written === false || $written !== $actualSize) {
            throw new RestException(500, 'Failed to write file to disk');
        }
        @chmod($destination, 0640);

        // Optional publication to customer gallery/documents portal
        $published = false;
        $publicationId = 0;
        if (!empty($publish_to_customer)) {
            $relativePath = basename($uploadDir).'/'.$storedName;
            $pubRes = cpcc_portal_gallery_publish($this->db, $shipment, $user, $relativePath, 'staff');
            if (!empty($pubRes['ok'])) {
                $published = true;
                $publicationId = (int) ($pubRes['id'] ?? 0);
            } else {
                throw new RestException(500, 'File saved but customer publication failed');
            }
        }

        return array(
            'success' => true,
            'shipment_id' => (string) $shipment->id,
            'shipment_ref' => (string) $shipment->ref,
            'filename' => $storedName,
            'original_filename' => $safeName,
            'size' => $actualSize,
            'customer_visible' => $published,
            'publication_id' => $publicationId > 0 ? $publicationId : null,
        );
    }
}
