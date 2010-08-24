<?php
/**
 * Copyright (C) 2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Jeremy DESVAGES <jeremy@ulteo.com>
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

require_once(dirname(__FILE__).'/includes/core.inc.php');

$languages = get_available_languages();
$keymaps = get_available_keymaps();

$wi_sessionmanager_host = '';
if (defined('SESSIONMANAGER_HOST'))
	$wi_sessionmanager_host	= SESSIONMANAGER_HOST;
if (isset($_COOKIE['webinterface']['sessionmanager_host']))
	$wi_sessionmanager_host = (string)$_COOKIE['webinterface']['sessionmanager_host'];

$wi_user_login = '';
if (isset($_COOKIE['webinterface']['user_login']))
	$wi_user_login = (string)$_COOKIE['webinterface']['user_login'];

$wi_use_local_credentials = 0;
if (isset($_COOKIE['webinterface']['use_local_credentials']))
	$wi_use_local_credentials = (int)$_COOKIE['webinterface']['use_local_credentials'];

$wi_session_mode = 'desktop';
if (isset($_COOKIE['webinterface']['session_mode']))
	$wi_session_mode = (string)$_COOKIE['webinterface']['session_mode'];

if (isset($_COOKIE['webinterface']['session_language']) && $_COOKIE['webinterface']['session_language'] != $user_language) {
	$wi_session_language = (string)$_COOKIE['webinterface']['session_language'];
	$user_language = $wi_session_language;
}

if (isset($_COOKIE['webinterface']['session_keymap']) && $_COOKIE['webinterface']['session_keymap'] != $user_keymap) {
	$wi_session_keymap = (string)$_COOKIE['webinterface']['session_keymap'];
	$user_language = $wi_session_keymap;
}

$wi_use_popup = 0;
if (isset($_COOKIE['webinterface']['use_popup']))
	$wi_use_popup = (int)$_COOKIE['webinterface']['use_popup'];

$wi_debug = 1;
if (isset($_COOKIE['webinterface']['debug']))
	$wi_debug = (int)$_COOKIE['webinterface']['debug'];

function get_users_list() {
	if (! defined('SESSIONMANAGER_HOST'))
		return false;

	global $sessionmanager_url;

	$ret = query_sm($sessionmanager_url.'/userlist.php');

	$dom = new DomDocument('1.0', 'utf-8');
	$buf = @$dom->loadXML($ret);
	if (! $buf)
		return false;

	if (! $dom->hasChildNodes())
		return false;

	$users_node = $dom->getElementsByTagname('users')->item(0);
	if (is_null($users_node))
		return false;

	$users = array();
	foreach ($users_node->childNodes as $user_node) {
		if ($user_node->hasAttribute('login'))
			$users[$user_node->getAttribute('login')] = ((strlen($user_node->getAttribute('displayname')) > 32)?substr($user_node->getAttribute('displayname'), 0, 32).'...':$user_node->getAttribute('displayname'));
	}
	natsort($users);

	return $users;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>Ulteo Open Virtual Desktop</title>

		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

		<script type="text/javascript" src="media/script/lib/prototype/prototype.js" charset="utf-8"></script>

		<script type="text/javascript" src="media/script/lib/scriptaculous/scriptaculous.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/lib/scriptaculous/extensions.js" charset="utf-8"></script>

		<link rel="stylesheet" type="text/css" href="media/script/lib/nifty/niftyCorners.css" />
		<script type="text/javascript" src="media/script/lib/nifty/niftyCorners.js" charset="utf-8"></script>
		<script type="text/javascript" charset="utf-8">
			NiftyLoad = function() {
				Nifty('div.rounded');
			}
		</script>

		<link rel="shortcut icon" type="image/png" href="media/image/favicon.ico" />
		<link rel="stylesheet" type="text/css" href="media/style/common.css" />
		<script type="text/javascript" src="media/script/common.js?<?php echo time(); ?>" charset="utf-8"></script>

		<script type="text/javascript" src="media/script/i18n.js.php?<?php echo time(); ?>" charset="utf-8"></script>

		<script type="text/javascript" src="media/script/daemon.js?<?php echo time(); ?>" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/daemon_desktop.js?<?php echo time(); ?>" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/daemon_applications.js?<?php echo time(); ?>" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/server.js?<?php echo time(); ?>" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/application.js?<?php echo time(); ?>" charset="utf-8"></script>

		<script type="text/javascript" src="media/script/timezones.js" charset="utf-8"></script>

		<script type="text/javascript">
			var daemon;

			Event.observe(window, 'load', function() {
				new Effect.Center($('splashContainer'));
				new Effect.Move($('splashContainer'), { x: 0, y: -75 });

				new Effect.Center($('endContainer'));
				new Effect.Move($('endContainer'), { x: 0, y: -75 });

				$('desktopModeContainer').hide();
				$('desktopAppletContainer').hide();

				$('applicationsModeContainer').hide();
				$('applicationsAppletContainer').hide();

				$('printingAppletContainer').hide();

				$('debugContainer').hide();
				$('debugLevels').hide();
			});
		</script>
	</head>

	<body style="margin: 50px; background: #ddd; color: #333;">
		<div id="lockWrap" style="display: none;">
		</div>

		<div style="background: #2c2c2c; width: 0px; height: 0px;">
			<div id="errorWrap" class="rounded" style="display: none;">
			</div>
			<div id="okWrap" class="rounded" style="display: none;">
			</div>
			<div id="infoWrap" class="rounded" style="display: none;">
			</div>
		</div>

		<div id="testJava">
			<applet id="CheckJava" code="org.ulteo.ovd.applet.CheckJava" codebase="applet/" archive="CheckJava.jar" mayscript="true" width="1" height="1">
				<param name="code" value="org.ulteo.ovd.applet.CheckJava" />
				<param name="codebase" value="applet/" />
				<param name="archive" value="CheckJava.jar" />
				<param name="mayscript" value="true" />
			</applet>
		</div>

		<div style="background: #2c2c2c; width: 0px; height: 0px;">
			<div id="systemTestWrap" class="rounded" style="display: none;">
				<div id="systemTest" class="rounded">
					<table style="width: 100%; margin-left: auto; margin-right: auto;" border="0" cellspacing="1" cellpadding="3">
						<tr>
							<td style="text-align: left; vertical-align: top;">
								<strong><?php echo _('Checking for system compatibility'); ?></strong>
								<div style="margin-top: 15px;">
									<p><?php echo _('If this is your first time here, a Java security window will show up and you have to accept it to use the service.'); ?></p>
									<p><?php echo _('You are advised to check the "<i>Always trust content from this publisher</i>" checkbox.'); ?></p>
								</div>
							</td>
							<td style="width: 32px; height: 32px; text-align: right; vertical-align: top;">
								<img src="media/image/rotate.gif" width="32" height="32" alt="" title="" />
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div id="systemTestErrorWrap" class="rounded" style="display: none;">
				<div id="systemTestError" class="rounded">
					<table style="width: 100%; margin-left: auto; margin-right: auto;" border="0" cellspacing="1" cellpadding="3">
						<tr>
							<td style="text-align: left; vertical-align: middle;">
								<strong><?php echo _('System compatibility error'); ?></strong>
								<div id="systemTestError1" style="margin-top: 15px; display: none;">
									<p><?php echo _('Java is not available on your system or in your web browser.'); ?></p>
									<p><?php echo _('Please install Java extension for your web browser or contact your administrator.'); ?></p>
								</div>

								<div id="systemTestError2" style="margin-top: 15px; display: none;">
									<p><?php echo _('You have not accepted the Java security window.'); ?></p>
								</div>

								<p>You <strong>cannot</strong> have access to this service.</p>
							</td>
							<td style="width: 32px; height: 32px; text-align: right; vertical-align: top;">
								<img src="media/image/error.png" width="32" height="32" alt="" title="" />
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<div id="splashContainer" class="rounded" style="display: none;">
			<table style="width: 100%; padding: 10px;" border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td style="text-align: center;" colspan="3">
						<img src="media/image/ulteo.png" alt="" title="" />
					</td>
				</tr>
				<tr>
					<td style="text-align: left; vertical-align: middle; margin-top: 15px;">
						<span style="font-size: 1.35em; font-weight: bold; color: #686868;"><?php echo _('Loading:'); ?> Open Virtual Desktop</span>
					</td>
					<td style="width: 20px"></td>
					<td style="text-align: left; vertical-align: middle;">
						<img src="media/image/rotate.gif" width="32" height="32" alt="" title="" />
					</td>
				</tr>
				<tr>
					<td style="text-align: left; vertical-align: middle;" colspan="3">
						<div id="progressBar">
							<div id="progressBarContent"></div>
						</div>
					</td>
				</tr>
			</table>
		</div>

		<div id="endContainer" class="rounded" style="display: none;">
			<table style="width: 100%; padding: 10px;" border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td style="text-align: center;">
						<img src="media/image/ulteo.png" alt="" title="" />
					</td>
				</tr>
				<tr>
					<td style="text-align: center; vertical-align: middle; margin-top: 15px;" id="endContent">
					</td>
				</tr>
			</table>
		</div>

		<div id="desktopModeContainer" style="display: none;">
			<div id="desktopAppletContainer" style="display: none;">
			</div>
		</div>

		<div id="applicationsModeContainer" style="display: none;">
			<div id="applicationsHeaderWrap">
				<table style="width: 100%; margin-left: auto; margin-right: auto;" border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td style="width: 175px; text-align: left; border-bottom: 1px solid #ccc;" class="logo">
							<img src="media/image/ulteo.png" height="80" alt="Ulteo Open Virtual Desktop" title="Ulteo Open Virtual Desktop" />
						</td>
						<td style="text-align: left; border-bottom: 1px solid #ccc; width: 60%;" class="title centered">
							<h1><?php echo _('Welcome!'); ?></h1>
						</td>
						<td style="text-align: right; padding-left: 5px; padding-right: 10px; border-bottom: 1px solid #ccc;">
							<table style="margin-left: auto; margin-right: 0px;" border="0" cellspacing="0" cellpadding="10">
								<tr>
									<?php
										/*{ //persistent session
									?>
									<td style="text-align: center; vertical-align: middle;"><a href="#" onclick="daemon.suspend(); return false;"><img src="media/image/suspend.png" width="32" height="32" alt="suspend" title="<?php echo _('Suspend'); ?>" /><br /><?php echo _('Suspend'); ?></a></td>
									<?php
										}*/
									?>
									<td style="text-align: center; vertical-align: middle;"><a href="#" onclick="daemon.logout(); return false;"><img src="media/image/logout.png" width="32" height="32" alt="logout" title="<?php echo _('Logout'); ?>" /><br /><?php echo _('Logout'); ?></a></td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</div>

			<table id="applicationsContainer" style="width: 100%; background: #eee;" border="0" cellspacing="0" cellpadding="5">
				<tr>
					<td style="width: 15%; text-align: left; vertical-align: top; background: #eee;">
						<div class="container rounded" style="background: #fff; width: 98%; margin-left: auto; margin-right: auto;">
						<div>
							<h2><?php echo _('My applications'); ?></h2>

							<div id="appsContainer" style="overflow: auto;">
							</div>
						</div>
						</div>
					</td>
					<td style="width: 5px;">
					</td>
					<td style="width: 15%; text-align: left; vertical-align: top; background: #eee;">
						<div class="container rounded" style="background: #fff; width: 98%; margin-left: auto; margin-right: auto;">
						<div>
							<h2><?php echo _('Running applications'); ?></h2>

							<div id="runningAppsContainer" style="overflow: auto;">
							</div>
						</div>
						</div>
					</td>
					<td style="width: 5px;">
					</td>
					<td style="text-align: left; vertical-align: top; background: #eee;">
						<div class="container rounded" style="background: #fff; width: 98%; margin-left: auto; margin-right: auto;">
						<div>
							<h2><?php echo _('My files'); ?></h2>

							<div id="fileManagerContainer">
							</div>
						</div>
						</div>
					</td>
				</tr>
			</table>

			<div id="applicationsAppletContainer" style="display: none;">
			</div>
		</div>

		<div id="printingAppletContainer" style="display: none;">
		</div>

		<div id="debugContainer" class="no_debug info warning error" style="display: none;">
		</div>

		<div id="debugLevels" style="display: none;">
			<span class="debug"><input type="checkbox" id="level_debug" onclick="daemon.switch_debug('debug');" value="10" /> Debug</span>
			<span class="info"><input type="checkbox" id="level_info" onclick="daemon.switch_debug('info');" value="20" checked="checked" /> Info</span>
			<span class="warning"><input type="checkbox" id="level_warning" onclick="daemon.switch_debug('warning');" value="30" checked="checked" /> Warning</span>
			<span class="error"><input type="checkbox" id="level_error" onclick="daemon.switch_debug('error');" value="40" checked="checked" /> Error</span><br />
			<input type="button" onclick="daemon.clear_debug(); return false;" value="Clear" />
		</div>

		<div id="mainWrap">
			<div id="headerWrap">
			</div>

			<div class="spacer"></div>

			<div id="pageWrap">
				<div id="loginBox" class="rounded" style="display: none;">
					<table style="width: 100%; margin-left: auto; margin-right: auto;" border="0" cellspacing="0" cellpadding="0">
						<tr>
							<td style="width: 300px; text-align: left; vertical-align: top;">
								<img src="media/image/ulteo.png" alt="" title="" />
							</td>
							<td style="width: 10px;">
							</td>
							<td style="text-align: center; vertical-align: top;">
								<div id="loginForm" class="rounded">
									<script type="text/javascript">
									var sessionmanager_host_example = '<?php echo _('Example: sm.ulteo.com'); ?>';
									Event.observe(window, 'load', function() {
										$('timezone').value = getTimezoneName();

										setTimeout(function() {
<?php
if (! defined('SESSIONMANAGER_HOST') && (! isset($wi_sessionmanager_host) || $wi_sessionmanager_host == ''))
	echo '$(\'sessionmanager_host\').focus();';
elseif (isset($wi_user_login) && $wi_user_login != '')
	echo '$(\'user_password\').focus();';
else
	echo '$(\'user_login\').focus();';
?>

checkLogin();
										}, 1500);
									});</script>
									<form id="startsession" action="launch.php" method="post" onsubmit="return startSession();">
										<input type="hidden" id="timezone" name="timezone" value="" />

										<table style="width: 100%; margin-left: auto; margin-right: auto; padding-top: 10px;" border="0" cellspacing="0" cellpadding="5">
											<tr style="<?php echo ((defined('SESSIONMANAGER_HOST'))?'display: none;':'') ?>">
												<td style="width: 22px; text-align: right; vertical-align: middle;">
													<img src="media/image/icons/sessionmanager.png" alt="" title="" />
												</td>
												<td style="text-align: left; vertical-align: middle;">
													<strong><?php echo _('Session Manager'); ?></strong>
												</td>
												<td style="text-align: right; vertical-align: middle;">
													<input type="text" id="sessionmanager_host" value="<?php echo $wi_sessionmanager_host; ?>" onchange="checkLogin();" onkeyup="checkLogin();" />
													<script type="text/javascript">Event.observe(window, 'load', function() {
														setTimeout(function() {
															if ($('sessionmanager_host').value == '') {
																$('sessionmanager_host').style.color = 'grey';
																$('sessionmanager_host').value = sessionmanager_host_example;
																setCaretPosition($('sessionmanager_host'), 0);
															}
															Event.observe($('sessionmanager_host'), 'keypress', function() {
																if ($('sessionmanager_host').value == sessionmanager_host_example) {
																	$('sessionmanager_host').style.color = 'black';
																	$('sessionmanager_host').value = '';
																}
															});
															Event.observe($('sessionmanager_host'), 'keyup', function() {
																if ($('sessionmanager_host').value == '') {
																	$('sessionmanager_host').style.color = 'grey';
																	$('sessionmanager_host').value = sessionmanager_host_example;
																	setCaretPosition($('sessionmanager_host'), 0);
																}
															});
														}, 1500);
													});</script>
												</td>
											</tr>
											<tr>
												<td style="width: 22px; text-align: right; vertical-align: middle;">
													<img src="media/image/icons/user_login.png" alt="" title="" />
												</td>
												<td style="text-align: left; vertical-align: middle;">
													<strong><?php echo _('Login'); ?></strong>
												</td>
												<td style="text-align: right; vertical-align: middle;">
													<?php
														if (defined('SESSIONMANAGER_HOST'))
															$users = get_users_list();

														if (! defined('SESSIONMANAGER_HOST') || $users === false) {
													?>
													<input type="text" id="user_login" value="<?php echo $wi_user_login; ?>" onchange="checkLogin();" onkeyup="checkLogin();" />
													<?php
														} else {
													?>
													<select id="user_login" onchange="checkLogin();" onkeyup="checkLogin();">
													<?php
														foreach ($users as $login => $displayname)
															echo '<option value="'.$login.'"'.(($login == $wi_user_login)?'selected="selected"':'').'>'.$login.' ('.$displayname.')</option>'."\n";
													?>
													</select>
													<?php
														}
													?>
												</td>
											</tr>
											<tr>
												<td style="text-align: right; vertical-align: middle;">
													<img src="media/image/icons/user_password.png" alt="" title="" />
												</td>
												<td style="text-align: left; vertical-align: middle;">
													<strong><?php echo _('Password'); ?></strong>
												</td>
												<td style="text-align: right; vertical-align: middle;">
													<input type="password" id="user_password" value="" />
												</td>
											</tr>
										</table>
