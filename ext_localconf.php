<?php

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['graphqlapi_entitycache'] ??= [];

// TODO(heid): Make this configurable via TypoScript
$GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['endpoints'] = [];
$GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['objectTypes'] = [];
$GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['typeMapping'] = [];

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] = \Heidtech\GraphqlApi\Hook\RemoveGeneratedTypeSchemasHook::class . '->process';