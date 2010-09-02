# -*- coding: UTF-8 -*-

# Copyright (C) 2009-2010 Ulteo SAS
# http://www.ulteo.com
# Author Laurent CLOUET <laurent@ulteo.com> 2009-2010
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

import Queue
from threading import Thread

from ovd.Logger import Logger
from ovd.Platform import Platform

from Platform import Platform as RolePlatform


class SessionManagement(Thread):
	def __init__(self, aps_instance, queue):
		Thread.__init__(self)
		
		self.aps_instance = aps_instance
		self.queue = queue
	
	def run(self):
		while True:
			#Logger.debug("%s wait job"%(str(self)))
			try:
				(request, obj) = self.queue.get(True, 4)
			except Queue.Empty, e:
				continue
			
			if request == "create":
				session = obj
				self.create_session(session)
			elif request == "destroy":
				session = obj
				self.destroy_session(session)
			elif request == "logoff":
				user = obj
				self.destroy_user(user)
	
	
	def create_session(self, session):
		Logger.info("SessionManagement::create %s for user %s"%(session.id, session.user.name))
		
		if Platform.System.userExist(session.user.name):
			Logger.error("unable to create session: user %s already exists"%(session.user.name))
			self.aps_instance.session_switch_status(session, RolePlatform.Session.SESSION_STATUS_ERROR)
			return self.destroy_session(session)
		
		
		session.user.infos["groups"] = [self.aps_instance.ts_group_name, self.aps_instance.ovd_group_name]
		
		if session.mode == "desktop":
			session.user.infos["shell"] = "OvdDesktop"
		else:
			session.user.infos["shell"] = "OvdRemoteApps"
		
		rr = session.user.create()
		if rr is False:
			Logger.error("unable to create session for user %s"%(session.user.name))
			self.aps_instance.session_switch_status(session, RolePlatform.Session.SESSION_STATUS_ERROR)
			return self.destroy_session(session)
		
		try:
			rr = session.install_client()
		except Exception,err:
			Logger.debug("Unable to initialize session %s: %s"%(session.id, str(err)))
			rr = False
		
		if rr is False:
			Logger.error("unable to initialize session %s"%(session.id))
			self.aps_instance.session_switch_status(session, RolePlatform.Session.SESSION_STATUS_ERROR)
			return self.destroy_session(session)
		
		
		self.aps_instance.session_switch_status(session, RolePlatform.Session.SESSION_STATUS_INITED)
	
	
	def destroy_session(self, session):
		Logger.info("SessionManagement::destroy %s"%(session.id))
		
		try:
			sessid = RolePlatform.TS.getSessionID(session.user.name)
		except Exception,err:
			Logger.error("RDP server dialog failed ... ")
			Logger.debug("SessionManagement::destroy_session: %s"%(str(err)))
			return
		
		if sessid is not None:
			session.user.infos["tsid"] = sessid
		
		self.logoff_user(session.user)
		
		session.uninstall_client()
		
		self.destroy_user(session.user)
		
		if self.aps_instance.sessions.has_key(session.id):
			del(self.aps_instance.sessions[session.id])
		self.aps_instance.session_switch_status(session, RolePlatform.Session.SESSION_STATUS_DESTROYED)

	def logoff_user(self, user):
		Logger.info("SessionManagement::logoff_user %s"%(user.name))

		if user.infos.has_key("tsid"):
			sessid = user.infos["tsid"]

			try:
				status = RolePlatform.TS.getState(sessid)
			except Exception,err:
				Logger.error("RDP server dialog failed ... ")
				Logger.debug("SessionManagement::logoff_user: %s"%(str(err)))
				return

			if status in [RolePlatform.TS.STATUS_LOGGED, RolePlatform.TS.STATUS_DISCONNECTED]:
				Logger.info("must log off ts session %s user %s"%(sessid, user.name))

				try:
					RolePlatform.TS.logoff(sessid)
				except Exception,err:
					Logger.error("RDP server dialog failed ... ")
					Logger.debug("SessionManagement::logoff_user: %s"%(str(err)))
					return
	
	
	def destroy_user(self, user):
		Logger.info("SessionManagement::destroy_user %s"%(user.name))
		
		if user.infos.has_key("tsid"):
			sessid = user.infos["tsid"]
			
			try:
				status = RolePlatform.TS.getState(sessid)
			except Exception,err:
				Logger.error("RDP server dialog failed ... ")
				Logger.debug("SessionManagement::destroy_user: %s"%(str(err)))
				return
			
			if status in [RolePlatform.TS.STATUS_LOGGED, RolePlatform.TS.STATUS_DISCONNECTED]:
				Logger.info("must log off ts session %s user %s"%(sessid, user.name))
				
				try:
					RolePlatform.TS.logoff(sessid)
				except Exception,err:
					Logger.error("RDP server dialog failed ... ")
					Logger.debug("SessionManagement::destroy_user: %s"%(str(err)))
					return
				
		
		user.destroy()
