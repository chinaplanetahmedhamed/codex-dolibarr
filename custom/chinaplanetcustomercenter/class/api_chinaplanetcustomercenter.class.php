<?php
/**
 * M017 customer-only mobile API.
 *
 * @access public
 */

require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
require_once DOL_DOCUMENT_ROOT.'/includes/restler/framework/Luracast/Restler/Compose.php';
require_once DOL_DOCUMENT_ROOT.'/includes/restler/framework/Luracast/Restler/Defaults.php';
require_once __DIR__.'/../lib/mobile_api.lib.php';
require_once __DIR__.'/../lib/mobile_api_data.lib.php';

/** Keep Restler errors inside the frozen M017 envelope and suppress debug data. */
class CpccMobileApiCompose extends \Luracast\Restler\Compose
{
	public function message(\Luracast\Restler\RestException $exception)
	{
		$details = $exception->getDetails();
		$code = !empty($details['mobile_code']) ? (string) $details['mobile_code'] : $this->codeForStatus((int) $exception->getCode());
		$fieldCodes = !empty($details['field_codes']) && is_array($details['field_codes']) ? $details['field_codes'] : array();
		$retryAfter = !empty($details['retry_after']) ? (int) $details['retry_after'] : 0;
		$requestId = !empty($details['request_id']) ? (string) $details['request_id'] : cpcc_mobile_request_id();
		return cpcc_mobile_error_envelope($code, $fieldCodes, $retryAfter, $requestId);
	}

	private function codeForStatus($status)
	{
		$map = array(400 => 'bad_request', 401 => 'session_expired', 403 => 'forbidden', 404 => 'not_found', 409 => 'conflict', 416 => 'validation', 422 => 'validation', 429 => 'rate_limited');
		return isset($map[$status]) ? $map[$status] : 'server_error';
	}
}

\Luracast\Restler\Defaults::$composeClass = 'CpccMobileApiCompose';

// The Dolibarr API bootstrap exposes its Restler instance as $api while this
// class file is included. Register the debug-mode credential gate before the
// request is handled; direct library/unit includes intentionally skip it.
if (isset($api) && is_object($api) && isset($api->r)) {
	cpcc_mobile_register_debug_auth_gate($api->r);
}

/**
 * Customer-only mobile projection authenticated by extension Bearer tokens.
 *
 * @access public
 * @cache private, no-store
 */
class Chinaplanetcustomercenter extends DolibarrApi
{
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	private function ready()
	{
		if (!cpcc_mobile_schema_ready($this->db)) cpcc_mobile_throw(503, 'server_error');
		header('Cache-Control: private, no-store');
		header('X-Content-Type-Options: nosniff');
		header('Referrer-Policy: no-referrer');
	}

	private function context()
	{
		$this->ready();
		return cpcc_mobile_access_context($this->db);
	}