<?php
	if ($debug_mode) {
?>
										<script type="text/javascript">
											Event.observe(window, 'load', function() {
												switchSettings();
											});
										</script>
<?php
	}
?>
										<div id="advanced_settings" style="display: none;">
											<table style="width: 100%; margin-left: auto; margin-right: auto;" border="0" cellspacing="0" cellpadding="5">
												<tr>
													<td style="text-align: right; vertical-align: middle;">
														<img src="media/image/icons/use_local_credentials.png" alt="" title="" />
													</td>
													<td style="text-align: left; vertical-align: middle;">
														<strong><?php echo _('Use local credentials'); ?></strong>
													</td>
													<td style="text-align: right; vertical-align: middle;">
														<input class="input_radio" type="radio" id="use_local_credentials_true" name="use_local_credentials" value="1"<?php if ($wi_use_local_credentials == 1) echo ' checked="checked"'; ?> onchange="checkLogin();" onclick="checkLogin();" /> <?php echo _('Yes'); ?>
														<input class="input_radio" type="radio" id="use_local_credentials_false" name="use_local_credentials" value="0"<?php if ($wi_use_local_credentials == 0) echo ' checked="checked"'; ?> onchange="checkLogin();" onclick="checkLogin();" /> <?php echo _('No'); ?>
													</td>
												</tr>
												<tr>
													<td style="width: 22px; text-align: right; vertical-align: middle;">
														<img src="media/image/icons/session_mode.png" alt="" title="" />
													</td>
													<td style="text-align: left; vertical-align: middle;">
														<strong><?php echo _('Mode'); ?></strong>
													</td>
													<td style="text-align: right; vertical-align: middle;">
														<select id="session_mode">
															<option value="desktop"<?php if ($wi_session_mode == 'desktop') echo ' selected="selected"'; ?>><?php echo _('Desktop'); ?></option>
															<option value="applications"<?php if ($wi_session_mode == 'applications') echo ' selected="selected"'; ?>><?php echo _('Portal'); ?></option>
														</select>
													</td>
												</tr>
												<tr>
													<td style="text-align: right; vertical-align: middle;">
														<img src="media/image/icons/session_language.png" alt="" title="" />
													</td>
													<td style="text-align: left; vertical-align: middle;">
														<strong><?php echo _('Language'); ?></strong>
													</td>
													<td style="text-align: right; vertical-align: middle;">
														<span style="margin-right: 5px;"><img id="session_language_flag" /></span><script type="text/javascript">Event.observe(window, 'load', function() { updateFlag($('session_language').value); updateKeymap($('session_language').value); });</script><select id="session_language" onchange="updateFlag($('session_language').value); updateKeymap($('session_language').value);" onkeyup="updateFlag($('session_language').value); updateKeymap($('session_language').value);">
															<?php
																foreach ($languages as $language)
																	echo '<option value="'.$language['id'].'" style="background: url(\'media/image/flags/'.$language['id'].'.png\') no-repeat right;"'.(($language['id'] == $user_language || $language['id'] == substr($user_language, 0, 2))?' selected="selected"':'').'>'.$language['english_name'].((array_key_exists('local_name', $language))?' - '.$language['local_name']:'').'</option>';
															?>
														</select>
													</td>
												</tr>
												<tr>
													<td style="text-align: right; vertical-align: middle;">
														<img src="media/image/icons/keyboard_layout.png" alt="" title="" />
													</td>
													<td style="text-align: left; vertical-align: middle;">
														<strong><?php echo _('Keyboard layout'); ?></strong>
													</td>
													<td style="text-align: right; vertical-align: middle;">
														<select id="session_keymap">
															<?php
																foreach ($keymaps as $keymap)
																	echo '<option value="'.$keymap['id'].'"'.(($keymap['id'] == $user_keymap || $keymap['id'] == substr($user_keymap, 0, 2))?' selected="selected"':'').'>'.$keymap['name'].'</option>';
															?>
														</select>
													</td>
												</tr>
												<tr>
													<td style="text-align: right; vertical-align: middle;">
														<img src="media/image/icons/use_popup.png" alt="" title="" />
													</td>
													<td style="text-align: left; vertical-align: middle;">
														<strong><?php echo _('Use pop-up'); ?></strong>
													</td>
													<td style="text-align: right; vertical-align: middle;">
														<input class="input_radio" type="radio" id="use_popup_true" name="popup" value="1"<?php if ($wi_use_popup == 1) echo ' checked="checked"'; ?> /> <?php echo _('Yes'); ?>
														<input class="input_radio" type="radio" id="use_popup_false" name="popup" value="0"<?php if ($wi_use_popup == 0) echo ' checked="checked"'; ?> /> <?php echo _('No'); ?>
													</td>
												</tr>
