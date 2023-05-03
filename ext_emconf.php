<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 CMS Setup',
    'description' => 'Allows users to edit a limited set of options for their user profile, including preferred language, their name and email address.',
    'category' => 'module',
    'state' => 'stable',
    'author' => 'TYPO3 Core Team',
    'author_email' => 'typo3cms@typo3.org',
    'author_company' => '',
    'version' => '13.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
