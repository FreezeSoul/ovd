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

import os
import pwd
import shutil

from ovd.Config import Config
from ovd.Logger import Logger
from ovd.Role.ApplicationServer.Session import Session as AbstractSession

from ovd.Platform import Platform

class Session(AbstractSession):
	
	SPOOL_USER = "/var/spool/ulteo/ovd/"
	
	def init(self):
		pass
	
	
	def install_client(self):
		d = os.path.join(self.SPOOL_USER, self.user.name)
		self.init_user_session_dir(d)
		
		os.chown(self.instanceDirectory, pwd.getpwnam(self.user.name)[2], -1)
		
		xdg_dir = os.path.join(d, "xdg")
		xdg_app_d = os.path.join(xdg_dir, "applications")
		if not os.path.isdir(xdg_app_d):
			os.makedirs(xdg_app_d)
		
		for p in ["icons", "pixmaps", "mime", "themes"]:
			src_dir = os.path.join("/usr/share/", p)
			dst_dir =  os.path.join(xdg_dir, p)
			
			os.symlink(src_dir, dst_dir)
		
		
		os.system('update-desktop-database "%s"'%(xdg_app_d))
	
		if self.parameters.has_key("desktop_icons"):
			path = os.path.join(xdg_app_d, ".show_on_desktop")
			f = file(path, "w")
			f.close()
		
		
		env_file_lines = []
		# Set the language
		if self.parameters.has_key("locale"):
			env_file_lines.append("LANG=%s.UTF-8\n"%(self.parameters["locale"]))
			env_file_lines.append("LC_ALL=%s.UTF-8\n"%(self.parameters["locale"]))
			env_file_lines.append("LANGUAGE=%s.UTF-8\n"%(self.parameters["locale"]))
		
		if self.parameters.has_key("timezone"):
			tz_file = "/usr/share/zoneinfo/" + self.parameters["timezone"]
			if not os.path.exists(tz_file):
				Logger.warn("Unsupported timezone '%s'"%(self.parameters["timezone"]))
				Logger.debug("Unsupported timezone '%s'. File '%s' does not exists"%(self.parameters["timezone"], tz_file))
			else:
				env_file_lines.append("TZ=%s\n"%(tz_file))
		
		f = file(os.path.join(d, "env"), "w")
		f.writelines(env_file_lines)
		f.close()
		
		if self.profile is not None:
			self.profile.mount()
		
		return True
	
	
	def uninstall_client(self):
		if self.profile is not None:
			self.profile.umount()
		
		d = os.path.join(self.SPOOL_USER, self.user.name)
		xdg_dir = os.path.join(d, "xdg")
		
		for p in ["icons", "pixmaps", "mime", "themes"]:
			dst_dir =  os.path.join(xdg_dir, p)
			if os.path.islink(dst_dir):
				os.remove(dst_dir)
		
		if os.path.exists(d):
			shutil.rmtree(d)
	
	def get_target_file(self, app_id, app_target):
		  return "%s.desktop"%(str(app_id))
	
	
	
	def clone_shortcut(self, src, dst, command, args):
		try:
			f = file(src, "r")
		except:
			return False
		
		lines = f.readlines()
		f.close()
		
		for i in xrange(len(lines)):
			if lines[i].startswith("Exec="):
				lines[i] = "Exec=%s %s\n"%(command, " ".join(args))  
		
		try:
			f = file(dst, "w")
		except:
			return False
		
		f.writelines(lines)
		f.close()
		return True
	
	
	def install_shortcut(self, shortcut):
		xdg_app_d = os.path.join(self.user_session_dir, "xdg", "applications")
		if not os.path.isdir(xdg_app_d):
			os.makedirs(xdg_app_d)
		
		dstFile = os.path.join(xdg_app_d, os.path.basename(shortcut))
		if os.path.exists(dstFile):
			os.remove(dstFile)
		
		shutil.copyfile(shortcut, dstFile)

