<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2009 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Module: User configuration
 *
 * This module lets users viev and change their individual settings
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 * Revised for TYPO3 3.7 6/2004 by Kasper Skaarhoj
 * XHTML compatible.
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   86: class SC_mod_user_setup_index
 *
 *              SECTION: Saving data
 *  114:     function storeIncomingData()
 *
 *              SECTION: Rendering module
 *  216:     function init()
 *  248:     function main()
 *  403:     function printContent()
 *
 *              SECTION: Helper functions
 *  432:     function getRealScriptUserObj()
 *  442:     function simulateUser()
 *  488:     function setLabel($str,$key='')
 *
 * TOTAL FUNCTIONS: 7
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

unset($MCONF);
require('conf.php');
require($BACK_PATH.'init.php');
require_once(PATH_t3lib.'class.t3lib_tcemain.php');
require_once(PATH_t3lib.'class.t3lib_loadmodules.php');














/**
 * Script class for the Setup module
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage tx_setup
 */
class SC_mod_user_setup_index {

		// Internal variables:
	var $MCONF = array();
	var $MOD_MENU = array();
	var $MOD_SETTINGS = array();

	/**
	 * document template object
	 *
	 * @var mediumDoc
	 */
	var $doc;

	var $content;
	var $overrideConf;

	/**
	 * backend user object, set during simulate-user operation
	 *
	 * @var t3lib_beUserAuth
	 */
	var $OLD_BE_USER;
	var $languageUpdate;





	/******************************
	 *
	 * Saving data
	 *
	 ******************************/

	/**
	 * If settings are submitted to _POST[DATA], store them
	 * NOTICE: This method is called before the template.php is included. See buttom of document
	 *
	 * @return	void
	 */
	function storeIncomingData()	{
		global $BE_USER;


			// First check if something is submittet in the data-array from POST vars
		$d = t3lib_div::_POST('data');
		if (is_array($d))	{

				// UC hashed before applying changes
			$save_before = md5(serialize($BE_USER->uc));

				// PUT SETTINGS into the ->uc array:

				// reload left frame when switching BE language
			if (isset($d['lang']) && ($d['lang'] != $BE_USER->uc['lang'])) {
				$this->languageUpdate = true;
			}
				// Language
			$BE_USER->uc['lang'] = $d['lang'];

				// Startup
			$BE_USER->uc['condensedMode'] = $d['condensedMode'];
			$BE_USER->uc['noMenuMode'] = $d['noMenuMode'];
			$BE_USER->uc['startModule'] = $d['startModule'];
			$BE_USER->uc['thumbnailsByDefault'] = $d['thumbnailsByDefault'];
			$BE_USER->uc['helpText'] = $d['helpText'];
			$BE_USER->uc['titleLen'] = intval($d['titleLen']);

				// Advanced functions:
			$BE_USER->uc['copyLevels'] = t3lib_div::intInRange($d['copyLevels'],0,100);
			$BE_USER->uc['recursiveDelete'] = $d['recursiveDelete'];

				// Edit
			$BE_USER->uc['edit_wideDocument'] = $d['edit_wideDocument'];
			if ($GLOBALS['TYPO3_CONF_VARS']['BE']['RTEenabled'])	{ $BE_USER->uc['edit_RTE'] = $d['edit_RTE']; }
			$BE_USER->uc['edit_docModuleUpload'] = $d['edit_docModuleUpload'];
			$BE_USER->uc['enableFlashUploader'] = $d['enableFlashUploader'];
			$BE_USER->uc['edit_showFieldHelp'] = $d['edit_showFieldHelp'];
			$BE_USER->uc['disableCMlayers'] = $d['disableCMlayers'];

				// Personal:
			$BE_USER->uc['emailMeAtLogin'] = $d['emailMeAtLogin'];


			if ($d['setValuesToDefault'])	{	// If every value should be default
				$BE_USER->resetUC();
			}
			$BE_USER->overrideUC();	// Inserts the overriding values.

			$save_after = md5(serialize($BE_USER->uc));
			if ($save_before!=$save_after)	{	// If something in the uc-array of the user has changed, we save the array...
				$BE_USER->writeUC($BE_USER->uc);
				$BE_USER->writelog(254,1,0,1,'Personal settings changed',Array());
			}


				// Personal data for the users be_user-record (email, name, password...)
				// If email and name is changed, set it in the users record:
			$be_user_data = t3lib_div::_GP('ext_beuser');
			$this->PASSWORD_UPDATED = strlen($be_user_data['password1'].$be_user_data['password2'])>0 ? -1 : 0;
			if ($be_user_data['email']!=$BE_USER->user['email']
					|| $be_user_data['realName']!=$BE_USER->user['realName']
					|| (strlen($be_user_data['password1'])==32
							&& !strcmp($be_user_data['password1'],$be_user_data['password2']))
					)	{
				$storeRec = array();
				$BE_USER->user['realName'] = $storeRec['be_users'][$BE_USER->user['uid']]['realName'] = substr($be_user_data['realName'],0,80);
				$BE_USER->user['email'] = $storeRec['be_users'][$BE_USER->user['uid']]['email'] = substr($be_user_data['email'],0,80);
				if (strlen($be_user_data['password1'])==32 && !strcmp($be_user_data['password1'],$be_user_data['password2']))	{
					$BE_USER->user['password'] = $storeRec['be_users'][$BE_USER->user['uid']]['password'] = $be_user_data['password1'];
					$this->PASSWORD_UPDATED = 1;
				}

					// Make instance of TCE for storing the changes.
				$tce = t3lib_div::makeInstance('t3lib_TCEmain');
				$tce->stripslashes_values=0;
				$tce->start($storeRec,Array(),$BE_USER);
				$tce->admin = 1;	// This is so the user can actually update his user record.
				$tce->bypassWorkspaceRestrictions = TRUE;	// This is to make sure that the users record can be updated even if in another workspace. This is tolerated.
				$tce->process_datamap();
				unset($tce);
			}
		}
	}












