<?php
defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE') {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
		'user',
		'setup',
		'after:task',
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'mod/',
		array(
			'script' => '_DISPATCH',
			'access' => 'group,user',
			'name' => 'user_setup',
			'labels' => array(
				'tabs_images' => array(
					'tab' => '../Resources/Public/Icons/module-setup.png',
				),
				'll_ref' => 'LLL:EXT:setup/mod/locallang_mod.xlf',
			),
		)
	);
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
		'_MOD_user_setup',
		'EXT:setup/locallang_csh_mod.xlf'
	);

	$GLOBALS['TYPO3_USER_SETTINGS'] = array(
		'columns' => array(
			'realName' => array(
				'type' => 'text',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:beUser_realName',
				'table' => 'be_users',
				'csh' => 'beUser_realName'
			),
			'email' => array(
				'type' => 'email',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:beUser_email',
				'table' => 'be_users',
				'csh' => 'beUser_email'
			),
			'emailMeAtLogin' => array(
				'type' => 'check',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:emailMeAtLogin',
				'csh' => 'emailMeAtLogin'
			),
			'password' => array(
				'type' => 'password',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:newPassword',
				'table' => 'be_users',
				'csh' => 'newPassword',
			),
			'password2' => array(
				'type' => 'password',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:newPasswordAgain',
				'table' => 'be_users',
				'csh' => 'newPasswordAgain',
			),
			'lang' => array(
				'type' => 'select',
				'itemsProcFunc' => \TYPO3\CMS\Setup\Controller\SetupModuleController::class . '->renderLanguageSelect',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:language',
				'csh' => 'language'
			),
			'startModule' => array(
				'type' => 'select',
				'itemsProcFunc' => \TYPO3\CMS\Setup\Controller\SetupModuleController::class . '->renderStartModuleSelect',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:startModule',
				'csh' => 'startModule'
			),
			'thumbnailsByDefault' => array(
				'type' => 'check',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:showThumbs',
				'csh' => 'showThumbs'
			),
			'titleLen' => array(
				'type' => 'text',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:maxTitleLen',
				'csh' => 'maxTitleLen'
			),
			'edit_RTE' => array(
				'type' => 'check',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:edit_RTE',
				'csh' => 'edit_RTE'
			),
			'edit_docModuleUpload' => array(
				'type' => 'check',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:edit_docModuleUpload',
				'csh' => 'edit_docModuleUpload'
			),
			'showHiddenFilesAndFolders' => array(
				'type' => 'check',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:showHiddenFilesAndFolders',
				'csh' => 'showHiddenFilesAndFolders'
			),
			'copyLevels' => array(
				'type' => 'text',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:copyLevels',
				'csh' => 'copyLevels'
			),
			'recursiveDelete' => array(
				'type' => 'check',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:recursiveDelete',
				'csh' => 'recursiveDelete'
			),
			'resetConfiguration' => array(
				'type' => 'button',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:resetConfiguration',
				'buttonlabel' => 'LLL:EXT:setup/mod/locallang.xlf:resetConfigurationShort',
				'csh' => 'reset',
				'onClick' => 'if (confirm(\'%s\')) { document.getElementById(\'setValuesToDefault\').value = 1; this.form.submit(); }',
				'onClickLabels' => array(
					'LLL:EXT:setup/mod/locallang.xlf:setToStandardQuestion'
				)
			),
			'clearSessionVars' => array(
				'type' => 'button',
				'access' => 'admin',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:clearSessionVars',
				'buttonlabel' => 'LLL:EXT:setup/mod/locallang.xlf:clearSessionVarsShort',
				'csh' => 'reset',
				'onClick' => 'if (confirm(\'%s\')) { document.getElementById(\'clearSessionVars\').value = 1; this.form.submit(); }',
				'onClickLabels' => array(
					'LLL:EXT:setup/mod/locallang.xlf:clearSessionVarsQuestion'
				)
			),
			'resizeTextareas_Flexible' => array(
				'type' => 'check',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:resizeTextareas_Flexible',
				'csh' => 'resizeTextareas_Flexible'
			),
			'resizeTextareas_MaxHeight' => array(
				'type' => 'text',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:flexibleTextareas_MaxHeight',
				'csh' => 'flexibleTextareas_MaxHeight'
			),
			'debugInWindow' => array(
				'type' => 'check',
				'label' => 'LLL:EXT:setup/mod/locallang.xlf:debugInWindow',
				'access' => 'admin'
			)
		),
		'showitem' => '--div--;LLL:EXT:setup/mod/locallang.xlf:personal_data,realName,email,emailMeAtLogin,password,password2,lang,
				--div--;LLL:EXT:setup/mod/locallang.xlf:opening,startModule,thumbnailsByDefault,titleLen,
				--div--;LLL:EXT:setup/mod/locallang.xlf:editFunctionsTab,edit_RTE,edit_docModuleUpload,showHiddenFilesAndFolders,resizeTextareas_Flexible,resizeTextareas_MaxHeight,copyLevels,recursiveDelete,resetConfiguration,clearSessionVars,
				--div--;LLL:EXT:setup/mod/locallang.xlf:adminFunctions,debugInWindow'
	);
}
