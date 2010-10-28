/*
 * Copyright (C) 2010 Ulteo SAS
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

package org.ulteo.utils.jni;

import java.awt.GraphicsEnvironment;
import java.awt.Rectangle;
import org.ulteo.Logger;
import org.ulteo.ovd.integrated.OSTools;

public class WorkArea {
	private static boolean loadLibrary = true;

	public static void disableLibraryLoading() {
		WorkArea.loadLibrary = false;
	}

	public static Rectangle getWorkAreaSize() {
		if (WorkArea.loadLibrary && OSTools.isLinux()) {
			try {
				int[] area = getWorkAreaSizeForX();
				if (area.length == 4)
					return new Rectangle(area[0], area[1], area[2], area[3]);
			} catch (UnsatisfiedLinkError e) {
				Logger.error("Failed to execute method: "+e.getMessage());
			}

			Logger.error("Failed to get the client workarea.");
		}

		return GraphicsEnvironment.getLocalGraphicsEnvironment().getMaximumWindowBounds();
	}

	private static native int[] getWorkAreaSizeForX();
}
