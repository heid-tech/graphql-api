<?php


namespace Heidtech\GraphqlApi\Hook;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class RemoveGeneratedTypeSchemasHook
{
    public function process(&$parameters, $ref)
    {
        if ($parameters['cacheCmd'] === 'all') {
            $typeSchemaDirectoryPath = GeneralUtility::getFileAbsFileName('EXT:graphql_api/Resources/Private/GraphQl/Types');

            $fileNames = GeneralUtility::getFilesInDir($typeSchemaDirectoryPath);
            foreach($fileNames as $fileName){
                $filePath = $typeSchemaDirectoryPath . '/' . $fileName;
                if(is_file($filePath)) {
                    unlink($filePath);
                }
            }
        }
    }
}