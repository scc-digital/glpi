<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace Glpi\Api\HL;

use Glpi\Api\HL\Controller\ProjectController;
use Glpi\Api\HL\Doc\Response;
use Glpi\Api\HL\Doc\SchemaReference;

/**
 * @phpstan-type OpenAPIInfo array{title: string, version: string, license: array{name: string, url: string}}
 * @phpstan-type SecuritySchemaComponent array{type: string, schema?: string, name?: string, in?: string}
 * @phpstan-type ResponseSchema array{description: string}
 * @phpstan-type SchemaArray array{
 *      type: string,
 *      format?: string,
 *      pattern?: string,
 *      properties?: array<string, array{type: string, format?: string}>
 *  }
 * @phpstan-type PathParameterSchema array{
 *      name: string,
 *      in: string,
 *      description: string,
 *      required: 'true'|'false',
 *      schema?: mixed
 * }
 * @phpstan-type PathSchema array{
 *      tags: string[],
 *      responses: array<string|int, ResponseSchema>,
 *      description?: string,
 *      parameters: PathParameterSchema[],
 *      requestBody?: RequestBodySchema,
 * }
 * @phpstan-type RequestBodySchema array{content: array{'application/json': array{schema: SchemaArray}}}
 */
final class OpenAPIGenerator
{
    public const OPENAPI_VERSION = '3.0.0';

    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    private function getPublicVendorExtensions(): array
    {
        return ['x-writeonly', 'x-readonly', 'x-full-schema'];
    }

    private function cleanVendorExtensions(array $schema, ?string $parent_key = null): array
    {
        $to_keep = $this->getPublicVendorExtensions();
        // Recursively walk through every key of the schema
        foreach ($schema as $key => &$value) {
            // If the key is a vendor extension
            // If the key is not a public vendor extension
            if (str_starts_with($key, 'x-') && !in_array($key, $to_keep, true)) {
                // Remove the key from the schema
                unset($schema[$key]);
                continue;
            }
            if ($parent_key === 'properties') {
                if ($key === 'id') {
                    //Implicitly set the id property as read-only
                    $value['x-readonly'] = true;
                }
            }
            // If the value is an array
            if (is_array($value)) {
                // Clean the value
                $schema[$key] = $this->cleanVendorExtensions($value, $key);
            }
        }
        return $schema;
    }

    /**
     * @return array
     * @phpstan-return OpenAPIInfo
     */
    private function getInfo(): array
    {
        $description = <<<EOT
The High-Level REST API documentation shown here is dynamically generated from the core GLPI code and any enabled plugins.
If a plugin is not enabled, its routes will not be shown here.
EOT;

        return [
            'title' => 'GLPI High-Level REST API',
            'description' => $description,
            'version' => Router::API_VERSION,
            'license' => [
                'name' => 'GNU General Public License v3 or later',
                'url' => 'https://www.gnu.org/licenses/gpl-3.0.html',
            ],
        ];
    }

    public static function getComponentSchemas(): array
    {
        static $schemas = null;

        if ($schemas === null) {
            $schemas = [];

            $controllers = Router::getInstance()->getControllers();
            foreach ($controllers as $controller) {
                $known_schemas = $controller::getKnownSchemas();
                $short_name = (new \ReflectionClass($controller))->getShortName();
                $controller_name = str_replace('Controller', '', $short_name);
                foreach ($known_schemas as $schema_name => $known_schema) {
                    // Ignore schemas starting with an underscore. They are only used internally.
                    if (str_starts_with($schema_name, '_')) {
                        continue;
                    }
                    $calculated_name = $schema_name;
                    if (isset($schemas[$schema_name])) {
                        // For now, set the new calculated name to the short name of the controller + the schema name
                        $calculated_name = $controller_name . ' - ' . $schema_name;
                        // Change the existing schema name to its own calculated name
                        $other_short_name = (new \ReflectionClass($schemas[$schema_name]['x-controller']))->getShortName();
                        $other_calculated_name = str_replace('Controller', '', $other_short_name) . ' - ' . $schema_name;
                        $schemas[$other_calculated_name] = $schemas[$schema_name];
                        unset($schemas[$schema_name]);
                    }
                    $schemas[$calculated_name] = $known_schema;
                    $schemas[$calculated_name]['x-controller'] = $controller::class;
                    $schemas[$calculated_name]['x-schemaname'] = $schema_name;
                }
            }
        }

        return $schemas;
    }

