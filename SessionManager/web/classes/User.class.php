<?php
/**
 * Copyright (C) 2008,2009 Ulteo SAS
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
class User {
	private $attributes;

	public function __construct(){
		$this->attributes = array();
	}

	public function setAttribute($myAttribute_,$value_){
		$this->attributes[$myAttribute_] = $value_;
	}

	public function hasAttribute($myAttribute_){
		return isset($this->attributes[$myAttribute_]);
	}

	public function getAttribute($myAttribute_){
		if (isset($this->attributes[$myAttribute_]))
			return $this->attributes[$myAttribute_];
		else
			return NULL;
	}

	public function getLocale() {
		if ( $this->hasAttribute('countrycode')) {
			$language = $this->getAttribute('countrycode'); // only works for ISO-3166
		}
		else {
			$prefs = Preferences::getInstance();
			if (! $prefs)
				die_error('get Preferences failed',__FILE__,__LINE__);
			
			$default_settings = $prefs->get('general', 'session_settings_defaults');
			$language = $default_settings['language'];
		}
		$locale = locale2unix($language);
		return $locale;
	}

	public function usersGroups(){
		Logger::debug('main','USER::UsersGroups');
		$result = array();
		// add the default user group is enable
		$prefs = Preferences::getInstance();
		if (!$prefs) {
			Logger::critical('main', 'USER::UsersGroups get prefs failed');
			die_error('get Preferences failed',__FILE__,__LINE__);
		}
		$user_default_group = $prefs->get('general', 'user_default_group');
		$userGroupDB = UserGroupDB::getInstance();

		$static = Abstract_Liaison::load('UsersGroup', $this->attributes['login'], NULL);
		if (is_null($static)) {
			Logger::error('main', 'User::usersGroups load('.$this->attributes['login'].') is null');
			return $result;
		}
		
		if ($userGroupDB->isDynamic()) {
			$dynamic = Abstract_Liaison_dynamic::load('UsersGroup', $this->attributes['login'], NULL);
			if (is_null($dynamic)) {
				$dynamic = array();
			}
		}
		else {
			$dynamic = array();
		}
		
		$rows = array_unique(array_merge($static, $dynamic));

		if (!is_null($user_default_group) && $user_default_group !== '-1' && $user_default_group !== '') {
			$g = $userGroupDB->import($user_default_group);// safe because even if  group = -1, the import failed safely
			if (is_object($g))
				$result[$user_default_group]= $g;
		}

		foreach ($rows as $lug){
			$g = $userGroupDB->import($lug->group);
			if (is_object($g))
				$result[$lug->group]= $g;
			else {
				Logger::error('main', 'USER::usersGroups user group (\''.$lug->group.'\') not ok');
			}
		}
		return $result;
	}

	public function appsGroups(){
		Logger::debug('main','USER::appsGroups');
		$apps_group_list = array();
		$users_grps = $this->usersGroups();
		foreach ($users_grps as $ugrp){
			if ($ugrp->published){
				$app_group_s = $ugrp->appsGroups();
				if (is_array($app_group_s)) {
					foreach($app_group_s as $app_group){
						if ($app_group->published){
							array_push($apps_group_list,$app_group->id);
						}
					}
				}
			}
		}
		return array_unique($apps_group_list);
	}

	public function getAvailableServers(){
		$prefs = Preferences::getInstance();
		if (! $prefs)
			die_error('get Preferences failed',__FILE__,__LINE__);

		$default_settings = $prefs->get('general', 'session_settings_defaults');
		$launch_without_apps = (int)$default_settings['launch_without_apps'];

		$user_profile_mode = $prefs->get('UserDB', 'enable');
		$prefs_ad = $prefs->get('UserDB', 'activedirectory');

		// get the list of server who the user can launch his applications
		
		$application_server_settings = $prefs->get('general', 'application_server_settings');
		$default_settings = $prefs->get('general', 'session_settings_defaults');
		
		if (!isset($application_server_settings['load_balancing'])) {
			Logger::error('main' , 'USER::getAvailableServer $application_server_settings[\'load_balancing\'] not set');
			return NULL;
		}
		$criterions = $application_server_settings['load_balancing'];
		if (is_null($criterions)) {
			Logger::error('main' , 'USER::getAvailableServer criterions is null');
			return NULL;
		}
		
		$available_servers = Servers::getAvailableByRole(Servers::$role_aps);
		$server_object = array();
		$servers = array();
		
		foreach($available_servers as $server) {
			if ($server->isOnline()) {
				$val = 0;
				foreach ($criterions as $criterion_name  => $criterion_value ) {
					$name_class1 = 'DecisionCriterion_'.$criterion_name;
					$d1 = new $name_class1($server);
					$r1 = $d1->get();
					$val += $r1* $criterion_value;
				}
				$servers[$server->fqdn] = $val;
				$server_object[$server->fqdn] = $server;
			}
		}
		arsort($servers);
		
		$applications = $this->applications(NULL, false);
		$servers_to_use = array();
		
		foreach($servers as $fqdn => $val) {
			$server = $server_object[$fqdn];
			if (count($applications) == 0)
				break;
			$applications_from_server = $server->getApplications();
			foreach ($applications_from_server as $k => $an_server_application) {
				if (in_array($an_server_application, $applications)) {
					$servers_to_use []= $server;
					unset($applications[array_search($an_server_application, $applications)]);
				}
			}
		}
		$servers_to_use = array_unique($servers_to_use);
		
		// TODO: bug if the user have static application
		
		if (count($applications) == 0)
			return $servers_to_use;
		else {
			if ($launch_without_apps == 1) {
				return $servers_to_use;
			}
			else {
				Logger::error('main' , "USER::getAvailableServer() no server found for user '".$this->getAttribute('login')."'");
				return NULL;
			}
		}
	}

	public function applications($type=NULL, $with_static_=true){
		Logger::debug('main', "USER::applications(type=$type, with_static=$with_static_)");

		$applicationDB = ApplicationDB::getInstance();

		$my_applications_id = array();
		$my_applications = array();
		$appgroups_id = $this->appsGroups();
		foreach ($appgroups_id as $agrp_id){
			$els = Abstract_Liaison::load('AppsGroup', NULL,$agrp_id);
			if (is_array($els))
				foreach ($els as $e)
					array_push($my_applications_id,$e->element);
		}

		$my_applications_id =  array_unique($my_applications_id);
		foreach ($my_applications_id as $id){
			$app = $applicationDB->import($id);
			if (is_object($app)) {
				if ( $type != NULL) {
					if ( $app->getAttribute('type') == $type) {
						if ( $app->getAttribute('static') ) {
							if ( $with_static_) {
								$my_applications []= $app;
							}
						}
						else {
							$my_applications []= $app;
						}
					}
				}
				else {
					if ( $app->getAttribute('static') ) {
						if ( $with_static_) {
							$my_applications []= $app;
						}
					}
					else {
						$my_applications []= $app;
					}

				}
			}
		}
		return $my_applications;
	}
	
	public function getNetworkFolders() {
		return Abstract_NetworkFolder::load_from_user($this->getAttribute('login'));
	}
	
	public function getSharedFolders() {
		$sharedfolders = array();
		$usergroups = $this->usersGroups();
		if (is_array($usergroups) === false) {
			Logger::error('main', 'User::getSharedFolders usersGroups failed for user (login='.$this->getAttribute('login').')');
		}
		else {
			foreach ($usergroups as $group) {
				$networkfolders = Abstract_NetworkFolder::load_from_usergroup($group->getUniqueID());
				foreach ($networkfolders as $a_networkfolder) {
					$sharedfolders[] = $a_networkfolder;
				}
			}
		}
		return array_unique($sharedfolders);
	}

	public function getAttributesList(){
		return array_keys($this->attributes);
	}

	public function __toString() {
		$ret = 'User(';
		foreach ($this->attributes as $k => $attr) {
				$ret .= "'$k':'";
				if ( is_array($attr)) {
					$ret .= ' [';
					foreach ($attr as $k => $v) {
						$ret .= "'$k':'$v', ";
					}
					$ret .= '] ';
				}
				else {
					$ret .= $attr;
				}
				$ret .= "', ";
		}
		$ret .= ')';
		return $ret;
	}
	
	public function getPolicy() {
		Logger::debug('main', 'User::getPolicy for '.$this->getAttribute('login'));
		$prefs = Preferences::getInstance();
		$policies = $prefs->get('general', 'policy');
		$default_policy = $policies['default_policy'];
		$elements = $prefs->getElements('general', 'policy');
		if (array_key_exists('default_policy', $elements) == false) {
			Logger::error('main', 'User::getPolicy, default_policy not found on general policy');
			return array();
		}
		$result = $elements['default_policy']->content_available;
		
		foreach ($result as $k => $v) {
				if (in_array($k, $default_policy))
						$result[$k] = true;
				else 
						$result[$k] = false;
		}
		$groups = $this->usersGroups();
		foreach ($groups as $a_group) {
			$policy = $a_group->getPolicy();
			if (is_array($policy)) {
				foreach ($policy as $key => $value){
					if (array_key_exists($key, $result)) {
						if ($value == true)
							$result[$key] = $value;
					}
					else {
						$result[$key] = $value;
					}
				}
			}
		}
		return $result;
	}
	
	public function getSessionSettings() {
		$prefs = Preferences::getInstance();
		$overriden = array();
		$default_settings = $prefs->get('general', 'session_settings_defaults');
		$groups = $this->usersGroups();
		foreach ($groups as $a_group) {
			$prefs_of_a_group_unsort = Abstract_UserGroup_Preferences::loadByUserGroupId($a_group->getUniqueID(), 'general',  'session_settings_defaults');
			foreach ($prefs_of_a_group_unsort as $key => $pref) {
				$element = $pref->toConfigElement();
				if (isset($overriden[$key]) && ($overriden[$key] == true) && ($element->content != $default_settings[$key])) {
					popup_error(sprintf(_("User '%s' have at least two groups with the same overriden rule but with differents value, the result will be unpredictable."), $this->getAttribute('login')));
				}
				$default_settings[$key] = $element->content;
				$overriden[$key] = true;
			}
		}
		return $default_settings;
	}
}
