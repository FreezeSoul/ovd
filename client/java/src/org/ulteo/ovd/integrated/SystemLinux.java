/*
 * Copyright (C) 2009 Ulteo SAS
 * http://www.ulteo.com
 * Author Thomas MOUTON <thomas@ulteo.com> 2010
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

package org.ulteo.ovd.integrated;

import java.awt.Graphics2D;
import java.awt.Image;
import java.awt.image.BufferedImage;
import java.io.BufferedInputStream;
import java.io.BufferedOutputStream;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.IOException;
import javax.imageio.ImageIO;
import org.ulteo.Logger;
import org.ulteo.ovd.Application;
import org.ulteo.ovd.integrated.shorcut.LinuxShortcut;

public class SystemLinux extends SystemAbstract {

	public SystemLinux() {
		this.shortcut = new LinuxShortcut();
	}

	@Override
	public String create(Application app) {
		Logger.debug("Creating the '"+app.getName()+"' shortcut");
		
		this.saveIcon(app);
		return this.shortcut.create(app);
	}

	@Override
	public void clean(Application app) {
		Logger.debug("Deleting the '"+app.getName()+"' shortcut");

		this.uninstall(app);
		this.shortcut.remove(app);
	}

	@Override
	public void install(Application app) {
		Logger.debug("Installing the '"+app.getName()+"' shortcut");
		
		File f = new File(Constants.PATH_SHORTCUTS+Constants.FILE_SEPARATOR+app.getId()+Constants.SHORTCUTS_EXTENSION);
		if (! f.exists()) {
			Logger.error("Cannot copy the '"+app.getId()+Constants.SHORTCUTS_EXTENSION+"' shortcut: The file does not exist ("+f.getPath()+")");
			return;
		}

		try {
			BufferedInputStream shortcutReader = new BufferedInputStream(new FileInputStream(f), 4096);
			
			if (new File(Constants.PATH_OVD_SPOOL_XDG_APPLICATIONS).exists()) {
				File xdgShortcut = new File(Constants.PATH_OVD_SPOOL_XDG_APPLICATIONS+Constants.FILE_SEPARATOR+app.getId()+Constants.SHORTCUTS_EXTENSION);
				BufferedOutputStream xdgStream = new BufferedOutputStream(new FileOutputStream(xdgShortcut), 4096);

				int currentChar;
				while ((currentChar = shortcutReader.read()) != -1) {
					xdgStream.write(currentChar);
				}

				xdgStream.close();
			}
			else {
				File desktopShortcut = null;
				if (true) /* ToDo: pref SM*/
					desktopShortcut = new File(Constants.PATH_DESKTOP+Constants.FILE_SEPARATOR+app.getId()+Constants.SHORTCUTS_EXTENSION);
				File xdgShortcut = new File(Constants.PATH_XDG_APPLICATIONS+Constants.FILE_SEPARATOR+app.getId()+Constants.SHORTCUTS_EXTENSION);

				BufferedOutputStream desktopStream = null;
				if (desktopShortcut != null)
					desktopStream = new BufferedOutputStream(new FileOutputStream(desktopShortcut), 4096);
				BufferedOutputStream xdgStream = new BufferedOutputStream(new FileOutputStream(xdgShortcut), 4096);

				int currentChar;
				while ((currentChar = shortcutReader.read()) != -1) {
					if (desktopStream != null)
						desktopStream.write(currentChar);
					xdgStream.write(currentChar);
				}

				if (desktopStream != null)
					desktopStream.close();
				xdgStream.close();
			}
			shortcutReader.close();
		} catch(FileNotFoundException e) {
			Logger.error("This file does not exists: "+e.getMessage());
			return;
		} catch(IOException e) {
			Logger.error("An error occured during the shortcut '"+app.getId()+Constants.SHORTCUTS_EXTENSION+"' copy: "+e.getMessage());
			return;
		}
	}

	@Override
	public void uninstall(Application app) {
		Logger.debug("Uninstalling the '"+app.getName()+"' shortcut");

		File desktop = new File(Constants.PATH_DESKTOP+Constants.FILE_SEPARATOR+app.getId()+Constants.SHORTCUTS_EXTENSION);
		if (desktop.exists())
			desktop.delete();
		desktop = null;

		File xdgApps = new File(Constants.PATH_XDG_APPLICATIONS+Constants.FILE_SEPARATOR+app.getId()+Constants.SHORTCUTS_EXTENSION);
		if (xdgApps.exists())
			xdgApps.delete();
		xdgApps = null;
	}

	@Override
	protected void saveIcon(Application app) {
		File output = new File(Constants.PATH_ICONS+Constants.FILE_SEPARATOR+app.getIconName()+".png");
		if (! output.exists()) {
			try {
				output.createNewFile();
			} catch (IOException ex) {
				Logger.error("Error while creating "+app.getName()+" icon file: "+ex.getMessage());
				return;
			}
		}
		try {
			Image icon = app.getIcon().getImage();
			BufferedImage buff = new BufferedImage(icon.getWidth(null), icon.getHeight(null), BufferedImage.TYPE_INT_RGB);
			Graphics2D g = buff.createGraphics();
			g.drawImage(icon, null, null);
			ImageIO.write(buff, "png", output);
		} catch (IOException ex) {
			Logger.error("Error while converting "+app.getName()+" icon: "+ex.getMessage());
		}
	}
}
