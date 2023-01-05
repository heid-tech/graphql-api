<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Heidtech GraphQL API',
    'description' => 'Configurable GraphQL API for TYPO3.',
    'category' => 'templates',
    'author' => 'Rafael Heid',
    'author_email' => 'contact@heid.tech',
    'author_company' => 'Heidtech',
    'version' => '0.0.1',
    'state' => 'dev',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
        ],
        'conflicts' => [
        ],
    ],
    'clearCacheOnLoad' => true
];