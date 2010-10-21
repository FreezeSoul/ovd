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
import pythoncom
import random
import time
import win32api
from win32com.shell import shell, shellcon
import win32con
import win32file
import win32net
import win32profile
import win32security
import _winreg

from ovd.Logger import Logger
from ovd.Role.ApplicationServer.Session import Session as AbstractSession

import Langs
import LnkFile
from Msi import Msi
from ovd.Platform import Platform
import Reg

class Session(AbstractSession):
	def init(self):
		self.installedShortcut = []
		self.succefully_installed = False
	
	
	def install_client(self):
		logon = win32security.LogonUser(self.user.name, None, self.user.infos["password"], win32security.LOGON32_LOGON_INTERACTIVE, win32security.LOGON32_PROVIDER_DEFAULT)
		
		data = {}
		data["UserName"] = self.user.name
		hkey = win32profile.LoadUserProfile(logon, data)
		self.windowsProfileDir = win32profile.GetUserProfileDirectory(logon)
		self.user.home = self.windowsProfileDir
		
		self.windowsProgramsDir = shell.SHGetFolderPath(0, shellcon.CSIDL_PROGRAMS, logon, 0)
		Logger.debug("startmenu: %s"%(self.windowsProgramsDir))
		# remove default startmenu
		if os.path.exists(self.windowsProgramsDir):
			Platform.System.DeleteDirectory(self.windowsProgramsDir)
		os.makedirs(self.windowsProgramsDir)
		
		self.windowsDesktopDir = shell.SHGetFolderPath(0, shellcon.CSIDL_DESKTOPDIRECTORY, logon, 0)
		
		self.appDataDir = shell.SHGetFolderPath(0, shellcon.CSIDL_APPDATA, logon, 0)
		Logger.debug("appdata: '%s'"%(self.appDataDir))
		
		win32profile.UnloadUserProfile(logon, hkey)
		win32api.CloseHandle(logon)
		
		if self.profile is not None and self.profile.hasProfile():
			if not self.profile.mount():
				return False
			
			self.profile.copySessionStart()
		
		
		
		self.init_user_session_dir(os.path.join(self.appDataDir, "ulteo", "ovd"))
		
		self.overwriteDefaultRegistry(self.windowsProfileDir)
		
		if self.profile is not None and self.profile.hasProfile():
			self.profile.umount()
		
		self.succefully_installed = True
		return True
	
	
	def install_shortcut(self, shortcut):
		self.installedShortcut.append(os.path.basename(shortcut))
		
		dstFile = os.path.join(self.windowsProgramsDir, os.path.basename(shortcut))
		if os.path.exists(dstFile):
			os.remove(dstFile)
		
		win32file.CopyFile(shortcut, dstFile, True)
		
		if self.parameters.has_key("desktop_icons"):
			if self.profile is not None and self.profile.mountPoint is not None:
				d = os.path.join(self.profile.mountPoint, self.profile.DesktopDir)
			else:
				d = self.windowsDesktopDir
				if  not os.path.exists(self.windowsDesktopDir):
					os.makedirs(self.windowsDesktopDir)
			  
			dstFile = os.path.join(d, os.path.basename(shortcut))
			if os.path.exists(dstFile):
				os.remove(dstFile)
			
			win32file.CopyFile(shortcut, dstFile, True)
	
	
	def get_target_file(self, application):
		return application["name"]+".lnk"
	
	
	def clone_shortcut(self, src, dst, command, args):
		return LnkFile.clone(src, dst, command, " ".join(args))
	
	
	def uninstall_client(self):
		if not self.succefully_installed:
			return
		
		self.archive_shell_dump()
		
		if self.profile is not None and self.profile.hasProfile():
			if not self.profile.mount():
				Logger.warn("Unable to mount profile at uninstall_client of session "+self.id)
			else:
				self.profile.copySessionStop()
				
				for shortcut in self.installedShortcut:
					dstFile = os.path.join(self.profile.mountPoint, self.profile.DesktopDir, shortcut)
					if os.path.exists(dstFile):
						os.remove(dstFile)
				
				if not self.profile.umount():
					Logger.error("Unable to umount profile at uninstall_client of session "+self.id)
		
		return True
	
	
	def unload(self, sid):
		try:
			# Unload user reg
			win32api.RegUnLoadKey(win32con.HKEY_USERS, sid)
			win32api.RegUnLoadKey(win32con.HKEY_USERS, sid+'_Classes')
		except Exception, e:
			Logger.warn("Unable to unload user reg: %s"%(str(e)))
			return False
		
		return True
	
	
	def obainPrivileges(self):
		# Get some privileges to load the hive
		priv_flags = win32security.TOKEN_ADJUST_PRIVILEGES | win32security.TOKEN_QUERY 
		hToken = win32security.OpenProcessToken (win32api.GetCurrentProcess (), priv_flags)
		backup_privilege_id = win32security.LookupPrivilegeValue (None, "SeBackupPrivilege")
		restore_privilege_id = win32security.LookupPrivilegeValue (None, "SeRestorePrivilege")
		win32security.AdjustTokenPrivileges (
			hToken, 0, [
			(backup_privilege_id, win32security.SE_PRIVILEGE_ENABLED),
			(restore_privilege_id, win32security.SE_PRIVILEGE_ENABLED)
			]
		)
	
	
	def overwriteDefaultRegistry(self, directory):
		registryFile = os.path.join(directory, "NTUSER.DAT")
		
		self.obainPrivileges()
		
		hiveName = "OVD_%s_%d"%(str(self.id), random.randrange(10000, 50000))
		
		# Load the hive
		_winreg.LoadKey(win32con.HKEY_USERS, hiveName, registryFile)
		
		# Set the language
		if self.parameters.has_key("locale"):
			path = r"%s\Control Panel\Desktop"%(hiveName)
			key = win32api.RegOpenKey(_winreg.HKEY_USERS, path, 0, win32con.KEY_SET_VALUE)
			win32api.RegSetValueEx(key, "MUILanguagePending", 0, win32con.REG_DWORD, Langs.getLCID(self.parameters["locale"]))
			win32api.RegSetValueEx(key, "MultiUILanguageId", 0, win32con.REG_DWORD, Langs.getLCID(self.parameters["locale"]))
			win32api.RegCloseKey(key)
		
		# Policies update
		path = r"%s\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer"%(hiveName)
		restrictions = ["DisableFavoritesDirChange",
				"DisableLocalMachineRun",
				"DisableLocalMachineRunOnce",
				"DisableMachineRunOnce",
				"DisableMyMusicDirChange",
				"DisableMyPicturesDirChange",
				"DisablePersonalDirChange",
				"EnforceShellExtensionSecurity",
				#"ForceStartMenuLogOff",
				"Intellimenus",
				"NoChangeStartMenu",
				"NoClose",
				"NoCommonGroups",
				"NoControlPanel",
				"NoDFSTab",
				"NoFind",
				"NoFolderOptions",
				"NoHardwareTab",
				"NoInstrumentation",
				"NoIntellimenus",
				"NoInternetIcon", # remove the IE icon
				"NoManageMyComputerVerb",
				"NonEnum",
				"NoNetworkConnections",
				"NoResolveSearch",
				"NoRun",
				"NoSetFolders",
				"NoSetTaskbar",
				"NoStartMenuSubFolders", # should remove the folders from startmenu but doesn't work
				"NoSMBalloonTip",
				"NoStartMenuEjectPC",
				"NoStartMenuNetworkPlaces",
				"NoTrayContextMenu",
				"NoWindowsUpdate",
				#"NoViewContextMenu", # Mouse right clic
				#"StartMenuLogOff",
				]
		try:
			Reg.CreateKeyR(_winreg.HKEY_USERS, path)
			key = _winreg.OpenKey(_winreg.HKEY_USERS, path, 0, win32con.KEY_SET_VALUE)
		except:
			key = None
		
		if key is None:
			Logger.error("Unable to open key '%s'"%(path))
		else:
			for item in restrictions:
				_winreg.SetValueEx(key, item, 0, _winreg.REG_DWORD, 1)
			_winreg.CloseKey(key)
		
		
		path = r"%s\Software\Microsoft\Windows\CurrentVersion\Policies\System"%(hiveName)
		restrictions = ["DisableRegistryTools",
				"DisableTaskMgr",
				"NoDispCPL",
				]
		
		try:
			Reg.CreateKeyR(_winreg.HKEY_USERS, path)
			key = _winreg.OpenKey(_winreg.HKEY_USERS, path, 0, win32con.KEY_SET_VALUE)
		except:
			key = None
		if key is None:
			Logger.error("Unable to open key '%s'"%(path))
		else:
			for item in restrictions:
				_winreg.SetValueEx(key, item, 0, _winreg.REG_DWORD, 1)
			_winreg.CloseKey(key)
		
		# Desktop customization
		path = r"%s\Control Panel\Desktop"%(hiveName)
		items = ["ScreenSaveActive", "ScreenSaverIsSecure"]
		
		try:
			Reg.CreateKeyR(_winreg.HKEY_USERS, path)
			key = _winreg.OpenKey(_winreg.HKEY_USERS, path, 0, win32con.KEY_SET_VALUE)
		except:
			key = None
		if key is None:
			Logger.error("Unable to open key '%s'"%(path))
		else:
			for item in items:
				_winreg.SetValueEx(key, item, 0, _winreg.REG_DWORD, 0)
			_winreg.CloseKey(key)
		
		# Overwrite Active Setup: works partially
		try:
			Reg.UpdateActiveSetup(self.user.name, hiveName, r"Software\Microsoft\Active Setup")
			# On 64 bits architecture, Active Setup is already present in path "Software\Wow6432Node\Microsoft\Active Setup"
			if "PROGRAMW6432" in os.environ.keys():
				Reg.UpdateActiveSetup(self.user.name, hiveName, r"Software\Wow6432Node\Microsoft\Active Setup")
			
		except Exception, err:
			Logger.warn("Unable to reset ActiveSetup")
			Logger.debug("Unable to reset ActiveSetup: "+str(err))
		
		if self.profile is not None:
			self.profile.overrideRegistry(hiveName)
		
		
		# Timezone override
		if self.parameters.has_key("timezone"):
			tz_name = Langs.getWinTimezone(self.parameters["timezone"])
			
			ret = Reg.setTimezone(hiveName, tz_name)
			if ret is False:
				Logger.warn("Unable to set TimeZone (%s, %s)"%(self.parameters["timezone"], tz_name))
		
		
		# Unload the hive
		win32api.RegUnLoadKey(win32con.HKEY_USERS, hiveName)