	private function listEtag($resource, $context, $response)
	{
		$version = hash('sha256', json_encode($response['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		cpcc_mobile_apply_etag(cpcc_mobile_etag($resource, $context, $version));
		return $response;
	}

	/**
	 * @url POST /mobile/v1/auth/login
	 */
	public function mobileLogin($login = null, $password = null, $device_id = null, $device_name = null)
	{
		$this->ready();
		if (cpcc_mobile_debug_logging_enabled()) cpcc_mobile_throw(503, 'server_error');
		$data = cpcc_mobile_request_json(array('login','password','device_id','device_name'), array('login','password','device_id'));
		list($loginValue, $passwordValue, $deviceId, $deviceName) = cpcc_mobile_validate_login_input($data);
		$fingerprint = cpcc_mobile_login_rate_prepare($this->db, $loginValue);
		$candidate = cpcc_mobile_verify_credentials($this->db, $loginValue, $passwordValue);
		if (!$candidate) {
			cpcc_mobile_login_rate_failure($this->db, $fingerprint);
			cpcc_mobile_throw(401, 'invalid_credentials');
		}
		$session = cpcc_mobile_issue_session($this->db, $candidate, $deviceId, $deviceName);
		return cpcc_mobile_envelope(array('access_token' => $session['access_token'], 'refresh_token' => $session['refresh_token'], 'token_type' => 'Bearer', 'access_expires_in' => CPCC_MOBILE_ACCESS_TTL, 'refresh_expires_at' => $session['refresh_expires_at']));
	}

	/**
	 * @url POST /mobile/v1/auth/refresh
	 */
	public function mobileRefresh($refresh_token = null, $device_id = null)
	{
		$this->ready();
		if (cpcc_mobile_debug_logging_enabled()) cpcc_mobile_throw(503, 'server_error');
		$data = cpcc_mobile_request_json(array('refresh_token','device_id'), array('refresh_token','device_id'));
		$session = cpcc_mobile_refresh_session($this->db, (string) $data['refresh_token'], (string) $data['device_id']);
		return cpcc_mobile_envelope(array('access_token' => $session['access_token'], 'refresh_token' => $session['refresh_token'], 'token_type' => 'Bearer', 'access_expires_in' => CPCC_MOBILE_ACCESS_TTL, 'refresh_expires_at' => $session['refresh_expires_at']));
	}

	/**
	 * @url POST /mobile/v1/auth/logout/revoke
	 * @status 204
	 */
	public function mobileLogout()
	{
		$context = $this->context();
		if (!empty($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) cpcc_mobile_throw(422, 'validation', array('body'));
		cpcc_mobile_logout($this->db, $context);
		http_response_code(204);
		return null;
	}

	/** @url GET /mobile/v1/me */
	public function mobileMe()
	{
		$context = $this->context();
		return cpcc_mobile_envelope(cpcc_mobile_me_data($this->db, $context));
	}

	/** @url GET /mobile/v1/dashboard */
	public function mobileDashboard()
	{
		$context = $this->context();
		return $this->listEtag('dashboard', $context, cpcc_mobile_dashboard($this->db, $context));
	}

	/** @url GET /mobile/v1/projects */
	public function mobileProjects($limit = 25, $cursor = '', $updated_since = '')
	{
		$context = $this->context();
		return $this->listEtag('projects', $context, cpcc_mobile_projects($this->db, $context, $limit, $cursor, $updated_since));
	}

	/** @url GET /mobile/v1/projects/{opaqueId} */
	public function mobileProject($opaqueId)
	{
		$context = $this->context();
		return cpcc_mobile_project_detail($this->db, $context, $opaqueId);
	}

	/** @url GET /mobile/v1/shipments */
	public function mobileShipments($limit = 25, $cursor = '', $updated_since = '')
	{
		$context = $this->context();
		return $this->listEtag('shipments', $context, cpcc_mobile_shipments($this->db, $context, $limit, $cursor, $updated_since));
	}

	/** @url GET /mobile/v1/shipments/{opaqueId} */
	public function mobileShipment($opaqueId)
	{
		$context = $this->context();
		return cpcc_mobile_shipment_detail($this->db, $context, $opaqueId);
	}

	/** @url GET /mobile/v1/orders */
	public function mobileOrders($limit = 25, $cursor = '', $updated_since = '')
	{
		$context = $this->context();
		return $this->listEtag('orders', $context, cpcc_mobile_orders($this->db, $context, $limit, $cursor, $updated_since));
	}

	/** @url GET /mobile/v1/account/balances */
	public function mobileBalances()
	{
		$context = $this->context();
		return $this->listEtag('balances', $context, cpcc_mobile_balances($this->db, $context));
	}

	/** @url GET /mobile/v1/account/transactions */
	public function mobileTransactions($limit = 25, $cursor = '', $updated_since = '')
	{
		$context = $this->context();
		return $this->listEtag('transactions', $context, cpcc_mobile_transactions($this->db, $context, $limit, $cursor, $updated_since));
	}

	/** @url GET /mobile/v1/service-invoices */
	public function mobileServiceInvoices($limit = 25, $cursor = '', $updated_since = '')
	{
		$context = $this->context();
		return $this->listEtag('service_invoices', $context, cpcc_mobile_service_invoices($this->db, $context, $limit, $cursor, $updated_since));
	}

	/** @url GET /mobile/v1/documents */
	public function mobileDocuments($limit = 25, $cursor = '', $updated_since = '')
	{
		$context = $this->context();
		return $this->listEtag('documents', $context, cpcc_mobile_documents($this->db, $context, $limit, $cursor, $updated_since));
	}

	/** @url GET /mobile/v1/documents/{opaqueId}/content */
	public function mobileDocumentContent($opaqueId)
	{
		$context = $this->context();
		cpcc_mobile_stream_file(cpcc_mobile_safe_file_record($this->db, $context, $opaqueId));
	}

	/** @url GET /mobile/v1/commercial-documents */
	public function mobileCommercialDocuments($limit = 25, $cursor = '', $updated_since = '')
	{
		$context = $this->context();
		return $this->listEtag('commercial_documents', $context, cpcc_mobile_commercial_documents($this->db, $context, $limit, $cursor, $updated_since));
	}

	/** @url GET /mobile/v1/commercial-documents/{opaqueId} */
	public function mobileCommercialDocument($opaqueId)
	{
		$context = $this->context();
		return cpcc_mobile_commercial_document_detail($this->db, $context, $opaqueId);
	}

	/** @url GET /mobile/v1/reports */
	public function mobileReports($limit = 25, $cursor = '', $updated_since = '')
	{
		$context = $this->context();
		return $this->listEtag('reports', $context, cpcc_mobile_reports($this->db, $context, $limit, $cursor, $updated_since));
	}

	/** @url GET /mobile/v1/reports/{opaqueId} */
	public function mobileReport($opaqueId)
	{
		$context = $this->context();
		return cpcc_mobile_report_detail($this->db, $context, $opaqueId);
	}

	/** @url GET /mobile/v1/reports/{opaqueId}/documents/{documentOpaqueId}/content */
	public function mobileReportDocumentContent($opaqueId, $documentOpaqueId)
	{
		$context = $this->context();
		$reportId = cpcc_mobile_resolve_public_id($this->db, 'report', $context, $opaqueId); if (!$reportId) cpcc_mobile_throw(404, 'not_found');
		cpcc_mobile_stream_file(cpcc_mobile_safe_file_record($this->db, $context, $documentOpaqueId, $reportId));
	}
}
