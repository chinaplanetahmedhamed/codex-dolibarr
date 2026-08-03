#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly HELPER='/usr/local/sbin/chinaplanetos-m041-delivery'
readonly ARTIFACT_SHA='6ee49c1fb298aaa248d56ce771d37d018001b1fc56c0813234e836d8facc7beb'
ENVIRONMENT=''
INBOX=''

fail() { echo "M041_SSH_DISPATCH=FAIL REASON=$1" >&2; return 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

[[ $# -eq 0 ]] || fail 'NO_ARGUMENTS_ALLOWED'
case "$(basename "$0")" in
	chinaplanetos-m041-staging-ssh)
		ENVIRONMENT='staging'; INBOX='/home/cp-m041-staging/inbox'
		[[ $(id -un) == 'cp-m041-staging' ]] || fail 'ACCOUNT_MISMATCH'
		;;
	chinaplanetos-m041-production-ssh)
		ENVIRONMENT='production'; INBOX='/home/cp-m041-production/inbox'
		[[ $(id -un) == 'cp-m041-production' ]] || fail 'ACCOUNT_MISMATCH'
		;;
	*) fail 'DISPATCH_PATH_INVALID' ;;
esac
[[ -d "$INBOX" && ! -L "$INBOX" && $(stat -c '%U:%G %a' "$INBOX") == "$(id -un):$(id -gn) 700" ]] || fail 'INBOX_INVALID'

write_limited() {
	local destination=$1 maximum=$2 mode=$3 expected_sha=${4:-} tmp rc=0
	tmp=$(mktemp "${INBOX}/.upload.XXXXXX")
	head -c $((maximum + 1)) >"$tmp" || rc=$?
	if [[ $rc -ne 0 ]]; then rm -f "$tmp"; fail 'UPLOAD_READ_FAILED'; fi
	if [[ $(stat -c '%s' "$tmp") -gt $maximum ]]; then rm -f "$tmp"; fail 'UPLOAD_TOO_LARGE'; fi
	if [[ -n "$expected_sha" && $(sha256 "$tmp") != "$expected_sha" ]]; then rm -f "$tmp"; fail 'UPLOAD_HASH_MISMATCH'; fi
	chmod "$mode" "$tmp"; mv -f "$tmp" "$destination"
}

original=${SSH_ORIGINAL_COMMAND:-}
case "$original" in
	upload-artifact)
		write_limited "${INBOX}/m041-47ceae0.tar.gz" 5242880 0644 "$ARTIFACT_SHA"
		echo "M041_${ENVIRONMENT^^}_UPLOAD=PASS TYPE=ARTIFACT SHA=$ARTIFACT_SHA"
		;;
	upload-provider-key)
		write_limited "${INBOX}/m041-${ENVIRONMENT}-deepseek.key" 512 0600
		echo "M041_${ENVIRONMENT^^}_UPLOAD=PASS TYPE=PROVIDER_KEY VALUE=REDACTED"
		;;
	upload-production-approval-message)
		[[ "$ENVIRONMENT" == 'production' ]] || fail 'COMMAND_NOT_ALLOWED'
		write_limited "${INBOX}/m041-production-approval.message" 512 0600
		echo 'M041_PRODUCTION_UPLOAD=PASS TYPE=APPROVAL_MESSAGE VALUE=REDACTED'
		;;
	upload-production-approval-signature)
		[[ "$ENVIRONMENT" == 'production' ]] || fail 'COMMAND_NOT_ALLOWED'
		write_limited "${INBOX}/m041-production-approval.signature" 64 0600
		if [[ $(stat -c '%s' "${INBOX}/m041-production-approval.signature") -ne 64 ]]; then rm -f "${INBOX}/m041-production-approval.signature"; fail 'APPROVAL_SIGNATURE_SIZE_INVALID'; fi
		echo 'M041_PRODUCTION_UPLOAD=PASS TYPE=APPROVAL_SIGNATURE VALUE=REDACTED'
		;;
	remove-transient-secrets)
		rm -f "${INBOX}/m041-${ENVIRONMENT}-deepseek.key"
		if [[ "$ENVIRONMENT" == 'production' ]]; then rm -f "${INBOX}/m041-production-approval.message" "${INBOX}/m041-production-approval.signature"; fi
		echo "M041_${ENVIRONMENT^^}_TRANSIENT_CLEANUP=PASS"
		;;
	'run staging-compare'|'run staging-preflight'|'run staging-apply'|'run staging-verify'|'run staging-rollback'|'run staging-status'|'run staging-configure-provider'|'run staging-provider-status'|'run staging-provider-test')
		[[ "$ENVIRONMENT" == 'staging' ]] || fail 'COMMAND_NOT_ALLOWED'
		exec sudo -n "$HELPER" "${original#run }"
		;;
	'run production-compare'|'run production-preflight'|'run production-apply'|'run production-verify'|'run production-rollback'|'run production-status'|'run production-configure-provider'|'run production-provider-status'|'run production-provider-test')
		[[ "$ENVIRONMENT" == 'production' ]] || fail 'COMMAND_NOT_ALLOWED'
		exec sudo -n "$HELPER" "${original#run }"
		;;
	*) fail 'COMMAND_NOT_ALLOWED' ;;
esac
