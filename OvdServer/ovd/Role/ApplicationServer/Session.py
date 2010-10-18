# -*- coding: UTF-8 -*-

# Copyright (C) 2009-2010 Ulteo SAS
# http://www.ulteo.com
# Author Laurent CLOUET <laurent@ulteo.com> 2010
# Author Julien LANGLOIS <julien@ulteo.com> 2009-2010
#
# This program is free software; you can redistribute it and/or 
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; version 2
# of the License
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

import glob
import locale
import os
import shutil
import time

from ovd.Config import Config
from ovd.Logger import Logger
from ovd.Platform import Platform

import Platform as RolePlatform

class Session:
	SESSION_STATUS_UNKNOWN = "unknown"
	SESSION_STATUS_ERROR = "error"
	SESSION_STATUS_INIT = "init"
	SESSION_STATUS_INITED = "ready"
	SESSION_STATUS_ACTIVE = "logged"
	SESSION_STATUS_INACTIVE = "disconnected"
	SESSION_STATUS_WAIT_DESTROY = "wait_destroy"
	SESSION_STATUS_DESTROYED = "destroyed"
	
	SESSION_END_STATUS_NORMAL = "exit"
	SESSION_END_STATUS_SHUTDOWN = "shutdown"
	SESSION_END_STATUS_ERROR = "internal"
	
	MODE_DESKTOP = "desktop"
	MODE_APPLICATIONS = "applications"
	
	def __init__(self, id_, mode_, user_, parameters_, applications_):
		self.id = id_
		self.user = user_
		self.mode = mode_
		self.parameters = parameters_
		self.profile = None
		self.applications = applications_
		self.instanceDirectory = None
		self.used_applications = {}
		self.external_apps_token = None
		self.end_status = None
		
		self.log = []
		self.switch_status(Session.SESSION_STATUS_INIT)
	
	def init(self):
		raise NotImplementedError()
	
	def init_user_session_dir(self, user_session_dir):
		self.user_session_dir = user_session_dir
		if os.path.isdir(self.user_session_dir):
			Platform.System.DeleteDirectory(self.user_session_dir)
		
		os.makedirs(self.user_session_dir)  
		
		self.instanceDirectory = os.path.join(self.user_session_dir, "instances")
		self.matchingDirectory = os.path.join(self.user_session_dir, "matching")
		self.shortcutDirectory = os.path.join(self.user_session_dir, "shortcuts")
		
		os.mkdir(self.instanceDirectory)
		os.mkdir(self.matchingDirectory)
		os.mkdir(self.shortcutDirectory)

		for application in self.applications:
			cmd = RolePlatform.Platform.ApplicationsDetection.getExec(application["file"])
			if cmd is None:
				Logger.error("Session::install_client unable to extract command from app_id %s (%s)"%(application["id"], application["file"]))
				continue
			
			f = file(os.path.join(self.matchingDirectory, application["id"]), "w")
			f.write(cmd)
			f.close()
		
		
		for application in self.applications:
			final_file = os.path.join(self.shortcutDirectory, self.get_target_file(application))
			Logger.debug("install_client %s %s %s"%(str(application["file"]), str(final_file), str(application["id"])))
			
			ret = self.clone_shortcut(application["file"], final_file, "startovdapp", [application["id"]])
			if not ret:
				Logger.warn("Unable to clone shortcut '%s' to '%s'"%(application["file"], final_file))
				continue
			
			self.install_shortcut(final_file)
		
		if self.external_apps_token is not None:
			f = open(os.path.join(self.user_session_dir, "sm"), "w")
			f.write(Config.session_manager+"\n")
			f.close()
			
			f = open(os.path.join(self.user_session_dir, "token"), "w")
			f.write(self.external_apps_token+"\n")
			f.close()
	
	def setExternalAppsToken(self, external_apps_token):
		self.external_apps_token = external_apps_token
	
	def install_client(self):
		pass
	
	def uninstall_client(self):
		pass
	
	def clone_shortcut(self, src, dst, command, args):
		pass
	
	def install_shortcut(self, shortcut):
		pass
	
	def get_target_file(self, application):
		pass
	
	def switch_status(self, status_):
		self.log.append((time.time(), status_))
		self.status = status_
	
	
	def getUsedApplication(self):
		if self.status in [Session.SESSION_STATUS_ACTIVE, Session.SESSION_STATUS_INACTIVE] and self.instanceDirectory is not None:
			(_, encoding) = locale.getdefaultlocale()
			if encoding is None:
				encoding = "UTF8"
			
			applications = {}
			for path in glob.glob(os.path.join(self.instanceDirectory, "*")):
				basename = os.path.basename(path)
				
				if type(basename) is unicode:
					name = basename
				else:
					name = unicode(basename, encoding)
				
				if not os.path.isfile(path):
					continue
				
				f = file(path, "r")
				data = f.read().strip()
				f.close()
				
				applications[name] = unicode(data, encoding)

			self.used_applications = applications
		return self.used_applications
	
	
	def archive_shell_dump(self):
		path = os.path.join(self.user.get_home(), "ovd-dump.txt")
		if not os.path.isfile(path):
			return
		
		spool = os.path.join(Config.spool_dir, "sessions dump archive")
		if not os.path.exists(spool):
			os.makedirs(spool)
		
		dst = os.path.join(spool, "%s %s.txt"%(self.id, self.user.name))
		shutil.copyfile(path, dst)
		
		try:
			os.remove(path)
		except:
			pass
