/* Options.java
 * Component: ProperJavaRDP
 * 
 * Revision: $Revision: 1.1.1.1 $
 * Author: $Author: suvarov $
 * Author: tomqq <hekong@gmail.com> 2009
 * Author: Julien LANGLOIS <julien@ulteo.com> 2009
 * Date: $Date: 2007/03/08 00:26:14 $
 *
 * Copyright (c) 2005 Propero Limited
 *
 * Purpose: Global static storage of user-definable options
 */
package net.propero.rdp;

import net.propero.rdp.rdp5.seamless.SeamlessChannel;
import java.awt.image.DirectColorModel;

public class Options {
	public static final int DIRECT_BITMAP_DECOMPRESSION = 0;
	public static final int BUFFEREDIMAGE_BITMAP_DECOMPRESSION = 1;
	public static final int INTEGER_BITMAP_DECOMPRESSION = 2;
	
	public int bitmap_decompression_store = INTEGER_BITMAP_DECOMPRESSION;

	public boolean low_latency = true; // disables bandwidth saving tcp packets
	public int keylayout = 0x809; // UK by default
	public String username = System.getProperty("user.name"); // -u username
	public String domain = ""; // -d domain
	public String password = ""; // -p password
	public String hostname = ""; // -n hostname
	public String command = "";  // -s command
	public String directory = ""; // -d directory
	public String windowTitle = "UlteoRDP"; // -T windowTitle
	public int width = 800; // -g widthxheight
	public int height = 600; // -g widthxheight
	public int port = 3389; // -t port
	public boolean fullscreen = false;
	public boolean built_in_licence = false;
	
	public boolean load_licence = false;
	public boolean save_licence = false;
	
	public String licence_path = "./";
	
	public boolean debug_keyboard = false;
	public boolean debug_hexdump = false;
	
	public boolean enable_menu = false;
	//public boolean paste_hack = true;
	
	public boolean altkey_quiet = false;
	public boolean caps_sends_up_and_down = true;
	public boolean remap_hash = true;
	public boolean useLockingKeyState = true;
	
	public boolean seamlessEnabled = false;
	public boolean soundEnabled = false;
	
	public boolean rdpdrEnabled = false;
	public boolean diskEnabled = false;
	public boolean printerEnabled = false;
	public String[] printers = null;
	
	public boolean use_rdp5 = true;
	public int server_bpp = 24;				// Bits per pixel
	public int Bpp = (server_bpp + 7) / 8;			// Bytes per pixel
	
	public int bpp_mask =  0xFFFFFF >> 8 * (3 - Bpp);	// Correction value to ensure only the relevant
								// number of bytes are used for a pixel
	
	public int imgCount = 0;
	
	
	public DirectColorModel colour_model = new DirectColorModel(24,0xFF0000,0x00FF00,0x0000FF);
	
	/**
	 * Set a new value for the server's bits per pixel
	 * @param server_bpp New bpp value
	 */
	public void set_bpp(int server_bpp_){
		this.server_bpp = server_bpp_;
		this.Bpp = (server_bpp_ + 7) / 8;
		switch (server_bpp_) {
			case 8:
				this.bpp_mask = 0xFF;
				break;
			case 15:
				this.bpp_mask = 0x7FFF;
				break;
			case 16:
				this.bpp_mask = 0xFFFF;
				break;
			case 24:
				this.bpp_mask = 0xFFFFFF;
				break;
			case 32:
				this.bpp_mask = 0xFFFFFFFF;
				break;
			default:
				System.err.println("The color depth "+ server_bpp_ +" is not available.");
				break;
		}
		this.colour_model = new DirectColorModel(24,0xFF0000,0x00FF00,0x0000FF);
	}
	
	
	public int server_rdp_version;
	
	public int win_button_size = 0;	/* If zero, disable single app mode */
	public boolean bitmap_compression = true; /* Must to be true if we use RDP v5 or later */
	public boolean persistent_bitmap_caching = false;
	public boolean bitmap_caching = true;
	public boolean precache_bitmaps = false;
	public boolean polygon_ellipse_orders = false;
	public boolean sendmotion = true;
	public boolean orders = true;
	public boolean encryption = true;
	public boolean packet_encryption = true;
	public boolean desktop_save = true;
	public boolean grab_keyboard = true;
	public boolean hide_decorations = false;
	public boolean console_session = false;
	public boolean owncolmap;
	public boolean readytosend = false;
	public boolean loggedon = false;
	
	public boolean use_ssl = false;
	public boolean map_clipboard = true;
	public int rdp5_performanceflags =   Rdp.RDP5_NO_CURSOR_SHADOW | Rdp.RDP5_NO_CURSORSETTINGS |
						Rdp.RDP5_NO_FULLWINDOWDRAG | Rdp.RDP5_NO_MENUANIMATIONS |
						/*Rdp.RDP5_NO_THEMING |*/ Rdp.RDP5_NO_WALLPAPER;
	public boolean save_graphics = false;
}
