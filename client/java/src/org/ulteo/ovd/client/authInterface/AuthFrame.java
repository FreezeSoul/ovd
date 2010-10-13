/*
 * Copyright (C) 2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Guillaume DUPAS <guillaume@ulteo.com> 2010
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

package org.ulteo.ovd.client.authInterface;

import java.awt.Color;
import java.awt.Component;
import java.awt.Dimension;
import java.awt.GridBagConstraints;
import java.awt.GridBagLayout;
import java.awt.Image;
import java.awt.Insets;
import java.awt.KeyboardFocusManager;
import java.awt.event.ActionEvent;
import java.awt.event.ActionListener;
import java.awt.event.KeyEvent;
import java.awt.event.KeyListener;
import java.lang.reflect.InvocationTargetException;
import java.net.URL;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.CopyOnWriteArrayList;

import javax.swing.ImageIcon;
import javax.swing.JButton;
import javax.swing.JCheckBox;
import javax.swing.JComboBox;
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JPasswordField;
import javax.swing.JSlider;
import javax.swing.JTextField;
import javax.swing.event.ChangeEvent;
import javax.swing.event.ChangeListener;
import org.ulteo.Logger;
import org.ulteo.ovd.client.gui.GUIActions;
import org.ulteo.ovd.client.gui.SwingTools;

import org.ulteo.ovd.client.I18n;
import org.ulteo.ovd.client.Language;
import org.ulteo.ovd.client.desktop.DesktopFrame;

public class AuthFrame implements ActionListener, Runnable {

	private static final int JOB_NOTHING = -1;
	private static final int JOB_LOCAL_CREDENTIALS = 0;
	private static final int JOB_RESOLUTION_BAR = 1;
	private static final int JOB_OPTIONS = 2;
	private static final int JOB_START = 3;

	private List<Integer> jobsList = null;
	
	private JFrame mainFrame = new JFrame();
	private boolean desktopLaunched = false;
	
	private JLabel login = new JLabel(I18n._("Login"));
	private JLabel password = new JLabel(I18n._("Password"));
	private JLabel host = new JLabel(I18n._("Host"));
	private JTextField loginTextField = new JTextField();
	private JPasswordField passwordTextField = new JPasswordField();
	private String loginStr = null;
	
	private JTextField hostTextField = new JTextField();
	private JButton startButton = new JButton(I18n._("Start !"));
	private boolean startButtonClicked = false;
	private JButton moreOption = new JButton();
	private Image frameLogo = null;
	private ImageIcon ulteoLogo = null;
	private ImageIcon optionLogo = null;
	private ImageIcon userLogo = null;
	private ImageIcon passwordLogo = null;
	private ImageIcon hostLogo = null;
	private ImageIcon showOption = null;
	private ImageIcon hideOption = null;
	private JLabel logoLabel = new JLabel();
	private JLabel userLogoLabel = new JLabel();
	private JLabel passwordLogoLabel = new JLabel();
	private JLabel hostLogoLabel = new JLabel();
	private boolean optionClicked;
	private JLabel optionLogoLabel = new JLabel();
	private JLabel mode = new JLabel(I18n._("Mode"));
	private JLabel resolution = new JLabel(I18n._("Resolution"));
	private JLabel language = new JLabel(I18n._("Language"));
	private JLabel keyboard = new JLabel(I18n._("Keyboard"));
	private JComboBox sessionModeBox = null;
	private JComboBoxItem itemModeAuto = new JComboBoxItem(I18n._("Auto"));
	private JComboBoxItem itemModeApplication = new JComboBoxItem(I18n._("Application"));
	private JComboBoxItem itemModeDesktop = new JComboBoxItem(I18n._("Desktop"));
	private JSlider resBar = null;
	private JLabel resolutionValue = null;
	private String[] resolutionStrings = null;
	private JComboBox languageBox = new JComboBox();
	private JComboBox keyboardBox = new JComboBox();
	private JCheckBox rememberMe = new JCheckBox(I18n._("Remember me"));
	private JCheckBox autoPublish = new JCheckBox(I18n._("Auto-publish shortcuts"));
	private JCheckBox useLocalCredentials = new JCheckBox(I18n._("Use local credentials"));
	private boolean displayUserLocalCredentials = (System.getProperty("os.name").startsWith("Windows"));
	private ActionListener optionListener = null;
	
	private ActionListener obj = null;
	
	public AuthFrame(ActionListener obj_, Dimension resolution_) {
		this.obj = obj_;

		this.jobsList = new CopyOnWriteArrayList();
		
		Object[] items = new Object[3];
		items[0] = this.itemModeAuto;
		items[1] = this.itemModeApplication;
		items[2] = this.itemModeDesktop;
		this.sessionModeBox = new JComboBox(items);
		this.sessionModeBox.setRenderer(new JComboBoxItem(""));
		this.sessionModeBox.addActionListener(this);
		
		this.keyboardBox.setRenderer(new JComboBoxItem(""));
		this.initKeyboardBox();
		
		this.languageBox.setRenderer(new JComboLanguage(null, ""));
		this.initLanguageBox();
		
		this.init(resolution_);
	}
	
	public void init(Dimension resolution_) {
		this.optionClicked = false;

		this.mainFrame.setVisible(false);
		mainFrame.setTitle("OVD Native Client");
		mainFrame.setSize(500,450);
		mainFrame.setResizable(false);
		mainFrame.setBackground(Color.white);
		frameLogo = mainFrame.getToolkit().getImage(getClass().getClassLoader().getResource("pics/ulteo.png"));
		ulteoLogo = new ImageIcon(mainFrame.getToolkit().getImage(getClass().getClassLoader().getResource("pics/logo_small.png")));
		optionLogo = new ImageIcon(mainFrame.getToolkit().getImage(getClass().getClassLoader().getResource("pics/options.png")));
		userLogo = new ImageIcon(mainFrame.getToolkit().getImage(getClass().getClassLoader().getResource("pics/users.png")));
		passwordLogo = new ImageIcon(mainFrame.getToolkit().getImage(getClass().getClassLoader().getResource("pics/password.png")));
		hostLogo = new ImageIcon(mainFrame.getToolkit().getImage(getClass().getClassLoader().getResource("pics/server.png")));
		showOption = new ImageIcon(mainFrame.getToolkit().getImage(getClass().getClassLoader().getResource("pics/show.png")));
		hideOption = new ImageIcon(mainFrame.getToolkit().getImage(getClass().getClassLoader().getResource("pics/hide.png")));
		
		mainFrame.setIconImage(frameLogo);
		logoLabel.setIcon(ulteoLogo);
		userLogoLabel.setIcon(userLogo);
		passwordLogoLabel.setIcon(passwordLogo);
		hostLogoLabel.setIcon(hostLogo);
		optionLogoLabel.setIcon(optionLogo);
		
		moreOption.setIcon(showOption);
		moreOption.setText(I18n._("More options ..."));
		
		this.useLocalCredentials.addActionListener(new ActionListener() {
			@Override
			public void actionPerformed(ActionEvent e) {
				startJob(JOB_LOCAL_CREDENTIALS);
			}
		});

		this.initResolutionSlider(resolution_);
		
		optionListener = new ActionListener() {
			
			@Override
			public void actionPerformed(ActionEvent e) {
				startJob(JOB_OPTIONS);
			}
		};
		
		moreOption.addActionListener(optionListener);
		
		mainFrame.setLayout(new GridBagLayout());
		GridBagConstraints gbc = new GridBagConstraints();
		startButton.setPreferredSize(new Dimension(150, 25));
		startButton.addActionListener(this.obj);
		
		gbc.gridx = gbc.gridy = 0;
		gbc.insets = new Insets(7, 7, 25, 0);
		gbc.gridwidth = 2;
		gbc.anchor = GridBagConstraints.NORTHWEST;
		gbc.weightx = 1;
		gbc.weighty = 1;
		mainFrame.add(logoLabel, gbc);
		
		gbc.gridwidth = 1;
		gbc.anchor = GridBagConstraints.LINE_END;
		gbc.gridx = 0;
		gbc.gridy = 3;
		gbc.insets.left = 0;
		gbc.insets.top = 0;
		gbc.insets.bottom = 5;
		mainFrame.add(userLogoLabel, gbc);
		
		gbc.gridy = 4;
		mainFrame.add(passwordLogoLabel, gbc);
		
		int pos = 5;
		if (this.displayUserLocalCredentials)
			pos++;
		
		gbc.gridy = pos;
		mainFrame.add(hostLogoLabel, gbc);
		
		pos = 1;
		gbc.anchor = GridBagConstraints.LINE_START;
		gbc.insets.left = 5;
		gbc.gridx = 1;
		gbc.gridy = 3;
		mainFrame.add(login, gbc);
		
		gbc.gridy = 4;
		mainFrame.add(password, gbc);      
		
		pos = 5;
		if (this.displayUserLocalCredentials)
			pos++;
		
		gbc.gridy = pos;
		mainFrame.add(host, gbc);
		
		gbc.gridwidth = GridBagConstraints.REMAINDER;
		gbc.gridheight = GridBagConstraints.REMAINDER;
		gbc.insets.top = 25;
		gbc.gridx = 0;
		gbc.gridy = 15;
		mainFrame.add(moreOption, gbc);
		
		gbc.gridwidth = 0;
		gbc.gridheight = 1;
		gbc.insets.top = 0;
		gbc.gridx = 2;
		gbc.gridy = 3;
		gbc.insets.left = 0;
		gbc.insets.right = 15;
		gbc.weightx = 0;
		gbc.weighty = 0;
		gbc.fill = GridBagConstraints.HORIZONTAL;
		mainFrame.add(loginTextField, gbc);
		
		gbc.gridy = 4;
		mainFrame.add(passwordTextField, gbc);
		
		pos = 5;
		if (this.displayUserLocalCredentials) {
			gbc.gridy = pos++;
			mainFrame.add(this.useLocalCredentials, gbc);
		}
		
		gbc.gridy = pos++;
		mainFrame.add(hostTextField, gbc);
		
		gbc.gridy = pos++;
		gbc.anchor = GridBagConstraints.CENTER;
		mainFrame.add(rememberMe, gbc);
		
		gbc.gridx = 2;
		gbc.gridy = pos++;
		gbc.anchor = GridBagConstraints.LINE_START;
		gbc.gridwidth = 1;
		gbc.fill = GridBagConstraints.NONE;
		mainFrame.add(startButton, gbc);
		
		KeyListener keyListener = new KeyListener() {

			public synchronized void keyTyped(KeyEvent ke) {
				if ((ke.getKeyChar() == KeyEvent.VK_ENTER) && (! startButtonClicked)) {
					startJob(JOB_START);
				}
			}

			public void keyPressed(KeyEvent ke) {}
			public void keyReleased(KeyEvent ke) {}

		};
		for (Component c : this.mainFrame.getContentPane().getComponents()) {
			if (c.getClass() != JLabel.class && c != this.startButton) {
				c.addKeyListener(keyListener);
			}
		}
		
		mainFrame.pack();
		mainFrame.setLocationRelativeTo(null);
		mainFrame.setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
		this.showWindow();
	}

	private void startJob(int job) {
		this.jobsList.add(new Integer(job));
		new Thread(this).start();
	}

	private synchronized int getJob() {
		if (this.jobsList.isEmpty())
			return JOB_NOTHING;

		Integer job = this.jobsList.get(0);
		this.jobsList.remove(job);

		return job.intValue();
	}

	public void run() {
		int job = this.getJob();
		
		if (job == JOB_NOTHING)
			return;

		switch(job) {
			case JOB_LOCAL_CREDENTIALS:
				this.toggleLocalCredentials();
				break;
			case JOB_RESOLUTION_BAR:
				int value = this.resBar.getValue();

				if (value >= this.resolutionStrings.length)
					return;

				SwingTools.invokeLater(GUIActions.setLabelText(this.resolutionValue, this.resolutionStrings[value]));
				break;
			case JOB_OPTIONS:
				if (! this.optionClicked) {
					try {
						SwingTools.invokeAndWait(moreOptionsAction(this));
						this.optionClicked = true;
						this.toggleSessionMode();
					} catch (InterruptedException ex) {
						org.ulteo.Logger.error("More options components adding was interrupted: "+ex.getMessage());
					} catch (InvocationTargetException ex) {
						org.ulteo.Logger.error("Failed to add more options components: "+ex.getMessage());
					}
				} else {
					try {
						SwingTools.invokeAndWait(fewerOptionsAction(this));
						this.optionClicked = false;
					} catch (InterruptedException ex) {
						org.ulteo.Logger.error("More options components removing was interrupted: "+ex.getMessage());
					} catch (InvocationTargetException ex) {
						org.ulteo.Logger.error("Failed to remove more options components: "+ex.getMessage());
					}
				}
				break;
			case JOB_START:
				this.startButtonClicked = true;
				SwingTools.invokeLater(GUIActions.doClick(this.startButton));
				break;
		}

	}
	
	protected void toggleLocalCredentials() {
		boolean isSelected = this.useLocalCredentials.isSelected();
		if (isSelected)
			this.loginStr = this.loginTextField.getText();

		SwingTools.invokeLater(enableLocalCredentials(this, isSelected));
	}
	
	public void toggleSessionMode() {
		if (! this.optionClicked)
			return;

		ArrayList<Component> componentList = new ArrayList<Component>();
		componentList.add(this.autoPublish);
		componentList.add(this.resolutionValue);
		componentList.add(this.resolution);
		componentList.add(this.resBar);
		try {
			SwingTools.invokeAndWait(GUIActions.removeComponents(this.mainFrame, componentList));
		} catch (InterruptedException ex) {
			org.ulteo.Logger.error("Session mode components cleaner was interrupted: "+ex.getMessage());
		} catch (InvocationTargetException ex) {
			org.ulteo.Logger.error("Failed to remove session mode components: "+ex.getMessage());
		}
		componentList = null;
		
		GridBagConstraints gbc = new GridBagConstraints();
		gbc.anchor = GridBagConstraints.LINE_START;
		gbc.insets.left = 0;
		gbc.gridwidth = 1;

		componentList = new ArrayList<Component>();
		List<GridBagConstraints> gbcToAdd = new ArrayList<GridBagConstraints>();
		
		if (this.sessionModeBox.getSelectedItem() == this.itemModeApplication) {	
			gbc.gridx = 2;
			gbc.gridy = 11;
			gbc.gridwidth = 2;
			gbc.anchor = GridBagConstraints.LINE_START;
			gbc.fill = GridBagConstraints.NONE;
			componentList.add(this.autoPublish);
			gbcToAdd.add((GridBagConstraints) gbc.clone());
		}
		
		else if (this.sessionModeBox.getSelectedItem() == this.itemModeDesktop) {
			gbc.insets.left = 0;
			gbc.gridx = 1;
			gbc.gridy = 11;
			componentList.add(this.resolution);
			gbcToAdd.add((GridBagConstraints) gbc.clone());
			
			gbc.anchor = GridBagConstraints.LINE_START;
			gbc.gridx = 2;
			gbc.gridy = 11;
			gbc.gridwidth = 1;
			gbc.fill = GridBagConstraints.NONE;
			this.resBar.setSize(new Dimension(sessionModeBox.getWidth(), 33));
			this.resBar.setPreferredSize(new Dimension(sessionModeBox.getWidth(), 33));
			componentList.add(this.resBar);
			gbcToAdd.add((GridBagConstraints) gbc.clone());
			
			gbc.insets.left = 0;
			gbc.gridy = 12;
			
			gbc.anchor = GridBagConstraints.CENTER;
			componentList.add(this.resolutionValue);
			gbcToAdd.add((GridBagConstraints) gbc.clone());
		}
		SwingTools.invokeLater(GUIActions.addComponentsAndPack(this.mainFrame, componentList, gbcToAdd));
	}
	
	public void initLanguageBox() {
		int size = Language.languageList.length;
		int sysLang = 0;
		
		
		for (int i = 0; i < size; i++) {
			String local = Language.languageList[i][0]+((! Language.languageList[i][1].equals("")) ? " - "+Language.languageList[i][1] : "");
			
			ImageIcon img = null;
			
			URL imgUrl = getClass().getClassLoader().getResource("pics/flags/"+Language.languageList[i][2]+".png");
			
			if (imgUrl != null) {
				img = new ImageIcon(mainFrame.getToolkit().getImage(imgUrl));
			}
			else {
				System.err.println("Missing file: "+Language.languageList[i][2]+".png");
			}
			JComboLanguage lang = new JComboLanguage(img, local);
			
			if (Language.languageList[i][2].contains(System.getProperty("user.language")) && Language.languageList[i][2].contains("-")) {
				if (Language.languageList[i][2].equalsIgnoreCase(System.getProperty("user.language")+"-"+System.getProperty("user.country"))) {
					sysLang = i;
				}
			}
			else {
				if (Language.languageList[i][2].equals(System.getProperty("user.language"))) {
					sysLang = i;
				}
			}
			
			languageBox.addItem(lang);
		}
		
		languageBox.setSelectedIndex(sysLang);
	}
	
	public void initKeyboardBox() {
		int size = Language.keymapList.length;
		JComboBoxItem item = null;
		int sysKeymap = 0;
		
		for (int i = 0; i < size; i++) {
			item = new JComboBoxItem(Language.keymapList[i][0]);
			if (Language.keymapList[i][1].contains(System.getProperty("user.language")) && Language.keymapList[i][1].contains("-")) {
				if (Language.keymapList[i][1].equalsIgnoreCase(System.getProperty("user.language")+"-"+System.getProperty("user.country"))) {
					sysKeymap = i;
				}
			}
			else {
				if (Language.keymapList[i][1].equals(System.getProperty("user.language"))) {
					sysKeymap = i;
				}
			}
			keyboardBox.addItem(item);
		}
		
		keyboardBox.setSelectedIndex(sysKeymap);
	}

	private void initResolutionSlider(Dimension res) {
		List<Dimension> defaultRes = new ArrayList<Dimension>();
		defaultRes.add(DesktopFrame.SMALL_RES);
		defaultRes.add(DesktopFrame.MEDUIM_RES);
		defaultRes.add(DesktopFrame.HIGH_RES);

		if (res != null) {
			boolean resFound = false;
			for (Dimension d : defaultRes) {
				if (d.width == res.width && d.height == res.height)
					resFound = true;
			}
			if (! resFound)
				defaultRes.add(res);
		}

		Dimension resolutions[] = new Dimension[defaultRes.size()];
		Dimension tmp = null;

		int position = 0;
		for (int i = 0; i < resolutions.length; i++) {
			for (Dimension d : defaultRes) {
				if (resolutions[i] == null || d.width < resolutions[i].width || (d.width == resolutions[i].width && d.height < resolutions[i].height)) {
					tmp = d;
					resolutions[i] = d;
				}
			}

			if (resolutions[i] == null) {
				Logger.error("resolutions["+i+"] is null: it should never appear");
			}

			if (resolutions[i] == res)
				position = i;

			defaultRes.remove(tmp);
		}
		defaultRes.clear();
		defaultRes = null;

		int sliderLength = resolutions.length + 2; // resolutions.length + 2 (Maximized + Fullscreen)

		this.resBar = new JSlider(JSlider.HORIZONTAL, 0, sliderLength - 1, position);
		this.resBar.setMajorTickSpacing(1);
		this.resBar.setPaintTicks(true);
		this.resBar.setSnapToTicks(true);
		this.resBar.addChangeListener(new ChangeListener() {

			@Override
			public void stateChanged(ChangeEvent ce) {
				startJob(JOB_RESOLUTION_BAR);
			}
		});

		this.resolutionStrings = new String[sliderLength];
		for (int i = 0; i < resolutions.length; i++) {
			this.resolutionStrings[i] = resolutions[i].width+"x"+resolutions[i].height;
		}
		this.resolutionStrings[sliderLength - 2] = I18n._("Maximized");
		this.resolutionStrings[sliderLength - 1] = I18n._("Fullscreen");

		this.resolutionValue = new JLabel(this.resolutionStrings[position]);
	}
	
	public void showWindow() {
		KeyboardFocusManager.setCurrentKeyboardFocusManager(null);
		this.startButtonClicked = false;
		this.toggleLocalCredentials();
		SwingTools.invokeLater(GUIActions.setVisible(this.mainFrame, true));
	}
	
	@Override
	public void actionPerformed(ActionEvent ev) {
		if (ev.getSource() == this.sessionModeBox)
			this.toggleSessionMode();
	}
	
	public void hideWindow() {
		SwingTools.invokeLater(GUIActions.setVisible(this.mainFrame, false));
	}
	
	public JTextField getLogin() {
		return loginTextField;
	}

	public void setLogin(String login_) {
		if (login_ == null)
			return;

		SwingTools.invokeLater(GUIActions.customizeTextComponent(this.loginTextField, login_));
	}

	public JPasswordField getPassword() {
		return passwordTextField;
	}

	public JTextField getHost() {
		return hostTextField;
	}

	public void setHost(String host_) {
		if (host_ == null)
			return;
		
		SwingTools.invokeLater(GUIActions.customizeTextComponent(this.hostTextField, host_));
	}

	public Dimension getResolution() {
		int position = this.resBar.getValue();

		
		if (position < 0) {
			return null;
		}
		if (position >= this.resolutionStrings.length + 1) {
			return DesktopFrame.FULLSCREEN;
		}
		if (position == this.resolutionStrings.length) {
			return DesktopFrame.MAXIMISED;
		}

		int p = this.resolutionStrings[position].indexOf("x");

		if (this.resolutionStrings[position].lastIndexOf("x") != p)
			return null;

		Dimension resolution = null;
		try {
			resolution = new Dimension();
			resolution.width = Integer.parseInt(this.resolutionStrings[position].substring(0, p));
			resolution.height = Integer.parseInt(this.resolutionStrings[position].substring(p + 1, this.resolutionStrings[position].length()));
		} catch (NumberFormatException ex) {
			Logger.error("Failed to parse '"+this.resolutionStrings[position]+"': "+ex.getMessage());
			resolution = null;
		}

		return resolution;
	}

	public void setResolution(int resolution_) {
		this.resBar.setValue(resolution_);
		SwingTools.invokeLater(GUIActions.customizeSlider(this.resBar, resolution_));
	}

	public JComboBox getSessionModeBox() {
		return this.sessionModeBox;
	}
	public JLabel getItemModeApplication() {
		return this.itemModeApplication;
	}
	public JLabel getItemModeAuto() {
		return this.itemModeAuto;
	}
	public JLabel getItemModeDesktop() {
		return this.itemModeDesktop;
	}
	
	public JFrame getMainFrame() {
		return mainFrame;
	}
	
	public boolean isDesktopLaunched() {
		return desktopLaunched;
	}
	
	public void setDesktopLaunched(boolean desktopLaunched) {
		this.desktopLaunched = desktopLaunched;
	}
	
	public boolean isRememberMeChecked() {
		return this.rememberMe.isSelected();
	}

	public void setRememberMeChecked(boolean checked_) {
		SwingTools.invokeLater(GUIActions.setBoxChecked(this.rememberMe, checked_));
	}
	
	public boolean isAutoPublishChecked() {
		return this.autoPublish.isSelected();
	}

	public void setAutoPublishChecked(boolean autoPublish_) {
		this.autoPublish.setSelected(autoPublish_);
		SwingTools.invokeLater(GUIActions.setBoxChecked(this.autoPublish, autoPublish_));
	}
	
	public void setUseLocalCredentials(boolean useLocalCredentials_) {
		this.useLocalCredentials.setSelected(useLocalCredentials_);
		SwingTools.invokeLater(GUIActions.setBoxChecked(this.useLocalCredentials, useLocalCredentials_));
	}
	
	public boolean isUseLocalCredentials() {
		return this.useLocalCredentials.isSelected();
	}
	
	public JButton getOptionButton() {
		return moreOption;
	}
	
	public JButton GetStartButton() {
		return this.startButton;
	}
	
	public JComboBox getLanguageBox() {
		return this.languageBox;
	}
	
	public JComboBox getKeyboardBox() {
		return this.keyboardBox;
	}

	/* MoreOptionsAction */
	public static Runnable moreOptionsAction(AuthFrame authFrame_) {
		return authFrame_.new MoreOptionsAction(authFrame_);

	}

	private class MoreOptionsAction implements Runnable {
		private JFrame wnd = null;
		private List<Component> components = null;
		private List<GridBagConstraints> gbcs = null;
		private JButton button = null;
		private ImageIcon img = null;
		private String buttonLabel = null;
		
		public MoreOptionsAction(AuthFrame authFrame_) {
			this.wnd = authFrame_.mainFrame;
			this.button = authFrame_.moreOption;
			this.img = authFrame_.hideOption;
			this.buttonLabel = I18n._("Fewer options");

			this.init(authFrame_);
		}

		private void init(AuthFrame authFrame_) {
			GridBagConstraints constraints = new GridBagConstraints();
			this.components = new ArrayList<Component>();
			this.gbcs = new ArrayList<GridBagConstraints>();

			constraints.anchor = GridBagConstraints.CENTER;
			constraints.gridwidth = 2;
			constraints.weightx = 1;
			constraints.weighty = 1;
			constraints.gridx = 0;
			constraints.gridy = 9;
			constraints.insets.top = 30;
			this.components.add(authFrame_.optionLogoLabel);
			this.gbcs.add((GridBagConstraints) constraints.clone());

			constraints.anchor = GridBagConstraints.LINE_START;
			constraints.weightx = 0;
			constraints.weighty = 0;
			constraints.gridwidth = 1;
			constraints.insets.top = 5;
			constraints.insets.left = 0;
			constraints.gridx = 1;
			constraints.gridy = 10;
			this.components.add(authFrame_.mode);
			this.gbcs.add((GridBagConstraints) constraints.clone());

			constraints.gridy = 13;
			this.components.add(language);
			this.gbcs.add((GridBagConstraints) constraints.clone());

			constraints.gridy = 14;
			this.components.add(keyboard);
			this.gbcs.add((GridBagConstraints) constraints.clone());

			constraints.gridwidth = 2;
			constraints.gridx = 2;
			constraints.gridy = 10;
			constraints.fill = GridBagConstraints.HORIZONTAL;
			constraints.insets.right = 15;
			this.components.add(authFrame_.sessionModeBox);
			this.gbcs.add((GridBagConstraints) constraints.clone());

			constraints.gridx = 2;
			constraints.gridwidth = 2;
			constraints.gridy = 13;
			constraints.fill = GridBagConstraints.HORIZONTAL;
			this.components.add(languageBox);
			this.gbcs.add((GridBagConstraints) constraints.clone());

			constraints.gridy = 14;
			this.components.add(keyboardBox);
			this.gbcs.add((GridBagConstraints) constraints.clone());
		}

		public void run() {
			GUIActions.addComponents(this.wnd, this.components, this.gbcs).run();
			GUIActions.customizeButton(this.button, this.img, this.buttonLabel).run();
			GUIActions.packWindow(this.wnd).run();
		}
	}

	/* FewerOptionsAction */
	public static Runnable fewerOptionsAction(AuthFrame authFrame_) {
		return authFrame_.new FewerOptionsAction(authFrame_);
	}

	private class FewerOptionsAction implements Runnable {
		private JFrame wnd = null;
		private List<Component> components = null;
		private JButton button = null;
		private ImageIcon img = null;
		private String buttonLabel = null;

		public FewerOptionsAction(AuthFrame authFrame_) {
			this.wnd = authFrame_.mainFrame;
			this.button = authFrame_.moreOption;
			this.img = authFrame_.showOption;
			this.buttonLabel = I18n._("More options ...");

			this.init(authFrame_);
		}

		private void init(AuthFrame authFrame_) {
			this.components = new ArrayList<Component>();

			this.components.add(authFrame_.optionLogoLabel);
			this.components.add(authFrame_.mode);
			this.components.add(authFrame_.resolution);
			this.components.add(authFrame_.language);
			this.components.add(authFrame_.keyboard);
			this.components.add(authFrame_.sessionModeBox);
			this.components.add(authFrame_.resBar);
			this.components.add(authFrame_.resolutionValue);
			this.components.add(authFrame_.languageBox);
			this.components.add(authFrame_.keyboardBox);
			this.components.add(authFrame_.autoPublish);
		}

		public void run() {
			GUIActions.removeComponents(this.wnd, this.components).run();
			GUIActions.customizeButton(this.button, this.img, this.buttonLabel).run();
			GUIActions.packWindow(this.wnd).run();
		}
	}

	/* EnableLocalCredentials */
	public static Runnable enableLocalCredentials(AuthFrame authFrame_, boolean enabled_) {
		return authFrame_.new EnableLocalCredentials(authFrame_, enabled_);
	}

	private class EnableLocalCredentials implements Runnable {
		private JTextField loginField = null;
		private String username = null;

		private List<Component> components = null;
		private boolean enabled;
		
		public EnableLocalCredentials(AuthFrame authFrame_, boolean enabled_) {
			this.loginField = authFrame_.loginTextField;
			this.username = authFrame_.loginStr;
			this.enabled = enabled_;

			this.init(authFrame_);
		}

		private void init(AuthFrame authFrame_) {
			this.components = new ArrayList<Component>();

			this.components.add(authFrame_.loginTextField);
			this.components.add(authFrame_.login);
			this.components.add(authFrame_.userLogoLabel);

			this.components.add(authFrame_.passwordTextField);
			this.components.add(authFrame_.password);
			this.components.add(authFrame_.passwordLogoLabel);
		}

		public void run() {
			GUIActions.customizeTextComponent(this.loginField, (this.enabled) ? System.getProperty("user.name") : ((this.username != null) ? this.username : this.loginField.getText())).run();
			GUIActions.setEnabledComponents(this.components, ! this.enabled).run();
		}

	}
}