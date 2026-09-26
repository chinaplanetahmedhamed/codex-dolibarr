#!/usr/bin/env python3
import importlib.util, subprocess, unittest
from unittest.mock import patch
from test_m091_c01_release import Correction
from test_m091_release import ROOT

class ClockCorrection(Correction):
    def setUp(self):
        super().setUp()
        self.action('apply')
        for p in reversed(self.patches):p.stop()
        c01data=self.data
        spec=importlib.util.spec_from_file_location('c02',ROOT/'deploy/releases/m091/c02.py')
        self.c=importlib.util.module_from_spec(spec);spec.loader.exec_module(self.c)
        self.c.INBOX=self.base.root.parent/'inbox'
        self.c.STATE=self.base.root.parent/'clock-state'
        self.data=subprocess.check_output(['git','show','6e5dd5604d089cbb6e7390e684bf2bd604a55ab3:custom/'+self.c.NAME],cwd=ROOT)
        (self.c.INBOX/'m091-c02-agenda.php').write_bytes(self.data)
        self.original_data=c01data
        self.patches=[patch.object(self.c,'exec',side_effect=lambda code,ns:ns.update(self.base.m.__dict__),create=True),patch.object(self.c,'runtime',return_value={'result':'PASS','scheduled_tasks':1})]
        for p in self.patches:p.start()
    def original(self):self.assertEqual((self.base.root/self.c.NAME).read_bytes(),self.original_data)
    def test_tampered_candidate_refused(self):
        (self.c.INBOX/'m091-c02-agenda.php').write_bytes(b'tampered')
        with self.assertRaisesRegex(AssertionError,'CANDIDATE_HASH'):self.action('apply')
        self.original()

if __name__=='__main__':unittest.main(defaultTest='ClockCorrection')
