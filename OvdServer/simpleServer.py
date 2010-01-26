#! /usr/bin/python
# -*- coding: utf-8 -*-

# Copyright (C) 2008-2009 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2008-2009
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

import getopt
import os
import signal
import sys

from ovd.Communication.HttpServer import HttpServer as Communication
from ovd.Config import Config
from ovd.Logger import Logger
from ovd.SlaveServer import SlaveServer
from ovd.Platform import Platform


def usage():
	print "Usage: %s [-c|--config-file= filename] [-d|--daemonize] [-h|--help] [-p|--pid-file= filename]"%(sys.argv[0])
	print "\t-c|--config-file filename: load filename as configuration file instead default one"
	print "\t-d|--daemonize: start in background"
	print "\t-h|--help: print this help"
	print "\t-p|--pid-file filename: write process id in specified file"
	print


def writePidFile(filename):
	try:
		f = file(filename, "w")
	except:
		return False
	
	f.write(str(os.getpid()))
	f.close()
	return True


def main():
	config_file = os.path.join(Platform.getInstance().get_default_config_dir(), "ovdserver.conf")
	daemonize = False
	pidFile = None

	try:
		opts, args = getopt.getopt(sys.argv[1:], 'c:dhp:', ['config-file=', 'daemonize', 'help', 'pid-file='])
	
	except getopt.GetoptError, err:
		print >> sys.stderr, str(err)
		usage()
		sys.exit(2)
	
	for o, a in opts:
		if o in ("-c", "--config-file"):
			config_file = a
		elif o in ("-d", "--daemonize"):
			daemonize = True
		elif o in ("-h", "--help"):
			usage()
			sys.exit()
		elif o in ("-p", "--pid-file"):
			pidFile = a
	
	if not Config.read(config_file):
		print >> sys.stderr, "invalid configuration file '%s'"%(config_file)
		sys.exit(1)
	
	if not Config.is_valid():
		print >> sys.stderr, "invalid config"
		sys.exit(1)
	
	if daemonize:
		pid = os.fork()
		if pid < 0:
			print >> sys.stderr, "Error when fork"
			sys.exit(1)
		if pid > 0:
			sys.exit(0)
		
		pid = os.fork()
		if pid < 0:
			print >> sys.stderr, "Error when fork"
			sys.exit(1)
		if pid > 0:
			sys.exit(0)
	
	
	if pidFile is not None:
		if not writePidFile(pidFile):
			print >> sys.stderr, "Unable to write pid-file '%s'"%(pidFile)
			sys.exit(1)
	
	
	log_flags = 0
	for item in Config.log_level:
		if item == "info":
			log_flags|= Logger.INFO
		elif item == "warn":
			log_flags|= Logger.WARN
		elif item == "error":
			log_flags|= Logger.ERROR
		elif item == "debug":
			log_flags|= Logger.DEBUG

	Logger.initialize("simpleServer", log_flags, Config.log_file, (not daemonize))
	
	
	server = SlaveServer(Communication)
	signal.signal(signal.SIGINT, server.stop)
	signal.signal(signal.SIGTERM, server.stop)
	server.loop()
	if not server.stopped:
		server.stop()
	
	if pidFile is not None and os.path.exists(pidFile):
		os.remove(pidFile)


if __name__ == "__main__":
	main()
