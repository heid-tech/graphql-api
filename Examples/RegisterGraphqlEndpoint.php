<?php

use Heidtech\GraphqlApi\Configuration\RouteRegistrationUtility;
use Heidtech\GraphqlApi\Utility\SchemaUtility;

/** For e.g. following graphql toplevel schema:
    schema {
      query: Query
    }

    type Query {
      greetings(input: HelloInput!): String!
    }

    input HelloInput {
      firstName: String!
      lastName: String
    }
 */
RouteRegistrationUtility::registerRoute(
    // URI for graphql endpoint. This will be e.g. "https://example.com/graphql-api".
    '/graphql-api',
    [
        // Your own schema file.
        'schema' => SchemaUtility::loadSchemaFromFile('EXT:graphql_api/Resources/Private/GraphQl/schema.graphql'), // TODO: Allow registering of multiple schema files.
        // Types generated from TCA, that should be included in the schema. See Examples/RegisterTcaTableAsGraphqlType.php for example.
        'types' => [
            'User', 'Page'
        ],
        // Configure how top-level queries should be resolved.
        // e.g.:
        // query {
        //   greetings(input: {firstName: 'Foo', lastName: 'Bar'})
        // }
        // will result in "Hello Foo Bar".
        'fieldConfig' => [
            'greetings' => function($root, $args, $context, \GraphQL\Type\Definition\ResolveInfo $info) {
                return trim(
                    sprintf(
                        'Hello %s %s',
                        $args['input']['firstName'],
                        $args['input']['lastName'] ?? ''
                    )
                );
            },
        ],
    ]
);