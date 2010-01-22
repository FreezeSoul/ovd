# -*- coding: UTF-8 -*-

# Copyright (C) 2009 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2009
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


from ovd.Logger import Logger

SESSION_STATUS_INIT = "init"

class Session:
	def __init__(self, id_, user_, parameters_, applications_):
		self.id = id_
		self.user = user_
		self.parameters = parameters_
		self.applications = applications_
		
		self.status = SESSION_STATUS_INIT
	
	def install_client(self):
		pass
	
	def uninstall_client(self):
		pass
