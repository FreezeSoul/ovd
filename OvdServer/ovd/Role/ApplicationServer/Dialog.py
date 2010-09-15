# -*- coding: utf-8 -*-

# Copyright (C) 2008-2010 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2008
# Author Laurent CLOUET <laurent@ulteo.com> 2009-2010
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

import httplib
import urllib
import urllib2
import socket
from xml.dom import minidom
from xml.dom.minidom import Document

from ovd.Logger import Logger
from ovd import util
from ovd.Communication.Dialog import Dialog as AbstractDialog

from Platform import Platform

class Dialog(AbstractDialog):
	def __init__(self, role_instance):
		self.role_instance = role_instance
	
	@staticmethod
	def getName():
		return "aps"
	
	
	def process(self, request):
		path = request["path"]
		
		if request["method"] == "GET":
			Logger.debug("do_GET "+path)
			
			if path == "/applications":
				return self.req_applications(request)
			
			elif path.startswith("/application/icon/"):
				app_id = path[len("/application/icon/"):]
				return self.req_icon(app_id)
			
			elif path.startswith("/session/status/"):
				buf = path[len("/session/status/"):]
				return self.req_session_status(buf)
			
			elif path.startswith("/session/destroy/"):
				buf = path[len("/session/destroy/"):]
				return self.req_session_destroy(buf)
			
			elif path.startswith("/debian/") and self.role_instance.canManageApplications():
				buf = path[len("/debian/"):]
				return self.req_debian_id(buf)
			
			return None
		
		elif request["method"] == "POST":
			Logger.debug("do_POST "+path)
			if path == "/session/create":
				return self.req_session_create(request)
			
			elif path == "/user/loggedin":
				return self.req_user_loggedin(request)
			
			elif path == "/user/logout":
				return self.req_user_logout(request)
			
			elif  path == "/debian" and self.role_instance.canManageApplications():
				return self.req_debian(request)
			
			return None
		
		return None

	@staticmethod
	def session2xmlstatus(session):
		doc = Document()
		rootNode = doc.createElement('session')
		rootNode.setAttribute("id", session.id)
		rootNode.setAttribute("status", session.status)
		doc.appendChild(rootNode)
		
		return doc
	
	
	def req_applications(self, request):
		doc = self.role_instance.getApplications()
		if doc is None:
			return None
		
		return self.req_answer(doc)

	
	def req_icon(self, app_id):
		if self.role_instance.applications is None:
			return self.req_unauthorized()
		
		if not self.role_instance.applications.has_key(app_id):
			return self.req_unauthorized()
		
		app =  self.role_instance.applications[app_id]
		

		appsdetect = Platform.ApplicationsDetection()
		data = appsdetect.getIcon(app["filename"])
		if data is None:
			return self.req_not_found()
		
		response = {}
		response["code"] = httplib.OK
		response["Content-Type"] = "image/png"
		response["data"] = data
		return response
	
	def req_session_create(self, request):
		try:
			document = minidom.parseString(request["data"])
			sessionNode = document.documentElement
			
			if sessionNode.nodeName != "session":
				raise Exception("invalid root node")
			
			if not sessionNode.hasAttribute("id"):
				raise Exception("invalid root node")
			
			if not sessionNode.hasAttribute("mode"):
				raise Exception("invalid root node")
			
			session = {}
			session["id"] = sessionNode.getAttribute("id")
			session["mode"] = sessionNode.getAttribute("mode")
			
			external_apps_token = None
			if sessionNode.hasAttribute("external_apps_token"):
				external_apps_token = sessionNode.getAttribute("external_apps_token")
			
			if len(session["id"])==0:
				raise Exception("Missing attribute id")
			
			if session["mode"] == "desktop":
				session["mode"] = Platform.Session.MODE_DESKTOP
			elif session["mode"] == "applications":
				session["mode"] = Platform.Session.MODE_APPLICATIONS
			else:
				raise Exception("Missing attribute id")
			
			
			userNode = sessionNode.getElementsByTagName("user")[0]
			
			for attr in ["login", "password", "displayName"]:
				if not userNode.hasAttribute(attr):
					raise Exception("invalid child node: missing attribute "+attr)
				
				session[attr] = userNode.getAttribute(attr)
			
			session["applications"] = []
			applicationNodes = sessionNode.getElementsByTagName("application")
			for node in applicationNodes:
				app_id = node.getAttribute("id")
				app_target = node.getAttribute("desktopfile")
				
				session["applications"].append((app_id, app_target))
			
			session["parameters"] = {}
			for node in sessionNode.getElementsByTagName("parameter"):
				session["parameters"][node.getAttribute("name")] = node.getAttribute("value")
			
			
			nodes = sessionNode.getElementsByTagName("profile")
			if len(nodes)>0:
				profileNode = nodes[0]
				profileNode.getAttribute("server")
				profileNode.getAttribute("dir")
				profileNode.getAttribute("login")
				profileNode.getAttribute("password")
			else:
				profileNode = None
			
			sharedfolderNodes = sessionNode.getElementsByTagName("sharedfolder")
			for node in sharedfolderNodes:
				node.getAttribute("server")
				node.getAttribute("dir")
				node.getAttribute("login")
				node.getAttribute("password")
		
		except Exception, err:
			Logger.warn("Invalid xml input: "+str(err))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		user = Platform.User(session["login"], {"displayName": session["displayName"], "password": session["password"]})
		if session["parameters"].has_key("locale"):
			user.infos["locale"] = session["parameters"]["locale"]
		
		session = Platform.Session(session["id"], session["mode"], user, session["parameters"], session["applications"])
		if external_apps_token is not None:
			session.setExternalAppsToken(external_apps_token)
		
		session.init()
		
		if profileNode is not None:
			profile = Platform.Profile(profileNode.getAttribute("server"), profileNode.getAttribute("dir"), profileNode.getAttribute("login"), profileNode.getAttribute("password"), session)
		
		for sharedFolderNode in sharedfolderNodes:
			folder = {}
			for attribute in ["server", "dir", "login", "password"]:
				folder[attribute] = sharedFolderNode.getAttribute(attribute)
			profile.sharedFolders.append(folder)
		
		self.role_instance.sessions[session.id] = session
		self.role_instance.sessions_spooler.put(("create", session))
		
		return self.req_answer(self.session2xmlstatus(session))
	
	
	def req_session_status(self, session_id):
		if self.role_instance.sessions.has_key(session_id):
			session = self.role_instance.sessions[session_id]
		else:
			session = Platform.Session(session_id, None, None, None, None)
			session.status = "unknown"
		
		return self.req_answer(self.session2xmlstatus(session))
	
	
	def req_session_destroy(self, session_id):
		if self.role_instance.sessions.has_key(session_id):
			session = self.role_instance.sessions[session_id]
			if session.status not in [Platform.Session.SESSION_STATUS_WAIT_DESTROY, Platform.Session.SESSION_STATUS_DESTROYED]:
				#self.role_instance.session_switch_status(session, Platform.Session.SESSION_STATUS_WAIT_DESTROY)
				self.role_instance.sessions_spooler.put(("destroy", session))
		else:
			session = Platform.Session(session_id, None, None, None, None)
			session.status = Platform.Session.SESSION_STATUS_UNKNOWN
		
		return self.req_answer(self.session2xmlstatus(session))
	
	

	def req_user_loggedin(self, request):
		try:
			document = minidom.parseString(request["data"])
			rootNode = document.documentElement
			
			if rootNode.nodeName != "user":
				raise Exception("invalid root node")
			
			if not rootNode.hasAttribute("login"):
				raise Exception("invalid root node")
			
			login = rootNode.getAttribute("login")
			
		except:
			Logger.warn("Invalid xml input !!")
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		try:
			ret = Platform.TS.getSessionID(login)
		except Exception,err:
			Logger.error("RDP server dialog failed ... ")
			Logger.debug("Dialog::req_user_loggedin: %s"%(str(err)))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "internalerror")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		rootNode.setAttribute("loggedin", str((ret is not None)).lower())
		
		return self.req_answer(document)
	
	
	def req_user_logout(self, request):
		try:
			document = minidom.parseString(request["data"])
			rootNode = document.documentElement
			
			if rootNode.nodeName != "user":
				raise Exception("invalid root node")
			
			if not rootNode.hasAttribute("login"):
				raise Exception("invalid root node")
			
			login = rootNode.getAttribute("login")
			
		except:
			Logger.warn("Invalid xml input !!")
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		
		try:
			ret = Platform.TS.getSessionID(login)
		except Exception,err:
			Logger.error("RDP server dialog failed ... ")
			Logger.debug("Dialog::req_user_logout: %s"%(str(err)))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "internalerror")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		if ret is None:
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "unknown user")
			doc.appendChild(rootNode)
			
			return self.req_answer(doc)
		
		user = Platform.User(login, {"tsid": ret})
		self.role_instance.sessions_spooler.put(("logoff", user))
		
		return self.req_answer(document)
	
	def req_debian(self, request):
		try:
			document = minidom.parseString(request["data"])
			rootNode = document.documentElement
			if rootNode.nodeName != "debian":
				raise Exception("invalid root node")
			
			request = rootNode.getAttribute("request")
			if request not in ["upgrade", "install", "remove"]:
				raise Exception("usage")
			
			packageNodes = rootNode.getElementsByTagName("package")
			if request in ["install", "remove"] and len(packageNodes)==0:
				raise Exception("usage")
			
			packages = []
			for packageNode in packageNodes:
				packages.append(packageNode.getAttribute("name"))
		
		except Exception, err:
			Logger.warn("Invalid xml input: "+str(err))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		
		deb_req = self.role_instance.apt.createRequest()
		deb_req["order"] = request
		deb_req["packages"] = packages
		
		self.role_instance.apt.pushRequest(deb_req)
		
		return self.req_answer(self.debian_request2xml(deb_req["id"], "created"))
	
		
	def req_debian_id(self, req):
		try:
			(rid, request) = req.split("/", 2)
			
			if request == "status":
				status = self.role_instance.apt.getRequestStatus(rid)
				return self.req_answer(self.debian_request2xml(rid, status))
			
			elif request in ["stdout", "stderr"]:
				response = {}
				response["code"] = httplib.OK
				response["Content-Type"] = "image/png"
				response["data"] = self.role_instance.apt.getRequestLog(rid, request)
				return response
			
			else:
				raise Exception("usage")
			
		except Exception, err:
			Logger.warn("Invalid xml input: "+str(err))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)


	@staticmethod
	def debian_request2xml(rid, status):
		doc = Document()
		rootNode = doc.createElement('debian_request')
		rootNode.setAttribute("id", rid)
		rootNode.setAttribute("status", status)
		doc.appendChild(rootNode)
		
		return doc
