<?php
/**
 * Swagger/OpenAPI Documentation
 * Outsourced Technologies E-Commerce Platform
 * 
 * Access this file at: /api/swagger.php
 */

header('Content-Type: application/json');

$openapi = [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'Outsourced Technologies API',
        'description' => 'E-commerce API for Outsourced Technologies - A full-featured online store with products, services, M-Pesa payments, and loyalty system.',
        'version' => '1.0.0',
        'contact' => [
            'name' => 'API Support',
            'email' => 'support@outsourcedtechnologies.co.ke'
        ],
        'license' => [
            'name' => 'Proprietary'
        ]
    ],
    'servers' => [
        [
            'url' => 'http://localhost/outsourced/api/v1',
            'description' => 'Local Development Server'
        ],
        [
            'url' => 'https://outsourcedtechnologies.co.ke/api/v1',
            'description' => 'Production Server'
        ]
    ],
    'components' => [
        'securitySchemes' => [
            'BearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'JWT token obtained from /auth/login or /auth/register'
            ],
            'ApiKeyAuth' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-API-Key',
                'description' => 'Optional API key for rate limiting'
            ]
        ],
        'schemas' => [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'message' => ['type' => 'string'],
                    'data' => ['type' => 'object', 'nullable' => true]
                ]
            ],
            'SuccessResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data' => ['type' => 'object', 'nullable' => true]
                ]
            ],
            'Product' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'price' => ['type' => 'number'],
                    'description' => ['type' => 'string'],
                    'category_id' => ['type' => 'integer'],
                    'stock' => ['type' => 'integer'],
                    'image' => ['type' => 'string', 'nullable' => true],
                    'visible' => ['type' => 'boolean']
                ]
            ],
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'email' => ['type' => 'string'],
                    'username' => ['type' => 'string'],
                    'full_name' => ['type' => 'string'],
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'loyalty_points' => ['type' => 'integer']
                ]
            ],
            'Order' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'order_number' => ['type' => 'string'],
                    'total_amount' => ['type' => 'number'],
                    'status' => ['type' => 'string'],
                    'payment_status' => ['type' => 'string'],
                    'delivery_type' => ['type' => 'string']
                ]
            ],
            'AuthResponse' => [
                'type' => 'object',
                'properties' => [
                    'access_token' => ['type' => 'string'],
                    'refresh_token' => ['type' => 'string'],
                    'token_type' => ['type' => 'string', 'example' => 'Bearer'],
                    'expires_in' => ['type' => 'integer'],
                    'user' => ['$ref' => '#/components/schemas/User']
                ]
            ]
        ],
        'responses' => [
            'Unauthorized' => [
                'description' => 'Unauthorized - Invalid or missing authentication',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                        'example' => [
                            'success' => false,
                            'message' => 'Authorization token required'
                        ]
                    ]
                ]
            ],
            'Forbidden' => [
                'description' => 'Forbidden - Insufficient permissions',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                        'example' => [
                            'success' => false,
                            'message' => 'Admin access required'
                        ]
                    ]
                ]
            ],
            'NotFound' => [
                'description' => 'Resource not found',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                        'example' => [
                            'success' => false,
                            'message' => 'Product not found'
                        ]
                    ]
                ]
            ],
            'BadRequest' => [
                'description' => 'Bad Request - Invalid parameters',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                        'example' => [
                            'success' => false,
                            'message' => 'Invalid product ID'
                        ]
                    ]
                ]
            ]
        ]
    ],
    'security' => [
        ['BearerAuth' => []],
        ['ApiKeyAuth' => []]
    ],
    'paths' => [
        // Auth Endpoints
        '/auth.php' => [
            'post' => [
                'summary' => 'User authentication',
                'description' => 'Login with email and password, returns JWT tokens',
                'operationId' => 'login',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['email', 'password'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'password' => ['type' => 'string', 'format' => 'password']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Login successful',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/AuthResponse']
                            ]
                        ]
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '400' => ['$ref' => '#/components/responses/BadRequest']
                ]
            ]
        ],
        
        // Products Endpoints
        '/products.php' => [
            'get' => [
                'summary' => 'Get all products',
                'description' => 'Retrieve a list of products with optional filtering',
                'operationId' => 'getProducts',
                'parameters' => [
                    [
                        'name' => 'category',
                        'in' => 'query',
                        'description' => 'Filter by category ID',
                        'schema' => ['type' => 'integer']
                    ],
                    [
                        'name' => 'search',
                        'in' => 'query',
                        'description' => 'Search products by name',
                        'schema' => ['type' => 'string']
                    ],
                    [
                        'name' => 'page',
                        'in' => 'query',
                        'description' => 'Page number',
                        'schema' => ['type' => 'integer', 'default' => 1]
                    ],
                    [
                        'name' => 'per_page',
                        'in' => 'query',
                        'description' => 'Items per page',
                        'schema' => ['type' => 'integer', 'default' => 12]
                    ],
                    [
                        'name' => 'sort',
                        'in' => 'query',
                        'description' => 'Sort order (newest, price_asc, price_desc, popular)',
                        'schema' => ['type' => 'string', 'default' => 'newest']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Products retrieved successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean'],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'products' => [
                                                    'type' => 'array',
                                                    'items' => ['$ref' => '#/components/schemas/Product']
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ],
        
        // Cart Endpoints
        '/cart.php' => [
            'get' => [
                'summary' => 'Get cart contents',
                'description' => 'Get items in the user\'s cart',
                'security' => [['BearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Cart retrieved successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean'],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'items' => ['type' => 'array'],
                                                'subtotal' => ['type' => 'number']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'summary' => 'Add to cart',
                'description' => 'Add a product to the shopping cart',
                'security' => [['BearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['product_id'],
                                'properties' => [
                                    'product_id' => ['type' => 'integer'],
                                    'quantity' => ['type' => 'integer', 'default' => 1]
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Product added to cart',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SuccessResponse']
                            ]
                        ]
                    ]
                ]
            ]
        ],
        
        // Orders Endpoints
        '/orders.php' => [
            'get' => [
                'summary' => 'Get user orders',
                'description' => 'Retrieve order history for authenticated user',
                'security' => [['BearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Orders retrieved successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean'],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'orders' => [
                                                    'type' => 'array',
                                                    'items' => ['$ref' => '#/components/schemas/Order']
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'summary' => 'Create order',
                'description' => 'Place a new order',
                'security' => [['BearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['delivery_type', 'phone'],
                                'properties' => [
                                    'delivery_type' => ['type' => 'string', 'enum' => ['pickup', 'home_delivery']],
                                    'phone' => ['type' => 'string'],
                                    'address' => ['type' => 'string'],
                                    'notes' => ['type' => 'string']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Order created successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean'],
                                        'data' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'order' => ['$ref' => '#/components/schemas/Order'],
                                                'checkout_request_id' => ['type' => 'string']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ],
        
        // Wishlist Endpoints
        '/wishlist.php' => [
            'get' => [
                'summary' => 'Get wishlist',
                'description' => 'Get user\'s wishlist',
                'security' => [['BearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Wishlist retrieved successfully'
                    ]
                ]
            ],
            'post' => [
                'summary' => 'Add to wishlist',
                'description' => 'Add a product to wishlist',
                'security' => [['BearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['product_id'],
                                'properties' => [
                                    'product_id' => ['type' => 'integer']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Product added to wishlist'
                    ]
                ]
            ],
            'delete' => [
                'summary' => 'Remove from wishlist',
                'description' => 'Remove a product from wishlist',
                'security' => [['BearerAuth' => []]],
                'parameters' => [
                    [
                        'name' => 'product_id',
                        'in' => 'query',
                        'required' => true,
                        'schema' => ['type' => 'integer']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Product removed from wishlist'
                    ]
                ]
            ]
        ],
        
        // Search Endpoints
        '/search.php' => [
            'get' => [
                'summary' => 'Advanced search',
                'description' => 'Search products with filters',
                'parameters' => [
                    [
                        'name' => 'search',
                        'in' => 'query',
                        'schema' => ['type' => 'string']
                    ],
                    [
                        'name' => 'category',
                        'in' => 'query',
                        'description' => 'Comma-separated category IDs',
                        'schema' => ['type' => 'string']
                    ],
                    [
                        'name' => 'min_price',
                        'in' => 'query',
                        'schema' => ['type' => 'number']
                    ],
                    [
                        'name' => 'max_price',
                        'in' => 'query',
                        'schema' => ['type' => 'number']
                    ],
                    [
                        'name' => 'brand',
                        'in' => 'query',
                        'schema' => ['type' => 'string']
                    ],
                    [
                        'name' => 'min_rating',
                        'in' => 'query',
                        'schema' => ['type' => 'number']
                    ],
                    [
                        'name' => 'in_stock',
                        'in' => 'query',
                        'schema' => ['type' => 'boolean']
                    ],
                    [
                        'name' => 'sort',
                        'in' => 'query',
                        'schema' => ['type' => 'string']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Search results retrieved'
                    ]
                ]
            ]
        ]
    ]
];

echo json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
