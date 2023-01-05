<?php
return [
    'frontend' => [
        'graphql-api' => [
            'target' => \Heidtech\GraphqlApi\Middleware\GraphqlApiMiddleware::class,
            'before' => [
                'typo3/cms-frontend/eid'
            ]
        ]
    ]
];