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
require_once(dirname(__FILE__).'/../includes/core.inc.php');

function return_error($errno_, $errstr_) {
	header('Content-Type: text/xml; charset=utf-8');
	$dom = new DomDocument('1.0', 'utf-8');
	$node = $dom->createElement('error');
	$node->setAttribute('id', $errno_);
	$node->setAttribute('message', $errstr_);
	$dom->appendChild($node);
	Logger::error('main', "(webservices/server_monitoring) return_error($errno_, $errstr_)");
	return $dom->saveXML();
}

function parse_monitoring_XML($xml_) {
	if (! $xml_ || strlen($xml_) == 0)
		return false;

	$dom = new DomDocument('1.0', 'utf-8');

	$buf = @$dom->loadXML($xml_);
	if (! $buf)
		return false;

	if (! $dom->hasChildNodes())
		return false;

	$server_node = $dom->getElementsByTagName('server')->item(0);
	if (is_null($server_node))
		return false;

	if (! $server_node->hasAttribute('name'))
		return false;

	$server = Abstract_Server::load($server_node->getAttribute('name'));
	if (! $server)
		return false;

	if (! $server->isAuthorized())
		return false;

	$ret = array(
		'server'	=>	$server_node->getAttribute('name')
	);

	$cpu_node = $dom->getElementsByTagName('cpu')->item(0);
	if (is_null($cpu_node))
		return false;

	if (! $cpu_node->hasAttribute('load'))
		return false;

	$ret['cpu_load'] = $cpu_node->getAttribute('load');

	$ram_node = $dom->getElementsByTagName('ram')->item(0);
	if (is_null($ram_node))
		return false;

	if (! $ram_node->hasAttribute('used'))
		return false;

	$ret['ram_used'] = $ram_node->getAttribute('used');

	$role_nodes = $dom->getElementsByTagName('role');
	foreach ($role_nodes as $role_node) {
		if (! $role_node->hasAttribute('name'))
			return false;

		switch ($role_node->getAttribute('name')) {
			case 'ApplicationServer':
				$sql_sessions = get_from_cache('reports', 'sessids');
				if (! is_array($sql_sessions))
					$sql_sessions = array();

				$tmp = array();

				$ret['sessions'] = array();

				$session_nodes = $dom->getElementsByTagName('session');
				foreach ($session_nodes as $session_node) {
					$ret['sessions'][$session_node->getAttribute('id')] = array(
						'id'		=>	$session_node->getAttribute('id'),
						'status'	=>	$session_node->getAttribute('status'),
						'instances'	=>	array()
					);

					$childnodes = $session_node->childNodes;
					foreach ($childnodes as $childnode) {
						if ($childnode->nodeName != 'instance')
							continue;

						$ret['sessions'][$session_node->getAttribute('id')]['instances'][$childnode->getAttribute('id')] = $childnode->getAttribute('application');
					}

					$token = $session_node->getAttribute('id');
					$tmp[] = $token;

					if (array_key_exists($token, $sql_sessions))
						$sql_sessions[$token]->update($session_node);
				}

				foreach ($sql_sessions as $token => $session) {
					if (! in_array($token, $tmp))
						unset($sql_sessions[$token]);
				}

				set_cache($sql_sessions, 'reports', 'sessids');

				$sri = new ServerReportItem($ret['server'], $xml_);
				$sri->save();
				break;
		}
	}

	return $ret;
}

$ret = parse_monitoring_XML(@file_get_contents('php://input'));
if (! $ret) {
	echo return_error(1, 'Server does not send a valid XML');
	die();
}

$server = Abstract_Server::load($ret['server']);
if (! $server) {
	echo return_error(2, 'Server does not exist');
	die();
}

if (! $server->isAuthorized()) {
	echo return_error(3, 'Server is not authorized');
	die();
}

$server->setAttribute('cpu_load', $ret['cpu_load']);
$server->setAttribute('ram_used', $ret['ram_used']);

Abstract_Server::save($server); //update Server cache timestamp

if (array_key_exists('sessions', $ret) && is_array($ret['sessions'])) {
	foreach ($ret['sessions'] as $session) {
		$buf = Abstract_Session::load($session['id']);
		if (! $buf)
			continue;

		$modified = false;

		if ($session['status'] != $buf->getAttribute('status')) {
			$modified = true;
			$buf->setStatus($session['status']);
		}

		if ($session['status'] == Session::SESSION_STATUS_ACTIVE) {
			$modified = true;
			$buf->setRunningApplications($ret['server'], $session['instances']);
		}

		if ($modified === true)
			Abstract_Session::save($buf); //update Session cache timestamp
	}
}

header('Content-Type: text/xml; charset=utf-8');

$dom = new DomDocument('1.0', 'utf-8');
$server_node = $dom->createElement('server');
$server_node->setAttribute('name', $ret['server']);
$dom->appendChild($server_node);

$xml = $dom->saveXML();

echo $xml;
die();
