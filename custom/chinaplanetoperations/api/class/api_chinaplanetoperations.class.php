<?php

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php';

/**
 * Restricted machine-to-machine intake API for the main React website.
 *
 * @access protected
 * @class DolibarrApiAccess {@requires user,external}
 */
class ChinaPlanetOperations extends DolibarrApi
{
    const MAX_BODY_BYTES = 102400;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * Create an internal intake request. This never creates a ThirdParty,
     * proposal, order, shipment, invoice or accounting entry.
     *
     * @return array
     * @url POST website-requests
     */
    public function websiteRequests()
    {
        $apiUser = DolibarrApiAccess::$user;
        if (!$apiUser || (!$apiUser->hasRight('chinaplanetoperations', 'website_intake_create') && empty($apiUser->admin))) {
            throw new RestException(403, 'Website intake permission required.');
        }
        // Read the JSON body explicitly. Dolibarr's bundled Restler maps method
        // parameters before invocation, so a synthetic array parameter can fail
        // with an empty HTTP 500 before our validation and error boundary run.
        $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($length > self::MAX_BODY_BYTES) throw new RestException(413, 'request_too_large');
        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if ($raw === false || strlen($raw) > self::MAX_BODY_BYTES) throw new RestException(413, 'request_too_large');
        $request_data = json_decode($raw, true);
        if (!is_array($request_data)) throw new RestException(400, 'invalid_json');
        try {
            $service = new PublicWebsiteRequestService($this->db);
            $result = $service->create($request_data, $apiUser);
            return array(
                'request_ref' => $result['ref'],
                'status' => $result['status'],
                'duplicate' => (bool) $result['duplicate'],
            );
        } catch (PublicWebsiteRequestException $e) {
            throw new RestException($e->getHttpStatus(), $e->getPublicCode());
        } catch (Throwable $e) {
            dol_syslog('M043 website intake failed: '.get_class($e), LOG_ERR);
            throw new RestException(503, 'intake_service_unavailable');
        }
    }
}
