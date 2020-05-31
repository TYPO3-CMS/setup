<?php

defined('TYPO3_MODE') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
    'user',
    'setup',
    'after:task',
    '',
    [
        'routeTarget' => \TYPO3\CMS\Setup\Controller\SetupModuleController::class . '::mainAction',
        'access' => 'group,user',
        'name' => 'user_setup',
        'icon' => 'EXT:setup/Resources/Public/Icons/module-setup.svg',
        'labels' => 'LLL:EXT:setup/Resources/Private/Language/locallang_mod.xlf'
    ]
);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
    '_MOD_user_setup',
    'EXT:setup/Resources/Private/Language/locallang_csh_mod.xlf'
);

$GLOBALS['TYPO3_USER_SETTINGS'] = [
    'columns' => [
        'realName' => [
            'type' => 'text',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:beUser_realName',
            'table' => 'be_users',
            'csh' => 'beUser_realName',
            'max' => 80
        ],
        'email' => [
            'type' => 'email',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:beUser_email',
            'table' => 'be_users',
            'csh' => 'beUser_email',
            'max' => 80
        ],
        'emailMeAtLogin' => [
            'type' => 'check',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:emailMeAtLogin',
            'csh' => 'emailMeAtLogin'
        ],
        'password' => [
            'type' => 'password',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:newPassword',
            'table' => 'be_users',
            'csh' => 'newPassword',
        ],
        'password2' => [
            'type' => 'password',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:newPasswordAgain',
            'table' => 'be_users',
            'csh' => 'newPasswordAgain',
        ],
        'passwordCurrent' => [
            'type' => 'password',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:passwordCurrent',
            'table' => 'be_users',
            'csh' => 'passwordCurrent',
        ],
        'avatar' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_users.avatar',
            'type' => 'avatar',
            'table' => 'be_users',
            'allowed' => $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
        ],
        'lang' => [
            'type' => 'select',
            'itemsProcFunc' => \TYPO3\CMS\Setup\Controller\SetupModuleController::class . '->renderLanguageSelect',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:language',
            'csh' => 'language'
        ],
        'startModule' => [
            'type' => 'select',
            'itemsProcFunc' => \TYPO3\CMS\Setup\Controller\SetupModuleController::class . '->renderStartModuleSelect',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:startModule',
            'csh' => 'startModule'
        ],
        'thumbnailsByDefault' => [
            'type' => 'check',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:showThumbs',
            'csh' => 'showThumbs'
        ],
        'titleLen' => [
            'type' => 'number',
            'class' => 'form-control-adapt',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:maxTitleLen',
            'csh' => 'maxTitleLen'
        ],
        'edit_RTE' => [
            'type' => 'check',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:edit_RTE',
            'csh' => 'edit_RTE'
        ],
        'edit_docModuleUpload' => [
            'type' => 'check',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:edit_docModuleUpload',
            'csh' => 'edit_docModuleUpload'
        ],
        'showHiddenFilesAndFolders' => [
            'type' => 'check',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:showHiddenFilesAndFolders',
            'csh' => 'showHiddenFilesAndFolders'
        ],
        'copyLevels' => [
            'type' => 'number',
            'class' => 'form-control-adapt',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:copyLevels',
            'csh' => 'copyLevels'
        ],
        'recursiveDelete' => [
            'type' => 'check',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:recursiveDelete',
            'csh' => 'recursiveDelete'
        ],
        'resetConfiguration' => [
            'type' => 'button',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:resetConfiguration',
            'buttonlabel' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:resetConfigurationButton',
            'csh' => 'reset',
            'confirm' => true,
            'confirmData' => [
                'message' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:setToStandardQuestion',
                'eventName' => 'setup:confirmation:response',
            ]
        ],
        'resizeTextareas_Flexible' => [
            'type' => 'check',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:resizeTextareas_Flexible',
            'csh' => 'resizeTextareas_Flexible'
        ],
        'resizeTextareas_MaxHeight' => [
            'type' => 'number',
            'class' => 'form-control-adapt',
            'label' => 'LLL:EXT:setup/Resources/Private/Language/locallang.xlf:flexibleTextareas_MaxHeight',
            'csh' => 'flexibleTextareas_MaxHeight'
        ],
    ],
    'showitem' => '--div--;LLL:EXT:setup/Resources/Private/Language/locallang.xlf:personal_data,realName,email,emailMeAtLogin,avatar,lang,
            --div--;LLL:EXT:setup/Resources/Private/Language/locallang.xlf:passwordHeader,passwordCurrent,password,password2,
            --div--;LLL:EXT:setup/Resources/Private/Language/locallang.xlf:opening,startModule,
            --div--;LLL:EXT:setup/Resources/Private/Language/locallang.xlf:editFunctionsTab,edit_RTE,resizeTextareas_Flexible,resizeTextareas_MaxHeight,titleLen,thumbnailsByDefault,edit_docModuleUpload,showHiddenFilesAndFolders,copyLevels,recursiveDelete,resetConfiguration'
];
