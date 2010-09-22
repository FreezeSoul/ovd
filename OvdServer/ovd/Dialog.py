# -*- coding: utf-8 -*-

# Copyright (C) 2008-2010 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2008
# Author Laurent CLOUET <laurent@ulteo.com> 2009,2010
# Author Jeremy DESVAGES <jeremy@ulteo.com> 2010
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
import urllib2
import cgi
import base64
import time
from xml.dom import minidom
from xml.dom.minidom import Document

from ovd.Communication.Dialog import Dialog as AbstractDialog
from ovd.Config import Config
from ovd.FileTailer import FileTailer
from ovd.Logger import Logger
from ovd.Platform import Platform


class Dialog(AbstractDialog):
	def __init__(self, server_instance):
		self.server = server_instance
		self.url = "http://%s:%d"%(Config.session_manager, Config.SM_SERVER_PORT)
		self.name = None
	
	@staticmethod
	def getName():
		return "server"
	
	def initialize(self):
		node = self.send_server_name()
		if node is None:
			raise Exception("invalid response")
		
		if not node.hasAttribute("name"):
			raise Exception("invalid response")
		
		self.name = node.getAttribute("name")
		return True
	
	
	def process(self, request):
		path = request["path"]
		
		if request["method"] == "GET":
			Logger.debug("do_GET "+path)
			
			if path == "/configuration":
				return self.req_server_conf(request)
			
			elif path == "/monitoring":
				return self.req_server_monitoring(request)
			
			elif path == "/status":
				return self.req_server_status(request)

			elif path.startswith("/logs"):
				since = 0
				extra = path[len("/logs"):]
				if extra.startswith("/since/"):
					since_str = extra[len("/since/"):]
					if since_str.isdigit():
						since = int(since_str)
				elif len(extra) > 0:
					return None  
				
				return self.req_server_logs(request, since)
			
			return None
		
		elif request["method"] == "POST":
			return None
		
		return None
	
	def stop(self):
		if self.name is not None:
			self.send_server_status("down")
	
	@staticmethod
	def get_response_xml(stream):
		if not stream.headers.has_key("Content-Type"):
			return None
		
		contentType = stream.headers["Content-Type"].split(";")[0]
		if not contentType == "text/xml":
			Logger.error("content type: %s"%(contentType))
			print stream.read()
			return None
		
		try:
			document = minidom.parseString(stream.read())
		except:
			Logger.warn("No response XML")
			return None
		
		return document
	
	
	def send_server_name(self):
		url = "%s/server/name"%(self.url)
		Logger.debug('SessionManagerRequest::server_name url '+url)
		
		req = urllib2.Request(url)
		try:
			f = urllib2.urlopen(req)
		except IOError, e:
			Logger.debug("SessionManagerRequest::server_status error"+str(e))
			return None
		
		
		document = self.get_response_xml(f)
		if document is None:
			Logger.warn("Dialog::send_server_name not XML response")
			return None
		
		rootNode = document.documentElement
		
		if rootNode.nodeName != "server":
			return None
		
		return rootNode
	
	
	def get(self, path):
		url = "%s%s"%(self.url, path)
		Logger.debug("Dialog::send_packet url %s"%(url))
		
		req = urllib2.Request(url)
		
		try:
			stream = urllib2.urlopen(req)
		except IOError, e:
			Logger.debug("Dialog::send_packet error"+str(e))
			return None
		
		return stream
	
	
	def send_packet(self, path, document):
		rootNode = document.documentElement
		rootNode.setAttribute("name", self.name)
				
		url = "%s%s"%(self.url, path)
		Logger.debug("Dialog::send_packet url %s"%(url))
		
		req = urllib2.Request(url)
		req.add_header("Content-type", "text/xml; charset=UTF-8")
		req.add_data(document.toxml())
		
		try:
			stream = urllib2.urlopen(req)
		except IOError, e:
			Logger.debug("Dialog::send_packet error"+str(e))
			return False
		
		return stream
	
	
	def send_server_status(self, status="ready"):
		doc = Document()
		rootNode = doc.createElement('server')
		rootNode.setAttribute("status", status)
		doc.appendChild(rootNode)
		
		response = self.send_packet("/server/status", doc)
	
		document = self.get_response_xml(response)
		if document is None:
			Logger.warn("Dialog::send_server_status response not XML")
			return False
		
		rootNode = document.documentElement
		
		if rootNode.nodeName != "server":
			Logger.error("Dialog::send_server_status response not valid %s"%(rootNode.toxml()))
			return False
		
		if not rootNode.hasAttribute("name") or rootNode.getAttribute("name") != self.name:
			Logger.error("Dialog::send_server_status response invalid name")
			return False
		
		if not rootNode.hasAttribute("status") or rootNode.getAttribute("status") != status:
			Logger.error("Dialog::send_server_status response invalid status")
			return False
		
		return True
	
	
	def send_server_monitoring(self, doc):
		response = self.send_packet("/server/monitoring", doc)
		if response is False:
			return False
		
		document = self.get_response_xml(response)
		if document is None:
			Logger.warn("Dialog::send_server_status response not XML")
			return False
		
		rootNode = document.documentElement
		if rootNode.nodeName != "server":
			Logger.error("Dialog::send_server_monitoring response not valid %s"%(rootNode.toxml()))
			return False
		
		if not rootNode.hasAttribute("name") or rootNode.getAttribute("name") != self.name:
			Logger.error("Dialog::send_server_monitoring response invalid name")
			return False
		
		return True
	
	
	def response_error(self, code):
		self.send_response(code)
		self.send_header('Content-Type', 'text/html')
		self.end_headers()
		self.wfile.write('')
	
	
	def req_server_status(self, request):
		doc = Document()
		rootNode = doc.createElement('server')
		rootNode.setAttribute("name", self.name)
		rootNode.setAttribute("status", "ready")
		
		doc.appendChild(rootNode)
		return self.req_answer(doc)

	def req_server_logs(self, request, since):
		response = {}
		response["code"] = httplib.OK
		response["Content-Type"] = "text/plain"
		response["data"] = ""
		
		if Logger._instance is None or Logger._instance.filename is None:
			return response
		
		lines = []
		t = time.time()
		
		tailer = FileTailer(Logger._instance.filename)
		while t > since and tailer.hasLines():
			buf = tailer.tail(20)
			buf.reverse()
			
			for line in buf:
				t = Logger._instance.get_time_from_line(line)
				if t is None:
					continue
				
				if t<since:
					break  
				
				lines.insert(0, line)
		
		response["data"] = "\n".join(lines)
		return response
	
	
	def req_server_monitoring(self, request):
		doc = self.server.getMonitoring()
		if doc is None:
			return None
		
		return self.req_answer(doc)
	
	def req_server_conf(self, request):
		cpuInfos = Platform.System.getCPUInfos()
		ram_total = Platform.System.getRAMTotal()
		
		doc = Document()
		rootNode = doc.createElement('configuration')
		
		rootNode.setAttribute("type", Platform.System.getName())
		rootNode.setAttribute("version", Platform.System.getVersion())
		rootNode.setAttribute("ram", str(ram_total))
		rootNode.setAttribute("ulteo_system", str(self.server.ulteo_system).lower())
		
		cpuNode = doc.createElement('cpu')
		cpuNode.setAttribute('nb_cores', str(cpuInfos[0]))
		textNode = doc.createTextNode(cpuInfos[1])
		cpuNode.appendChild(textNode)
		
		rootNode.appendChild(cpuNode)
		
		for role in self.server.roles:
			roleNode = doc.createElement('role')
			roleNode.setAttribute('name', role.dialog.getName())
			rootNode.appendChild(roleNode)
		
		doc.appendChild(rootNode)
		return self.req_answer(doc)
	
	
	def webservices_server_log(self):
		try :
			args = {}
			args2 = cgi.parse_qsl(self.path[self.path.index('?')+1:])
			for (k,v) in args2:
				args[k] = v.decode('utf-8')
		except Exception, err:
			Logger.debug("webservices_server_log error decoding args %s"%(err))
			args = {}
		
		Logger.debug("webservices_server_log args: "+str(args))
		
		if not args.has_key('since'):
			Logger.warn("webservices_server_log: no since arg")
			self.response_error(httplib.BAD_REQUEST)
			return
		
		try:
			since = int(args['since'])
		except:
			Logger.warn("webservices_server_log: since arg not int")
			self.response_error(httplib.BAD_REQUEST)
			return

		(last, data) = self.getLogSince(since)
		data = base64.encodestring("".join(data))
		
		doc = Document()
		rootNode = doc.createElement("log")
		rootNode.setAttribute("since", str(since))
		rootNode.setAttribute("last", str(int(last)))

		node = doc.createElement("web")
		dataNode = doc.createCDATASection(data)
		node.appendChild(dataNode)
		rootNode.appendChild(node)
		
		node = doc.createElement("daemon")
		dataNode = doc.createCDATASection("")
		node.appendChild(dataNode)
		rootNode.appendChild(node)
		
		doc.appendChild(rootNode)
		
		self.send_response(httplib.OK)
		self.send_header('Content-Type', 'text/xml')
		self.end_headers()
		self.wfile.write(doc.toxml())
		
		return
