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
	
	
	def install_client(self):
		logon = win32security.LogonUser(self.user.name, None, self.user.infos["password"], win32security.LOGON32_LOGON_INTERACTIVE, win32security.LOGON32_PROVIDER_DEFAULT)
		
		data = {}
		data["UserName"] = self.user.name
		hkey = win32profile.LoadUserProfile(logon, data)
		win32profile.UnloadUserProfile(logon, hkey)
		self.windowsProfileDir = win32profile.GetUserProfileDirectory(logon)
		
		self.windowsProgramsDir = shell.SHGetSpecialFolderPath(logon, shellcon.CSIDL_PROGRAMS)
		Logger.debug("startmenu: %s"%(self.windowsProgramsDir))
		# remove default startmenu
		if os.path.exists(self.windowsProgramsDir):
			Platform.System.DeleteDirectory(self.windowsProgramsDir)
		os.makedirs(self.windowsProgramsDir)
		
		self.windowsDesktopDir = shell.SHGetSpecialFolderPath(logon, shellcon.CSIDL_DESKTOPDIRECTORY)
		desktopDir = os.path.join(self.windowsProfileDir, "Desktop")
		if self.windowsDesktopDir != desktopDir:
			# bug: this return the Administrator desktop dir path ...
			Logger.warn("desktop dir bug#1: v1: '%s', v2: '%s'"%(self.windowsDesktopDir, desktopDir))
			self.windowsDesktopDir = desktopDir
		
		
		self.appDataDir = shell.SHGetSpecialFolderPath(logon, shellcon.CSIDL_APPDATA)
		Logger.debug("appdata: '%s'"%(self.appDataDir))
		
		win32api.CloseHandle(logon)
		
		if self.profile is not None:
			if not self.profile.mount():
				Logger.warn("Session is going to continue without profile")  
				self.profile = None
			else:
				self.profile.copySessionStart()
		
		
		
		self.init_user_session_dir(os.path.join(self.appDataDir, "ulteo", "ovd"))
		
		self.overwriteDefaultRegistry(self.windowsProfileDir)
		
		if self.profile is not None:
			self.profile.umount()
	
	
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
	
	
	def get_target_file(self, app_id, app_target):
		return os.path.basename(app_target)
	
	
	def clone_shortcut(self, src, dst, command, args):
		LnkFile.clone(src, dst, command, " ".join(args))
	
	
	def uninstall_client(self):
		if self.profile is not None:
			self.profile.mount()
			
			self.profile.copySessionStop()
			
			for shortcut in self.installedShortcut:
				dstFile = os.path.join(self.profile.mountPoint, self.profile.DesktopDir, shortcut)
				if os.path.exists(dstFile):
					os.remove(dstFile)
			
			self.profile.umount()
		
		self.user.destroy()
		
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
		
		hiveName = "OVD_%d"%(random.randrange(10000, 50000))
		
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
		
		key = _winreg.OpenKey(_winreg.HKEY_USERS, path, 0, _winreg.KEY_SET_VALUE)
		for item in restrictions:
			_winreg.SetValueEx(key, item, 0, _winreg.REG_DWORD, 1)
		_winreg.CloseKey(key)
		
		
		path = r"%s\Software\Microsoft\Windows\CurrentVersion\Policies"%(hiveName)
		key = _winreg.OpenKey( _winreg.HKEY_USERS, path, 0, _winreg.KEY_SET_VALUE)
		_winreg.CreateKey(key, "System")
		_winreg.CloseKey(key)
		
		path = r"%s\Software\Microsoft\Windows\CurrentVersion\Policies\System"%(hiveName)
		restrictions = ["DisableRegistryTools",
				"DisableTaskMgr",
				"NoDispCPL",
				]
		
		key = _winreg.OpenKey(_winreg.HKEY_USERS, path, 0, _winreg.KEY_SET_VALUE)
		for item in restrictions:
			_winreg.SetValueEx(key, item, 0, _winreg.REG_DWORD, 1)
		_winreg.CloseKey(key)
		
		
		
		
		# Desktop customization
		path = r"%s\Control Panel\Desktop"%(hiveName)
		items = ["ScreenSaveActive", "ScreenSaverIsSecure"]
		
		key = _winreg.OpenKey(_winreg.HKEY_USERS, path, 0, _winreg.KEY_SET_VALUE)
		for item in items:
			_winreg.SetValueEx(key, item, 0, _winreg.REG_DWORD, 0)
		_winreg.CloseKey(key)
		
		
		# Rediect the Shell Folders to the remote profile
		path = r"%s\Software\Microsoft\Windows\CurrentVersion\Explorer\User Shell Folders"%(hiveName)
		data = [
			"Desktop",
		]
		key = win32api.RegOpenKey(win32con.HKEY_USERS, path, 0, win32con.KEY_SET_VALUE)
		
		for item in data:
			dst = os.path.join(directory, item)
			win32api.RegSetValueEx(key, item, 0, win32con.REG_SZ, dst)
		win32api.RegCloseKey(key)
		
		
		# Overwrite Active Setup: works partially
		hkey_src = win32api.RegOpenKey(win32con.HKEY_LOCAL_MACHINE, "Software\Microsoft\Active Setup", 0, win32con.KEY_ALL_ACCESS)
		hkey_dst = win32api.RegOpenKey(win32con.HKEY_USERS, r"%s\Software\Microsoft\Active Setup"%(hiveName), 0, win32con.KEY_ALL_ACCESS)
		
		Reg.CopyTree(hkey_src, "Installed Components", hkey_dst)
		Reg.UpdateActiveSetup(hkey_dst, self.user.name)
		win32api.RegCloseKey(hkey_src)
		win32api.RegCloseKey(hkey_dst)
		
		if self.profile is not None:
			self.profile.overrideRegistry(hiveName)
		
		
		# Unload the hive
		win32api.RegUnLoadKey(win32con.HKEY_USERS, hiveName)

