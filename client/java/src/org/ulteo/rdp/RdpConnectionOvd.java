/*
 * Copyright (C) 2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Thomas MOUTON <thomas@ulteo.com> 2010
 * Author Arnaud LEGRAND <arnaud@ulteo.com> 2010
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

package org.ulteo.rdp;

import java.util.ArrayList;
import java.util.Locale;
import net.propero.rdp.Common;
import net.propero.rdp.Options;
import net.propero.rdp.RdesktopException;
import net.propero.rdp.RdpConnection;
import org.ulteo.ovd.Application;
import org.ulteo.ovd.OvdException;
import org.ulteo.ovd.disk.LinuxDiskManager;
import org.ulteo.ovd.disk.WindowsDiskManager;
import org.ulteo.ovd.disk.DiskManager;
import org.ulteo.ovd.integrated.OSTools;
import org.ulteo.ovd.printer.OVDPrinterManager;
import org.ulteo.rdp.rdpdr.OVDRdpdrChannel;
import org.ulteo.rdp.seamless.SeamlessChannel;
import java.net.InetAddress;
import java.net.UnknownHostException;
import org.ulteo.Logger;
import org.ulteo.rdp.TCPSSLSocketFactory;

public class RdpConnectionOvd extends RdpConnection {

	public static final byte MODE_DESKTOP = 0x01;
	public static final byte MODE_APPLICATION = 0x02;
	public static final byte MODE_MULTIMEDIA = 0x04;
	public static final byte MOUNT_PRINTERS = 0x08;

	private byte flags = 0x00;
	private ArrayList<Application> appsList = null;
	private OvdAppChannel ovdAppChannel = null;
	private DiskManager diskManager = null;

	/**
	 * Instanciate a new RdpConnectionOvd with default options:
	 *	- bitmap compression
	 *	- volatile bitmap caching
	 *	- persistent bitmap caching
	 *	- 24 bits
	 *	- Clip channel
	 */
	public RdpConnectionOvd(byte flags_) throws OvdException, RdesktopException {
		super(new Options(), new Common());

		this.flags = flags_;

		if ((this.flags & MODE_DESKTOP) != 0 && (this.flags & MODE_APPLICATION) != 0)
			throw new OvdException("Unable to create connection: Desktop and Application modes can't work together");

		this.opt.bitmap_compression = true;
		this.setVolatileCaching(true);
		this.setPersistentCaching(false);

		if ((this.flags & MODE_DESKTOP) != 0) {
			this.setDesktopMode();
		}
		else if ((this.flags & MODE_APPLICATION) != 0) {
			this.setApplicationMode();
		}
		else {
			throw new OvdException("Unable to create connection: Neither desktop nor application mode specified");
		}

		this.appsList = new ArrayList<Application>();
		this.detectKeymap();
	}

	/**
	 * Register all secondary channels requested. They could be:
	 *	- sound channel
	 *	- rdpdr channel
	 * @throws OvdException
	 */
	public void initSecondaryChannels() throws OvdException, RdesktopException {
		this.initClipChannel();
		
		this.mountLocalDrive();
		if ((this.flags & MODE_MULTIMEDIA) != 0) {
			this.setMultimediaMode();
		}
		if ((this.flags & MOUNT_PRINTERS) != 0) {
			this.mountLocalPrinters();
		}
	}

	@Override
	protected void initSeamlessChannel() throws RdesktopException {
		this.opt.seamlessEnabled = true;
		this.seamChannel = new SeamlessChannel(this.opt, this.common);
		if (! this.addChannel(this.seamChannel))
			throw new RdesktopException("Unable to add seamless channel");
	}

	protected void initOvdAppChannel() throws OvdException {
		this.ovdAppChannel = new OvdAppChannel(this.opt, this.common);
		if (! this.addChannel(this.ovdAppChannel))
			throw new OvdException("Unable to add ovdapp channel");
	}

	/**
	 * Enable OVD desktop mode
	 */
	private void setDesktopMode() {
		this.opt.seamlessEnabled = false;
	}

	/**
	 * Enable OVD applications mode
	 *	- Init seamless channel
	 *	- Add OvdApp channel
	 */
	private void setApplicationMode() throws OvdException, RdesktopException {
		this.initSeamlessChannel();

		this.initOvdAppChannel();
	}

	/**
	 * Enable OVD multimedia mode
	 *	- Add sound channel
	 */
	private void setMultimediaMode() throws OvdException, RdesktopException {
		this.initSoundChannel();
		System.out.println("Sound channel added");
	}

	/**
	 * Init rdpdr channel
	 *	- Add device redirection channel
	 */
	@Override
	protected void initRdpdrChannel() throws RdesktopException {
		if (this.rdpdrChannel != null)
			return;
		this.rdpdrChannel = new OVDRdpdrChannel(this.opt, this.common);
		if (! this.addChannel(this.rdpdrChannel))
			throw new RdesktopException("Unable to add rdpdr channel");
	}
	
	/**
	 * process the disconnected step
	 *	- stop the disk timer task
	 */
	@Override
	protected void fireDisconnected() {
		super.fireDisconnected();

		if (this.diskManager != null)
			this.diskManager.stop();
	}
	
	/**
	 * Mount local printers
	 *	- Add rdpdr channel
	 *	- Use a PrinterManager instance in order to register all local printers
	 */
	private void mountLocalPrinters() throws OvdException, RdesktopException {
		OVDPrinterManager printerManager = new OVDPrinterManager(this.rdpdrChannel);
		printerManager.searchAllPrinter();
		if (printerManager.hasPrinter()) {
			this.initRdpdrChannel();
			System.out.println("Rdpdr channel added");
			printerManager.registerAll(this.rdpdrChannel);
		}
		else
			throw new OvdException("Have to map local printers but no printer found ....");
	}

	/**
	 * Mount local drive
	 *	- Add rdpdr channel
	 *	- Use a diskmanager instance in order to register all local disks
	 */
	private void mountLocalDrive() throws OvdException, RdesktopException {
		this.initRdpdrChannel();
		if (OSTools.isWindows()) {
			diskManager = new WindowsDiskManager((OVDRdpdrChannel)rdpdrChannel);
		}
		else {
			diskManager = new LinuxDiskManager((OVDRdpdrChannel)rdpdrChannel);
		}
		diskManager.init();
		diskManager.launch();		
	}
	
	@Override
	public void setPersistentCaching(boolean persistentCaching) {
		super.setPersistentCaching(persistentCaching);

		String separator = System.getProperty("file.separator");
		String cacheDir = System.getProperty("user.home")+separator+
			((System.getProperty("os.name").startsWith("Windows")) ? "Application Data"+separator : ".")+
			"ulteo"+separator+"ovd"+separator+"cache"+separator;
		this.setPersistentCachingPath(cacheDir);
	}

	protected void detectKeymap() {
		String language = System.getProperty("user.language");
		String country = System.getProperty("user.country");

		this.mapFile =  new Locale(language, country).toString().toLowerCase();
		this.mapFile = this.mapFile.replace('_', '-');
	}

	@Override
	public void stop() {
		super.stop();

		if (this.diskManager != null) {
			this.diskManager.stop();
			this.diskManager = null;
		}
	}

	public void addApp(Application app_) {
		this.appsList.add(app_);
	}

	public ArrayList<Application> getAppsList() {
		return this.appsList;
	}

	/**
	 * Return the current OvdAppChannel instance
	 * @return OvdAppChannel instance
	 */
	public OvdAppChannel getOvdAppChannel() {
		return this.ovdAppChannel;
	}

	public void sendLogoff() throws OvdException {
		if (this.ovdAppChannel == null)
			throw new OvdException("Unable to send logoff: OvdAppChannel does not exist");
		if (! this.ovdAppChannel.isReady())
			throw new OvdException("Unable to send logoff: OvdAppChannel is not initialized");

		this.ovdAppChannel.sendLogoff();
	}

	/**
	 * Register an OvdAppListener
	 * @param listener
	 * @throws OvdException
	 */
	public void addOvdAppListener(OvdAppListener listener) throws OvdException {
		if (this.ovdAppChannel == null)
			throw new OvdException("Could not add an OvdAppListener: OvdAppChannel does not exist");
		this.ovdAppChannel.addOvdAppListener(listener);
	}

	/**
	 * Unregister an OvdAppListener
	 * @param listener
	 * @throws OvdException
	 */
	public void removeOvdAppListener(OvdAppListener listener) throws OvdException {
		if (this.ovdAppChannel == null)
			throw new OvdException("Could not remove an OvdAppListener: OvdAppChannel does not exist");
		this.ovdAppChannel.removeOvdAppListener(listener);
	}

	public void useSSLWrapper(String host, int port) throws OvdException, UnknownHostException {
		InetAddress hostv = null;

		try {
			hostv = InetAddress.getByName(host);
		} catch(Exception e) {
			throw new OvdException("Could not convert String fqdn to InetAdress host : " + e.getMessage());
		}

		this.opt.port = port;

		try {
			this.opt.socketFactory = new TCPSSLSocketFactory(hostv, port);
		} catch (Exception e2) {
			throw new OvdException("Could not create TCPSSLSocketFactory : " + e2.getMessage());
		}
	}
}
