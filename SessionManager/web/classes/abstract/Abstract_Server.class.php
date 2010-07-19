<?php
/**
 * Copyright (C) 2009-2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com> 2010
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2009
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/
require_once(dirname(__FILE__).'/../../includes/core.inc.php');

class Abstract_Server {
	public static $server_properties = array(
		'roles'				=>	'roles',
		'external_name'		=>	'external_name',
		'max_sessions'		=>	'max_sessions',
		'ulteo_system'		=>	'ulteo_system',
		'windows_domain'	=>	'windows_domain'
	);

	public static function init($prefs_) {
		Logger::debug('main', 'Starting Abstract_Server::init');

		$sql_conf = $prefs_->get('general', 'sql');
		$SQL = SQL::newInstance($sql_conf);

		$servers_table_structure = array(
			'fqdn'			=>	'varchar(255) NOT NULL',
			'status'		=>	'varchar(255) NOT NULL',
			'registered'	=>	'int(8) NOT NULL',
			'locked'		=>	'int(8) NOT NULL',
			'type'			=>	'varchar(255) NOT NULL',
			'version'		=>	'varchar(255) NOT NULL',
			'cpu_model'		=>	'varchar(255) NOT NULL',
			'cpu_nb_cores'	=>	'int(8) NOT NULL',
			'cpu_load'		=>	'int(8) NOT NULL',
			'ram_total'		=>	'int(16) NOT NULL',
			'ram_used'		=>	'int(16) NOT NULL',
			'timestamp'		=>	'int(10) NOT NULL'
		);

		$ret = $SQL->buildTable($sql_conf['prefix'].'servers', $servers_table_structure, array('fqdn'));

		if (! $ret) {
			Logger::error('main', 'Unable to create MySQL table \''.$sql_conf['prefix'].'servers\'');
			return false;
		}

		Logger::debug('main', 'MySQL table \''.$sql_conf['prefix'].'servers\' created');

		$servers_properties_table_structure = array(
			'fqdn'			=>	'varchar(255) NOT NULL',
			'property'		=>	'varchar(64) NOT NULL',
			'value'			=>	'varchar(255) NOT NULL'
		);

		$ret = $SQL->buildTable($sql_conf['prefix'].'servers_properties', $servers_properties_table_structure, array('fqdn', 'property'));

		if (! $ret) {
			Logger::error('main', 'Unable to create MySQL table \''.$sql_conf['prefix'].'servers_properties\'');
			return false;
		}

		Logger::debug('main', 'MySQL table \''.$sql_conf['prefix'].'servers_properties\' created');

		return true;
	}

	public static function exists($fqdn_) {
		Logger::debug('main', 'Starting Abstract_Server::exists for \''.$fqdn_.'\'');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT 1 FROM @1 WHERE @2 = %3 LIMIT 1', $SQL->prefix.'servers', 'fqdn', $fqdn_);
		$total = $SQL->NumRows();

		if ($total == 0)
			return false;

		return true;
	}

	public static function load($fqdn_) {
		Logger::debug('main', 'Starting Abstract_Server::load for \''.$fqdn_.'\'');

		if (substr($fqdn_, -1) == '.')
			$fqdn_ = substr($fqdn_, 0, (strlen($fqdn_)-1));

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM @1 WHERE @2 = %3 LIMIT 1', $SQL->prefix.'servers', 'fqdn', $fqdn_);
		$total = $SQL->NumRows();

		if ($total == 0) {
			Logger::error('main', "Abstract_Server::load($fqdn_) failed: NumRows == 0");
			return false;
		}

		$row = $SQL->FetchResult();

		$buf = self::generateFromRow($row);

		return $buf;
	}

	public static function save($server_) {
		Logger::debug('main', 'Starting Abstract_Server::save for \''.$server_->fqdn.'\'');

		$SQL = SQL::getInstance();

		$fqdn = $server_->fqdn;

		if (! Abstract_Server::exists($fqdn)) {
			Logger::info('main', "Abstract_Server::save($server_) server does NOT exist, we must create it");

			if (! Abstract_Server::create($server_)) {
				Logger::error('main', "Abstract_Server::save($server_) create failed");
				return false;
			}
		}

		$SQL->DoQuery('UPDATE @1 SET @2=%3,@4=%5,@6=%7,@8=%9,@10=%11,@12=%13,@14=%15,@16=%17,@18=%19,@20=%21,@22=%23 WHERE @24 = %25 LIMIT 1', $SQL->prefix.'servers', 'status', $server_->status, 'registered', (int)$server_->registered, 'locked', (int)$server_->locked, 'type', $server_->type, 'version', $server_->version, 'cpu_model', $server_->cpu_model,
		'cpu_nb_cores', $server_->cpu_nb_cores, 'cpu_load', (int)($server_->cpu_load*100), 'ram_total', $server_->ram_total, 'ram_used', $server_->ram_used, 'timestamp', time(), 'fqdn', $fqdn);

		$properties = Abstract_Server::loadProperties($server_);

		foreach (Abstract_Server::$server_properties as $object_property => $db_property)
			Abstract_Server::saveProperty($server_, $object_property, $db_property, (isset($properties[$object_property])?$properties[$object_property]:NULL));

		return true;
	}

	private static function loadProperties($server_) {
		Logger::debug('main', 'Starting Abstract_Server::loadProperties for \''.$server_->fqdn.'\'');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT @1,@2 FROM @3 WHERE @4 = %5', 'property', 'value', $SQL->prefix.'servers_properties', 'fqdn', $server_->fqdn);
		$rows = $SQL->FetchAllResults();

		$properties = array();
		foreach ($rows as $row)
			$properties[$row['property']] = unserialize($row['value']);

		return $properties;
	}

	private static function saveProperty($server_, $object_property_, $db_property_, $old_property_) {
		Logger::debug('main', 'Starting Abstract_Server::saveProperty for \''.$server_->fqdn.'\' object_property \''.$object_property_.'\' db_property \''.$db_property_.'\'');

		$property_ = ((isset($server_->$object_property_))?serialize($server_->$object_property_):NULL);
		$old_property_ = ((! is_null($old_property_))?serialize($old_property_):NULL);

		$SQL = SQL::getInstance();

		if (! is_null($old_property_) && is_null($property_))
			$SQL->DoQuery('DELETE FROM @1 WHERE @2 = %3 AND @4 = %5 LIMIT 1', $SQL->prefix.'servers_properties', 'property', $db_property_, 'fqdn', $server_->fqdn);
		elseif (is_null($old_property_) && ! is_null($property_))
			$SQL->DoQuery('INSERT INTO @1 (@2,@3,@4) VALUES(%5,%6,%7)', $SQL->prefix.'servers_properties', 'fqdn', 'property', 'value', $server_->fqdn, $db_property_, $property_);
		elseif ($old_property_ != $property_)
			$SQL->DoQuery('UPDATE @1 SET @2=%3 WHERE @4 = %5 AND @6 = %7 LIMIT 1', $SQL->prefix.'servers_properties', 'value', $property_, 'property', $db_property_, 'fqdn', $server_->fqdn);

		return true;
	}

	private static function create($server_) {
		Logger::debug('main', 'Starting Abstract_Server::create for \''.$server_->fqdn.'\'');

		$SQL = SQL::getInstance();

		$fqdn = $server_->fqdn;

		$SQL->DoQuery('SELECT 1 FROM @1 WHERE @2 = %3 LIMIT 1', $SQL->prefix.'servers', 'fqdn', $fqdn);
		$total = $SQL->NumRows();

		if ($total != 0) {
			Logger::error('main', "Abstract_Server::create($server_) server already exist (NumRows == $total)");
			return false;
		}

		$SQL->DoQuery('INSERT INTO @1 (@2) VALUES (%3)', $SQL->prefix.'servers', 'fqdn', $fqdn);

		return true;
	}

	public static function modify($server_) {
		Logger::debug('main', 'Starting Abstract_Server::modify for \''.$server_->fqdn.'\'');

		$SQL = SQL::getInstance();

		$fqdn = $server_->fqdn;

		if (! Abstract_Server::load($fqdn)) {
			Logger::error('main', 'Abstract_Server::modify('.$server_->fqdn.') failed to load server');
			return false;
		}

		$SQL->DoQuery('UPDATE @1 SET @2=%3,@4=%5,@6=%7,@8=%9,@10=%11,@12=%13,@14=%15,@16=%17,@18=%19,@20=%21,@22=%23 WHERE @24 = %25 LIMIT 1', $SQL->prefix.'servers', 'status', $server_->status, 'registered', (int)$server_->registered, 'locked', (int)$server_->locked, 'type', $server_->type, 'version', $server_->version, 'cpu_model', $server_->cpu_model,
		'cpu_nb_cores', $server_->cpu_nb_cores, 'cpu_load', (int)($server_->cpu_load*100), 'ram_total', $server_->ram_total, 'ram_used', $server_->ram_used, 'timestamp', time(), 'fqdn', $fqdn);

		$properties = Abstract_Server::loadProperties($server_);

		foreach (Abstract_Server::$server_properties as $object_property => $db_property)
			Abstract_Server::saveProperty($server_, $object_property, $db_property, (isset($properties[$object_property])?$properties[$object_property]:NULL));

		return true;
	}

	public static function delete($fqdn_) {
		Logger::debug('main', 'Starting Abstract_Server::delete for \''.$fqdn_.'\'');

		if (substr($fqdn_, -1) == '.')
			$fqdn_ = substr($fqdn_, 0, (strlen($fqdn_)-1));

		$prefs = Preferences::getInstance();
		if (! $prefs)
			die_error('get Preferences failed',__FILE__,__LINE__);
		
		$slave_server_settings = $prefs->get('general', 'slave_server_settings');
		$remove_orphan = (bool)$slave_server_settings['remove_orphan'];

		$SQL = SQL::getInstance();

		$fqdn = $fqdn_;

		if ($remove_orphan) {
			$a_server = Abstract_Server::load($fqdn_);
			$apps = $a_server->getApplications();
		}

		$SQL->DoQuery('SELECT 1 FROM @1 WHERE @2 = %3 LIMIT 1', $SQL->prefix.'servers', 'fqdn', $fqdn);
		$total = $SQL->NumRows();

		if ($total == 0) {
			Logger::error('main', "Abstract_Server::delete($server_) server does not exist (NumRows == 0)");
			return false;
		}

		$sessions_liaisons = Abstract_Liaison::load('ServerSession', $fqdn_, NULL);
		foreach ($sessions_liaisons as $sessions_liaison) {
			Abstract_Session::delete($sessions_liaison->group);
		}
		Abstract_Liaison::delete('ServerSession', $fqdn_, NULL);

		Abstract_Liaison::delete('ApplicationServer', NULL, $fqdn_);
		
		
		if ($remove_orphan) {
			$applicationDB = ApplicationDB::getInstance();
			
			// remove the orphan applications
			if (is_array($apps)) {
				foreach ($apps as $an_application) {
					if ($an_application->isOrphan()) {
						Logger::debug('main', "Abstract_Server::delete $an_application is orphan");
						$applicationDB->remove($an_application);
					}
				}
			}
		}
		
		$tm = new Tasks_Manager();
		$tm->load_from_server($fqdn_);
		foreach ($tm->tasks as $a_task) {
			$tm->remove($a_task->id);
		}
		
		$folders = Abstract_NetworkFolder::load_from_server($fqdn_);
		foreach ($folders as $a_folder) {
			Abstract_NetworkFolder::delete($a_folder);
		}

		$SQL->DoQuery('DELETE FROM @1 WHERE @2 = %3 LIMIT 1', $SQL->prefix.'servers', 'fqdn', $fqdn);
		$SQL->DoQuery('DELETE FROM @1 WHERE @2 = %3', $SQL->prefix.'servers_properties', 'fqdn', $fqdn);

		return true;
	}

	private static function generateFromRow($row_) {
		foreach ($row_ as $k => $v)
			$$k = $v;

		$buf = new Server((string)$fqdn);
		$buf->status = (string)$status;
		$buf->registered = (bool)$registered;
		$buf->locked = (bool)$locked;
		$buf->type = (string)$type;
		$buf->version = (string)$version;
		$buf->cpu_model = (string)$cpu_model;
		$buf->cpu_nb_cores = (int)$cpu_nb_cores;
		$buf->cpu_load = (float)($cpu_load/100);
		$buf->ram_total = (int)$ram_total;
		$buf->ram_used = (int)$ram_used;

		$properties = Abstract_Server::loadProperties($buf);

		foreach (Abstract_Server::$server_properties as $object_property => $db_property) {
			if (isset($properties[$db_property]))
				$buf->$object_property = $properties[$db_property];
		}

		return $buf;
	}

	public static function load_all() {
		Logger::debug('main', 'Starting Abstract_Server::load_all');

		$prefs = Preferences::getInstance();
		if (! $prefs) {
			Logger::critical('main', 'get Preferences failed in '.__FILE__.' line '.__LINE__);
			return false;
		}

		$sql_conf = $prefs->get('general', 'sql');
		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM @1', $sql_conf['prefix'].'servers');
		$rows = $SQL->FetchAllResults();

		$servers = array();
		foreach ($rows as $row) {
			$server = self::generateFromRow($row);
			if (! is_object($server))
				continue;

			$servers[] = $server;
		}

		return $servers;
	}

	public static function uptodate($server_) {
		Logger::debug('main', 'Starting Abstract_Server::uptodate for \''.$server_->fqdn.'\'');
		
		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT @1 FROM @2 WHERE @3 = %4 LIMIT 1', 'timestamp', $SQL->prefix.'servers', 'fqdn', $server_->fqdn);
		$total = $SQL->NumRows();

		if ($total == 0) {
			Logger::warning('main', "Abstract_Server::uptodate($server_) server does not exist (NumRows == 0)");
			return false;
		}

		$row = $SQL->FetchResult();

		if ((int)$row['timestamp'] < time()-60)
			return false;

		return true;
	}
}
