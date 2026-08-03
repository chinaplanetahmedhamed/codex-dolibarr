#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_m041_delivery.sh"
readonly SOURCE_DISPATCH="${SOURCE_ROOT}/chinaplanetos_m041_ssh_dispatch.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-m041-delivery.sudoers"
readonly SOURCE_STAGING_PUB="${SOURCE_ROOT}/m041-staging-deploy.pub"
readonly SOURCE_PRODUCTION_PUB="${SOURCE_ROOT}/m041-production-deploy.pub"
readonly SOURCE_APPROVAL_PUB="${SOURCE_ROOT}/m041-production-approval-public.pem"
readonly HELPER_SHA='e02cbd675ae5bfce8cc6c6cabfaae2ec25de0aec20bf3bfbd4e2836d2cf5747c'
readonly DISPATCH_SHA='25e6362da8e47e9b861d54b36d2b007b70ad1354c51766f7b516594749188c2b'
readonly SUDOERS_SHA='c0ec2c4c049ed562adcd3905166316b7e3019b47eb2904b3b34fa68481ad76b3'
readonly STAGING_PUB_SHA='97b1ee7fe52f5091c5484b954eca83816018f29ca1dfb33b8de80a2bff318682'
readonly PRODUCTION_PUB_SHA='f232bc128ff4107222fd93ffaf8fba90d78f57a43179a648281931a501ac4cf4'
readonly APPROVAL_PUB_SHA='683ff83437ab5df160ca8e4f49673f0eba01c9fbbf744a1b27e58df4c33a6e9a'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-m041-delivery'
readonly TARGET_STAGING_DISPATCH='/usr/local/bin/chinaplanetos-m041-staging-ssh'
readonly TARGET_PRODUCTION_DISPATCH='/usr/local/bin/chinaplanetos-m041-production-ssh'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-m041-delivery'
readonly TARGET_APPROVAL_PUB='/etc/chinaplanetos/m041-production-approval-public.pem'

die() { echo "M041_HELPER_INSTALL=FAIL REASON=$1" >&2; return 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m041-helper-install.XXXXXX)
trap 'if [[ -n "${private_root:-}" && "$private_root" == /root/m041-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi' EXIT

declare -A expected_hashes=(
	["$SOURCE_HELPER"]="$HELPER_SHA"
	["$SOURCE_DISPATCH"]="$DISPATCH_SHA"
	["$SOURCE_SUDOERS"]="$SUDOERS_SHA"
	["$SOURCE_STAGING_PUB"]="$STAGING_PUB_SHA"
	["$SOURCE_PRODUCTION_PUB"]="$PRODUCTION_PUB_SHA"
	["$SOURCE_APPROVAL_PUB"]="$APPROVAL_PUB_SHA"
)
for source in "${!expected_hashes[@]}"; do
	[[ -f "$source" && ! -L "$source" ]] || die 'SOURCE_INVALID'
	[[ $(stat -c '%U:%G %a' "$source") == 'codex-deploy:codex-deploy 644' ]] || die 'SOURCE_METADATA_INVALID'
	[[ $(sha256 "$source") == "${expected_hashes[$source]}" ]] || die 'SOURCE_HASH_MISMATCH'
done
bash -n "$SOURCE_HELPER" || die 'HELPER_SYNTAX_INVALID'
bash -n "$SOURCE_DISPATCH" || die 'DISPATCH_SYNTAX_INVALID'
ssh-keygen -lf "$SOURCE_STAGING_PUB" >/dev/null || die 'STAGING_PUBLIC_KEY_INVALID'
ssh-keygen -lf "$SOURCE_PRODUCTION_PUB" >/dev/null || die 'PRODUCTION_PUBLIC_KEY_INVALID'
openssl pkey -pubin -in "$SOURCE_APPROVAL_PUB" -noout >/dev/null 2>&1 || die 'APPROVAL_PUBLIC_KEY_INVALID'