    private function getComponentReference(string $name, string $controller): ?array
    {
        $components = self::getComponentSchemas();
        // Try matching by name and controller first
        $match = null;
        $is_ref_array = str_ends_with($name, '[]');
        if ($is_ref_array) {
            $name = substr($name, 0, -2);
        }
        foreach ($components as $component_name => $component) {
            if ($component['x-controller'] === $controller && $component['x-schemaname'] === $name) {
                $match = $component_name;
                break;
            }
        }
        // If no match was found, try matching by name only
        if ($match === null) {
            foreach ($components as $component_name => $component) {
                if ($component['x-schemaname'] === $name) {
                    $match = $component_name;
                    break;
                }
            }
        }
        if ($match === null) {
            return null;
        }
        if ($is_ref_array) {
            return [
                'type' => 'array',
                'items' => [
                    '$ref' => '#/components/schemas/' . $match,
                ],
            ];
        }
        return [
            '$ref' => '#/components/schemas/' . $match,
        ];
    }

    /**
     * @return array{openapi: string, info: OpenAPIInfo, servers: array<array{url: string, description: string}>, components: array{securitySchemes: array<string, SecuritySchemaComponent>}, paths: array<string, array<string, PathSchema>>}
     */
    public function getSchema(): array
    {
        global $CFG_GLPI;

        $component_schemas = self::getComponentSchemas();
        ksort($component_schemas);
        $schema = [
            'openapi' => self::OPENAPI_VERSION,
            'info' => $this->getInfo(),
            'servers' => [
                [
                    'url' => $CFG_GLPI['url_base'] . '/api.php',
                    'description' => 'GLPI High-Level REST API'
                ]
            ],
            'components' => [
                'securitySchemes' => $this->getSecuritySchemeComponents(),
                'schemas' => $component_schemas,
            ]
        ];

        $routes = $this->router->getAllRoutes();
        $paths = [];

        foreach ($routes as $route_path) {
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $paths = array_merge_recursive($paths, $this->getPathSchemas($route_path));
        }

        $schema['paths'] = $this->expandGenericPaths($paths);

        // Clean vendor extensions
        if ($_SESSION['glpi_use_mode'] !== \Session::DEBUG_MODE) {
            $schema = $this->cleanVendorExtensions($schema);
        }

        return $schema;
    }

    /**
     * Replace any generic paths like `/Assets/{itemtype}` with the actual paths for each itemtype as long as the parameter pattern(s) are explicit lists.
     * Example: "Computer|Monitor|NetworkEquipment".
     * This method currently only expands paths based on the first parameter that can be expanded.
     * @param array $paths
     * @return array
     */
    private function expandGenericPaths(array $paths): array
    {
        $expanded = [];
        foreach ($paths as $path_url => $path) {
            foreach ($path as $method => $route) {
                $is_expanded = false;
                foreach ($route['parameters'] as $param_key => $param) {
                    if (isset($param['schema']['pattern']) && preg_match('/^[\w+|]+$/', $param['schema']['pattern'])) {
                        $itemtypes = explode('|', $param['schema']['pattern']);
                        foreach ($itemtypes as $itemtype) {
                            $new_url = str_replace('{itemtype}', $itemtype, $path_url);
                            // Check there isn't already a route for this URL
                            if (!isset($paths[$new_url][$method])) {
                                unset($route['parameters'][$param_key]);
                                $expanded[$new_url][$method] = $route;
                                $is_expanded = true;
                            }
                        }
                    }
                }
                if (!$is_expanded) {
                    $expanded[$path_url][$method] = $route;
                }
            }
        }
        return $expanded;
    }

