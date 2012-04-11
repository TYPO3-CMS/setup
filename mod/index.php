<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2011 Kasper Skårhøj (kasperYYYY@typo3.com)
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
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 * Revised for TYPO3 3.7 6/2004 by Kasper Skårhøj
 * XHTML compatible.
 */

unset($MCONF);
require('conf.php');
require($BACK_PATH.'init.php');














/**
 * Script class for the Setup module
 *
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
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
	protected $pagetreeNeedsRefresh = FALSE;

	protected $isAdmin;
	protected $dividers2tabs;

	protected $tsFieldConf;

	protected $saveData = FALSE;
	protected $passwordIsUpdated = FALSE;
	protected $passwordIsSubmitted = FALSE;
	protected $setupIsUpdated = FALSE;
	protected $tempDataIsCleared = FALSE;
	protected $settingsAreResetToDefault = FALSE;

	/**
	 * @var bool
	 * @deprecated since TYPO3 4.6 - will be removed with TYPO3 4.8
	 */
	protected $installToolFileExists = FALSE;

	/**
	 * @var bool
	 * @deprecated since TYPO3 4.6 - will be removed with TYPO3 4.8
	 */
	protected $installToolFileKeep = FALSE;

	/**
	 * Form protection instance
	 *
	 * @var t3lib_formprotection_BackendFormProtection
	 */
	protected $formProtection;

	/******************************
	 *
	 * Saving data
	 *
	 ******************************/


	/**
	 * Instanciate the form protection before a simulated user is initialized.
	 */
	public function __construct() {
		$this->formProtection = t3lib_formProtection_Factory::get();
	}

	/**
	 * Getter for the form protection instance.
	 *
	 * @return t3lib_formprotection_BackendFormProtection
	 */
	public function getFormProtection() {
		return $this->formProtection;
	}

	/**
	 * If settings are submitted to _POST[DATA], store them
	 * NOTICE: This method is called before the template.php is included. See
	 * bottom of document.
	 */
	public function storeIncomingData() {

			// First check if something is submitted in the data-array from POST vars
		$d = t3lib_div::_POST('data');
		$columns = $GLOBALS['TYPO3_USER_SETTINGS']['columns'];
		$beUserId = $GLOBALS['BE_USER']->user['uid'];
		$storeRec = array();
		$fieldList = $this->getFieldsFromShowItem();

		if (is_array($d) && $this->formProtection->validateToken(
				(string) t3lib_div::_POST('formToken'),
				'BE user setup', 'edit'
			)
		) {
				// UC hashed before applying changes
			$save_before = md5(serialize($GLOBALS['BE_USER']->uc));

				// PUT SETTINGS into the ->uc array:

				// reload left frame when switching BE language
			if (isset($d['lang']) && ($d['lang'] != $GLOBALS['BE_USER']->uc['lang'])) {
				$this->languageUpdate = TRUE;
			}

				// reload pagetree if the title length is changed
			if (isset($d['titleLen']) && ($d['titleLen'] !== $GLOBALS['BE_USER']->uc['titleLen'])) {
				$this->pagetreeNeedsRefresh = TRUE;
			}

			if ($d['setValuesToDefault']) {
					// If every value should be default
				$GLOBALS['BE_USER']->resetUC();
				$this->settingsAreResetToDefault = TRUE;
			} elseif ($d['clearSessionVars']) {
				foreach ($GLOBALS['BE_USER']->uc as $key => $value) {
					if (!isset($columns[$key])) {
						unset ($GLOBALS['BE_USER']->uc[$key]);
					}
				}
				$this->tempDataIsCleared = TRUE;
			} elseif ($d['save']) {
					// save all submitted values if they are no array (arrays are with table=be_users) and exists in $GLOBALS['TYPO3_USER_SETTINGS'][columns]

				foreach($columns as $field => $config) {
					if (!in_array($field, $fieldList)) {
						continue;
					}
					if ($config['table']) {
						if ($config['table'] == 'be_users' && !in_array($field, array('password', 'password2', 'email', 'realName', 'admin'))) {
							if (!isset($config['access']) || $this->checkAccess($config) && $GLOBALS['BE_USER']->user[$field] !== $d['be_users'][$field]) {
								$storeRec['be_users'][$beUserId][$field] = $d['be_users'][$field];
								$GLOBALS['BE_USER']->user[$field] = $d['be_users'][$field];
							}
						}
					}
					if ($config['type'] == 'check') {
						$GLOBALS['BE_USER']->uc[$field] = isset($d[$field]) ? 1 : 0;
					} else {
						$GLOBALS['BE_USER']->uc[$field] = htmlspecialchars($d[$field]);
					}
				}

					// Personal data for the users be_user-record (email, name, password...)
					// If email and name is changed, set it in the users record:
				$be_user_data = $d['be_users'];

					// Possibility to modify the transmitted values. Useful to do transformations, like RSA password decryption
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/setup/mod/index.php']['modifyUserDataBeforeSave'])) {
					foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/setup/mod/index.php']['modifyUserDataBeforeSave'] as $function) {
						$params = array('be_user_data' => &$be_user_data);
						t3lib_div::callUserFunction($function, $params, $this);
					}
				}

				$this->passwordIsSubmitted = (strlen($be_user_data['password']) > 0);
				$passwordIsConfirmed = ($this->passwordIsSubmitted && $be_user_data['password'] === $be_user_data['password2']);

					// Update the real name:
				if ($be_user_data['realName'] !== $GLOBALS['BE_USER']->user['realName']) {
					$GLOBALS['BE_USER']->user['realName'] = $storeRec['be_users'][$beUserId]['realName'] = substr($be_user_data['realName'], 0, 80);
				}
					// Update the email address:
				if ($be_user_data['email'] !== $GLOBALS['BE_USER']->user['email']) {
					$GLOBALS['BE_USER']->user['email'] = $storeRec['be_users'][$beUserId]['email'] = substr($be_user_data['email'], 0, 80);
				}
					// Update the password:
				if ($passwordIsConfirmed) {
					$storeRec['be_users'][$beUserId]['password'] = $be_user_data['password2'];
					$this->passwordIsUpdated = TRUE;
				}

				$this->saveData = TRUE;
			}

				// Inserts the overriding values.
			$GLOBALS['BE_USER']->overrideUC();

			$save_after = md5(serialize($GLOBALS['BE_USER']->uc));
			if ($save_before!=$save_after)	{	// If something in the uc-array of the user has changed, we save the array...
				$GLOBALS['BE_USER']->writeUC($GLOBALS['BE_USER']->uc);
				$GLOBALS['BE_USER']->writelog(254, 1, 0, 1, 'Personal settings changed', array());
				$this->setupIsUpdated = TRUE;
			}
				// If the temporary data has been cleared, lets make a log note about it
			if ($this->tempDataIsCleared) {
				$GLOBALS['BE_USER']->writelog(254, 1, 0, 1, $GLOBALS['LANG']->getLL('tempDataClearedLog'), array());
			}

				// Persist data if something has changed:
			if (count($storeRec) && $this->saveData) {
					// Make instance of TCE for storing the changes.
				$tce = t3lib_div::makeInstance('t3lib_TCEmain');
				$tce->stripslashes_values=0;
				$tce->start($storeRec, array(), $GLOBALS['BE_USER']);
				$tce->admin = 1;	// This is so the user can actually update his user record.
				$tce->bypassWorkspaceRestrictions = TRUE;	// This is to make sure that the users record can be updated even if in another workspace. This is tolerated.
				$tce->process_datamap();
				unset($tce);

				if (!$this->passwordIsUpdated || count($storeRec['be_users'][$beUserId]) > 1) {
					$this->setupIsUpdated = TRUE;
				}
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

			// check Install Tool enable file
			// @deprecated since TYPO3 4.6 - will be removed with TYPO3 4.8
		$this->installToolFileExists = is_file(PATH_typo3conf . 'ENABLE_INSTALL_TOOL');
		if ($this->installToolFileExists) {
			$this->installToolFileKeep = (trim(file_get_contents(PATH_typo3conf . 'ENABLE_INSTALL_TOOL')) === 'KEEP_FILE');
		}

			// Returns the script user - that is the REAL logged in user! ($GLOBALS[BE_USER] might be another user due to simulation!)
		$scriptUser = $this->getRealScriptUserObj();
			// ... and checking module access for the logged in user.
		$scriptUser->modAccess($this->MCONF, 1);

		$this->isAdmin = $scriptUser->isAdmin();

			// Getting the 'override' values as set might be set in User TSconfig
		$this->overrideConf = $GLOBALS['BE_USER']->getTSConfigProp('setup.override');
			// Getting the disabled fields might be set in User TSconfig (eg setup.fields.password.disabled=1)
		$this->tsFieldConf = $GLOBALS['BE_USER']->getTSConfigProp('setup.fields');
			// id password is disabled, disable repeat of password too (password2)
		if (isset($this->tsFieldConf['password.']) && ($this->tsFieldConf['password.']['disabled'])) {
			$this->tsFieldConf['password2.']['disabled'] = 1;
		}
			// Create instance of object for output of data
		$this->doc = t3lib_div::makeInstance('template');
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->setModuleTemplate('templates/setup.html');
		$this->doc->form = '<form action="index.php" method="post" name="usersetup" enctype="application/x-www-form-urlencoded">';
		$this->doc->tableLayout = array(
			'defRow' => array(
				'0' => array('<td class="td-label">','</td>'),
				'defCol' => array('<td valign="top">','</td>')
			)
		);
		$this->doc->table_TR = '<tr>';
		$this->doc->table_TABLE = '<table border="0" cellspacing="1" cellpadding="2" class="typo3-usersettings">';
		$this->doc->JScode .= $this->getJavaScript();
	}

	/**
	 * Generate necessary JavaScript
	 *
	 * @return string
	 */
	protected function getJavaScript() {
		$javaScript = '';
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/setup/mod/index.php']['setupScriptHook'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/setup/mod/index.php']['setupScriptHook'] as $function) {
				$params = array();
				$javaScript .= t3lib_div::callUserFunction($function, $params, $this);
			}
		}

		return $javaScript;
	}

	/**
	 * Generate the main settings formular:
	 *
	 * @return	void
	 */
	function main()	{
		global $LANG;

		if ($this->languageUpdate) {
			$this->doc->JScodeArray['languageUpdate'] .=  '
				if (top.refreshMenu) {
					top.refreshMenu();
				} else {
					top.TYPO3ModuleMenu.refreshMenu();
				}
			';
		}

		if ($this->pagetreeNeedsRefresh) {
			t3lib_BEfunc::setUpdateSignal('updatePageTree');
		}

			// Start page:
		$this->doc->loadJavascriptLib('md5.js');

			// use a wrapper div
		$this->content .= '<div id="user-setup-wrapper">';

			// Load available backend modules
		$this->loadModules = t3lib_div::makeInstance('t3lib_loadModules');
		$this->loadModules->observeWorkspaces = TRUE;
		$this->loadModules->load($GLOBALS['TBE_MODULES']);

		$this->content .= $this->doc->header($LANG->getLL('UserSettings'));

			// show if setup was saved
		if ($this->setupIsUpdated && !$this->tempDataIsCleared && !$this->settingsAreResetToDefault) {
			$flashMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				$LANG->getLL('setupWasUpdated'),
				$LANG->getLL('UserSettings')
			);
			$this->content .= $flashMessage->render();
		}
			// Show if temporary data was cleared
		if ($this->tempDataIsCleared) {
			$flashMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				$LANG->getLL('tempDataClearedFlashMessage'),
				$LANG->getLL('tempDataCleared')
			);
			$this->content .= $flashMessage->render();
		}
			// Show if temporary data was cleared
		if ($this->settingsAreResetToDefault) {
			$flashMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				$LANG->getLL('settingsAreReset'),
				$LANG->getLL('resetConfiguration')
			);
			$this->content .= $flashMessage->render();
		}

			// Notice
		if ($this->setupIsUpdated || $this->settingsAreResetToDefault) {
			$flashMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				$LANG->getLL('activateChanges'),
				'',
				t3lib_FlashMessage::INFO
			);
			$this->content .= $flashMessage->render();
		}

			// If password is updated, output whether it failed or was OK.
		if ($this->passwordIsSubmitted) {
			if ($this->passwordIsUpdated) {
				$flashMessage = t3lib_div::makeInstance(
					't3lib_FlashMessage',
					$LANG->getLL('newPassword_ok'),
					$LANG->getLL('newPassword')
				);
			} else {
				$flashMessage = t3lib_div::makeInstance(
					't3lib_FlashMessage',
					$LANG->getLL('newPassword_failed'),
					$LANG->getLL('newPassword'),
					t3lib_FlashMessage::ERROR
				);
			}
			$this->content .= $flashMessage->render();
		}


			// render the menu items
		$menuItems = $this->renderUserSetup();

		$this->content .= $this->doc->getDynTabMenu($menuItems, 'user-setup', FALSE, FALSE, 1, FALSE, 1, $this->dividers2tabs);

		$formToken = $this->formProtection->generateToken('BE user setup', 'edit');
		$this->content .= $this->doc->section('',
			'<input type="hidden" name="simUser" value="'.$this->simUser.'" />
			<input type="hidden" name="formToken" value="' . $formToken . '" />
			<input type="hidden" name="data[setValuesToDefault]" value="0" id="setValuesToDefault" />
			<input type="hidden" name="data[clearSessionVars]" value="0" id="clearSessionVars" />'
		);

			// Section: Reset settings
		$this->content .= $this->doc->spacer(20);
		$this->content .= $this->doc->section($LANG->getLL('resetSectionHeader') . ' ' . t3lib_BEfunc::cshItem('_MOD_user_setup', 'reset', $GLOBALS['BACK_PATH']),
			'<input type="button" value="' . $LANG->getLL('resetConfiguration') .
					'" onclick="if (confirm(\'' . $LANG->getLL('setToStandardQuestion') . '\')) { document.getElementById(\'setValuesToDefault\').value = 1; this.form.submit(); }" />
			<input type="button" value="' . $LANG->getLL('clearSessionVars') .
					'" onclick="if (confirm(\'' . $LANG->getLL('clearSessionVarsQuestion') . '\')) { document.getElementById(\'clearSessionVars\').value = 1;this.form.submit(); }" />',
			FALSE,
			FALSE,
			0,
			TRUE
		);

			// end of wrapper div
		$this->content .= '</div>';

			// Setting up the buttons and markers for docheader
		$docHeaderButtons = $this->getButtons();
		$markers['CSH'] = $docHeaderButtons['csh'];
		$markers['CONTENT'] = $this->content;

			// Build the <body> for the module
		$this->content = $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);

			// Renders the module page
		$this->content = $this->doc->render(
			$LANG->getLL('UserSettings'),
			$this->content
		);

	}

	/**
	 * Sets existance of Install Tool file
	 *
	 * @return void
	 * @deprecated since TYPO3 4.6 - will be removed with TYPO3 4.8 - use Tx_Install_Service_BasicService
	 */
	public function setInstallToolFileExists() {
		t3lib_div::logDeprecatedFunction();
		$this->installToolFileExists = is_file(PATH_typo3conf . 'ENABLE_INSTALL_TOOL');
	}

	/**
	 * Sets property if Install Tool file contains "KEEP_FILE"
	 * @deprecated since TYPO3 4.6 - will be removed with TYPO3 4.8 - use Tx_Install_Service_BasicService
	 */
	public function setInstallToolFileKeep() {
		t3lib_div::logDeprecatedFunction();
		if ($this->installToolFileExists) {
			$this->installToolFileKeep = (trim(file_get_contents(PATH_typo3conf . 'ENABLE_INSTALL_TOOL')) === 'KEEP_FILE');
		}
	}

	/**
	 * Gets property installToolFileExists
	 *
	 * @return boolean $this->installToolFileExists
	 * @deprecated since TYPO3 4.6 - will be removed with TYPO3 4.8 - use Tx_Install_Service_BasicService
	 */
	public function getInstallToolFileExists() {
		t3lib_div::logDeprecatedFunction();
		return $this->installToolFileExists;
	}

	/**
	 * Gets property installToolFileKeep
	 *
	 * @return boolean $this->installToolFileKeep
	 * @deprecated since TYPO3 4.6 - will be removed with TYPO3 4.8 - use Tx_Install_Service_BasicService
	 */
	public function getInstallToolFileKeep() {
		t3lib_div::logDeprecatedFunction();
		return $this->installToolFileKeep;
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

		$buttons['csh'] = t3lib_BEfunc::cshItem('_MOD_user_setup', '', $GLOBALS['BACK_PATH'], '|', TRUE);

		$buttons['save'] = t3lib_iconWorks::getSpriteIcon(
			'actions-document-save',
			array('html' => '<input type="image" name="data[save]" class="c-inputButton" src="clear.gif" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:rm.saveDoc', 1) . '" />')
		);

		if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
			$buttons['shortcut'] = $this->doc->makeShortcutIcon('','',$this->MCONF['name']);
		}

		return $buttons;
	}




	/******************************
	 *
	 * Render module
	 *
	 ******************************/


	 /**
	 * renders the data for all tabs in the user setup and returns
	 * everything that is needed with tabs and dyntab menu
	 *
	 * @return	ready to use for the dyntabmenu itemarray
	 */
	protected function renderUserSetup() {
		$result = array();
		$firstTabLabel = '';
		$code = array();
		$i = 0;

		$fieldArray = $this->getFieldsFromShowItem();

		$this->dividers2tabs = isset($GLOBALS['TYPO3_USER_SETTINGS']['ctrl']['dividers2tabs']) ? intval($GLOBALS['TYPO3_USER_SETTINGS']['ctrl']['dividers2tabs']) : 0;
		$tabLabel = '';

		foreach ($fieldArray as $fieldName) {
			$more = '';

			if (substr($fieldName, 0, 8) == '--div--;') {
				if ($firstTabLabel == '') {
					// first tab
					$tabLabel = $this->getLabel(substr($fieldName, 8), '', FALSE);
					$firstTabLabel = $tabLabel;
				} else {
					if ($this->dividers2tabs) {
						$result[] = array(
							'label'   => $tabLabel,
							'content' => count($code) ? $this->doc->spacer(20) . $this->doc->table($code) : ''
						);
						$tabLabel = $this->getLabel(substr($fieldName, 8), '', FALSE);
						$i = 0;
						$code = array();
					}
				}
				continue;
			}

			$config = $GLOBALS['TYPO3_USER_SETTINGS']['columns'][$fieldName];

				// field my be disabled in setup.fields
			if (isset($this->tsFieldConf[$fieldName . '.']['disabled']) && $this->tsFieldConf[$fieldName . '.']['disabled'] == 1) {
				continue;
			}
			if (isset($config['access']) && !$this->checkAccess($config)) {
				continue;
			}

			$label = $this->getLabel($config['label'], $fieldName);
			$label = $this->getCSH($config['csh'] ? $config['csh'] : $fieldName, $label);

			$type = $config['type'];
			$eval = $config['eval'];
			$class = $config['class'];
			$style = $config['style'];

			if ($class) {
				$more .= ' class="' . $class . '"';
			}
			if ($style) {
				$more .= ' style="' . $style . '"';
			}
			if ($this->overrideConf[$fieldName]) {
				$more .= ' disabled="disabled"';
			}

			$value = $config['table'] == 'be_users' ? $GLOBALS['BE_USER']->user[$fieldName] : $GLOBALS['BE_USER']->uc[$fieldName];
			if (!$value && isset($config['default'])) {
				$value = $config['default'];
			}

			switch ($type) {
				case 'text':
				case 'password':
					$dataAdd = '';
					if ($config['table'] == 'be_users') {
						$dataAdd = '[be_users]';
					}
					if ($eval == 'md5') {
						$more .= ' onchange="this.value=this.value?MD5(this.value):\'\';"';
					}

					if ($type == 'password') {
						$value = '';
					}

					$noAutocomplete = ($type == 'password' ? 'autocomplete="off" ' : '');
					$html = '<input id="field_' . $fieldName . '"
							type="' . $type . '"
							name="data' . $dataAdd . '[' . $fieldName . ']" ' .
							$noAutocomplete .
							'value="' . htmlspecialchars($value) . '" ' . $GLOBALS['TBE_TEMPLATE']->formWidth(20) . $more . ' />';
				break;
				case 'check':
					if (!$class) {
						$more .= ' class="check"';
					}
					$html = '<input id="field_' . $fieldName . '"
									type="checkbox"
									name="data[' . $fieldName . ']"' .
									($value ? ' checked="checked"' : '') . $more . ' />';
				break;
				case 'select':
					if (!$class) {
						$more .= ' class="select"';
					}

					if ($config['itemsProcFunc']) {
						$html = t3lib_div::callUserFunction($config['itemsProcFunc'], $config, $this, '');
					} else {
						$html = '<select id="field_' . $fieldName . '" name="data[' . $fieldName . ']"' . $more . '>' . LF;
						foreach ($config['items'] as $key => $optionLabel) {
							$html .= '<option value="' . $key . '"' .
								($value == $key ? ' selected="selected"' : '') .
								'>' . $this->getLabel($optionLabel, '', FALSE) . '</option>' . LF;
						}
						$html .= '</select>';
					}

				break;
				case 'user':
					$html = t3lib_div::callUserFunction($config['userFunc'], $config, $this, '');
				break;
				default:
					$html = '';
			}


			$code[$i][1] = $label;
			$code[$i++][2] = $html;



		}

		if ($this->dividers2tabs == 0) {
			$tabLabel = $firstTabLabel;
		}

		$result[] = array(
			'label'   => $tabLabel,
			'content' => count($code) ? $this->doc->spacer(20) . $this->doc->table($code) : ''
		);


		return $result;
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
	protected function getRealScriptUserObj()	{
		return is_object($this->OLD_BE_USER) ? $this->OLD_BE_USER : $GLOBALS['BE_USER'];
	}


	/**
	* Return a select with available languages
	 *
	* @return	string		complete select as HTML string or warning box if something went wrong.
	 */
	public function renderLanguageSelect($params, $pObj) {

		$languageOptions = array();

			// compile the languages dropdown
		$langDefault = $GLOBALS['LANG']->getLL('lang_default', 1);
		$languageOptions[$langDefault] = '<option value=""' .
			($GLOBALS['BE_USER']->uc['lang'] === '' ? ' selected="selected"' : '') .
			'>' . $langDefault . '</option>';

			// traverse the number of languages
		/** @var $locales t3lib_l10n_Locales */
		$locales = t3lib_div::makeInstance('t3lib_l10n_Locales');
		$languages = $locales->getLanguages();
		foreach ($languages as $locale => $name) {
			if ($locale !== 'default') {
				$defaultName = isset($GLOBALS['LOCAL_LANG']['default']['lang_' . $locale]) ? $GLOBALS['LOCAL_LANG']['default']['lang_' . $locale][0]['source'] : $name;
				$localizedName = $GLOBALS['LANG']->getLL('lang_' . $locale, TRUE);
				if ($localizedName === '') {
					$localizedName = htmlspecialchars($name);
				}
				$localLabel = '  -  [' . htmlspecialchars($defaultName) . ']';
				$available = (is_dir(PATH_typo3conf . 'l10n/' . $locale) ? TRUE : FALSE);
				if ($available) {
					$languageOptions[$defaultName] = '<option value="' . $locale . '"' . ($GLOBALS['BE_USER']->uc['lang'] === $locale ?
						' selected="selected"' : '') . '>' .
						$localizedName . $localLabel . '</option>';
				}
			}
		}
		ksort($languageOptions);

		$languageCode = '
				<select id="field_lang" name="data[lang]" class="select">' .
					implode('', $languageOptions) . '
				</select>';
		if ( $GLOBALS['BE_USER']->uc['lang'] && !@is_dir(PATH_typo3conf . 'l10n/' . $GLOBALS['BE_USER']->uc['lang'])) {
			$languageUnavailableWarning = 'The selected language "'
				. $GLOBALS['LANG']->getLL('lang_' . $GLOBALS['BE_USER']->uc['lang'], 1)
				. '" is not available before the language pack is installed.<br />'
				. ($GLOBALS['BE_USER']->isAdmin() ?
					'You can use the Extension Manager to easily download and install new language packs.'
				:	'Please ask your system administrator to do this.');


			$languageUnavailableMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				$languageUnavailableWarning,
				'',
				t3lib_FlashMessage::WARNING
			);

			$languageCode = $languageUnavailableMessage->render() . $languageCode;
		}

		return $languageCode;
	}

	/**
	* Returns a select with all modules for startup
	*
	* @return	string		complete select as HTML string
	*/
	public function renderStartModuleSelect($params, $pObj) {
			// start module select
		if (empty($GLOBALS['BE_USER']->uc['startModule']))	{
			$GLOBALS['BE_USER']->uc['startModule'] = $GLOBALS['BE_USER']->uc_default['startModule'];
		}
		$startModuleSelect = '<option value=""></option>';
		foreach ($pObj->loadModules->modules as $mainMod => $modData) {
			if (isset($modData['sub']) && is_array($modData['sub'])) {
				$startModuleSelect .= '<option disabled="disabled">'.$GLOBALS['LANG']->moduleLabels['tabs'][$mainMod.'_tab'].'</option>';
				foreach ($modData['sub'] as $subKey => $subData) {
					$modName = $subData['name'];
					$startModuleSelect .= '<option value="' . $modName . '"' . ($GLOBALS['BE_USER']->uc['startModule'] == $modName ? ' selected="selected"' : '') . '>';
					$startModuleSelect .= ' - ' . $GLOBALS['LANG']->moduleLabels['tabs'][$modName.'_tab'] . '</option>';
				}
			}
		}


		return '<select id="field_startModule" name="data[startModule]" class="select">' . $startModuleSelect . '</select>';
		}

 	/**
	 *
	 * @param array $params                    config of the field
	 * @param SC_mod_user_setup_index $parent  this class as reference
	 * @return string	                       html with description and button
	 * @deprecated since TYPO3 4.6 - will be removed with TYPO3 4.8
	 */
	public function renderInstallToolEnableFileButton(array $params, SC_mod_user_setup_index $parent) {
		t3lib_div::logDeprecatedFunction();

			// Install Tool access file
		$installToolEnableFile = PATH_typo3conf . 'ENABLE_INSTALL_TOOL';
		if ($parent->getInstallToolFileExists() && ($GLOBALS['EXEC_TIME'] - filemtime($installToolEnableFile) > 3600)) {
			if (!$parent->getInstallToolFileKeep()) {
					// Delete the file if it is older than 3600s (1 hour)
				unlink($installToolEnableFile);
				$parent->setInstallToolFileExists();
			}
		}

		if ($parent->getInstallToolFileExists()) {
			return '<input type="button" name="deleteInstallToolEnableFile"' .
					($parent->getInstallToolFileKeep() ? ' disabled="disabled"' : '') .
					' value="' . $GLOBALS['LANG']->sL('LLL:EXT:setup/mod/locallang.xml:enableInstallTool.deleteFile') . '" onclick="document.getElementById(\'deleteInstallToolEnableFile\').value=1;this.form.submit();" />
					<input type="hidden" name="deleteInstallToolEnableFile" value="0" id="deleteInstallToolEnableFile" />
					';

		} else {
			return '<input type="button" name="createInstallToolEnableFile" value="' .
					$GLOBALS['LANG']->sL('LLL:EXT:setup/mod/locallang.xml:enableInstallTool.createFile') . '" onclick="document.getElementById(\'createInstallToolEnableFile\').value=1;this.form.submit();" />
					<input type="hidden" name="createInstallToolEnableFile" value="0" id="createInstallToolEnableFile" />';
		}
	}

	/**
	 * Will make the simulate-user selector if the logged in user is administrator.
	 * It will also set the GLOBAL(!) BE_USER to the simulated user selected if any (and set $this->OLD_BE_USER to logged in user)
	 *
	 * @return	void
	 */
	public function simulateUser()	{

		// *******************************************************************************
		// If admin, allow simulation of another user
		// *******************************************************************************
		$this->simUser = 0;
		$this->simulateSelector = '';
		unset($this->OLD_BE_USER);
		if ($GLOBALS['BE_USER']->isAdmin()) {
			$this->simUser = intval(t3lib_div::_GP('simUser'));

				// Make user-selector:
			$users = t3lib_BEfunc::getUserNames('username,usergroup,usergroup_cached_list,uid,realName', t3lib_BEfunc::BEenableFields('be_users'));
			$opt = array();
			foreach ($users as $rr) {
				if ($rr['uid'] != $GLOBALS['BE_USER']->user['uid']) {
					$opt[] = '<option value="'.$rr['uid'].'"'.($this->simUser==$rr['uid']?' selected="selected"':'').'>'.htmlspecialchars($rr['username'].' ('.$rr['realName'].')').'</option>';
				}
			}
			if (count($opt)) {
				$this->simulateSelector = '<select id="field_simulate" name="simulateUser" onchange="window.location.href=\'index.php?simUser=\'+this.options[this.selectedIndex].value;"><option></option>'.implode('',$opt).'</select>';
			}
		}

		if ($this->simUser>0)	{	// This can only be set if the previous code was executed.
				// Save old user...
			$this->OLD_BE_USER = $GLOBALS['BE_USER'];
			unset($GLOBALS['BE_USER']);	// Unset current

			$BE_USER = t3lib_div::makeInstance('t3lib_beUserAuth');	// New backend user object
			$BE_USER->OS = TYPO3_OS;
			$BE_USER->setBeUserByUid($this->simUser);
			$BE_USER->fetchGroupData();
			$BE_USER->backendSetUC();
			$GLOBALS['BE_USER'] = $BE_USER;	// Must do this, because unsetting $BE_USER before apparently unsets the reference to the global variable by this name!
		}
	}

	/**
	* Returns a select with simulate users
	*
	* @return	string		complete select as HTML string
	*/
	public function renderSimulateUserSelect($params, $pObj) {
		return $pObj->simulateSelector;
	}

	/**
	* Returns access check (currently only "admin" is supported)
	*
	* @param	array		$config: Configuration of the field, access mode is defined in key 'access'
	* @return	boolean		Whether it is allowed to modify the given field
	*/
	protected function checkAccess(array $config) {
		$access = $config['access'];
			// check for hook
		if (t3lib_div::hasValidClassPrefix($access)) {
			$accessObject = t3lib_div::getUserObj($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['setup']['accessLevelCheck'][$access] . ':&' . $access);
			if (is_object($accessObject) && method_exists($accessObject, 'accessLevelCheck'))	{
					// initialize vars. If method fails, $set will be set to FALSE
				return $accessObject->accessLevelCheck($config);
			}
		} elseif ($access == 'admin') {
			return $this->isAdmin;
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
	protected function getLabel($str, $key='', $addLabelTag=TRUE, $altLabelTagId='')	{
		if (substr($str, 0, 4) == 'LLL:') {
			$out = $GLOBALS['LANG']->sL($str);
		} else {
			$out = htmlspecialchars($str);
 		}


		if (isset($this->overrideConf[($key?$key:$str)]))	{
			$out = '<span style="color:#999999">'.$out.'</span>';
		}

		if($addLabelTag) {
			$out = '<label for="' . ($altLabelTagId ? $altLabelTagId : 'field_' . $key) . '">' . $out . '</label>';
		}
		return $out;
	}

	/**
	 * Returns the CSH Icon for given string
	 *
	 * @param	string		Locallang key
	 * @param	string		The label to be used, that should be wrapped in help
	 * @return	string		HTML output.
	 */
	protected function getCSH($str, $label) {
		$context = '_MOD_user_setup';
		$field = $str;
		$strParts = explode(':', $str);
		if (count($strParts) > 1) {
				// Setting comes from another extension
			$context = $strParts[0];
			$field = $strParts[1];
		} elseif (!t3lib_div::inList('language,simuser', $str)) {
			$field = 'option_' . $str;
 		}
		return t3lib_BEfunc::wrapInHelp($context, $field, $label);
	}
	/**
	 * Returns array with fields defined in $GLOBALS['TYPO3_USER_SETTINGS']['showitem']
	 *
	 * @param	void
	 * @return	array	array with fieldnames visible in form
	 */
	protected function getFieldsFromShowItem() {
		$fieldList = $GLOBALS['TYPO3_USER_SETTINGS']['showitem'];

			// disable fields depended on settings
		if (!$GLOBALS['TYPO3_CONF_VARS']['BE']['RTEenabled']) {
			$fieldList = t3lib_div::rmFromList('edit_RTE', $fieldList);
		}

		$fieldArray = t3lib_div::trimExplode(',', $fieldList, TRUE);
		return $fieldArray;
	}
}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/setup/mod/index.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/setup/mod/index.php']);
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