ensure_account() {
	local account=$1 home="/home/$1"
	if ! getent passwd "$account" >/dev/null; then
		useradd --create-home --home-dir "$home" --shell /bin/bash --user-group "$account"
	fi
	[[ $(getent passwd "$account" | awk -F: '{print $6"|"$7}') == "$home|/bin/bash" ]] || die 'ACCOUNT_IDENTITY_INVALID'
	[[ $(id -Gn "$account") == "$account" ]] || die 'ACCOUNT_GROUP_SCOPE_INVALID'
	passwd -l "$account" >/dev/null 2>&1 || die 'ACCOUNT_LOCK_FAILED'
	install -d -o root -g root -m 0755 "$home"
	install -d -o root -g root -m 0700 "$home/.ssh"
	install -d -o "$account" -g "$account" -m 0700 "$home/inbox"
}
ensure_account 'cp-m041-staging'
ensure_account 'cp-m041-production'

install_exact() {
	local source=$1 target=$2 mode=$3 expected_sha=$4
	if [[ -e "$target" || -L "$target" ]]; then
		[[ -f "$target" && ! -L "$target" && $(stat -c '%U:%G %a' "$target") == "root:root $mode" && $(sha256 "$target") == "$expected_sha" ]] || die 'UNEXPECTED_EXISTING_TARGET'
	else
		install -o root -g root -m "$mode" "$source" "${target}.tmp.$$"
		[[ $(sha256 "${target}.tmp.$$") == "$expected_sha" ]] || die 'TARGET_HASH_INVALID'
		mv -f "${target}.tmp.$$" "$target"
	fi
}

install -d -o root -g root -m 0700 /etc/chinaplanetos
install_exact "$SOURCE_HELPER" "$TARGET_HELPER" 755 "$HELPER_SHA"
install_exact "$SOURCE_DISPATCH" "$TARGET_STAGING_DISPATCH" 755 "$DISPATCH_SHA"
install_exact "$SOURCE_DISPATCH" "$TARGET_PRODUCTION_DISPATCH" 755 "$DISPATCH_SHA"
install_exact "$SOURCE_APPROVAL_PUB" "$TARGET_APPROVAL_PUB" 644 "$APPROVAL_PUB_SHA"

install_authorized_key() {
	local account=$1 dispatch=$2 source_pub=$3 target="/home/${account}/.ssh/authorized_keys" line
	line="restrict,command=\"${dispatch}\" $(sed -n '1p' "$source_pub")"
	if [[ -e "$target" || -L "$target" ]]; then
		[[ -f "$target" && ! -L "$target" && $(stat -c '%U:%G %a' "$target") == 'root:root 600' && $(<"$target") == "$line" ]] || die 'UNEXPECTED_EXISTING_AUTHORIZED_KEYS'
	else
		printf '%s\n' "$line" >"${target}.tmp.$$"
		chmod 0600 "${target}.tmp.$$"; chown root:root "${target}.tmp.$$"; mv -f "${target}.tmp.$$" "$target"
	fi
}
install_authorized_key 'cp-m041-staging' "$TARGET_STAGING_DISPATCH" "$SOURCE_STAGING_PUB"
install_authorized_key 'cp-m041-production' "$TARGET_PRODUCTION_DISPATCH" "$SOURCE_PRODUCTION_PUB"

visudo -cf "$SOURCE_SUDOERS" >/dev/null || die 'SUDOERS_PARSE_FAILED'
install_exact "$SOURCE_SUDOERS" "$TARGET_SUDOERS" 440 "$SUDOERS_SHA"
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PARSE_FAILED'

audit_sudo_surface() {
	local account=$1 environment=$2 output count
	output=$(sudo -l -U "$account") || die 'SUDO_AUDIT_FAILED'
	count=$(grep -o "/usr/local/sbin/chinaplanetos-m041-delivery ${environment}-" <<<"$output" | wc -l | tr -d ' ')
	[[ $count -eq 9 ]] || die 'SUDO_AUDIT_COUNT_INVALID'
	[[ -z $(grep 'NOPASSWD:' <<<"$output" | grep -Fv '/usr/local/sbin/chinaplanetos-m041-delivery' || true) ]] || die 'SUDO_AUDIT_BROAD_AUTHORITY'
}
audit_sudo_surface 'cp-m041-staging' 'staging'
audit_sudo_surface 'cp-m041-production' 'production'

echo "M041_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA DISPATCH_SHA=$DISPATCH_SHA SUDOERS_SHA=$SUDOERS_SHA USERS=2 FORCED_COMMAND=YES DEPLOY_EXECUTED=NO PROVIDER_CONFIGURED=NO"