    /**
     * @return array<string, SecuritySchemaComponent>
     */
    private function getSecuritySchemeComponents(): array
    {
        return [
            'oauth' => [
                'type' => 'oauth2',
                'flows' => [
                    'authorizationCode' => [
                        'authorizationUrl' => '/api.php/authorize',
                        'tokenUrl' => '/api.php/token',
                        'refreshUrl' => '/api.php/token',
                    ],
                    'password' => [
                        'tokenUrl' => '/api.php/token',
                    ]
                ]
            ],
            'basicAuth' => [
                'type' => 'http',
                'scheme' => 'basic',
            ],
            'userTokenAuth' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'Glpi-User-Token',
            ],
            'sessionTokenAuth' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'Glpi-Session-Token',
            ],
        ];
    }

    /**
     * @param Doc\Parameter $route_param
     * @return array
     * @phpstan-return SchemaArray
     */
    private function getRouteParamSchema(Doc\Parameter $route_param): array
    {
        return $route_param->getSchema()->toArray();
    }

    /**
     * @param RoutePath $route_path
     * @param string $route_method
     * @return RequestBodySchema|null
     */
    private function getRequestBodySchema(RoutePath $route_path, string $route_method): ?array
    {
        $route_doc = $route_path->getRouteDoc($route_method);
        if ($route_doc === null) {
            return null;
        }
        $request_params = array_filter($route_doc->getParameters(), static function (Doc\Parameter $param) {
            return $param->getLocation() === Doc\Parameter::LOCATION_BODY;
        });
        if (count($request_params) === 0) {
            return null;
        }
        $request_body = [
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [],
                    ]
                ]
            ]
        ];

        // If there is a parameter with the location of body and name of "_", it should be an object that represents the entire request body (or at least the base schema of it)
        $request_body_param = array_filter($request_params, static function (Doc\Parameter $param) {
            return $param->getName() === '_';
        });
        if (count($request_body_param) > 0) {
            $request_body_param = array_values($request_body_param)[0];
            if ($request_body_param->getSchema() instanceof SchemaReference) {
                $body_schema = $this->getComponentReference($request_body_param->getSchema()['ref'], $route_path->getController());
            } else {
                $body_schema = $request_body_param->getSchema()->toArray();
            }
            $request_body['content']['application/json']['schema'] = $body_schema;
        }

        foreach ($request_params as $route_param) {
            if ($route_param->getName() === '_') {
                continue;
            }
            $body_param = [
                'type' => $route_param->getSchema()->getType()
            ];
            if ($route_param->getSchema()->getFormat() !== null) {
                $body_param['format'] = $route_param->getSchema()->getFormat();
            }
            if (count($route_param->getSchema()->getProperties())) {
                $body_param['properties'] = $route_param->getSchema()->getProperties();
            }
            $request_body['content']['application/json']['schema']['properties'][$route_param->getName()] = $body_param;
        }
        return $request_body;
    }

    /**
     * @param Doc\Parameter $route_param
     * @return array
     * @phpstan-return PathParameterSchema
     */
    private function getPathParameterSchema(Doc\Parameter $route_param): array
    {
        $schema = $this->getRouteParamSchema($route_param);
        return [
            'name' => $route_param->getName(),
            'description' => $route_param->getDescription(),
            'in' => $route_param->getLocation(),
            'required' => $route_param->getRequired() ? 'true' : 'false',
            'schema' => $schema
        ];
    }

    /**
     * @param RoutePath $route_path
     * @param string $route_method
     * @return array<string, array<string, mixed>>[]
     */
    private function getPathSecuritySchema(RoutePath $route_path, string $route_method): array
    {
        $schemas = [
            [
                'oauth' => []
            ]
        ];
        // Handle special Session case
        if ($route_method === 'POST' && $route_path->getRoutePath() === '/Session') {
            $schemas = array_merge($schemas, [
                [
                    'basicAuth' => []
                ],
                [
                    'userTokenAuth' => []
                ]
            ]);
        } else if ($route_path->getRouteSecurityLevel() !== Route::SECURITY_NONE) {
            $schemas = array_merge($schemas, [
                [
                    'sessionTokenAuth' => []
                ]
            ]);
        }

        return $schemas;
    }

    private function getPathResponseSchemas(RoutePath $route_path, string $method): array
    {
        $route_doc = $route_path->getRouteDoc($method);
        if ($route_doc === null) {
            return [];
        }
        $responses = $route_doc->getResponses();
        $response_schemas = [];
        foreach ($responses as $response) {
            if ($response->isReference()) {
                $resolved_schema = $this->getComponentReference($response->getSchema()['ref'], $route_path->getController());
            } else {
                $resolved_schema = $response->getSchema()->toArray();
            }
            $response_media_type = $response->getMediaType();
            $response_schema = [
                'description' => $response->getDescription(),
                'content' => [
                    $response_media_type => [
                        'schema' => $resolved_schema
                    ]
                ],
            ];
            if ($response_media_type === 'application/json') {
                // add csv and xml
                $response_schema['content']['text/csv'] = [
                    'schema' => $resolved_schema
                ];
                $response_schema['content']['application/xml'] = [
                    'schema' => $resolved_schema
                ];
            }
            $response_schemas[$response->getStatusCode()] = $response_schema;
        }
        return $response_schemas;
    }

    /**
     * @param RoutePath $route_path
     * @return array<string, array<string, PathSchema>>
     */
    private function getPathSchemas(RoutePath $route_path): array
    {
        $path_schemas = [];
        $route_methods = $route_path->getRouteMethods();

        foreach ($route_methods as $route_method) {
            $route_doc = $route_path->getRouteDoc($route_method);
            $method = strtolower($route_method);
            $response_schema = $this->getPathResponseSchemas($route_path, $route_method);
            $path_schema = [
                'tags' => $route_path->getRouteTags(),
                'responses' => $response_schema,
            ];
            if (!isset($path_schema['responses']['200'])) {
                $path_schema['responses']['200'] = [
                    'description' => 'Success'
                ];
            } else {
                $path_schema['responses']['200']['produces'] = array_keys($response_schema[200]['content']);
            }
            if (!isset($path_schema['responses']['500'])) {
                $path_schema['responses']['500'] = [
                    'description' => 'Internal server error'
                ];
            }
            $request_body = $this->getRequestBodySchema($route_path, $route_method);

            if ($route_doc !== null) {
                $path_schema['description'] = $route_doc->getDescription();
            }

            $requirements = $route_path->getRouteRequirements();
            if (count($requirements)) {
                $path_schema['parameters'] = [];
                if ($route_doc !== null) {
                    $route_params = $route_doc->getParameters();
                    if (count($route_params) > 0) {
                        foreach ($route_params as $route_param) {
                            if (!array_key_exists($route_param->getName(), $requirements)) {
                                continue;
                            }
                            $location = $route_param->getLocation();
                            if ($location !== Doc\Parameter::LOCATION_BODY) {
                                $path_schema['parameters'][$route_param->getName()] = $this->getPathParameterSchema($route_param);
                            }
                        }
                    }
                }
                foreach ($requirements as $name => $requirement) {
                    if (!str_contains($route_path->getRoutePath(), '{' . $name . '}')) {
                        continue;
                    }
                    if (is_callable($requirement)) {
                        $values = $requirement();
                        $requirement = implode('|', $values);
                    }
                    if ($requirement === '\d+') {
                        $param = [
                            'name' => $name,
                            'in' => 'path',
                            'required' => 'true',
                            'schema' => [
                                'type' => 'integer',
                                'pattern' => $requirement
                            ]
                        ];
                    } else {
                        $param = [
                            'name' => $name,
                            'in' => 'path',
                            'required' => 'true',
                            'schema' => [
                                'type' => 'string',
                                'pattern' => $requirement
                            ]
                        ];
                    }

                    $existing = $path_schema['parameters'][$param['name']] ?? [];
                    $path_schema['parameters'][$param['name']] = [
                        'name' => $existing['name'] ?? $param['name'],
                        'description' => $existing['description'] ?? '',
                        'in' => $existing['in'] ?? $param['in'],
                        'required' => $existing['required'] ?? $param['required'],
                    ];
                    /** @var SchemaArray $combined_schema */
                    $combined_schema = $param['schema'];
                    if (!empty($existing['schema'])) {
                        $combined_schema = array_replace($existing['schema'], $param['schema']);
                    }
                    $path_schema['parameters'][$param['name']]['schema'] = $combined_schema;
                }
            }

            if (strcasecmp($method, 'delete') && $request_body !== null) {
                $path_schema['requestBody'] = $request_body;
            }
            $path_schema['security'] = $this->getPathSecuritySchema($route_path, $route_method);
            $path_schema['parameters'] = array_values($path_schema['parameters'] ?? []);
            $path_schemas[$method] = $path_schema;
        }
        return [
            $route_path->getRoutePath() => $path_schemas
        ];
    }
}
