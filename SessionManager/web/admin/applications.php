<?php
/**
 * Copyright (C) 2008 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com>
 * Author Julien LANGLOIS <julien@ulteo.com>
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
require_once(dirname(__FILE__).'/includes/page_template.php');

$prefs = Preferences::getInstance();
if (! $prefs)
	die_error('get Preferences failed',__FILE__,__LINE__);

$mods_enable = $prefs->get('general','module_enable');
if (!in_array('ApplicationDB',$mods_enable)){
	die_error(_('Module ApplicationDB must be enabled'),__FILE__,__LINE__);
}
$mod_app_name = 'admin_ApplicationDB_'.$prefs->get('ApplicationDB','enable');
$applicationDB = new $mod_app_name();

if ($applicationDB->isWriteable()) {
if (isset($_GET['mass_action']) && $_GET['mass_action'] == 'block') {
	if (isset($_GET['manage_applications']) && is_array($_GET['manage_applications'])) {
		foreach ($_GET['manage_applications'] as $application) {
			$app = $applicationDB->import($application);
			if (is_object($app)) {
				if (isset($_GET['block']))
					$app->setAttribute('published', 0);
				else
					$app->setAttribute('published', 1);
				$buf = $applicationDB->update($app);
			}
		}
	}

	redirect($_SERVER['HTTP_REFERER']);
}
}

if (isset($_REQUEST['action'])) {
  if ($_REQUEST['action']=='manage') {
    if (isset($_REQUEST['id']))
      show_manage($_REQUEST['id'], $applicationDB);
  }

  if ($_REQUEST['action']=='modify' && $applicationDB->isWriteable()) {
    if (isset($_REQUEST['id'])) {
      modify_user($applicationDB, $_REQUEST['id']);
      show_manage($_REQUEST['id'], $applicationDB);
    }
  }
}

if (! isset($_GET['view']))
  $_GET['view'] = 'all';

if ($_GET['view'] == 'all')
  show_default($applicationDB);

function action_modify($applicationDB, $id) {
  $app = $applicationDB->import($id);
  if (!is_object($app))
    return false;
//     die_error('Unable to import application "'.$id.'"',__FILE__,__LINE__);

  if (isset($_REQUEST['published']))
      return false;

  $app->setAttribute('published', $_REQUEST['published']);

  $res = $applicationDB->update($app);
  if (! $res)
    die_error('Unable to modify application '.$res,__FILE__,__LINE__);

  return true;
}

function show_default($applicationDB) {
  $applications = $applicationDB->getList(true);
  $is_empty = (is_null($applications) or count($applications)==0);

  $is_rw = $applicationDB->isWriteable();

  page_header();

  echo '<div>'; // general div
  echo '<h1>'._('Applications').'</h1>';
  echo '<div id="apps_list_div">';

  if ($is_empty)
    echo _('No available application').'<br />';
  else {
    echo '<div id="apps_list">';
//     echo '<form action="applications.php" method="get">';
//     echo '	<input type="hidden" name="mass_action" value="block" />';
    echo '<table class="main_sub sortable" id="applications_list_table" border="0" cellspacing="1" cellpadding="5">';
    echo '<thead>';
    echo '<tr class="title">';
//     if ($is_rw)
//       echo '<th class="unsortable"></th>';
    echo '<th>'._('Name').'</th>';
    echo '<th>'._('Description').'</th>';
    echo '<th>'._('Type').'</th>';
    //echo '<th>'._('Status').'</th>';
    echo '</tr>';
    echo '</thead>';
    $count = 0;
    foreach($applications as $app) {
      $content = 'content'.(($count++%2==0)?1:2);

      if ($app->getAttribute('published')) {
// 	$status = '<span class="msg_ok">'._('Available').'</span>';
// 	$status_change = _('Block');
	$status_change_value = 0;
      } else {
// 	$status = '<span class="msg_error">'._('Blocked').'</span>';
// 	$status_change = _('Unblock');
	$status_change_value = 1;
      }

      echo '<tr class="'.$content.'">';
      if ($is_rw)
// 	echo '<td><input class="input_checkbox" type="checkbox" name="manage_applications[]" value="'.$app->getAttribute('id').'" /></td><form></form>';
      echo '<td><img src="media/image/cache.php?id='.$app->getAttribute('id').'" alt="" title="" /> <a href="?action=manage&id='.$app->getAttribute('id').'">'.$app->getAttribute('name').'</a></td>';
      echo '<td>'.$app->getAttribute('description').'</td>';
      echo '<td style="text-align: center;"><img src="media/image/server-'.$app->getAttribute('type').'.png" alt="'.$app->getAttribute('type').'" title="'.$app->getAttribute('type').'" /><br />'.$app->getAttribute('type').'</td>';
//       echo '<td>'.$status.'</td>';

      echo '<td><form action="">';
      echo '<input type="hidden" name="action" value="manage" />';
      echo '<input type="hidden" name="id" value="'.$app->getAttribute('id').'" />';
      echo '<input type="submit" value="'._('Manage').'"/>';
      echo '</form></td>';

      /*if ($is_rw) {
	echo '<td><form action="" method="post">';
	echo '<input type="hidden" name="action" value="modify" />';
	echo '<input type="hidden" name="id" value="'.$app->getAttribute('id').'" />';
	echo '<input type="hidden" name="published" value="'.$status_change_value.'" />';
	echo '<input type="submit" value="'.$status_change.'"/>';
	echo '</form></td>';
      }*/
      echo '</tr>';
    }

    if ($is_rw) {
//       echo '<tfoot>';
      $content = 'content'.(($count++%2==0)?1:2);

//       echo '<tr class="'.$content.'">';
//       echo '<td colspan="6">';
//       echo '<a href="javascript:;" onclick="markAllRows(\'applications_list_table\'); return false">'._('Mark all').'</a>';
//       echo ' / <a href="javascript:;" onclick="unMarkAllRows(\'applications_list_table\'); return false">'._('Unmark all').'</a>';
//       echo '</td>';
//       echo '<td>';

      /*echo '<input type="submit" name="unblock" value="'._('Unblock').'" />';
      echo '<br />';
      echo '<input type="submit" name="block" value="'._('Block').'" />';*/
//       echo '</td>';
//       echo '</tr>';
//       echo '</tfoot>';
    }

    echo '</table>';
