<?php
/**
 * Copyright (C) 2010 Ulteo SAS
 * http://www.ulteo.com
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
require_once(dirname(__FILE__).'/../admin/includes/core-minimal.inc.php');

//BEGIN Sessions expiration
$sessions = Abstract_Session::load_all();
foreach ($sessions as $session) {
	if ($session->start_time != 0 && array_key_exists('timeout', $session->settings) && $session->settings['timeout'] != 0) {
		if ($session->start_time+$session->settings['timeout'] < time()) {
			Logger::info('main', '(minutely cron) Session \''.$session->id.'\' has expired, ending...');
			$session->orderDeletion();
		}
	}
}
//END Sessions expiration

exit(0);
