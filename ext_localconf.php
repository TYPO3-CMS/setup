<?php

declare(strict_types=1);

use TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRecordTypeValue;
use TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRowDefaultValues;
use TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca;
use TYPO3\CMS\Backend\Form\FormDataProvider\PageTsConfig;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessCommon;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessFieldLabels;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessShowitem;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsRemoveUnused;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaGroup;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaSelectItems;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaText;
use TYPO3\CMS\Backend\Form\FormDataProvider\UserTsConfig;
use TYPO3\CMS\Setup\Form\FormDataProvider\UserSettingsDatabaseEditRow;

defined('TYPO3') or die();

// Register FormDataGroup for backend user settings
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['backendUserSettings'] = [
    InitializeProcessedTca::class => [],
    UserSettingsDatabaseEditRow::class => [
        'depends' => [
            InitializeProcessedTca::class,
        ],
    ],
    UserTsConfig::class => [
        'depends' => [
            UserSettingsDatabaseEditRow::class,
        ],
    ],
    PageTsConfig::class => [
        'depends' => [
            UserTsConfig::class,
        ],
    ],
    DatabaseRowDefaultValues::class => [
        'depends' => [
            InitializeProcessedTca::class,
            UserSettingsDatabaseEditRow::class,
        ],
    ],
    DatabaseRecordTypeValue::class => [
        'depends' => [
            DatabaseRowDefaultValues::class,
        ],
    ],
    TcaColumnsProcessCommon::class => [
        'depends' => [
            DatabaseRecordTypeValue::class,
        ],
    ],
    TcaColumnsProcessShowitem::class => [
        'depends' => [
            TcaColumnsProcessCommon::class,
        ],
    ],
    TcaColumnsRemoveUnused::class => [
        'depends' => [
            TcaColumnsProcessCommon::class,
            TcaColumnsProcessShowitem::class,
        ],
    ],
    TcaText::class => [
        'depends' => [
            TcaColumnsRemoveUnused::class,
        ],
    ],
    TcaGroup::class => [
        'depends' => [
            TcaText::class,
        ],
    ],
    TcaSelectItems::class => [
        'depends' => [
            TcaGroup::class,
        ],
    ],
    TcaColumnsProcessFieldLabels::class => [
        'depends' => [
            TcaSelectItems::class,
        ],
    ],
];
