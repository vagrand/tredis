<?php
/**
 * Config for TRedis class
 *
 * @package FLibs
 * @subpackage configs
 * @copyright (C) 2014 point.od.ua <support@point.od.ua>
 * @author Tishenko Vladimir <vagrand@mail.ru>
 */
return array(
	'data' => array(
		'namespace' => 'FLData_',
		'servers' => array(
			array('host' => '127.0.0.1', 'port' => 6379, 'db' => 1)
		)
	),
);
