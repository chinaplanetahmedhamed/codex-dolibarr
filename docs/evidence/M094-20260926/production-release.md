# M094 production release — 2026-09-26

Direct production authorization reused. Includes M093 task controls/identity cards plus M094 200 MB files and native team membership correction.

Source 6d692aff2db0281ac9a68a4a84dcf9eca07e7471; 19 runtime files. Payload d066104086f8124341a83ed31d294c7e4dea2a15531a1c433d331bd37c0026a5; manifest d68780f1b235303e22bbf5e2e6582313decd8808a964a24e8e4cc4c88d187085; final helper 6290f5b2dc9b1e0c4b377543e738f2c3577bc8ac03d21bc6337af02fc510e7a8.

Preflight, apply and independent verify PASS / DEPLOYED on vision_erp. Private recovery state /root/chinaplanetos-m094-production-r1/. Code backup b621572ab348e7cf614f92bd13be763b23e9cd8f097a8de70d2b75035b8300bc; database backup 273b9bf8074025edacbf71267b70c38f1e9f670b3ab0872ecfcd57f5ed0d6859. Actual Apache SAPI confirms upload 200M / post 201M for asset.php, probe removed. Team 4 native entity-zero administrator members pass picker and membership validation.

The first attempt automatically restored the baseline after its HTTP-wrapper probe failed. Fixed socket probe passed on retry; runtime artifact unchanged. Historical first state /root/chinaplanetos-m094-production/ is AUTO_ROLLED_BACK. No database restore occurred.

Authenticated production browser acceptance remains pending. Local upload of exactly 209715200 bytes, PDF and XLSX persisted after reload; native live 200 MB upload not yet verified. Earlier M093 preflight stopped before writes because the pre-existing real test reference task 62 had been deleted by the user; task 62 was not changed or restored.
