# -*- coding: utf-8 -*-

# Copyright (C) 2011-2013 Ulteo SAS
# http://www.ulteo.com
# Author Samuel BOVEE <samuel@ulteo.com> 2011
# Author Wojciech LICHOTA <wojciech.lichota@stxnext.pl> 2013
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

import re
from gzip import GzipFile
from cStringIO import StringIO
import pycurl
from xml.dom.minidom import Document

from ovd.Logger import Logger
from ovd.SMRequestManager import SMRequestManager as GenericSMRequestManager
from Config import Config
from SessionsRepository import SessionsRepository


RE_PARAM = re.compile('\$\(([^)]*)\)')


class SMRequestManager(GenericSMRequestManager):
    def run_sql(self, app_id, query, params):
        doc = Document()
        rootNode = doc.createElement("run-sql")
        rootNode.setAttribute("app_id", app_id)
        rootNode.setAttribute("query", query)
        
        for param, value in params.items():
            paramNode = doc.createElement('param')
            paramNode.setAttribute('name', param)
            textNode = doc.createTextNode(value)
            paramNode.appendChild(textNode)
            rootNode.appendChild(paramNode)
            
        doc.appendChild(rootNode)
        
        response = self.send_packet("/run/sql", doc)
        if response is False:
            Logger.warn("SMRequest::run_sql Unable to send packet")
            return None
        
        document = self.get_response_xml(response)
        if document is None:
            Logger.warn("SMRequest:run_sql not XML response")
            return None
        
        rootNode = document.documentElement
        Logger.warn("SMRequest:run_sql result: "+rootNode.nodeName)
        
        if rootNode.nodeName != "sql-result":
            return None
        
        return rootNode
        

def gzip(buf):
    zbuf = StringIO()
    zfile = GzipFile(mode='wb',  fileobj=zbuf)
    zfile.write(buf)
    zfile.close()
    return zbuf.getvalue()


def gunzip(buf):
    zfile = GzipFile(fileobj=StringIO(buf))
    data = zfile.read()
    zfile.close()
    return data


def replace_params(text, params):
    def get_value(m):
        value = params.get(m.group(1), '')
        if not value.startswith('EXECUTE_SQL: '):
            value = replace_params(value, params)
        return value
        
    return RE_PARAM.sub(get_value, text)


def exec_sql(value, app_config, session):
    if not value.startswith('EXECUTE_SQL: '):
        return value

    query = value[13:]

    result = session.get(query, None)
    if result is None:
        ## send query to SM, save result in session for later use
        params = {}
        for param in RE_PARAM.findall(query):
            value = '$({0})'.format(param)
            value = replace_params(value, app_config)
            value = value.format(**session.credentials())
            params[param] = value
        
        Logger.debug("Executing query {0} with params {1}".format(query, str(params)))
        sm_request_manager = SMRequestManager()
        sql_result_dom = sm_request_manager.run_sql(app_config['app_id'], query, params)
        result = sql_result_dom.getAttribute('value')

        session[query] = result
    return result


HTTP_200_connected = """
HTTP/1.1 200 OK
Content-Type: application/javascript
Content-Length: 54

(function(){{
    webappServerStatus({0}, 'ready');
}})();
""".strip()

HTTP_301 = """
HTTP/1.1 301 Moved Permanently
Location: {0}
Set-Cookie: {1}="{2}"; Domain=.{3}; Path=/; HttpOnly
Content-Type: text/html
Content-Length: 80

<html>
<head>
<title>Moved</title>
</head>
<body>
<h1>Moved</h1>
</body>
</html>
""".strip()

HTTP_403 = """
HTTP/1.1 403 Forbidden
Content-Type: text/html
Content-Length: 88

<html>
<head>
<title>Forbidden</title>
</head>
<body>
<h1>Forbidden</h1>
</body>
</html>
""".strip()


class CurlResponse(object):
    def __init__(self, status, header_buf, body_buf):
        self.status = status
        self.header_buf = header_buf
        self.body_buf = body_buf
        header_buf.seek(0)
        body_buf.seek(0)
        
    def getheaders(self):
        result = self.header_buf.getvalue()
        if result.startswith('HTTP/1.1 401'):
            empty_line = result.find('\r\n\r\n')
            if empty_line > 0:
                result = result[empty_line:].strip()
        
        headers = []
        for l in result.split('\r\n'):
            l = l.strip()
            colon = l.find(':')
            if colon > 0:
                k, v = l[:colon].strip(), l[colon+1:].strip()
                headers.append((k, v))
        return headers
    
    def read(self, size=-1):
        return self.body_buf.read(size)
        
        
class CurlConnection(object):
    def __init__(self, scheme, netloc, auth):
        self.scheme = scheme
        self.netloc = netloc
        self.auth = auth
        
        self._body_buf = StringIO()
        self._header_buf = StringIO()
    
    def request(self, method, path, data, headers):
        body = ''
        if isinstance(data, basestring):
            body = data
        elif data is not None:
            body = []
            datablock = data.read(Config.chunk_size)
            while datablock:
                body.append(datablock)
                datablock = data.read(Config.chunk_size)
            body = ''.join(body)
        
        c = pycurl.Curl()
        c.setopt(c.URL, "{0}://{1}/{2}".format(self.scheme, self.netloc, path))
        
        c.setopt(c.CONNECTTIMEOUT, Config.connection_timeout)
        c.setopt(c.TIMEOUT, Config.connection_timeout)
        c.setopt(c.SSL_VERIFYPEER, False)
        c.setopt(c.SSL_VERIFYHOST, False)
        
        c.setopt(c.HTTPHEADER, ['{0}: {1}'.format(k, v) for k, v in headers.items()])
        if method.lower() == 'post':
            c.setopt(c.POSTFIELDS, body)
        
        c.setopt(c.HTTPAUTH, c.HTTPAUTH_NTLM)
        c.setopt(c.USERPWD, self.auth)

        c.setopt(c.WRITEFUNCTION, self._body_buf.write)
        c.setopt(c.HEADERFUNCTION, self._header_buf.write)
        c.perform()

        self._response = CurlResponse(
            c.getinfo(c.HTTP_CODE),
            self._header_buf,
            self._body_buf,
        )
    
    def getresponse(self):
        return self._response 
    
    def close(self):
        self._body_buf.close()
        self._header_buf.close()
        return True