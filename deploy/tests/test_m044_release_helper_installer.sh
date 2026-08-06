#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
installer="$root/deploy/tools/install_m044_palestine_release_helper.sh"

bash -n "$installer"
grep -Fq "readonly HELPER_SHA='d0d747bb445414848310824ef18c6ac6ef7317ecdd2dfe8c07030626369f5549'" "$installer"
grep -Fq "readonly PREVIOUS_HELPER_SHA='b4b412433e91f162e2995a48fe6023fef544e4b8218b0cbf146ba60cc4d7a51a'" "$installer"
grep -Fq "readonly SUDOERS_SHA='6fab772137a2ebffe824bbd23e8b7d82a1775a98c07abc3a9affd29e0c8099c4'" "$installer"
grep -Fq "readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-m044-palestine-release'" "$installer"
grep -Fq "readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-m044-palestine-release'" "$installer"
grep -Fq "stat -c '%U:%G %a'" "$installer"
grep -Fq 'visudo -cf /etc/sudoers' "$installer"
grep -Fq 'DEPLOY_EXECUTED=NO' "$installer"
! grep -Eq '(curl|wget|ssh|scp|docker|production-apply|staging-apply)' "$installer"

echo 'M044_HELPER_INSTALLER_TEST=PASS HASHES=PINNED DEPLOY_EXECUTED=NO'