	/******************************
	 *
	 * Rendering module
	 *
	 ******************************/

	/**
	 * Initializes the module for display of the settings form.
	 *
	 * @return	void
	 */
	function init()	{
		$this->MCONF = $GLOBALS['MCONF'];

			// Returns the script user - that is the REAL logged in user! ($GLOBALS[BE_USER] might be another user due to simulation!)
		$scriptUser = $this->getRealScriptUserObj();
			// ... and checking module access for the logged in user.
		$scriptUser->modAccess($this->MCONF, 1);

			// Getting the 'override' values as set might be set in User TSconfig
		$this->overrideConf = $GLOBALS['BE_USER']->getTSConfigProp('setup.override');

			// Create instance of object for output of data
		$this->doc = t3lib_div::makeInstance('template');
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->setModuleTemplate('templates/setup.html');
		$this->doc->JScodeLibArray['dyntabmenu'] = $this->doc->getDynTabMenuJScode();
		$this->doc->form = '<form action="index.php" method="post" name="usersetup" enctype="application/x-www-form-urlencoded">';
		$this->doc->tableLayout = array(
			'defRow' => array(
				'0' => array('<td class="td-label">','</td>'),
				'defCol' => array('<td valign="top">','</td>')
			)
		);
		$this->doc->table_TR = '<tr>';
		$this->doc->table_TABLE = '<table border="0" cellspacing="1" cellpadding="2" class="typo3-usersettings">';
	}