//     echo '</form>';
    echo '</div>';
  echo '</div>';
  }
  echo '</div>'; // apps_list_div
  echo '</div>'; // general div
  page_footer();
  die();
}

function show_manage($id, $applicationDB) {
  $app = $applicationDB->import($id);
  if (!is_object($app))
    return false;
//     die_error('Unable to import application "'.$id.'"',__FILE__,__LINE__);

  $is_rw = $applicationDB->isWriteable();

  if ($app->getAttribute('published')) {
    $status = '<span class="msg_ok">'._('Available').'</span>';
    $status_change = _('Block');
    $status_change_value = 0;
  } else {
    $status = '<span class="msg_error">'._('Blocked').'</span>';
    $status_change = _('Unblock');
    $status_change_value = 1;
  }

    // Tasks
  $tm = new Tasks_Manager();
  $tm->load_from_application($id);
  $tm->refresh_all();

  $servers_in_install = array();
  $servers_in_remove = array();
  $tasks = array();
  foreach($tm->tasks as $task) {
	  if ($task->succeed())
		  continue;
	  if ($task->failed())
		  continue;

	  $tasks[]= $task;
	  if (get_class($task) == 'Task_install') {
		  if (! in_array($task->server, $servers_in_install))
			  $servers_in_install[]= $task->server;
	  }
	  if (get_class($task) == 'Task_remove') {
		  if (! in_array($task->server, $servers_in_remove))
			  $servers_in_remove[]= $task->server;
	  }
  }

  // Servers
  if ( $app->getAttribute('static'))
    $servers_all = array();
  else
    $servers_all = Servers::getAll();
  $liaisons = Abstract_Liaison::load('ApplicationServer', $app->getAttribute('id'), NULL);
  $servers_id = array();
  foreach ($liaisons as $liaison)
    $servers_id []= $liaison->group;
  $servers = array();
  $servers_available = array();
  foreach($servers_all as $server) {
    if (in_array($server->fqdn, $servers_id))
      $servers[]= $server;
    elseif(in_array($server->fqdn, $servers_in_install))
      continue;
    elseif (! $server->isOnline())
      continue;
    elseif ( $server->type != $app->getAttribute('type'))
      continue;
    else
      $servers_available[]= $server;
  }

  // App groups
  $appgroups = getAllAppsGroups();
  $groups_id = array();
  $liaisons = Abstract_Liaison::load('AppsGroup', $app->getAttribute('id'), NULL);
  foreach ($liaisons as $liaison)
    $groups_id []= $liaison->group;
  $groups = array();
  $groups_available = array();
  foreach ($appgroups as $group) {
    if (in_array($group->id, $groups_id))
      $groups[]= $group;
    else
      $groups_available[]= $group;
  }


  page_header();

  echo '<div>';
  echo '<h1><img src="media/image/cache.php?id='.$app->getAttribute('id').'" alt="" title="" /> '.$app->getAttribute('name').'</h1>';

  echo '<table class="main_sub" border="0" cellspacing="1" cellpadding="3">';
  echo '<tr class="title">';
  echo '<th>'._('Package').'</th>';
  echo '<th>'._('Type').'</th>';
//   echo '<th>'._('Status').'</th>';
  echo '<th>'._('Description').'</th>';
  echo '<th>'._('Executable').'</th>';
  echo '</tr>';

  echo '<tr class="content1">';
  echo '<td>'.$app->getAttribute('package').'</td>';
  echo '<td style="text-align: center;"><img src="media/image/server-'.$app->getAttribute('type').'.png" alt="'.$app->getAttribute('type').'" title="'.$app->getAttribute('type').'" /><br />'.$app->getAttribute('type').'</td>';
//   echo '<td>'.$status.'</td>';
  echo '<td>'.$app->getAttribute('description').'</td>';
  echo '<td>'.$app->getAttribute('executable_path').'</td>';
  echo '</tr>';
  echo '</table>';

//   if ($is_rw) {
//     echo '<h2>'._('Settings').'</h2>';
//
//     echo '<form action="" method="post">';
//     echo '<input type="hidden" name="action" value="modify" />';
//     echo '<input type="hidden" name="id" value="'.$app->getAttribute('id').'" />';
//     echo '<input type="hidden" name="published" value="'.$status_change_value.'" />';
//     echo '<input type="submit" value="'.$status_change.'"/>';
//     echo '</form>';
//   }

  // Server part
  if (count($servers_all) > 0) {
    echo '<div>';
    echo '<h2>'._('Servers with this application').'</h2>';
    echo '<table border="0" cellspacing="1" cellpadding="3">';
    foreach($servers as $server) {
      $remove_in_progress = in_array($server->fqdn, $servers_in_remove);

      echo '<tr><td>';
      echo '<a href="servers.php?action=manage&fqdn='.$server->fqdn.'">'.$server->fqdn.'</a>';
      echo '</td>';
      echo '<td>';
      if ($remove_in_progress) {
	echo 'remove in progress';
      }
      elseif ($server->isOnline()) {
	echo '<form action="actions.php" method="post" onsubmit="return confirm(\''._('Are you sure you want remove this application from this server?').'\');">';
	echo '<input type="hidden" name="action" value="del" />';
	echo '<input type="hidden" name="name" value="Application_Server" />';
	echo '<input type="hidden" name="application" value="'.$id.'" />';
	echo '<input type="hidden" name="server" value="'.$server->fqdn.'" />';
	echo '<input type="submit" value="'._('Remove from this server').'"/>';
	echo '</form>';
      }
      echo '</td>';
      echo '</tr>';
    }

    foreach($servers_in_install as $server) {
      echo '<tr><td>';
      echo '<a href="servers.php?action=manage&fqdn='.$server.'">'.$server.'</a>';
      echo '</td>';
      echo '<td>install in progress</td>';
      echo '</tr>';
    }

    if (count($servers_available) > 0) {
      echo '<tr>';
      echo '<form action="actions.php" method="post"><td>';
      echo '<input type="hidden" name="name" value="Application_Server" />';
      echo '<input type="hidden" name="action" value="add" />';
      echo '<input type="hidden" name="application" value="'.$id.'" />';
      echo '<select name="server">';
      foreach ($servers_available as $server)
        echo '<option value="'.$server->fqdn.'">'.$server->fqdn.'</option>';
      echo '</select>';
      echo '</td><td><input type="submit" value="'._('Install on this server').'" /></td>';
      echo '</form>';
      echo '</tr>';
    }
    echo '</table>';
    echo "<div>\n";
  }

  if (count($tasks) >0) {
    echo '<h2>'._('Active tasks on this application').'</h1>';
    echo '<table border="0" cellspacing="1" cellpadding="3">';
    echo '<tr class="title">';
    echo '<th>'._('ID').'</th>';
    echo '<th>'._('Type').'</th>';
    echo '<th>'._('Status').'</th>';
    echo '<th>'._('Details').'</th>';
    echo '</tr>';

    $count = 0;
    foreach($tasks as $task) {
      $content = 'content'.(($count++%2==0)?1:2);
      if ($task->failed())
	$status = '<span class="msg_error">'._('Error').'</span>';
      else
	$status = '<span class="msg_ok">'.$task->status.'</span>';

      echo '<tr class="'.$content.'">';
      echo '<td>'.$task->id.'</td>';
      echo '<td>'.get_class($task).'</td>';
      echo '<td>'.$status.'</td>';
      echo '<td>'.$task->server.', '.$task->getRequest().', '.$task->status_code.'</td>';
      echo '</tr>';
    }
    echo '</table>';
    echo "<div>\n";
  }


  if (count($appgroups) > 0) {
    echo '<div>';
    echo '<h2>'._('Groups with this application').'</h2>';
    echo '<table border="0" cellspacing="1" cellpadding="3">';
    foreach ($groups as $group) {
      echo '<tr>';
      echo '<td>';
      echo '<a href="appsgroup.php?action=manage&id='.$group->id.'">'.$group->name.'</a>';
      echo '</td>';
      echo '<td><form action="actions.php" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this application from this group?').'\');">';
      echo '<input type="hidden" name="name" value="Application_ApplicationGroup" />';
      echo '<input type="hidden" name="action" value="del" />';
      echo '<input type="hidden" name="element" value="'.$id.'" />';
      echo '<input type="hidden" name="group" value="'.$group->id.'" />';
      echo '<input type="submit" value="'._('Delete from this group').'" />';
      echo '</form></td>';
      echo '</tr>';
    }

    if (count($groups_available) > 0) {
      echo '<tr>';
      echo '<form action="actions.php" method="post"><td>';
      echo '<input type="hidden" name="name" value="Application_ApplicationGroup" />';
      echo '<input type="hidden" name="action" value="add" />';
      echo '<input type="hidden" name="element" value="'.$id.'" />';
      echo '<select name="group">';
      foreach ($groups_available as $group)
	echo '<option value="'.$group->id.'">'.$group->name.'</option>';
      echo '</select>';
      echo '</td><td><input type="submit" value="'._('Add to this group').'" /></td>';
      echo '</form>';
      echo '</tr>';
    }

    echo '</table>';
    echo "<div>\n";
  }

  echo '</div>';
  echo '</div>';
  echo '</div>';
  echo '</div>';
  echo '</div>';
  echo '</div>';
  page_footer();
  die();
}
