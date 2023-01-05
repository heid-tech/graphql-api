<?php


namespace Heidtech\GraphqlApi\Middleware;


use GraphQL\Error\DebugFlag;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Utils\BuildSchema;
use Heidtech\GraphqlApi\Utility\SchemaUtility;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use GraphQL\Language\AST\TypeDefinitionNode;

class GraphqlApiMiddleware implements MiddlewareInterface
{
    /** @var FrontendInterface */
    protected FrontendInterface $cache;

    /** @var ResponseFactoryInterface */
    protected $responseFactory;

    /** @var ContainerInterface */
    protected $container;

    public function __construct(
        FrontendInterface $cache,
        ResponseFactoryInterface $responseFactory,
        ContainerInterface $container
    )
    {
        $this->cache = $cache;
        $this->responseFactory = $responseFactory;
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if current URI is a graphql endpoint.
        $uriPath = $request->getUri()->getPath();
        if (!in_array($uriPath, array_keys($GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['endpoints'])))
        {
            return $handler->handle($request);
        }

        // Manually set parsedBody of request, because StandardServer needs it, when parsing request.
        if ($request->getHeader('content-type')[0] == 'application/json') {
            $requestBody = (string) $request->getBody();
            $parsedBody = json_decode($requestBody, true);
            $request = $request->withParsedBody($parsedBody);
        }

        // Generate type schema files for all types registered for TCA type mapping.
        // These will be regenerated after clearing cache.
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['typeMapping']['tca2graphql'] as $tcaTableName => $graphQlTypeName) {
            $typeSchemaFileName = GeneralUtility::getFileAbsFileName('EXT:graphql_api/Resources/Private/GraphQl/Types/' . $graphQlTypeName . '.graphql');
            if (!is_file($typeSchemaFileName)) {
                $graphQlTypeSchema = SchemaUtility::getGraphQlTypeForTcaTable($tcaTableName);
                file_put_contents($typeSchemaFileName, $graphQlTypeSchema);
            }
        }

        $endpointConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['endpoints'][$uriPath];
        $schema = $endpointConfiguration['schema']; // TODO: Allow multiple schemas which get appended.
        $rootResolver = $endpointConfiguration['fieldConfig'];
        $additionalTypes = $endpointConfiguration['types'];

        // Load schemas of types registered for current route.
        foreach ($additionalTypes as $additionalType) {
            $schema .= PHP_EOL . PHP_EOL . SchemaUtility::loadSchemaFromFile('EXT:graphql_api/Resources/Private/GraphQl/Types/' . $additionalType . '.graphql');
        }

        // TODO: Add custom type resolvers for types generated from TCA tables.
        // https://webonyx.github.io/graphql-php/schema-definition-language/#defining-resolvers

        /*$typeConfigDecorator = function (array $typeConfig, TypeDefinitionNode $typeDefinitionNode): array {
            $typeName = $typeConfig['name'];

            if (in_array($typeName, $GLOBALS['TYPO3_CONF_VARS']['EXT']['graphql_api']['typeMapping']['tca2graphql'])) {

            }
            return $typeConfig;
        };*/

        $builtSchema = BuildSchema::build($schema);//, $typeConfigDecorator);

        $context = [
            'DependencyInjectionContainer' => $this->container
        ];

        $serverConfig = ServerConfig::create()
            ->setSchema($builtSchema)
            ->setRootValue($rootResolver);
            //->setContext($context)
            //->setDebugFlag(DebugFlag::INCLUDE_DEBUG_MESSAGE);



        $graphQlServer = new StandardServer($serverConfig);

        $response = $this->responseFactory->createResponse();
        return $graphQlServer->processPsrRequest($request, $response, new Stream('php://temp', 'w+'));
    }
}