	/**
	 * Generate the main settings formular:
	 *
	 * @return	void
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TBE_MODULES;

		if ($this->languageUpdate) {
			$this->doc->JScodeArray['languageUpdate'] .=  '
				if (top.refreshMenu) {
					top.refreshMenu();
				} else {
					top.TYPO3ModuleMenu.refreshMenu();
				}

				if (top.shortcutFrame) {
					top.shortcutFrame.refreshShortcuts();
				}
			';
		}

			// Start page:
		$this->doc->loadJavascriptLib('md5.js');

			// use a wrapper div
		$this->content .= '<div id="user-setup-wrapper">';

			// Load available backend modules
		$this->loadModules = t3lib_div::makeInstance('t3lib_loadModules');
		$this->loadModules->observeWorkspaces = true;
		$this->loadModules->load($TBE_MODULES);

		$this->content .= $this->doc->header($LANG->getLL('UserSettings').' - '.$BE_USER->user['realName'].' ['.$BE_USER->user['username'].']');

			// If password is updated, output whether it failed or was OK.
		if ($this->PASSWORD_UPDATED) {
			if ($this->PASSWORD_UPDATED > 0) {
				$this->content .= $this->doc->section($LANG->getLL('newPassword').':',$LANG->getLL('newPassword_ok'),1,0,1);
			} else {
				$this->content .= $this->doc->section($LANG->getLL('newPassword').':',$LANG->getLL('newPassword_failed'),1,0,2);
			}
		}

			// compile the menu item data
		$data = $this->compileUserSetupData();

			// render the menu items
		$menuItems = $this->renderUserSetupTabs($data);

		$this->content .= $this->doc->spacer(20) . $this->doc->getDynTabMenu($menuItems, 'user-setup', false, false, 100);


			// Submit and reset buttons
		$this->content .= $this->doc->spacer(20);
		$this->content .= $this->doc->section('',
			t3lib_BEfunc::cshItem('_MOD_user_setup', 'reset', $BACK_PATH) . '
			<input type="hidden" name="simUser" value="'.$this->simUser.'" />
			<input type="submit" name="submit" value="'.$LANG->getLL('save').'" />
			<input type="submit" name="data[setValuesToDefault]" value="'.$LANG->getLL('setToStandard').'" onclick="return confirm(\''.$LANG->getLL('setToStandardQuestion').'\');" />'
		);

			// Notice
		$this->content .= $this->doc->spacer(30);
		$this->content .= $this->doc->section('', $LANG->getLL('activateChanges'));

			// Setting up the buttons and markers for docheader
		$docHeaderButtons = $this->getButtons();
		$markers['CSH'] = $docHeaderButtons['csh'];
		$markers['CONTENT'] = $this->content;

			// Build the <body> for the module
		$this->content = $this->doc->startPage($LANG->getLL('UserSettings'));
		$this->content.= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
		$this->content.= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);

			// end of wrapper div
		$this->content .= '</div>';
	}

	/**
	 * Prints the content / ends page
	 *
	 * @return	void
	 */
	function printContent()	{
		echo $this->content;
	}

	/**
	 * Create the panel of buttons for submitting the form or otherwise perform operations.
	 *
	 * @return	array	all available buttons as an assoc. array
	 */
	protected function getButtons()	{
		$buttons = array(
			'csh' => '',
			'save' => '',
			'shortcut' => '',
		);

		$buttons['csh'] = t3lib_BEfunc::cshItem('_MOD_user_setup', '', $GLOBALS['BACK_PATH'], '|', true);

		if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
			$buttons['shortcut'] = $this->doc->makeShortcutIcon('','',$this->MCONF['name']);
		}

