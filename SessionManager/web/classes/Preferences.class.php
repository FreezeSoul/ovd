<?php
/**
 * Copyright (C) 2008-2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com>
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
require_once(dirname(__FILE__).'/../includes/core.inc.php');

class Preferences {
	public $elements;
	protected $conf_file;
	private static $instance;
	public $prettyName;

	public function __construct(){
		$this->conf_file = SESSIONMANAGER_CONFFILE_SERIALIZED;
		$this->elements = array();
		$this->initialize();
		$filecontents = $this->getConfFileContents();
		if (!is_array($filecontents)) {
			Logger::error('main', 'Preferences::construct contents of conf file is not an array');
		}
		else {
			$this->mergeWithConfFile($filecontents);
		}
	}

	public static function hasInstance() {
		return isset(self::$instance);
	}

	public static function getInstance() {
		if (!isset(self::$instance)) {
			try {
				self::$instance = new Preferences();
			} catch (Exception $e) {
				return false;
			}
		}
		return self::$instance;
	}
	
	public static function fileExists() {
		return @file_exists(SESSIONMANAGER_CONFFILE_SERIALIZED); // ugly
	}

	public function getKeys(){
		return array_keys($this->elements);
	}
	
	public function getElements($container_,$container_sub_) {
		if (isset($this->elements[$container_])) {
			if (isset($this->elements[$container_][$container_sub_])) {
				return $this->elements[$container_][$container_sub_];
			}
			else {
				Logger::error('main',"Preferences::getElements($container_,$container_sub_), '$container_' found but '$container_sub_' not found");
				return NULL;
			}
		}
		else {
			Logger::error('main',"Preferences::getElements($container_,$container_sub_), '$container_'  not found");
			return NULL;
		}
	}

	public function get($container_,$container_sub_,$sub_sub_=NULL){
		if (isset($this->elements[$container_])) {
			if (isset($this->elements[$container_][$container_sub_])) {
				if (is_null($sub_sub_)) {
					$buf = $this->elements[$container_][$container_sub_];
					if (is_array($buf)) {
						$buf2 = array();
						foreach ($buf as $k=> $v) {
							$buf2[$k] = $v->content;
						}
						return $buf2;
					}
					else
						return $buf->content;
				}
				else {
					if (isset($this->elements[$container_][$container_sub_][$sub_sub_])) {
						$buf = $this->elements[$container_][$container_sub_][$sub_sub_];
						return $buf->content;
					}
					else {
						return NULL;
					}
				}
			}
			else {
				return NULL;
			}

		}
		else {
			//Logger::error('main','Preferences::get \''.$container_.'\' not found');
			return NULL;
		}
	}

	protected function getConfFileContents(){
		if (!is_readable($this->conf_file)) {
			return array();
		}
		
		$ret = @unserialize(@file_get_contents($this->conf_file, LOCK_EX));
		if ($ret === false) {
			return array();
		}
		
		return $ret;
	}

	public function getPrettyName($key_) {
		if (isset($this->prettyName[$key_]))
			return $this->prettyName[$key_];
		else {
			return $key_;
		}
	}

	public function mergeWithConfFile($filecontents) {
		if (is_array($filecontents)) {
			foreach($filecontents as $key1 => $value1) {
				if ((isset($this->elements[$key1])) && is_object($this->elements[$key1])) {
					$buf = &$this->elements[$key1];
					$buf->content = $filecontents[$key1];
				}
				else if (is_array($filecontents[$key1])) {
					foreach($value1 as $key2 => $value2) {
						if ((isset($this->elements[$key1][$key2])) && is_object($this->elements[$key1][$key2])) {
							$buf = &$this->elements[$key1][$key2];
							$buf->content = $filecontents[$key1][$key2];
						}
						else if (is_array($value2)) {
							foreach($value2 as $key3 => $value3) {
								if ((isset($this->elements[$key1][$key2][$key3])) && is_object($this->elements[$key1][$key2][$key3])) {
									$buf = &$this->elements[$key1][$key2][$key3];
									$buf->content = $filecontents[$key1][$key2][$key3];
								}
								else if (is_array($value3)) {
									foreach($value3 as $key4 => $value4) {
										if ((isset($this->elements[$key1][$key2][$key3][$key4])) && is_object($this->elements[$key1][$key2][$key3][$key4])) {
											$buf = &$this->elements[$key1][$key2][$key3][$key4];
											$buf->content = $filecontents[$key1][$key2][$key3][$key4];
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}

	public function initialize(){

		$this->addPrettyName('general',_('General configuration'));

		$c = new ConfigElement_select('system_in_maintenance', _('System in maintenance mode'), _('System in maintenance mode'), _('System in maintenance mode'), 0);
		$c->setContentAvailable(array(0=>_('no'), 1=>_('yes')));
		$this->add($c,'general');

		$c = new ConfigElement_select('admin_language', _('Administration console language'), _('Administration console language'), _('Administration console language'), 'auto');
		$c->setContentAvailable(array('auto'=>_('Autodetect'),'en_GB'=>'English','fr_FR'=>'Français','ja_JP'=>'日本語','ru_RU'=>'Русский'));
		$this->add($c,'general');

		$c = new ConfigElement_multiselect('log_flags', _('Debug options list'), _('Select debug options you want to enable.'), _('Select debug options you want to enable.'), array('info','warning','error','critical'));
		$c->setContentAvailable(array('debug' => _('debug'),'info' => _('info'), 'warning' => _('warning'),'error' => _('error'),'critical' => _('critical')));
		$this->add($c,'general');
		$c = new ConfigElement_select('cache_update_interval', _('Cache logs update interval'), _('Cache logs update interval'), _('Cache logs update interval'), 30);
		$c->setContentAvailable(array(30=>_('30 seconds'), 60=>_('1 minute'), 300=>_('5 minutes'), 900=>_('15 minutes'), 1800=>_('30 minutes'), 3600=>_('1 hour'), 7200=>_('2 hours')));
		$this->add($c,'general');
		$c = new ConfigElement_select('cache_expire_time', _('Cache logs expiry time'), _('Cache logs expiry time'), _('Cache logs expiry time'), (86400*366));
		$c->setContentAvailable(array(86400=>_('A day'), (86400*7)=>_('A week'), (86400*31)=>_('A month'), (86400*366)=>_('A year')));
		$this->add($c,'general');

// 		$c = new ConfigElement_input('start_app','start_app','start_app_des','');
// 		$this->add('general',$c);

		$c = new ConfigElement_text('user_default_group', _('Default user group'), _('Default user group'), _('Default user group'), '');
		$this->add($c,'general');

		$c = new ConfigElement_input('max_items_per_page', _('Maximum items per page'), _('The maximum number of items that can be displayed.'), _('The maximum number of items that can be displayed.'), 100);
		$this->add($c,'general');
		
		$c = new ConfigElement_inputlist('default_browser', _('Default browser'), _('Default browser'), _('Default browser'), array('linux' => NULL)); // TODO: 'windows' to add
		$this->add($c,'general');
		
		$c = new ConfigElement_multiselect('default_policy', _('Default policy'), _('Default policy'), _('Default policy'), array());
		$c->setContentAvailable(array(
			'canUseAdminPanel' => _('use Admin panel'),
			'viewServers' => _('view Servers'),
			'manageServers' => _('manage Servers'),
			'viewSharedFolders' => _('view Shared folders'),
			'manageSharedFolders' => _('manage Shared folders'),
			'viewUsers' => _('view Users'),
			'manageUsers' => _('manage Users'),
			'viewUsersGroups' => _('view Usergroups'),
			'manageUsersGroups' => _('manage Usergroups'),
			'viewApplications' => _('view Applications'),
			'manageApplications' => _('manage Applications'),
			'viewApplicationsGroups' => _('view Application groups'),
			'manageApplicationsGroups' => _('manage Application groups'),
			'viewPublications' => _('view Publications'),
			'managePublications' => _('manage Publications'),
			'viewConfiguration' => _('view Configuration'),
			'manageConfiguration' => _('manage Configuration'),
			'viewStatus' => _('view Status'),
			'viewSummary' => _('view Summary'),
			'viewNews' => _('view News'),
			'manageNews' => _('manage News')
		));

		$this->add($c,'general', 'policy');
		$this->addPrettyName('policy', _('Policy for administration delegation'));

		$this->addPrettyName('sql',_('SQL configuration'));
		$c = new ConfigElement_select('type', _('Database type'), _('The type of your database.'), _('The type of your database.'), 'mysql');
		$c->setContentAvailable(array('mysql'=>_('MySQL')));
		$this->add($c,'general','sql');
		$c = new ConfigElement_input('host', _('Database host address'), _('The address of your database host. This database contains adminstration console data. Example: localhost or db.mycorporate.com.'), _('The address of your database host. This database contains adminstrations console data. Example: localhost or db.mycorporate.com.'),'');
		$this->add($c,'general','sql');
		$c = new ConfigElement_input('user', _('Database username'), _('The username that must be used to access the database.'), _('The user name that must be used to access the database.'),'');
		$this->add($c,'general','sql');
		$c = new ConfigElement_password('password',_('Database password'), _('The user password that must be used to access the database.'), _('The user password that must be used to access the database.'),'');
		$this->add($c,'general','sql');
		$c = new ConfigElement_input('database', _('Database name'), _('The name of the database.'), _('The name of the database.'), '');
		$this->add($c,'general','sql');
		$c = new ConfigElement_input('prefix', _('Table prefix'), _('The table prefix for the database.'), _('The table prefix for the database.'), 'ulteo_');
		$this->add($c,'general','sql');

		$this->addPrettyName('mails_settings',_('Email settings'));
		$c = new ConfigElement_select('send_type', _('Mail server type'), _('Mail server type'), _('Mail server type'),'mail');
		$c->setContentAvailable(array('mail'=>_('Local'),'smtp'=>_('SMTP server')));
		$this->add($c,'general','mails_settings');
		$c = new ConfigElement_input('send_from', _('From'), _('From'), _('From'), 'no-reply@'.@$_SERVER['SERVER_NAME']);
		$this->add($c,'general','mails_settings');
		$c = new ConfigElement_input('send_host', _('Host'), _('Host'), _('Host'), '');
		$this->add($c,'general','mails_settings');
		$c = new ConfigElement_input('send_port', _('Port'), _('Port'), _('Port'), 25);
		$this->add($c,'general','mails_settings');
		$c = new ConfigElement_select('send_ssl', _('Use SSL with SMTP'), _('Use SSL with SMTP'), _('Use SSL with SMTP'), 0);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','mails_settings');
		$c = new ConfigElement_select('send_auth', _('Authentication'), _('Authentication'), _('Authentication'),0);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','mails_settings');
		$c = new ConfigElement_input('send_username', _('SMTP username'), _('SMTP username'), _('SMTP username'), '');
		$this->add($c,'general','mails_settings');
		$c = new ConfigElement_password('send_password', _('SMTP password'), _('SMTP password'), _('SMTP password'), '');
		$this->add($c,'general','mails_settings');

		$this->addPrettyName('slave_server_settings',_('Slave Server settings'));
		$c = new ConfigElement_list('authorized_fqdn', _('Authorized machines (FQDN or IP - the use of wildcards (*.) is allowed)'), _('Authorized machines (FQDN or IP - the use of wildcards (*.) is allowed)'), _('Authorized machines (FQDN or IP - the use of wildcards (*.) is allowed)'), array('*'));
		$this->add($c,'general', 'slave_server_settings');
		//fqdn_private_address : array('dns' => ip);
		$c = new ConfigElement_dictionary('fqdn_private_address', _('Name/IP Address association (name <-> ip)'), _('Enter a private addresses you wish to associate to a specific IP in case of issue with the DNS configuration or to override a reverse address result. Example: pong.office.ulteo.com (field 1) 192.168.0.113 (field 2)'), _('Enter a private addresses you wish to associate to a specific IP in case of issue with the DNS configuration or to override a reverse address result. Example: pong.office.ulteo.com (field 1) 192.168.0.113 (field 2)'), array());
		$this->add($c,'general', 'slave_server_settings');
		$c = new ConfigElement_select('disable_fqdn_check', _('Disable reverse FQDN checking'), _('Enable this option if you don\'t want to check that the result of the reverse FQDN address fits the one that was registered.'), _('Enable this option if you don\'t want to check that the result of the reverse FQDN address fits the one that was registered.'), 0);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general', 'slave_server_settings');
		$c = new ConfigElement_select('action_when_as_not_ready', _('Action when an server status is not ready anymore'), _('Action when an server status is not ready anymore'), _('Action when an server status is not ready anymore'), 0);
		$c->setContentAvailable(array(0=>_('Do nothing'),1=>_('Switch to maintenance')));
		$this->add($c,'general', 'slave_server_settings');
		$c = new ConfigElement_select('remove_orphan', _('Remove orphan applications when the application server is deleted'), _('Remove orphan applications when the application server is deleted'), _('Remove orphan applications when the application server is deleted'), 0);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','slave_server_settings');
		$c = new ConfigElement_select('auto_register_new_servers', _('Auto register new servers'), _('Auto register new servers'), _('Auto register new servers'), 0);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','slave_server_settings');
		$c = new ConfigElement_select('auto_switch_new_servers_to_production', _('Auto switch new servers to production mode'), _('Auto switch new servers to production mode'), _('Auto switch new servers to production mode'), 0);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','slave_server_settings');

		$roles = array(Servers::$role_aps => _('Load Balancing policy for Application Server'), Servers::$role_fs => _('Load Balancing policy for File Server'));
		foreach ($roles as $role => $text) {
			$decisionCriterion = get_classes_startwith('DecisionCriterion_');
			$content_load_balancing = array();
			foreach ($decisionCriterion as $criterion_class_name) {
				$c = new $criterion_class_name(NULL); // ugly
				if ($c->applyOnRole($role)) {
					$content_load_balancing[substr($criterion_class_name, strlen('DecisionCriterion_'))] = $c->default_value();
				}
			}
			$c = new ConfigElement_sliders_loadbalancing('load_balancing_'.$role, $text, $text, $text, $content_load_balancing);
			$this->add($c,'general', 'slave_server_settings');
		}

		$this->addPrettyName('remote_desktop_settings', _('Remote Desktop settings'));

		$c = new ConfigElement_select('enabled', _('Enable Remote Desktop'), _('Enable Remote Desktop'), _('Enable Remote Desktop'), 1);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','remote_desktop_settings');
		$c = new ConfigElement_select('persistent', _('Sessions are persistent'), _('Sessions are persistent'), _('Sessions are persistent'), 0);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','remote_desktop_settings');
		$c = new ConfigElement_select('desktop_icons', _('Show icons on user desktop'), _('Show icons on user desktop'), _('Show icons on user desktop'), 1);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','remote_desktop_settings');
		$c = new ConfigElement_select('allow_external_applications', _('Allow external applications in Desktop'), _('Allow external applications in Desktop'), _('Allow external applications in Desktop'), 1);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','remote_desktop_settings');
		$c = new ConfigElement_select('desktop_type', _('Desktop type'), _('Desktop type'), _('Desktop type'), 'any');
		$c->setContentAvailable(array('any'=>_('Any'),'linux'=>_('Linux'),'windows'=>_('Windows')));
		$this->add($c,'general','remote_desktop_settings');

		$this->addPrettyName('remote_applications_settings', _('Remote Applications settings'));

		$c = new ConfigElement_select('enabled', _('Enable Remote Applications'), _('Enable Remote Applications'), _('Enable Remote Applications'), 1);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','remote_applications_settings');

		$this->addPrettyName('session_settings_defaults',_('Sessions settings'));

		$c = new ConfigElement_select('session_mode', _('Default mode for session'), _('Default mode for session'), _('Default mode for session'), Session::MODE_APPLICATIONS);
		$c->setContentAvailable(array(Session::MODE_DESKTOP=>_('Desktop'), Session::MODE_APPLICATIONS=>_('Applications')));
		$this->add($c,'general','session_settings_defaults');

		$c = new ConfigElement_select('language', _('Default language for session'), _('Default language for session'), _('Default language for session'), 'en_GB');
		$c->setContentAvailable(array('en_GB'=>'English','fr_FR'=>'Français','ja_JP'=>'日本語','ru_RU'=>'Русский'));
		$this->add($c,'general','session_settings_defaults');
		$c = new ConfigElement_select('timeout', _('Default timeout for session'), _('Default timeout for session'), _('Default timeout for session'), 86400);
		$c->setContentAvailable(array(60 => _('1 minute'),120 => _('2 minutes'),300 => _('5 minutes'),600 => _('10 minutes'),900 => _('15 minutes'),1800 => _('30 minutes'),3600 => _('1 hour'),7200 => _('2 hours'),18000 => _('5 hours'),43200 => _('12 hours'),86400 => _('1 day'),172800 => _('2 days'),604800 => _('1 week'),2764800 => _('1 month'),-1 => _('Never')));
		$this->add($c,'general','session_settings_defaults');
		$c = new ConfigElement_select('launch_without_apps', _('User can launch a session with no application'), _('User can launch a session with no application'), _('User can launch a session with no application'), 0);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','session_settings_defaults');
		$c = new ConfigElement_select('allow_shell', _('User can use a console in the session'), _('User can use a console in the session'), _('User can use a console in the session'), 0);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','session_settings_defaults');

		$c = new ConfigElement_select('multimedia', _('Multimedia'), _('Multimedia'), _('Multimedia'), 1);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','session_settings_defaults');
		$c = new ConfigElement_select('redirect_client_printers', _('Redirect client printers'), _('Redirect client printers'), _('Redirect client printers'), 1);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','session_settings_defaults');

		$c = new ConfigElement_select('auto_create_profile', _('Auto-create user profile when nonexistant'), _('Auto-create user profile when nonexistant'), _('Auto-create user profile when nonexistant'), 1);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','session_settings_defaults');
		$c = new ConfigElement_select('start_without_profile', _('Launch a session without a valid profile'), _('Launch a session without a valid profile'), _('Launch a session without a valid profile'), 1);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','session_settings_defaults');
		$c = new ConfigElement_select('start_without_all_sharedfolders', _('Launch a session even if a shared folder\'s fileserver is missing'), _('Launch a session even if a shared folder\'s fileserver is missing'), _('Launch a session even if a shared folder\'s fileserver is missing'), 1);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','session_settings_defaults');

		$c = new ConfigElement_multiselect('advanced_settings_startsession', _('Forceable paramaters by users'), _('Choose Advanced Settings options you want to make available to users before they launch a session.'), _('Choose Advanced Settings options you want to make available to users before they launch a session.'), array('session_mode', 'language'));
		$c->setContentAvailable(array('session_mode' => _('session mode'), 'language' => _('language'), 'server' => _('server'), 'timeout' => _('timeout'), /*'persistent' => _('persistent'), 'shareable' => _('shareable')*/));
		$this->add($c,'general','session_settings_defaults');

		$this->addPrettyName('web_interface_settings',_('Web interface settings'));

		$c = new ConfigElement_select('show_list_users', _('Display users list'), _('Display the list of users from the corporate directory in the login box. If the list is not displayed, the user must enter his login name.'), _('Display the list of users from the corporate directory in the login box. If the list is not displayed, the user must enter his login name.'), 1);
		$c->setContentAvailable(array(0=>_('no'),1=>_('yes')));
		$this->add($c,'general','web_interface_settings');

		$this->getPrefsModules();
		$this->getPrefsPlugins();
		$this->getPrefsEvents();
	}


	public function getPrefsPlugins(){
		$plugs = new Plugins();
		$p2 = $plugs->getAvailablePlugins();
		// we remove all disabled Plugins
		foreach ($p2 as $plugin_dir2 => $plu2) {
			foreach ($plu2 as $plugin_name2 => $plugin_name2_value){
				if ($plugin_dir2 == 'plugins')
					$plugin_enable2 = call_user_func(array('Plugin_'.$plugin_name2, 'enable'));
				else
					$plugin_enable2 = call_user_func(array($plugin_dir2.'_'.$plugin_name2, 'enable'));
				if ($plugin_enable2 !== true)
					unset($p2[$plugin_dir2][$plugin_name2]);
			}
		}
		$plugins_prettyname = array();
		if (array_key_exists('plugins', $p2)) {
			foreach ($p2['plugins'] as $plugin_name => $plu6) {
				$plugin_prettyname = call_user_func(array('Plugin_'.$plugin_name, 'prettyName'));
				if (is_null($plugin_prettyname))
					$plugin_prettyname = $plugin_name;
				$plugins_prettyname[$plugin_name] = $plugin_prettyname;
			}

			$c = new ConfigElement_multiselect('plugin_enable', _('Plugins activation'), _('Choose the plugins you want to enable.'), _('Choose the plugins you want to enable.'), array());
			$c->setContentAvailable($plugins_prettyname);
			$this->addPrettyName('plugins',_('Plugins configuration'));
			$this->add($c,'plugins');
			unset($p2['plugins']);
		}

		foreach ($p2 as $key1 => $value1){
			$plugins_prettyname = array();
			$c = new ConfigElement_select($key1, $key1, 'plugins '.$key1, 'plugins '.$key1, array());
			foreach ($value1 as $plugin_name => $plu6) {
				$plugin_prettyname = call_user_func(array($key1.'_'.$plugin_name, 'prettyName'));
				if (is_null($plugin_prettyname))
					$plugin_prettyname = $plugin_name;
				$plugins_prettyname[$plugin_name] = $plugin_prettyname;
				$c->setContentAvailable($plugins_prettyname);

				$isdefault1 = call_user_func(array($key1.'_'.$plugin_name, 'isDefault'));
				if ($isdefault1 === true) // replace the default value
					$c->content = $plugin_name;

				$plugin_conf = 'return '.$key1.'_'.$plugin_name.'::configuration();';
				$list_conf = call_user_func(array($key1.'_'.$plugin_name, 'configuration'));
				if (is_array($list_conf)) {
					foreach ($list_conf as $l_conf){
						$this->add($l_conf,'plugins', $key1.'_'.$plugin_name);
					}
				}
			}
			$this->add($c,'plugins');
		}
	}

	public function getPrefsModules(){
		$available_module = $this->getAvailableModule();
		// we remove all diseable modules
		foreach ($available_module as $mod2 => $sub_mod2){
			foreach ($sub_mod2 as $sub_mod_name2 => $sub_mod_pretty2){
				$enable =  call_user_func(array($mod2.'_'.$sub_mod_name2, 'enable'));
				if ($enable !== true)
					unset ($available_module[$mod2][$sub_mod_name2]);
			}

		}
		$modules_prettyname = array();
		$enabledByDefault = array();
		foreach ($available_module as $module_name => $sub_module) {
			$modules_prettyname[$module_name] = $module_name;
			if (call_user_func(array($module_name, 'enabledByDefault')))
				$enabledByDefault[] =  $module_name;
		}
		$c2 = new ConfigElement_multiselect('module_enable',_('Modules activation'), _('Choose the modules you want to enable.'), _('Choose the modules you want to enable.'), $enabledByDefault);
		$c2->setContentAvailable($modules_prettyname);
		$this->add($c2, 'general');

		foreach ($available_module as $mod => $sub_mod){
			$module_is_multiselect = call_user_func(array($mod, 'multiSelectModule'));
			if ( $module_is_multiselect) {
				$c = new ConfigElement_multiselect('enable', $mod, $mod, $mod, array());
				$c->setContentAvailable($sub_mod);
			}
			else {
				$c = new ConfigElement_select('enable', $mod, $mod, $mod, NULL);
				$c->setContentAvailable($sub_mod);
			}

			foreach ($sub_mod as $k4 => $v4) {
				$default1 = call_user_func(array($mod.'_'.$k4, 'isDefault'));
				if ($default1 === true) {
					if ( $module_is_multiselect) {
						$c->content[] = $k4;
					}
					else {
						$c->content = $k4;
					}
				}
			}

			//dirty hack (if this->elements[mod] will be empty)
			if (!isset($this->elements[$mod]))
				$this->elements[$mod] = array();

			$this->add($c,$mod);
			$this->addPrettyName($mod,'Module '.$mod);

			foreach ($sub_mod as $sub_mod_name => $sub_mod_pretty){
				$module_name= $mod.'_'.$sub_mod_name;
				$list_conf = call_user_func(array($module_name, 'configuration'));
				if (is_array($list_conf)) {
					foreach ($list_conf as $l_conf){
						$this->add($l_conf,$mod,$sub_mod_name);
					}
				}
			}
		}
	}

	public function getPrefsEvents() {
		/* Events settings */
		$this->addPrettyName('events', _("Events settings"));

		$c = new ConfigElement_list('mail_to', _('Mail addresses to send alerts to'), _('On system alerts, mails will be sent to these addresses'), NULL, array());
		$this->add($c,'events');

		$events = Events::loadAll();
		foreach ($events as $event) {
			$list = array();
			$pretty_list = array();
			foreach ($event->getCallbacks() as $cb) {
				if (! $cb['is_internal']) {
					$list[] = $cb['name'];
					$pretty_list[$cb['name']] = $cb['description'];
				}
			}
			if (count($list) == 0)
				continue;

			$event_name = $event->getPrettyName();
			/* FIXME: descriptions */
			$c = new ConfigElement_multiselect(get_class($event), $event_name,
			                       "When $event_name is emitted",
			                       "When $event_name is emitted",
			                       array());
			$c->setContentAvailable($pretty_list);
			$this->add($c, 'events', 'active_callbacks');
		}
		$this->addPrettyName('active_callbacks', _('Activated callbacks'));
		unset($events);
	}

	protected function getAvailableModule(){
		$ret = array();
		$files = glob(MODULES_DIR.'/*');
		foreach ($files as $path){
			if (is_dir($path)) {
				$files2 = glob($path.'/*');
				foreach ($files2 as $file2){
					if (is_file($file2)) {
						$pathinfo = pathinfo_filename($file2);
						if (!isset($ret[basename($pathinfo["dirname"])])){
							$ret[basename($pathinfo["dirname"])] = array();
						}
						if (array_key_exists('extension', $pathinfo) && ($pathinfo['extension'] == 'php')) {
							$pretty_name = call_user_func(array(basename($pathinfo["dirname"]).'_'.$pathinfo["filename"],'prettyName'));
							if ( is_null($pretty_name))
								$pretty_name = $pathinfo["filename"];
							$ret[basename($pathinfo["dirname"])][$pathinfo["filename"]] = $pretty_name;
						}
					}
				}
			}
		}
		return $ret;
	}

	public function add($value_,$key_,$container_=NULL){
		if (!is_null($container_)) {
			if (!isset($this->elements[$key_])) {
				$this->elements[$key_] = array();
			}
			else {
				if (is_object($this->elements[$key_])) {
					$val = $this->elements[$key_];
					$this->elements[$key_] = array();
					$this->elements[$key_][$val->id]= $val;
				}
			}
			if (!isset($this->elements[$key_][$container_])) {
				$this->elements[$key_][$container_] = array();
			}
			// already something on [$key_][$container_]
			if (is_array($this->elements[$key_][$container_]))
				$this->elements[$key_][$container_][$value_->id]= $value_;
			else {
				$val = $this->elements[$key_][$container_];
				$this->elements[$key_][$container_] = array();
				$this->elements[$key_][$container_][$val->id]= $val;
				$this->elements[$key_][$container_][$value_->id]= $value_;
			}
		}
		else {
			if (isset($this->elements[$key_])) {
				// already something on [$key_]
				if (is_array($this->elements[$key_]))
					$this->elements[$key_][$value_->id]= $value_;
				else {
					$val = $this->elements[$key_];
					$this->elements[$key_] = array();
					$this->elements[$key_][$val->id]= $val;
					$this->elements[$key_][$value_->id]= $value_;
				}
			}
			else {
				$this->elements[$key_] = $value_;
			}
		}
	}

	public function addPrettyName($key_,$prettyName_) {
		$this->prettyName[$key_] = $prettyName_;
	}


}
