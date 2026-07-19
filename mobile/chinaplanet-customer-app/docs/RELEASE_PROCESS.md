# China Planet customer app release process

## Branches

- `feature/*`: isolated mobile work; open a pull request into `mobile-develop`.
- `mobile-develop`: internal-test source. It uses the live, read-only customer Mobile API and may build an installable Android APK or an iOS internal-distribution build.
- `main`: accepted store source only. Merge `mobile-develop` into `main` after Ahmed accepts the internal build.

The internal build is named `chinaplanetCS Test` and uses `com.chinaplanet.customer.internal`. The official build remains `chinaplanetCS` with `com.chinaplanet.customer`, so both can be installed on the same phone.

The live API root is public configuration, not a credential:

`https://erp.visiontrading.biz/api/index.php/chinaplanetcustomercenter/mobile/v1`

The app never connects directly to the Dolibarr database and never contains a Dolibarr API master key, customer ID selector, password, refresh token, token pepper, store credential, or signing key.

## GitHub environments

Create two protected GitHub Environments:

1. `mobile-internal`
2. `mobile-production`

`mobile-production` must require Ahmed or a company-authorized reviewer. Add these values to each environment only after the company-owned Expo project exists:

- Secret `EXPO_TOKEN`: revocable least-privilege Expo robot/access token.
- Variable `EAS_PROJECT_ID`: Expo project ID for `com.chinaplanet.customer`.
- Variable `EXPO_OWNER`: company-controlled Expo account/organization.
- Variable `MOBILE_STORE_ACCOUNTS_READY=true`: production environment only, after Google Play and Apple credentials are configured and reviewed.

Store service-account JSON, Apple App Store Connect keys, Android upload/signing keys, certificates, and provisioning profiles must not be committed to GitHub. Configure them in the company-owned store and Expo/EAS credential systems.

## Internal test flow

1. Merge a reviewed `feature/*` pull request into `mobile-develop` after Mobile CI passes.
2. Open **Actions → Build mobile internal test release → Run workflow** from `mobile-develop`.
3. Enter `BUILD_CHINAPLANET_MOBILE_INTERNAL` and select Android, iOS, or both.
4. Install the internal build and test login, customer isolation, projects, shipments, account, and protected downloads.
5. Record Ahmed's acceptance before opening the release pull request to `main`.

### Internal OTA flow after the M021 replacement APK

The existing pre-M021 APK cannot receive OTA updates. Build and install one new `internal` APK from the M021 native/config commit first. That binary is `com.chinaplanet.customer.internal`, uses channel `internal`, and uses the app-version runtime policy (`1.0.1` for the first M021 binary).

For later JavaScript, style, and bundled-asset updates:

1. Merge the reviewed change into `mobile-develop` without changing native dependencies, `app.json`, `app.config.ts`, `eas.json`, or native project files after the retained binary-source commit.
2. Open **Actions → Publish mobile internal OTA update → Run workflow** from `mobile-develop`.
3. Enter `PUBLISH_CHINAPLANET_INTERNAL_OTA`, the full Git SHA used to build the installed replacement APK, and a short non-customer update message.
4. Approve the protected `mobile-internal-ota` GitHub Environment. The workflow verifies the branch, project/owner secrets, clean source, tests, runtime configuration, and absence of native changes since the retained binary commit.
5. Open the internal app. It checks the compiled `internal` channel, offers to download the compatible update, and asks before restarting.

If native dependencies, permissions, app identifiers, Expo SDK, Android/iOS configuration, or runtime version changed, stop and build a new internal binary. Never use OTA to bypass that native-build boundary.

Rollback is internal-only: republish the last accepted compatible source commit to `internal`, or use the Expo project's supported rollback-to-embedded control. Retain the accepted APK and record the update group IDs. Revoking `EXPO_TOKEN` stops future publications without affecting the embedded app.

Internal builds use real customer-visible ERP data but remain read-only. Do not use an administrator account or copy payloads/documents into issues, logs, or artifacts.

## Store release flow

1. Merge the accepted `mobile-develop` release pull request into `main`.
2. Confirm version/store metadata and that `MOBILE_STORE_ACCOUNTS_READY` is true.
3. Open **Actions → Release mobile app to stores → Run workflow** from `main`.
4. Enter `RELEASE_CHINAPLANET_MOBILE_TO_STORES` and select the platform.
5. Approve the protected `mobile-production` GitHub Environment.

The workflow builds with EAS and submits the Android AAB to the Google Play production track and the iOS binary to App Store Connect. Google/Apple review and any required listing actions still apply.

## Account data still required

- Expo organization/project owned by Ahmed or the legal company representative.
- Google Play Organization account, Play App Signing, service account with minimum release permissions, and completed Play listing/policy forms.
- Apple Developer Organization account, App Store Connect app, distribution certificate/profile, and least-privilege App Store Connect API key.
- Confirmed privacy, support, and account-deletion URLs before public release.

## Emergency stop

- Cancel the GitHub run if it has not dispatched the EAS build.
- Revoke `EXPO_TOKEN` if exposure is suspected.
- Disable/remove the GitHub Environment approval or set `MOBILE_STORE_ACCOUNTS_READY=false` to prevent store submission.
- Store rollback or phased-release halt is a separate exact Ahmed-approved operation.
- Remove approval from `mobile-internal-ota` or revoke `EXPO_TOKEN` to stop internal OTA publication. This does not authorize or affect the production/store channel.
