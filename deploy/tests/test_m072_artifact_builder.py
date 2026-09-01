#!/usr/bin/env python3

import pathlib
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
BUILDER = ROOT / "deploy/tools/build_m072_release_artifacts.py"


class ArtifactBuilderContractTest(unittest.TestCase):
    def test_builder_is_deterministic_metadata_free_and_commit_pinned(self):
        source = BUILDER.read_text()
        for marker in (
            "tarfile.USTAR_FORMAT", 'mtime = 0', 'uid = info.gid = 0',
            'GzipFile(filename=""', 'mtime=0', 'git_blob(repo, commit, path)',
            'SOURCE_WORKTREE_NOT_CLEAN', 'M069_C04_RESIDUE_INCLUDED', 'MIGRATIONS_MISSING',
            'release-pins.sha256', 'TARGET_ADAPTERS_AND_ENVIRONMENT_REQUIRE_M073_APPROVAL',
            'historical-helper-deprecation-ledger.tsv', 'LEGACY_RETAINED_NOT_AUTHORIZED',
            'path.startswith("deploy/tools/")',
            'for repository in ("dolibarr", "react", "wordpress")',
            'path.startswith(".github/workflows/")',
            'EXPECTED_COMPATIBILITY_LINKS', 'dolibarr.layout.tsv', 'COMPATIBILITY_LINK_TARGET_MISMATCH',
            'runtime & M069_C04_RESIDUE',
        ):
            self.assertIn(marker, source)
        self.assertNotIn("tarfile.PAX_FORMAT", source)

    def test_all_system_owners_are_separate_components(self):
        source = BUILDER.read_text()
        self.assertIn('COMPONENTS = ("dolibarr", "react", "wordpress", "mobile")', source)
        self.assertIn('"container-build-input"', source)
        self.assertIn('"plugin-source"', source)
        self.assertIn('"mobile-source"', source)
        self.assertIn('"runtime"', source)
        self.assertIn('"support"', source)
        self.assertIn('runtime_target = path if classification in ("runtime", "container-build-input", "plugin-source") else "-"', source)


if __name__ == "__main__":
    unittest.main()
