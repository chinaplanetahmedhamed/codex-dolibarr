# Retired customer mobile application

Ahmed explicitly retired the customer application in favor of the web Customer Center and Operations Boards. The customer mobile source and its four build/release workflows are removed from the current baseline. They remain recoverable in Git history at bd7b54e0b130e514e3439fe0f1ba7e2e291a2b0c and in the verified private pre-M140 refs bundle. No live API compatibility files are removed: deployed application bytes remain exactly the captured manifest.

The old customer-app CI failed during the baseline PR because of Expo dependency drift in that retired project. No application dependency upgrade or live code change was used to hide the failure. The replacement baseline check verifies every captured deployed file and the repository layout without accessing any private configuration or records.
