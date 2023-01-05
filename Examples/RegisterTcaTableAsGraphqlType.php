<?php

use Heidtech\GraphqlApi\Utility\SchemaUtility;

SchemaUtility::registerTcaTableForGraphQlType(
    'fe_users',
    'User',
    [
        'uid' => 'identifier',
        'username'
    ]
);

SchemaUtility::registerTcaTableForGraphQlType(
    'pages',
    'Page',
    [
        'uid' => 'pageId',
        'title',
        'slug' => 'uri',
        //'categories'
    ]
);