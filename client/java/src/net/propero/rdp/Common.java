/* Common.java
 * Component: ProperJavaRDP
 * 
 * Revision: $Revision: 1.1.1.1 $
 * Author: $Author: suvarov $
 * Author: tomqq <hekong@gmail.com> 2009
 * Date: $Date: 2007/03/08 00:26:14 $
 *
 * Copyright (c) 2005 Propero Limited
 *
 * Purpose: Provide a static interface to individual network layers
 *          and UI components
 */
package net.propero.rdp;

import net.propero.rdp.rdp5.Rdp5;
import net.propero.rdp.rdp5.rdpsnd.SoundChannel;
import net.propero.rdp.rdp5.seamless.SeamlessChannel;

public class Common {

    public boolean underApplet = false;
	public Rdp5 rdp;
	public Secure secure;
	public MCS mcs;
	public RdesktopFrame frame;
	public SeamlessChannel seamlessChannelInstance = null;
	public SoundChannel soundChannel;

    /**
     * Quit the application
     */
	public void exit(){
		Rdesktop.exit(0,this.rdp,this.frame,true);
	}
}
