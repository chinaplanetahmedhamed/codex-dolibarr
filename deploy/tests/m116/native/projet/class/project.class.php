<?php
/** Synthetic native Project API stand-in. No ERP connection. */
class Project { public function __construct($db) {} public function getProjectsAuthorizedForUser($actor,$a,$b) {return array_fill_keys(array_diff([10,11,20],$actor->hidden),1);} }
