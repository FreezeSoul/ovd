# -*- coding: utf-8 -*-

# Copyright (C) 2010 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2010
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

import commands
import pwd
import xrdp

from ovd.Logger import Logger
from ovd.Role.ApplicationServer.User import User as AbstractUser


class User(AbstractUser):
	def create(self):
		cmd = "useradd -m -k /dev/null"
		if self.infos.has_key("displayName"):
			cmd+= " --comment '%s,,,'"%(self.infos["displayName"])
		
		groups = ["video", "audio", "pulse", "pulse-rt", "pulse-access", "fuse"]
		if self.infos.has_key("groups"):
			groups+= self.infos["groups"]
		cmd+= " --groups %s"%(",".join(groups))
		
		cmd+= " "+self.name
		
		s,o = commands.getstatusoutput(cmd)
		if s != 0:
			Logger.error("userAdd return %d (%s)"%(s, o))
			return False
		
		
		if self.infos.has_key("password"):
			cmd = 'echo "%s:%s" | chpasswd'%(self.name, self.infos["password"])
			s,o = commands.getstatusoutput(cmd)
			if s != 0:
				Logger.error("chpasswd return %d (%s)"%(s, o))
				return False
		
		return self.post_create()
	

	def post_create(self):
		if self.infos.has_key("shell"):
			xrdp.UserSetShell(self.name, self.infos["shell"])
			xrdp.UserAllowUserShellOverride(self.name, True)
		return True
	
	
	def exists(self):
		try:
			pwd.getpwnam(self.name)
		except KeyError:
			return False
		
		return True
	
	
	def destroy(self):
		cmd = "userdel --force  --remove %s"%(self.name)
		
		s,o = commands.getstatusoutput(cmd)
		if s != 0:
			Logger.error("userdel return %d (%s)"%(s, o))
			return False
		
		return True
