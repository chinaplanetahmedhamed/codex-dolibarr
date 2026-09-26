#!/usr/bin/env python3
import importlib.util, json, subprocess, sys, unittest
from unittest.mock import patch
from test_m091_release import Release, ROOT, ARTIFACT

class Correction(unittest.TestCase):
    def setUp(self):
        self.base=Release('test_apply_verify_rollback');self.base.setUp();self.base.action('apply')
        spec=importlib.util.spec_from_file_location('c01',ROOT/'deploy/releases/m091/c01.py')
        self.c=importlib.util.module_from_spec(spec);spec.loader.exec_module(self.c)
        self.c.INBOX=self.base.root.parent/'inbox';self.c.INBOX.mkdir()
        self.c.STATE=self.base.root.parent/'correction-state'
        (self.c.INBOX/'m091-deploy.py').write_bytes((ARTIFACT/'m091-deploy.py').read_bytes())
        self.data=subprocess.check_output(['git','show','db746106ef5fbe6a62db61ccac70064390fa3fd7:custom/'+self.c.NAME],cwd=ROOT)
        (self.c.INBOX/'m091-c01-agenda.php').write_bytes(self.data)
        self.patches=[patch.object(self.c,'exec',side_effect=lambda code,ns:ns.update(self.base.m.__dict__),create=True),patch.object(self.c,'runtime',return_value={'result':'PASS','scheduled_tasks':1})]
        for p in self.patches:p.start()
    def tearDown(self):
        for p in reversed(self.patches):p.stop()
        self.base.tearDown()
    def action(self,action):
        with patch.object(sys,'argv',['c01',action]):self.c.main()
    def original(self):self.assertEqual((self.base.root/self.c.NAME).read_bytes(),self.base.payload[self.c.NAME])
    def test_apply_verify_rollback(self):
        self.action('preflight');self.action('apply');self.assertEqual((self.base.root/self.c.NAME).read_bytes(),self.data)
        self.action('verify');self.action('rollback');self.original()
        self.base.m.backup_db.assert_called_once() # Only the original release; correction never writes the database.
    def test_candidate_failure_leaves_original(self):
        self.c.runtime.side_effect=RuntimeError('candidate-failure')
        with self.assertRaisesRegex(RuntimeError,'candidate-failure'):self.action('apply')
        self.original();self.assertFalse((self.c.STATE/'state.json').exists())
    def test_postwrite_failure_rolls_back(self):
        self.c.runtime.side_effect=[{'result':'PASS'},RuntimeError('runtime-failure')]
        with self.assertRaisesRegex(RuntimeError,'runtime-failure'):self.action('apply')
        self.original();self.assertEqual(json.loads((self.c.STATE/'state.json').read_text())['status'],'AUTO_ROLLED_BACK')
    def test_tampered_candidate_refused(self):
        (self.c.INBOX/'m091-c01-agenda.php').write_bytes(b'tampered')
        with self.assertRaisesRegex(AssertionError,'CANDIDATE_HASH'):self.action('apply')
        self.original()
    def test_drift_refuses_writes(self):
        p=self.base.root/self.c.NAME;p.write_bytes(b'later-code')
        with self.assertRaisesRegex(RuntimeError,'FILE_DRIFT'):self.action('apply')
        self.assertEqual(p.read_bytes(),b'later-code')
    def test_runtime_candidate_php_parses(self):
        self.patches[-1].stop()
        def php(web,code):
            result=subprocess.run(['php','-l'],input=('<?php '+code).encode(),capture_output=True)
            self.assertEqual(result.returncode,0,result.stdout+result.stderr)
            return b'{"result":"PASS","scheduled_tasks":1}'
        ns=dict(self.base.m.__dict__);ns['php']=php
        self.c.runtime(ns,'web',self.data);self.c.runtime(ns,'web')

if __name__=='__main__':unittest.main(defaultTest='Correction')
