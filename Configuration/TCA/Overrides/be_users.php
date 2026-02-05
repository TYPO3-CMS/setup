<?php

declare(strict_types=1);

use TYPO3\CMS\Backend\Backend\ColorScheme;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Setup\UserFunctions\UserSettingsItemsProcFunc;

defined('TYPO3') or die();

$GLOBALS['TCA']['be_users']['columns']['user_settings'] = [
    'label' => 'setup.messages:user_settings',
    'config' => [
        'type' => 'json',
    ],
];

// Set up the showitem structure with tabs
$GLOBALS['TCA']['be_users']['columns']['user_settings']['showitem'] = '
    --div--;core.form.tabs:personaldata,
    --div--;core.form.tabs:account_security,
    --div--;core.form.tabs:backend_appearance,
    --div--;core.form.tabs:personalization';

// Personal data tab
ExtensionManagementUtility::addUserSetting(
    'realName',
    [
        'inheritFromParent' => true,
    ],
    'after:--div--;core.form.tabs:personaldata'
);
ExtensionManagementUtility::addUserSetting(
    'email',
    [
        'inheritFromParent' => true,
    ],
    'after:realName'
);
ExtensionManagementUtility::addUserSetting(
    'emailMeAtLogin',
    [
        'label' => 'setup.messages:emailMeAtLogin',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
    'after:email'
);
ExtensionManagementUtility::addUserSetting(
    'avatar',
    [
        'label' => 'setup.messages:avatar',
        'config' => [
            'type' => 'none',
            'renderType' => 'avatar',
        ],
    ],
    'after:emailMeAtLogin'
);
ExtensionManagementUtility::addUserSetting(
    'lang',
    [
        'label' => 'setup.messages:language',
        'table' => 'be_users',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'itemsProcFunc' => UserSettingsItemsProcFunc::class . '->addLanguageItems',
        ],
    ],
    'after:avatar'
);

// Account security tab
ExtensionManagementUtility::addUserSetting(
    'password',
    [
        'inheritFromParent' => true,
        'label' => 'setup.messages:newPassword',
    ],
    'after:--div--;core.form.tabs:account_security'
);
ExtensionManagementUtility::addUserSetting(
    'password2',
    [
        'label' => 'setup.messages:newPasswordAgain',
        'config' => [
            'type' => 'password',
            'passwordPolicy' => $GLOBALS['TYPO3_CONF_VARS']['BE']['passwordPolicy'] ?? '',
            'size' => 20,
            'required' => true,
        ],
    ],
    'after:password'
);
ExtensionManagementUtility::addUserSetting(
    'mfaProviders',
    [
        'label' => 'setup.messages:mfaProviders',
        'config' => [
            // @todo Use a new internal TCA type to prevent raw data being displayed in the backend
            'type' => 'none',
            'renderType' => 'mfaInfo',
        ],
        'authenticationContext' => [
            'group' => 'be.userManagement',
        ],
    ],
    'after:password2'
);

// Backend appearance tab
ExtensionManagementUtility::addUserSetting(
    'colorScheme',
    [
        'label' => 'backend.messages:colorScheme',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => ColorScheme::getAvailableItemsForSelection(),
        ],
    ],
    'after:--div--;core.form.tabs:backend_appearance'
);
ExtensionManagementUtility::addUserSetting(
    'theme',
    [
        'label' => 'backend.messages:theme',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'backend.messages:theme.fresh', 'value' => 'fresh'],
                ['label' => 'backend.messages:theme.modern', 'value' => 'modern'],
                ['label' => 'backend.messages:theme.classic', 'value' => 'classic'],
            ],
        ],
    ],
    'after:colorScheme'
);
ExtensionManagementUtility::addUserSetting(
    'startModule',
    [
        'label' => 'setup.messages:startModule',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'itemsProcFunc' => UserSettingsItemsProcFunc::class . '->renderStartModuleSelect',
            'items' => [],
        ],
    ],
    'after:theme'
);
ExtensionManagementUtility::addUserSetting(
    'backendTitleFormat',
    [
        'label' => 'setup.messages:backendTitleFormat',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'setup.messages:backendTitleFormat.titleFirst', 'value' => 'titleFirst'],
                ['label' => 'setup.messages:backendTitleFormat.sitenameFirst', 'value' => 'sitenameFirst'],
            ],
        ],
    ],
    'after:startModule'
);
ExtensionManagementUtility::addUserSetting(
    'dateTimeFirstDayOfWeek',
    [
        'label' => 'setup.messages:datetime_first_day_of_week',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'itemsProcFunc' => UserSettingsItemsProcFunc::class . '->renderDateTimeFirstDayOfWeekSelect',
            'items' => [],
        ],
    ],
    'after:backendTitleFormat'
);

// Personalization tab
ExtensionManagementUtility::addUserSetting(
    'titleLen',
    [
        'label' => 'setup.messages:maxTitleLen',
        'config' => [
            'type' => 'number',
            'size' => 5,
            'range' => [
                'lower' => 10,
                'upper' => 255,
            ],
            'default' => 50,
        ],
    ],
    'after:--div--;core.form.tabs:personalization'
);
ExtensionManagementUtility::addUserSetting(
    'edit_docModuleUpload',
    [
        'label' => 'setup.messages:edit_docModuleUpload',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
    'after:titleLen'
);
ExtensionManagementUtility::addUserSetting(
    'showHiddenFilesAndFolders',
    [
        'label' => 'setup.messages:showHiddenFilesAndFolders',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
    'after:edit_docModuleUpload'
);
ExtensionManagementUtility::addUserSetting(
    'displayRecentlyUsed',
    [
        'label' => 'setup.messages:displayRecentlyUsed',
        'persistentUpdate' => true,
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 1,
        ],
    ],
    'after:showHiddenFilesAndFolders'
);
ExtensionManagementUtility::addUserSetting(
    'contextualRecordEdit',
    [
        'label' => 'setup.messages:contextualRecordEdit',
        'persistentUpdate' => true,
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 1,
        ],
    ],
    'after:edit_docModuleUpload'
);
ExtensionManagementUtility::addUserSetting(
    'copyLevels',
    [
        'label' => 'setup.messages:copyLevels',
        'config' => [
            'type' => 'number',
            'size' => 5,
            'range' => [
                'lower' => 0,
                'upper' => 100,
            ],
            'default' => 0,
        ],
    ],
    'after:displayRecentlyUsed'
);
