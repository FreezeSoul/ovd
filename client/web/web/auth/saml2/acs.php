<?php
/**
 * Copyright (C) 2014 Ulteo SAS
 * http://www.ulteo.com
 * Author Julien LANGLOIS <julien@ulteo.com> 2014
 * Author David PHAM-VAN <d.pham-van@ulteo.com> 2014
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


require_once(dirname(__FILE__).'/common.inc.php');

try {
	$auth = init_saml2_auth();
	$auth->processResponse();
} catch (Exception $e) {
	send_error($e->getMessage());
}

$errors = $auth->getErrors();
if (!empty($errors)) {
	send_error(implode(', ', $errors));
}

if (!$auth->isAuthenticated()) {
	send_error("Not authenticated");
}

$_SESSION['SAML2'] = true;
$_SESSION['SAML2_login'] = $auth->getNameId();
$_SESSION['SAML2_ticket'] = $_POST['SAMLResponse'];

setcookie('ovd-sso', 'true', 0, '/ovd/');
$auth->redirectTo(SAML2_REDIRECT_URI.'/ovd/');