		return $buttons;
	}










	/******************************
	 *
	 * Helper functions
	 *
	 ******************************/

	/**
	 * Returns the backend user object, either the global OR the $this->OLD_BE_USER which is set during simulate-user operation.
	 * Anyway: The REAL user is returned - the one logged in.
	 *
	 * @return	object		The REAL user is returned - the one logged in.
	 */
	function getRealScriptUserObj()	{
		return is_object($this->OLD_BE_USER) ? $this->OLD_BE_USER : $GLOBALS['BE_USER'];
	}

	/**
	 * adds every field and every tab that will be used in a multi-dimensional array to be
	 * displayed in this module
	 *
	 * @return	array	the full data array that is used for rendering the tabs
	 */
	function compileUserSetupData() {
		global $BE_USER, $LANG;
		$data = array();

			// Tab: Personal data
			// compile the languages dropdown
		$languageOptions = array(
			'000000000' => chr(10) . '<option value="">' . $LANG->getLL('lang_default', 1) . '</option>'
		);
			// traverse the number of languages
		$theLanguages = t3lib_div::trimExplode('|', TYPO3_languages);
		foreach ($theLanguages as $language) {
			if ($language != 'default') {
				$languageValue = $GLOBALS['LOCAL_LANG']['default']['lang_' . $language];
				$localLabel = '  -  ['.htmlspecialchars($languageValue) . ']';
				$unavailable = (is_dir(PATH_typo3conf . 'l10n/' . $language) ? false : true);
				if (!$unavailable) {
					$languageOptions[$languageValue . '--' . $language] = '
					<option value="'.$language.'"'.($BE_USER->uc['lang'] == $language ? ' selected="selected"' : '') . ($unavailable ? ' class="c-na"' : '').'>'.$LANG->getLL('lang_' . $language, 1) . $localLabel . '</option>';
				}
			}
		}
		ksort($languageOptions);
		$languageCode = '
				<select id="field_language" name="data[lang]">' .
					implode('', $languageOptions) . '
				</select>';
		if ($BE_USER->uc['lang'] && !@is_dir(PATH_typo3conf . 'l10n/' . $BE_USER->uc['lang'])) {
			$languageCode .= '<table border="0" cellpadding="0" cellspacing="0" class="warningbox"><tr><td>'.
					$this->doc->icons(3) .
					'The selected language is not available before the language pack is installed.<br />'.
					($BE_USER->isAdmin() ? 'You can use the Extension Manager to easily download and install new language packs.':'Please ask your system administrator to do this.') .
					'</td></tr></table>';
		}


		// the data for the user configuration (uc),
		$data['personal_data'] = array(
			'ctrl' => array(
				'label' => $LANG->getLL('personal_data'),
			),
			'columns' => array(
				'beUser_realName' => array(
					'type' => 'text',
					'form' => '<input id="field_beUser_realName" type="text" name="ext_beuser[realName]" value="'.htmlspecialchars($BE_USER->user['realName']).'"'.$GLOBALS['TBE_TEMPLATE']->formWidth(20).' />',
				),
				'beUser_email' => array(
					'type' => 'text',
					'form' => '<input id="field_beUser_email" type="text" name="ext_beuser[email]" value="'.htmlspecialchars($BE_USER->user['email']).'"'.$GLOBALS['TBE_TEMPLATE']->formWidth(20).' />',
				),
				'emailMeAtLogin' => array(
					'type' => 'check',
					'form' => '<input id="field_emailMeAtLogin" type="checkbox" name="data[emailMeAtLogin]"'.($BE_USER->uc['emailMeAtLogin']?' checked="checked"':'').' />',
				),
				'newPassword' => array(
					'type' => 'password',
					'form' => '<input id="field_newPassword" type="password" name="ext_beuser[password1]" value="" '.$GLOBALS['TBE_TEMPLATE']->formWidth(20).' onchange="this.value=this.value?MD5(this.value):\'\';" />',
				),
				'newPasswordAgain' => array(
					'type' => 'password',
					'form' => '<input id="field_newPasswordAgain" type="password" name="ext_beuser[password2]" value="" '.$GLOBALS['TBE_TEMPLATE']->formWidth(20).' onchange="this.value=this.value?MD5(this.value):\'\'" />',
				),
				'language' => array(
					'type' => 'select',
					'form' => $languageCode,
				),
			)
		);


			// Tab: compiling the 'Startup' section
			// start module select
		if (empty($BE_USER->uc['startModule']))	{
			$BE_USER->uc['startModule'] = $BE_USER->uc_default['startModule'];
		}
		$startModuleSelect .= '<option value=""></option>';
		foreach ($this->loadModules->modules as $mainMod => $modData) {
			if (isset($modData['sub']) && is_array($modData['sub'])) {
				$startModuleSelect .= '<option disabled="disabled">'.$LANG->moduleLabels['tabs'][$mainMod.'_tab'].'</option>';
				foreach ($modData['sub'] as $subKey => $subData) {
					$modName = $subData['name'];
					$startModuleSelect .= '<option value="'.$modName.'"'.($BE_USER->uc['startModule']==$modName?' selected="selected"':'').'>';
					$startModuleSelect .= ' - '.$LANG->moduleLabels['tabs'][$modName.'_tab'].'</option>';
				}
			}
		}
		$startModuleSelect = '<select id="field_startModule" name="data[startModule]">' . $startModuleSelect . '</select>';

		$data['startup'] = array(
			'ctrl' => array(
				'label' => $LANG->getLL('opening'),
			),
			'columns' => array(
				'condensedMode' => array(
					'type' => 'checkbox',
					'form' => '<input id="field_condensedMode" type="checkbox" name="data[condensedMode]"'.($BE_USER->uc['condensedMode']?' checked="checked"':'').' />',
					'label' => 'condensedMode'
				),
				'noMenuMode' => array(
					'type' => 'select',
					'form' => '<select id="field_noMenuMode" name="data[noMenuMode]">
					<option value=""'.(!$BE_USER->uc['noMenuMode']?' selected="selected"':'').'>'.$LANG->getLL('noMenuMode_def').'</option>
					<option value="1"'.($BE_USER->uc['noMenuMode'] && (string)$BE_USER->uc['noMenuMode']!="icons"?' selected="selected"':'').'>'.$LANG->getLL('noMenuMode_sel').'</option>
					<option value="icons"'.((string)$BE_USER->uc['noMenuMode']=='icons'?' selected="selected"':'').'>'.$LANG->getLL('noMenuMode_icons').'</option>
				</select>',
					'labelkey' => 'noMenuMode'
				),
				'startModule' => array(
					'type' => 'select',
					'form' => $startModuleSelect,
					'labelkey' => 'startModule'
				),
				'showThumbs' => array(
					'type' => 'check',
					'form' => '<input id="field_showThumbs" type="checkbox" name="data[thumbnailsByDefault]"'.($BE_USER->uc['thumbnailsByDefault']?' checked="checked"':'').' />',
					'labelkey' => 'thumbnailsByDefault',
				),
				'helpText' => array(
					'type' => 'check',
					'form' => '<input id="field_helpText" type="checkbox" name="data[helpText]"'.($BE_USER->uc['helpText']?' checked="checked"':'').' />',
				),
				'edit_showFieldHelp' => array(
					'type' => 'select',
					'form' => '<select id="field_edit_showFieldHelp" name="data[edit_showFieldHelp]">
				<option value="">'.$LANG->getLL('edit_showFieldHelp_none').'</option>
				<option value="icon"'.($BE_USER->uc['edit_showFieldHelp']=='icon'?' selected="selected"':'').'>'.$LANG->getLL('edit_showFieldHelp_icon').'</option>
				<option value="text"'.($BE_USER->uc['edit_showFieldHelp']=='text'?' selected="selected"':'').'>'.$LANG->getLL('edit_showFieldHelp_message').'</option>
			</select>',
				),
				'maxTitleLen' => array(
					'type' => 'text',
					'form' => '<input id="field_maxTitleLen" type="text" name="data[titleLen]" value="'.$BE_USER->uc['titleLen'].'"'.$GLOBALS['TBE_TEMPLATE']->formWidth(5).' maxlength="5" />',
					'labelkey' => 'titleLen',
				),
			)
		);

		if ($GLOBALS['BE_USER']->uc['interfaceSetup'] != 'backend_old') {
			unset($data['startup']['columns']['noMenuMode']);
		}


			// Tab: Edit section & advanced options
		$data['edit'] = array(
			'ctrl' => array(
				'label' => $LANG->getLL('edit_functions') . ' & ' . $LANG->getLL('functions'),
			),
			'columns' => array(
				'edit_RTE' => array(
					'type' => 'check',
					'form' => '<input id="field_edit_RTE" type="checkbox" name="data[edit_RTE]"'.($BE_USER->uc['edit_RTE']?' checked="checked"':'').' />',
				),
				'edit_docModuleUpload' => array(
					'type' => 'check',
					'form' => '<input id="field_edit_docModuleUpload" type="checkbox" name="data[edit_docModuleUpload]"'.($BE_USER->uc['edit_docModuleUpload']?' checked="checked"':'').' />'
				),
				'enableFlashUploader' => array(
					'type' => 'check',
					'form' => '<input id="field_enableFlashUploader" type="checkbox" name="data[enableFlashUploader]"' . ($BE_USER->uc['enableFlashUploader'] ? ' checked="checked"' : '') . ' />'
				),
				'disableCMlayers' => array(
					'type' => 'check',
					'form' => '<input id="field_disableCMlayers" type="checkbox" name="data[disableCMlayers]"'.($BE_USER->uc['disableCMlayers']?' checked="checked"':'').' />'
				),
				'copyLevels' => array(
					'type' => 'text',
					'form' => '<input id="field_copyLevels" type="text" name="data[copyLevels]" value="'.$BE_USER->uc['copyLevels'].'"'.$GLOBALS['TBE_TEMPLATE']->formWidth(5).' maxlength="5" />&nbsp;'.$LANG->getLL('levels')
				),
				'recursiveDelete' => array(
					'type' => 'check',
					'form' => '<input id="field_recursiveDelete" type="checkbox" name="data[recursiveDelete]"'.($BE_USER->uc['recursiveDelete']?' checked="checked"':'').' />',
				),
			)
		);

		if (!$GLOBALS['TYPO3_CONF_VARS']['BE']['RTEenabled']) {
			unset($data['edit']['columns']['edit_RTE']);
		}


			// Tab: Admin functions
		if ($BE_USER->isAdmin()) {
			$data['admin'] = array(
				'ctrl' => array(
					'label' => $LANG->getLL('adminFunctions')
				),
				'columns' => array()
			);
				// Simulate selector box:
			if ($this->simulateSelector) {
				$data['admin']['columns']['simulate'] = array(
					'type' => 'select',
					'form' => $this->simulateSelector,
					'csh'  => 'simuser'
				);
			}
		}

		return $data;
	}

	/**
	 * renders the data for one tab in the user setup and returns
	 * everything that is needed to have a complete tab
	 * for the dyntab menu
	 * @param	$data	a multi-dimensional array that will be converted to table contents
	 * @param	$tabLabel	the label that is put on top of the tab array
	 * @return	ready to use for the dyntabmenu itemarray
	 */
	function renderUserSetupTabs($data) {
		$result = array();
		foreach ($data as $k => $tabData) {
			$code = array();
			$i = 0;

				// "display full help" is active?
			$displayFullText = ($GLOBALS['BE_USER']->uc['edit_showFieldHelp'] == 'text');
			if ($displayFullText) {
				$this->doc->tableLayout['defRowEven'] = array('defCol' => array ('<td valign="top" colspan="3">','</td>'));
			}

			foreach ($tabData['columns'] as $fieldname => $data) {
				$csh = $this->getCSH($data['csh'] ? $data['csh'] : $fieldname);
					// add another table row with the full text help if needed
				if ($displayFullText) {
					$code[$i++][1] = $csh;
				}

				if ($displayFullText) {
					$code[$i][1] = $this->setLabel($fieldname, ($data['labelkey'] ? $data['labelkey'] : ''));
				} else {
					$code[$i][1] = $csh . $this->setLabel($fieldname, ($data['labelkey'] ? $data['labelkey'] : ''));
				}
				$code[$i++][2]   = $data['form'];
			}

			if (count($code)) {
				$result[] = array(
					'label'   => $tabData['ctrl']['label'],
					'content' => $this->doc->spacer(20) . $this->doc->table($code)
				);
			}
		}
		return $result;
	}

	/**
	 * Will make the simulate-user selector if the logged in user is administrator.
	 * It will also set the GLOBAL(!) BE_USER to the simulated user selected if any (and set $this->OLD_BE_USER to logged in user)
	 *
	 * @return	void
	 */
	function simulateUser()	{
		global $BE_USER,$LANG,$BACK_PATH;

		// *******************************************************************************
		// If admin, allow simulation of another user
		// *******************************************************************************
		$this->simUser = 0;
		$this->simulateSelector = '';
		unset($this->OLD_BE_USER);
		if ($BE_USER->isAdmin())	{
			$this->simUser = t3lib_div::_GP('simUser');

				// Make user-selector:
			$users = t3lib_BEfunc::getUserNames('username,usergroup,usergroup_cached_list,uid,realName', t3lib_BEfunc::BEenableFields('be_users'));
			$opt = array();
			foreach ($users as $rr) {
				if ($rr['uid'] != $BE_USER->user['uid']) {
					$opt[] = '<option value="'.$rr['uid'].'"'.($this->simUser==$rr['uid']?' selected="selected"':'').'>'.htmlspecialchars($rr['username'].' ('.$rr['realName'].')').'</option>';
				}
			}
			if (count($opt)) {
				$this->simulateSelector = '<select id="field_simulate" name="simulateUser" onchange="window.location.href=\'index.php?simUser=\'+this.options[this.selectedIndex].value;"><option></option>'.implode('',$opt).'</select>';
			}
		}

		if ($this->simUser>0)	{	// This can only be set if the previous code was executed.
			$this->OLD_BE_USER = $BE_USER;	// Save old user...
			unset($BE_USER);	// Unset current

			$BE_USER = t3lib_div::makeInstance('t3lib_beUserAuth');	// New backend user object
			$BE_USER->OS = TYPO3_OS;
			$BE_USER->setBeUserByUid($this->simUser);
			$BE_USER->fetchGroupData();
			$BE_USER->backendSetUC();
			$GLOBALS['BE_USER'] = $BE_USER;	// Must do this, because unsetting $BE_USER before apparently unsets the reference to the global variable by this name!
		}
	}

	/**
	 * Returns the label $str from getLL() and grays out the value if the $str/$key is found in $this->overrideConf array
	 *
	 * @param	string		Locallang key
	 * @param	string		Alternative override-config key
	 * @param	boolean		Defines whether the string should be wrapped in a <label> tag.
	 * @param	string		Alternative id for use in "for" attribute of <label> tag. By default the $str key is used prepended with "field_".
	 * @return	string		HTML output.
	 */
	function setLabel($str, $key='', $addLabelTag=true, $altLabelTagId='')	{
		$out = $GLOBALS['LANG']->getLL($str) . ': ';
		if (isset($this->overrideConf[($key?$key:$str)]))	{
			$out = '<span style="color:#999999">'.$out.'</span>';
		}
		if($addLabelTag) {
			$out = '<label for="'.($altLabelTagId?$altLabelTagId:'field_'.$str).'">'.$out.'</label>';
		}
		return $out;
	}

	/**
	 * Returns the CSH Icon for given string
	 *
	 * @param	string		Locallang key
	 * @return	string		HTML output.
	 */
	function getCSH($str) {
		if (!t3lib_div::inList('language', $str)) {
			$str = 'option_' . $str;
		}
		return t3lib_BEfunc::cshItem('_MOD_user_setup', $str, $GLOBALS['BACK_PATH'], '|', false, 'margin-bottom:0px;');
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/setup/mod/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/setup/mod/index.php']);
}



// Make instance:
$SOBE = t3lib_div::makeInstance('SC_mod_user_setup_index');
$SOBE->simulateUser();
$SOBE->storeIncomingData();

// These includes MUST be afterwards the settings are saved...!
require ($BACK_PATH.'template.php');
$LANG->includeLLFile('EXT:setup/mod/locallang.xml');

$SOBE->init();
$SOBE->main();
$SOBE->printContent();

?>