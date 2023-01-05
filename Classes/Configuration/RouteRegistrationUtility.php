<?php


namespace Heidtech\GraphqlApi\Configuration;


class RouteRegistrationUtility
{
    public static function registerRoute(
        string $routePath,
        array $routeConfiguration
    )
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['endpoints'][$routePath] = $routeConfiguration;
    }
}