<?php
	if ($debug_mode) {
?>
												<tr>
													<td style="text-align: right; vertical-align: middle;">
														<img src="media/image/icons/debug.png" alt="" title="" />
													</td>
													<td style="text-align: left; vertical-align: middle;">
														<strong><?php echo _('Debug'); ?></strong>
													</td>
													<td style="text-align: right; vertical-align: middle;">
														<input class="input_radio" type="radio" id="debug_true" name="debug" value="1"<?php if ($wi_debug == 1) echo ' checked="checked"'; ?> /> <?php echo _('Yes'); ?>
														<input class="input_radio" type="radio" id="debug_false" name="debug" value="0"<?php if ($wi_debug == 0) echo ' checked="checked"'; ?> /> <?php echo _('No'); ?>
													</td>
												</tr>
<?php
	}
?>
											</table>
										</div>
										<table style="width: 100%; margin-left: auto; margin-right: auto; margin-top: 25px; padding-bottom: 10px;" border="0" cellspacing="0" cellpadding="5">
											<tr style="height: 40px;">
												<td style="text-align: left; vertical-align: bottom;">
													<span id="advanced_settings_status" style="position: relative; left: 20px;"><img src="media/image/show.png" width="12" height="12" alt="" title="" /></span><input style="padding-left: 18px;" type="button" value="<?php echo _('Advanced settings'); ?>" onclick="switchSettings(); return false;" />
												</td>
												<td style="text-align: right; vertical-align: bottom;">
													<span id="submitButton"><input type="submit" id="submitLogin" value="<?php echo _('Connect'); ?>" /></span>
													<span id="submitLoader" style="display: none;"><img src="media/image/loader.gif" width="24" height="24" alt="" title="" /></span>
												</td>
											</tr>
										</table>
									</form>
								</div>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="spacer"></div>

			<div id="footerWrap">
			</div>
		</div>
	</body>
</html>
