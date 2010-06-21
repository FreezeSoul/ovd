<?php
/**
 * Copyright (C) 2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com> 2010
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2010
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
require_once(dirname(__FILE__).'/../includes/core-minimal.inc.php');

function return_error($errno_, $errstr_) {
	$dom = new DomDocument('1.0', 'utf-8');
	$node = $dom->createElement('error');
	$node->setAttribute('id', $errno_);
	$node->setAttribute('message', $errstr_);
	$dom->appendChild($node);
	Logger::error('main', "(client/remote_apps) return_error($errno_, $errstr_)");
	return $dom->saveXML();
}

if (! array_key_exists('token', $_REQUEST)) {
	echo return_error(1, 'Usage: missing "token" $_REQUEST parameter');
	die();
}

$session = Abstract_Session::load($_REQUEST['token']);
if (! $session) {
	echo return_error(2, 'No such session: '.$_REQUEST['token']);
	die();
}

$userDB = UserDB::getInstance();
$user = $userDB->import($session->user_login);
if (! is_object($user)) {
	echo return_error(3, 'No such user: '.$session->user_login);
	die();
}

header('Content-Type: text/xml; charset=utf-8');
$dom = new DomDocument('1.0', 'utf-8');

$session_node = $dom->createElement('session');
$session_node->setAttribute('id', $session->id);
$session_node->setAttribute('mode', Session::MODE_APPLICATIONS);
$session_node->setAttribute('multimedia', false);
$session_node->setAttribute('redirect_client_printers', false);
foreach ($session->servers as $server) {
	$server = Abstract_Server::load($server);
	if (! $server)
		continue;

	if ($server->fqdn == $session->server)
		continue;

	$server_applications = $server->getApplications();
	if (! is_array($server_applications))
		$server_applications = array();

	$available_applications = array();
	foreach ($server_applications as $server_application)
		$available_applications[] = $server_application->getAttribute('id');

	$server_node = $dom->createElement('server');
	$server_node->setAttribute('fqdn', $server->getAttribute('external_name'));
	$server_node->setAttribute('login', $session->settings['aps_access_login']);
	$server_node->setAttribute('password', $session->settings['aps_access_password']);
	foreach ($user->applications() as $application) {
		if ($application->getAttribute('static'))
			continue;

		if ($application->getAttribute('type') != $server->getAttribute('type'))
			continue;

		if (! in_array($application->getAttribute('id'), $available_applications))
			continue;

		$application_node = $dom->createElement('application');
		$application_node->setAttribute('id', $application->getAttribute('id'));
		$application_node->setAttribute('name', $application->getAttribute('name'));
		$application_node->setAttribute('server', $server->getAttribute('external_name'));
		foreach (explode(';', $application->getAttribute('mimetypes')) as $mimetype) {
			if ($mimetype == '')
				continue;

			$mimetype_node = $dom->createElement('mime');
			$mimetype_node->setAttribute('type', $mimetype);
			$application_node->appendChild($mimetype_node);
		}
		$server_node->appendChild($application_node);
	}
	$session_node->appendChild($server_node);
}
$dom->appendChild($session_node);

echo $dom->saveXML();
exit(0);
