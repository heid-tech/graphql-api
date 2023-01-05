<?php


namespace Heidtech\GraphqlApi\Utility;


use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SchemaUtility
{
    public static function loadSchemaFromFile($filePath): string
    {
        $absolutePath = GeneralUtility::getFileAbsFileName($filePath);
        return file_get_contents($absolutePath);
    }

    /**
     * @param string $tcaTable
     *
     * @param string $graphQlTypeName
     *
     * @param array|null $includeFields
     * Fields inside $tcaTable that should be included in the GraphQl type declaration.
     * TCA fields can be given a different name in the GraphQl field declaration like this:
     * [$tcaFieldName => $graphQlFieldName]
     *
     * @param array|null $excludeFields
     */
    public static function registerTcaTableForGraphQlType(string $tcaTable, string $graphQlTypeName, ?array $includeFields = null, ?array $excludeFields = null)
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['typeMapping']['tca2graphql'][$tcaTable] = $graphQlTypeName;

        if ($includeFields) {
            $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['fieldMapping']['tca2graphql'][$tcaTable]['includeFields'] = $includeFields;
        }

        if (!$includeFields && $excludeFields) {
            $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['fieldMapping']['tca2graphql'][$tcaTable]['excludeFields'] = $excludeFields;
        }
    }

    public static function getGraphQlTypeForTcaTable(string $tcaTable): string
    {
        $typeFieldsDefinition = '';

        $includeFields = $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['fieldMapping']['tca2graphql'][$tcaTable]['includeFields'];
        $excludeFields = $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['fieldMapping']['tca2graphql'][$tcaTable]['excludeFields'];
        if (!empty($includeFields)) {
            /** Iterate over all fields set in $includeFields.
             *  Check if field has a name mapping and use array entry accordingly.
             */
            foreach ($includeFields as $key => $value) {
                if (is_string($key)) {
                    $typeFieldsDefinition .= '  ' . self::getGraphQlDefinitionForTcaField($tcaTable, $key);
                } else {
                    $typeFieldsDefinition .= '  ' . self::getGraphQlDefinitionForTcaField($tcaTable, $value);
                }
            }
        } else {
            /**
             * Iterate over all fields in TCA column and remove fields in $excludeFields.
             * Add field 'uid', because it is created implicitly by default and thus is not
             * included in the TCA configuration.
             */

            $tcaFieldColumns = $GLOBALS['TCA'][$tcaTable]['columns'];
            if (!isset($tcaFieldColumns['uid'])) {
                $tcaFieldColumns['uid'] = [
                    'config' => [
                        'type' => 'input',
                        'eval' => 'int'
                    ]
                ];
            }

            foreach (array_keys($tcaFieldColumns) as $tcaFieldName) {
                if (!in_array($tcaFieldName, $excludeFields)) {
                    $typeFieldsDefinition .= '  ' . self::getGraphQlDefinitionForTcaField($tcaTable, $tcaFieldName);
                }
            }
        }

        $typeName = $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['typeMapping']['tca2graphql'][$tcaTable] ?? $tcaTable;
        return 'type ' . $typeName . ' {' . PHP_EOL . $typeFieldsDefinition . '}';
    }

    protected static function getGraphQlDefinitionForTcaField(string $tcaTable, string $field): string
    {
        $graphQlFieldName = $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['fieldMapping']['tca2graphql'][$tcaTable]['includeFields'][$field] ?? $field;

        if ($field == 'uid') {
            return $graphQlFieldName . ' : Int' . PHP_EOL;
        }

        $tcaColumn = $GLOBALS['TCA'][$tcaTable]['columns'][$field];
        $graphQlFieldType = self::getGraphQlTypeForTcaField($tcaColumn);
        return $graphQlFieldName . ' : ' . $graphQlFieldType . PHP_EOL;
    }

    protected static function getGraphQlTypeForTcaField(array $tcaFieldColumn): string
    {
        $tcaFieldConfig = $tcaFieldColumn['config'];

        if ($tcaFieldConfig['type'] == 'input') {
            $eval = $tcaFieldConfig['eval'];

            if (empty($eval)) {
                return 'String';
            }

            if (str_contains($eval, 'int') ||
                str_contains($eval, 'num')) {
                return 'Int';
            }

            if (str_contains($eval, 'double2')) {
                return 'Float';
            }

            return 'String';
        }
        elseif ($tcaFieldConfig['type'] == 'check') {
            return 'Int';
        }
        elseif ($tcaFieldConfig['type'] == 'language') {
            return 'Int';
        }
        elseif ($tcaFieldConfig['type'] == 'slug') {
            return 'String';
        }
        elseif ($tcaFieldConfig['type'] == 'text') {
            return 'String';
        }
        elseif ($tcaFieldConfig['type'] == 'radio') {
            if (isset($tcaFieldConfig['items']) &&
                count($tcaFieldConfig['items']) > 0 &&
                is_string($tcaFieldConfig['items'][0][1])) {
                return 'String';
            }

            return 'Int';
        }
        elseif ($tcaFieldConfig['type'] == 'inline') {
            $graphQlSingleType = '';

            if (isset($tcaFieldConfig['foreign_table'])) {
                $graphQlSingleType = $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['typeMapping']['tca2graphql'][$tcaFieldConfig['foreign_table']] ?? $tcaFieldConfig['foreign_table'];
            }

            if (self::canFieldHoldMultipleValues($tcaFieldConfig)) {
                return '[' . $graphQlSingleType . ']';
            }

            return $graphQlSingleType;
        }
        elseif ($tcaFieldConfig['type'] == 'group') {
            $graphQlSingleType = '';

            if (isset($tcaFieldConfig['allowed']) && !strpos($tcaFieldConfig['allowed'], ',')) {
                $graphQlSingleType = $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['typeMapping']['tca2graphql'][$tcaFieldConfig['allowed']] ?? $tcaFieldConfig['allowed'];
            } else {
                return '';
            }

            if (self::canFieldHoldMultipleValues($tcaFieldConfig)) {
                return '[' . $graphQlSingleType . ']';
            }

            return $graphQlSingleType;
        }
        elseif ($tcaFieldConfig['type'] == 'category') {
            $graphQlSingleType = $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['typeMapping']['tca2graphql']['sys_category'] ?? 'sys_category';

            if (self::canFieldHoldMultipleValues($tcaFieldConfig)) {
                return '[' . $graphQlSingleType . ']';
            }

            return $graphQlSingleType;
        }
        elseif ($tcaFieldConfig['type'] == 'select') {
            if (!isset($tcaFieldConfig['foreign_table'])) {
                if (isset($tcaFieldConfig['items'][0][1])) {
                    if (is_string($tcaFieldConfig['items'][0][1])) {
                        return 'String';
                    }
                    elseif (is_int($tcaFieldConfig['items'][0][1])) {
                        return 'Int';
                    }
                }
            }

            $graphQlSingleType = $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['typeMapping']['tca2graphql'][$tcaFieldConfig['foreign_table']] ?? $tcaFieldConfig['foreign_table'];

            if (self::canFieldHoldMultipleValues($tcaFieldConfig)) {
                return '[' . $graphQlSingleType . ']';
            }
        }
        elseif ($tcaFieldConfig['type'] == 'passthrough') {
            return 'String';
        }
        return '';
    }

    protected static function canFieldHoldMultipleValues(array $tcaFieldConfig): bool
    {
        if (isset($tcaFieldConfig['maxitems']) && $tcaFieldConfig['maxitems'] > 1) {
            return true;
        }

        if (isset($tcaFieldConfig['renderType']) && $tcaFieldConfig['renderType'] != 'selectSingle') {
            return true;
        }

        return false;
    }

    public static function getDatabaseResolveFieldClosure(): \Closure
    {
        return function ($parentObject, ?array $args, ?array $context, \GraphQL\Type\Definition\ResolveInfo $info) {
            $tableNameOrRepository = self::getTableNameForObjectType($info->fieldDefinition->getType()->name);

            /** @var ConnectionPool $connectionPool */
            $connectionPool = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
            $queryBuilder = $connectionPool->getQueryBuilderForTable($tableNameOrRepository);

            $query = $queryBuilder
                ->from($tableNameOrRepository)
                ->select('*');

            foreach ($args as $name => $value) {
                $query->andWhere($query->expr()->eq($name, $value));
            }

            return $query->execute()->fetchAssociative();
        };
    }

    public static function getTcaResolveFieldClosure(): \Closure
    {
        return function ($parentObject, ?array $args, ?array $context, \GraphQL\Type\Definition\ResolveInfo $info) {
            $tcaTableName = self::getTableNameForObjectType($info->fieldDefinition->getType()->name);

            /** @var ConnectionPool $connectionPool */
            $connectionPool = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
            $queryBuilder = $connectionPool->getQueryBuilderForTable($tcaTableName);

            $query = $queryBuilder
                ->from($tcaTableName);

            foreach ($info->getFieldSelection() as $queriedField => $_) {
                $query->addSelect(
                    self::getTcaFieldSelectClauseForQueriedField($queriedField, $tcaTableName)
                );
            }

            return $query->execute()->fetchAssociative();
        };
    }

    protected static function getTcaFieldSelectClauseForQueriedField(string $queriedField, string $tcaTable): string
    {
        $selectClause = $queriedField;
        $tcaFieldNameForQueriedField = array_search($queriedField, $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['fieldMapping']['tca2graphql'][$tcaTable]['includeFields']);

        // If key in field mapping does exist, the field should be queried as an alias.
        if (is_string($tcaFieldNameForQueriedField)) {
            $selectClause = $tcaFieldNameForQueriedField . ' AS ' . $queriedField;
        }

        return $selectClause;
    }

    public static function getTableNameForObjectType(string $objectTypeName): string
    {
        $tcaTableName = array_search($objectTypeName, $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['typeMapping']['tca2graphql']);
        return $tcaTableName ? $tcaTableName : $objectTypeName;
    }


}