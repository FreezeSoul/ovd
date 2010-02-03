# -*- coding: utf-8 -*-

# Copyright (C) 2009,2010 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2009, 2010
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
import signal
import sys
import time

from ovd_shells.InstancesManager import InstancesManager as AbstractInstancesManager

class InstancesManager(AbstractInstancesManager):
	def launch(self, cmd):
		pid = os.fork()
		if pid > 0:
			return pid
		
		# Child process
		os.execl("/bin/sh", "sh", "-c" , cmd)
		sys.exit(1)
	
	
	def wait(self):
		haveWorked = False
		for (pid, instance) in self.instances:
			os.waitpid(pid, os.P_NOWAIT)
			
			path = os.path.join("/proc", str(pid))
			if os.path.isdir(path):
				continue
			
			self.onInstanceExited((pid, instance))
			haveWorked = True
		
		return haveWorked
	
	
	def kill(self, pid):
		os.kill(signal.SIGTERM)

