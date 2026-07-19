<?php
/**
 * Synthetic-only identities and scenario counts for M017 local integration.
 * Passwords are generated at seed time and are never stored in this file.
 */
return array(
	'marker' => 'M017_SYNTH',
	'customers' => array(
		array('key'=>'A', 'entity'=>1, 'login'=>'m017_e1_a', 'company'=>'M017 Synthetic Customer A', 'projects'=>2, 'shipments'=>2, 'orders'=>2, 'currencies'=>array('USD','CNY','EGP')),
		array('key'=>'B', 'entity'=>1, 'login'=>'m017_e1_b', 'company'=>'M017 Synthetic Customer B', 'projects'=>1, 'shipments'=>1, 'orders'=>1, 'currencies'=>array('USD')),
		array('key'=>'C', 'entity'=>2, 'login'=>'m017_e2_c', 'company'=>'M017 Synthetic Customer C', 'projects'=>1, 'shipments'=>1, 'orders'=>1, 'currencies'=>array('CNY')),
	),
	'negative_users' => array(
		array('key'=>'INACTIVE', 'entity'=>1, 'login'=>'m017_e1_inactive', 'kind'=>'inactive'),
		array('key'=>'INTERNAL', 'entity'=>1, 'login'=>'m017_e1_internal', 'kind'=>'internal'),
		array('key'=>'NOSOC', 'entity'=>1, 'login'=>'m017_e1_nosoc', 'kind'=>'nosoc'),
	),
);
