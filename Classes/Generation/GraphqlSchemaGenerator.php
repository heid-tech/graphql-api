<?php
declare(strict_types=1);

namespace Heidtech\GraphqlApi\Generation;


use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Heidtech\GraphqlApi\Parsing\EntityParser;

class GraphqlSchemaGenerator
{
    protected EntityParser $entityParser;

    public function __construct()
    {
        $this->entityParser = new EntityParser();
    }

    public function generateSchemaForEndpoint(array $endpointConfiguration) : Schema
    {
        $schemaConfig = [];
        if (array_key_exists('query', $endpointConfiguration)) {
            $schemaConfig['query'] = $this->getQueryObjectType($endpointConfiguration['query']);
        }

        if (array_key_exists('mutation', $endpointConfiguration)) {
            $schemaConfig['mutation'] = $this->getMutationObjectType($endpointConfiguration['mutation']);
        }

        return new Schema($schemaConfig);
    }

    protected function getQueryObjectType(array $queryConfiguration) : ObjectType
    {
        $queryFields = [];
        foreach ($queryConfiguration['entities'] as $className => $config) {
            $parsedFields = $this->entityParser->parseByClassName(
                $className,
                $config['allowedFields'] ?? [],
                $config['disallowedFields'] ?? []
            );
            $queryFields[$parsedFields->name] = [
                'type' => $parsedFields
            ];
        }
        return new ObjectType([
            'name' => 'Query',
            'fields' => $queryFields
        ]);
    }

    protected function getMutationObjectType(array $mutationConfiguration) : ObjectType
    {
        return new ObjectType([
            'name' => 'Mutation',
            'fields' => []
        ]);
    }
}