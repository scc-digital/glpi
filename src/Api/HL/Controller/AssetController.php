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

namespace Glpi\Api\HL\Controller;

use AutoUpdateSystem;
use CommonDBTM;
use Entity;
use Glpi\Api\HL\Doc as Doc;
use Glpi\Api\HL\Middleware\ResultFormatterMiddleware;
use Glpi\Api\HL\Route;
use Glpi\Api\HL\Search;
use Glpi\Http\JSONResponse;
use Glpi\Http\Request;
use Glpi\Http\Response;
use Glpi\Socket;
use Glpi\SocketModel;
use Group;
use Location;
use Manufacturer;
use Network;
use State;
use User;

#[Route(path: '/Assets', priority: 1, tags: ['Assets'])]
#[Doc\Route(
    parameters: [
        [
            'name' => 'itemtype',
            'description' => 'Asset type',
            'location' => Doc\Parameter::LOCATION_PATH,
            'schema' => ['type' => Doc\Schema::TYPE_STRING]
        ],
        [
            'name' => 'id',
            'description' => 'The ID of the Asset',
            'location' => Doc\Parameter::LOCATION_PATH,
            'schema' => ['type' => Doc\Schema::TYPE_INTEGER]
        ]
    ]
)]
final class AssetController extends AbstractController
{
    use CRUDControllerTrait;

    public static function getRawKnownSchemas(): array
    {
        global $CFG_GLPI;
        $schemas = [];

        $schemas['_BaseAsset'] = [
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['PrinterModel'] = [
            'x-itemtype' => \PrinterModel::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'product_number' => ['type' => Doc\Schema::TYPE_STRING],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['SoftwareCategory'] = [
            'x-itemtype' => \SoftwareCategory::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'completename' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'parent' => self::getDropdownTypeSchema(class: \SoftwareCategory::class, full_schema: 'SoftwareCategory'),
                'level' => ['type' => Doc\Schema::TYPE_INTEGER],
            ]
        ];

        $schemas['OperatingSystem'] = [
            'x-itemtype' => \OperatingSystem::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['RackModel'] = [
            'x-itemtype' => \RackModel::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'product_number' => ['type' => Doc\Schema::TYPE_STRING],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['RackType'] = [
            'x-itemtype' => \RackType::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: \Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['PDUModel'] = [
            'x-itemtype' => \PDUModel::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'product_number' => ['type' => Doc\Schema::TYPE_STRING],
                'weight' => ['type' => Doc\Schema::TYPE_INTEGER],
                'rack_units' => ['x-field' => 'required_units', 'type' => Doc\Schema::TYPE_INTEGER],
                'depth' => ['type' => Doc\Schema::TYPE_NUMBER, 'format' => Doc\Schema::FORMAT_NUMBER_FLOAT],
                'power_connections' => ['type' => Doc\Schema::TYPE_INTEGER],
                'max_power' => ['type' => Doc\Schema::TYPE_INTEGER],
                'is_half_rack' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_rackable' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['PDUType'] = [
            'x-itemtype' => \PDUType::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: \Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['PassiveDCEquipmentModel'] = [
            'x-itemtype' => \PassiveDCEquipmentModel::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'product_number' => ['type' => Doc\Schema::TYPE_STRING],
                'weight' => ['type' => Doc\Schema::TYPE_INTEGER],
                'rack_units' => ['x-field' => 'required_units', 'type' => Doc\Schema::TYPE_INTEGER],
                'depth' => ['type' => Doc\Schema::TYPE_NUMBER, 'format' => Doc\Schema::FORMAT_NUMBER_FLOAT],
                'power_connections' => ['type' => Doc\Schema::TYPE_INTEGER],
                'power_consumption' => ['type' => Doc\Schema::TYPE_INTEGER],
                'max_power' => ['type' => Doc\Schema::TYPE_INTEGER],
                'is_half_rack' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['PassiveDCEquipmentType'] = [
            'x-itemtype' => \PassiveDCEquipmentType::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['SocketModel'] = [
            'x-itemtype' => SocketModel::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['NetworkPort'] = [
            'x-itemtype' => \NetworkPort::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: \Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'logical_number' => ['type' => Doc\Schema::TYPE_INTEGER],
                'mac' => ['type' => Doc\Schema::TYPE_STRING],
                'is_deleted' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_dynamic' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'if_mtu' => ['x-field' => 'ifmtu', 'type' => Doc\Schema::TYPE_INTEGER],
                'if_speed' => ['x-field' => 'ifspeed', 'type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'if_internal_status' => ['x-field' => 'ifinternalstatus', 'type' => Doc\Schema::TYPE_STRING],
                'if_connection_status' => ['x-field' => 'ifconnectionstatus', 'type' => Doc\Schema::TYPE_INTEGER],
                'if_last_change' => ['x-field' => 'iflastchange', 'type' => Doc\Schema::TYPE_STRING],
                'if_in_bytes' => ['x-field' => 'ifinbytes', 'type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'if_out_bytes' => ['x-field' => 'ifoutbytes', 'type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'if_in_errors' => ['x-field' => 'ifinerrors', 'type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'if_out_errors' => ['x-field' => 'ifouterrors', 'type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'if_status' => ['x-field' => 'ifstatus', 'type' => Doc\Schema::TYPE_STRING],
                'if_description' => ['x-field' => 'ifdescr', 'type' => Doc\Schema::TYPE_STRING],
                'if_alias' => ['x-field' => 'ifalias', 'type' => Doc\Schema::TYPE_STRING],
                'port_duplex' => ['x-field' => 'portduplex', 'type' => Doc\Schema::TYPE_STRING],
                'trunk' => ['type' => Doc\Schema::TYPE_INTEGER],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['DCRoom'] = [
            'x-itemtype' => \DCRoom::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: \Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_deleted' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'location' => self::getDropdownTypeSchema(class: \Location::class, full_schema: 'Location'),
                'datacenter' => self::getDropdownTypeSchema(class: \Datacenter::class, full_schema: 'DataCenter'),
                'rows' => ['x-field' => 'vis_rows', 'type' => Doc\Schema::TYPE_INTEGER],
                'cols' => ['x-field' => 'vis_cols', 'type' => Doc\Schema::TYPE_INTEGER],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $asset_types = self::getAssetTypes();

        foreach ($asset_types as $asset_type) {
            if (!is_subclass_of($asset_type, CommonDBTM::class)) {
                continue;
            }
            // Replace namespace separator with underscore
            $schema_name = str_replace('\\', '_', $asset_type);
            $schemas[$schema_name] = $schemas['_BaseAsset'];
            $schemas[$schema_name]['x-itemtype'] = $asset_type;

            // Need instance since some fields are not static even if they aren't related to instances
            $asset = new $asset_type();

            if (in_array($asset_type, $CFG_GLPI['state_types'], true)) {
                $schemas[$schema_name]['properties']['status'] = self::getDropdownTypeSchema(class: State::class, full_schema: 'State');
            }

            if (in_array($asset_type, $CFG_GLPI['location_types'], true)) {
                $schemas[$schema_name]['properties']['location'] = self::getDropdownTypeSchema(class: Location::class, full_schema: 'Location');
            }

            if ($asset->isEntityAssign()) {
                $schemas[$schema_name]['properties']['entity'] = self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity');
                // Add completename field
                $schemas[$schema_name]['properties']['entity']['properties']['completename'] = ['type' => Doc\Schema::TYPE_STRING];
                $schemas[$schema_name]['properties']['is_recursive'] = ['type' => Doc\Schema::TYPE_BOOLEAN];
            }

            $type_class = $asset->getTypeClass();
            if ($type_class !== null) {
                $expected_schema_name = $type_class;
                $schemas[$schema_name]['properties']['type'] = self::getDropdownTypeSchema(
                    class: $type_class,
                    full_schema: $schemas[$expected_schema_name] ?? null
                );
            }
            if ($asset->isField('manufacturers_id')) {
                $schemas[$schema_name]['properties']['manufacturer'] = self::getDropdownTypeSchema(class: Manufacturer::class, full_schema: 'Manufacturer');
            }
            $model_class = $asset->getModelClass();
            if ($model_class !== null) {
                $expected_schema_name = $type_class;
                $schemas[$schema_name]['properties']['model'] = self::getDropdownTypeSchema(
                    class: $model_class,
                    full_schema: $schemas[$expected_schema_name] ?? null
                );
            }

            if (in_array($asset_type, $CFG_GLPI['linkuser_tech_types'], true)) {
                $schemas[$schema_name]['properties']['user_tech'] = self::getDropdownTypeSchema(
                    class: User::class,
                    field: 'users_id_tech',
                    full_schema: 'User'
                );
            }
            if (in_array($asset_type, $CFG_GLPI['linkgroup_tech_types'], true)) {
                $schemas[$schema_name]['properties']['group_tech'] = self::getDropdownTypeSchema(
                    class: Group::class,
                    field: 'groups_id_tech',
                    full_schema: 'Group'
                );
            }
            if (in_array($asset_type, $CFG_GLPI['linkuser_types'], true)) {
                $schemas[$schema_name]['properties']['user'] = self::getDropdownTypeSchema(
                    class: User::class,
                    field: 'users_id',
                    full_schema: 'User'
                );
            }
            if (in_array($asset_type, $CFG_GLPI['linkgroup_types'], true)) {
                $schemas[$schema_name]['properties']['group'] = self::getDropdownTypeSchema(
                    class: Group::class,
                    field: 'groups_id',
                    full_schema: 'Group'
                );
            }

            if ($asset->isField('contact')) {
                $schemas[$schema_name]['properties']['contact'] = ['type' => Doc\Schema::TYPE_STRING];
            }
            if ($asset->isField('contact_num')) {
                $schemas[$schema_name]['properties']['contact_num'] = ['type' => Doc\Schema::TYPE_STRING];
            }
            if ($asset->isField('serial')) {
                $schemas[$schema_name]['properties']['serial'] = ['type' => Doc\Schema::TYPE_STRING];
            }
            if ($asset->isField('otherserial')) {
                $schemas[$schema_name]['properties']['otherserial'] = ['type' => Doc\Schema::TYPE_STRING];
            }
            if ($asset->isField('networks_id')) {
                $schemas[$schema_name]['properties']['network'] = self::getDropdownTypeSchema(Network::class);
            }

            if ($asset->isField('uuid')) {
                $schemas[$schema_name]['properties']['uuid'] = ['type' => Doc\Schema::TYPE_STRING];
            }
            if ($asset->isField('autoupdatesystems_id')) {
                $schemas[$schema_name]['properties']['autoupdatesystem'] = self::getDropdownTypeSchema(AutoUpdateSystem::class);
            }

            if ($asset->maybeDeleted()) {
                $schemas[$schema_name]['properties']['is_deleted'] = ['type' => Doc\Schema::TYPE_BOOLEAN];
            }
        }

        $schemas['Cartridge'] = [
            'x-itemtype' => \Cartridge::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'entities_id' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'cartridgeitems_id' => self::getDropdownTypeSchema(class: \CartridgeItem::class, full_schema: 'CartridgeItem'),
                'pages' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'date_in' => [
                    'type' => Doc\Schema::TYPE_STRING,
                    'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                    'x-readonly' => true,
                ],
                'date_use' => [
                    'type' => Doc\Schema::TYPE_STRING,
                    'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                    'x-readonly' => true,
                ],
                'date_out' => [
                    'type' => Doc\Schema::TYPE_STRING,
                    'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                    'x-readonly' => true,
                ],
                'date_creation' => [
                    'type' => Doc\Schema::TYPE_STRING,
                    'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                    'x-readonly' => true,
                ],
                'date_mod' => [
                    'type' => Doc\Schema::TYPE_STRING,
                    'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                    'x-readonly' => true,
                ],
            ]
        ];

        $schemas['CartridgeItem'] = [
            'x-itemtype' => \CartridgeItem::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'printer_models' => [
                    'type' => Doc\Schema::TYPE_ARRAY,
                    'description' => 'List of printer models that can use this cartridge',
                    'items' => [
                        'type' => Doc\Schema::TYPE_OBJECT,
                        'x-full-schema' => 'PrinterModel',
                        'x-join' => [
                            'table' => \PrinterModel::getTable(),
                            'fkey' => 'printermodels_id',
                            'field' => 'id',
                            'ref_join' => [
                                'table' => \CartridgeItem_PrinterModel::getTable(),
                                'fkey' => 'id', // The ID field of the main table used to refer to the cartridgeitems_id of the joined table
                                'field' => \CartridgeItem::getForeignKeyField(),
                            ]
                        ],
                        'properties' => [
                            'id' => [
                                'type' => Doc\Schema::TYPE_INTEGER,
                                'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                                'x-readonly' => true,
                            ],
                            'name' => ['type' => Doc\Schema::TYPE_STRING],
                            'comment' => ['type' => Doc\Schema::TYPE_STRING],
                        ]
                    ]
                ],
                'cartridges' => [
                    'type' => Doc\Schema::TYPE_ARRAY,
                    'description' => 'List of cartridges',
                    'items' => [
                        'type' => Doc\Schema::TYPE_OBJECT,
                        'x-full-schema' => 'Cartridge',
                        'x-join' => [
                            'table' => \Cartridge::getTable(),
                            'fkey' => 'id',
                            'field' => \CartridgeItem::getForeignKeyField(),
                        ],
                        'properties' => [
                            'id' => [
                                'type' => Doc\Schema::TYPE_INTEGER,
                                'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                                'x-readonly' => true,
                            ],
                            'pages' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                            'date_in' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                            'date_use' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                            'date_out' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                            'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                            'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                        ]
                    ]
                ]
            ]
        ];

        $schemas['Consumable'] = [
            'x-itemtype' => \Consumable::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'entities_id' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'consumableitems_id' => self::getDropdownTypeSchema(class: \ConsumableItem::class, full_schema: 'ConsumableItem'),
                'date_in' => [
                    'type' => Doc\Schema::TYPE_STRING,
                    'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                    'x-readonly' => true,
                ],
                'date_out' => [
                    'type' => Doc\Schema::TYPE_STRING,
                    'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                    'x-readonly' => true,
                ],
                'date_creation' => [
                    'type' => Doc\Schema::TYPE_STRING,
                    'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                    'x-readonly' => true,
                ],
                'date_mod' => [
                    'type' => Doc\Schema::TYPE_STRING,
                    'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                    'x-readonly' => true,
                ],
                'itemtype' => ['type' => Doc\Schema::TYPE_STRING],
                'items_id' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
            ]
        ];

        $schemas['ConsumableItem'] = [
            'x-itemtype' => \ConsumableItem::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'consumables' => [
                    'type' => Doc\Schema::TYPE_ARRAY,
                    'description' => 'List of consumables',
                    'items' => [
                        'type' => Doc\Schema::TYPE_OBJECT,
                        'x-full-schema' => 'Consumable',
                        'x-join' => [
                            'table' => \Consumable::getTable(),
                            'fkey' => 'id',
                            'field' => \ConsumableItem::getForeignKeyField(),
                        ],
                        'properties' => [
                            'id' => [
                                'type' => Doc\Schema::TYPE_INTEGER,
                                'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                                'x-readonly' => true,
                            ],
                            'date_in' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                            'date_out' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                            'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                            'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                            'itemtype' => ['type' => Doc\Schema::TYPE_STRING],
                            'items_id' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                        ]
                    ]
                ]
            ]
        ];

        $schemas['Software'] = [
            'x-itemtype' => \Software::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'location' => self::getDropdownTypeSchema(class: Location::class, full_schema: 'Location'),
                'category' => self::getDropdownTypeSchema(class: \SoftwareCategory::class, full_schema: 'SoftwareCategory'),
                'manufacturer' => self::getDropdownTypeSchema(class: Manufacturer::class, full_schema: 'Manufacturer'),
                'parent' => self::getDropdownTypeSchema(class: \Software::class, full_schema: 'Software'),
                'is_helpdesk_visible' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'user' => self::getDropdownTypeSchema(class: User::class, full_schema: 'User'),
                'group' => self::getDropdownTypeSchema(class: Group::class, full_schema: 'Group'),
                'user_tech' => self::getDropdownTypeSchema(class: User::class, field: 'users_id_tech', full_schema: 'User'),
                'group_tech' => self::getDropdownTypeSchema(class: Group::class, field: 'groups_id_tech', full_schema: 'Group'),
                'is_deleted' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_update' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_valid' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['SoftwareVersion'] = [
            'x-itemtype' => \SoftwareVersion::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'arch' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'software' => self::getDropdownTypeSchema(class: \Software::class, full_schema: 'Software'),
                'state' => self::getDropdownTypeSchema(class: State::class, full_schema: 'State'),
                'operating_system' => self::getDropdownTypeSchema(class: \OperatingSystem::class, full_schema: 'OperatingSystem'),
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['Rack'] = [
            'x-itemtype' => \Rack::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'location' => self::getDropdownTypeSchema(class: Location::class, full_schema: 'Location'),
                'serial' => ['type' => Doc\Schema::TYPE_STRING],
                'otherserial' => ['type' => Doc\Schema::TYPE_STRING],
                'model' => self::getDropdownTypeSchema(class: \RackModel::class, full_schema: 'RackModel'),
                'manufacturer' => self::getDropdownTypeSchema(class: Manufacturer::class, full_schema: 'Manufacturer'),
                'type' => self::getDropdownTypeSchema(class: \RackType::class, full_schema: 'RackType'),
                'state' => self::getDropdownTypeSchema(class: \State::class, full_schema: 'State'),
                'user_tech' => self::getDropdownTypeSchema(class: User::class, field: 'users_id_tech', full_schema: 'User'),
                'group_tech' => self::getDropdownTypeSchema(class: Group::class, field: 'groups_id_tech', full_schema: 'Group'),
                'width' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'height' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'depth' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'number_units' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'is_deleted' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'room' => self::getDropdownTypeSchema(class: \DCRoom::class, full_schema: 'DCRoom'),
                'room_orientation' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'position' => ['type' => Doc\Schema::TYPE_STRING],
                'bgcolor' => ['type' => Doc\Schema::TYPE_STRING],
                'max_power' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'measured_power' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT32,
                    'x-field' => 'mesured_power' // Took liberty to fix typo in DB without having to mess with the DB itself or other code
                ],
                'max_weight' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['Enclosure'] = [
            'x-itemtype' => \Enclosure::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'location' => self::getDropdownTypeSchema(class: Location::class, full_schema: 'Location'),
                'serial' => ['type' => Doc\Schema::TYPE_STRING],
                'otherserial' => ['type' => Doc\Schema::TYPE_STRING],
                'model' => self::getDropdownTypeSchema(\EnclosureModel::class),
                'manufacturer' => self::getDropdownTypeSchema(class: Manufacturer::class, full_schema: 'Manufacturer'),
                'state' => self::getDropdownTypeSchema(class: \State::class, full_schema: 'State'),
                'user_tech' => self::getDropdownTypeSchema(class: User::class, field: 'users_id_tech', full_schema: 'User'),
                'group_tech' => self::getDropdownTypeSchema(class: Group::class, field: 'groups_id_tech', full_schema: 'Group'),
                'is_deleted' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'orientation' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'power_supplies' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['PDU'] = [
            'x-itemtype' => \PDU::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'location' => self::getDropdownTypeSchema(class: Location::class, full_schema: 'Location'),
                'serial' => ['type' => Doc\Schema::TYPE_STRING],
                'otherserial' => ['type' => Doc\Schema::TYPE_STRING],
                'model' => self::getDropdownTypeSchema(class: \PDUModel::class, full_schema: 'PDUModel'),
                'manufacturer' => self::getDropdownTypeSchema(class: Manufacturer::class, full_schema: 'Manufacturer'),
                'type' => self::getDropdownTypeSchema(class: \PDUType::class, full_schema: 'PDUType'),
                'state' => self::getDropdownTypeSchema(class: \State::class, full_schema: 'State'),
                'user_tech' => self::getDropdownTypeSchema(class: User::class, field: 'users_id_tech', full_schema: 'User'),
                'group_tech' => self::getDropdownTypeSchema(class: Group::class, field: 'groups_id_tech', full_schema: 'Group'),
                'is_deleted' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['PassiveDCEquipment'] = [
            'x-itemtype' => \PassiveDCEquipment::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'location' => self::getDropdownTypeSchema(class: Location::class, full_schema: 'Location'),
                'serial' => ['type' => Doc\Schema::TYPE_STRING],
                'otherserial' => ['type' => Doc\Schema::TYPE_STRING],
                'model' => self::getDropdownTypeSchema(class: \PassiveDCEquipmentModel::class, full_schema: 'PassiveDCEquipmentModel'),
                'manufacturer' => self::getDropdownTypeSchema(class: Manufacturer::class, full_schema: 'Manufacturer'),
                'type' => self::getDropdownTypeSchema(class: \PassiveDCEquipmentType::class, full_schema: 'PassiveDCEquipmentType'),
                'state' => self::getDropdownTypeSchema(class: \State::class, full_schema: 'State'),
                'user_tech' => self::getDropdownTypeSchema(class: User::class, field: 'users_id_tech', full_schema: 'User'),
                'group_tech' => self::getDropdownTypeSchema(class: Group::class, field: 'groups_id_tech', full_schema: 'Group'),
                'is_deleted' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['Cable'] = [
            'x-itemtype' => \Cable::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'otherserial' => ['type' => Doc\Schema::TYPE_STRING],
                'state' => self::getDropdownTypeSchema(class: \State::class, full_schema: 'State'),
                'user_tech' => self::getDropdownTypeSchema(class: User::class, field: 'users_id_tech', full_schema: 'User'),
                'is_deleted' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'itemtype_endpoint_a' => ['type' => Doc\Schema::TYPE_STRING],
                'items_id_endpoint_a' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'socketmodel_endpoint_a' => self::getDropdownTypeSchema(
                    class: SocketModel::class,
                    field: 'socketmodels_id_endpoint_a',
                    full_schema: 'SocketModel'
                ),
                'sockets_id_endpoint_a' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'itemtype_endpoint_b' => ['type' => Doc\Schema::TYPE_STRING],
                'items_id_endpoint_b' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'socketmodel_endpoint_b' => self::getDropdownTypeSchema(
                    class: SocketModel::class,
                    field: 'socketmodels_id_endpoint_b',
                    full_schema: 'SocketModel'
                ),
                'sockets_id_endpoint_b' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['Socket'] = [
            'x-itemtype' => Socket::class,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'location' => self::getDropdownTypeSchema(class: Location::class, full_schema: 'Location'),
                'model' => self::getDropdownTypeSchema(class: SocketModel::class, full_schema: 'SocketModel'),
                'wiring_side' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32],
                'itemtype' => ['type' => Doc\Schema::TYPE_STRING],
                'items_id' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'network_port' => self::getDropdownTypeSchema(class: \NetworkPort::class, full_schema: 'NetworkPort'),
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['CommonAsset'] = self::getGlobalAssetSchema($schemas);

        return $schemas;
    }

    /**
     * @param bool $classes_only If true, only the class names are returned. If false, the class name => localized name pairs are returned..
     * @return array<class-string<CommonDBTM>, string>
     */
    public static function getAssetTypes(bool $classes_only = true): array
    {
        global $CFG_GLPI;

        static $assets = null;

        if ($assets === null) {
            $assets = [];
            $types = $CFG_GLPI["asset_types"];
            /**
             * @var class-string<CommonDBTM> $type
             */
            foreach ($types as $type) {
                $assets[$type] = $type::getTypeName(1);
            }
        }
        return $classes_only ? array_keys($assets) : $assets;
    }

    #[Route(path: '/', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get all available asset types',
        methods: ['GET'],
        responses: [
            '200' => [
                'description' => 'List of asset types',
                'schema' => [
                    'type' => Doc\Schema::TYPE_ARRAY,
                    'items' => [
                        'type' => Doc\Schema::TYPE_OBJECT,
                        'properties' => [
                            'itemtype' => ['type' => Doc\Schema::TYPE_STRING],
                            'name' => ['type' => Doc\Schema::TYPE_STRING],
                            'href' => ['type' => Doc\Schema::TYPE_STRING],
                        ],
                    ],
                ]
            ]
        ]
    )]
    public function index(Request $request): Response
    {
        $asset_types = self::getAssetTypes(false);
        $asset_paths = [];
        foreach ($asset_types as $asset_type => $asset_name) {
            $asset_paths[] = [
                'itemtype'  => $asset_type,
                'name'      => $asset_name,
                'href'      => self::getAPIPathForRouteFunction(self::class, 'search', ['itemtype' => $asset_type]),
            ];
        }
        return new JSONResponse($asset_paths);
    }

    private static function getGlobalAssetSchema($asset_schemas)
    {
        $asset_types = self::getAssetTypes();
        $asset_schemas = array_filter($asset_schemas, static function ($key) use ($asset_types) {
            return !str_starts_with($key, '_') && in_array($key, $asset_types, true);
        }, ARRAY_FILTER_USE_KEY);

        $shared_properties = [];
        $subtype_info = [];
        foreach ($asset_schemas as $schema_name => $schema) {
            $itemtype = $schema['x-itemtype'];
            // Need to check rights for each asset type
            if (!$itemtype::canView()) {
                continue;
            }
            $subtype_info[] = [
                'schema_name' => $schema_name,
                'itemtype' => $itemtype,
            ];
            if ($shared_properties === []) {
                $shared_properties = Doc\Schema::flattenProperties($schema['properties']);
                // Remove array properties (complex handling may be required. No support added for now)
                $shared_properties = array_filter($shared_properties, static function ($property) {
                    return !isset($property['type']) || $property['type'] !== Doc\Schema::TYPE_ARRAY;
                });
            } else {
                $props = Doc\Schema::flattenProperties($schema['properties']);
                foreach ($shared_properties as $key => $value) {
                    if (!array_key_exists($key, $props)) {
                        unset($shared_properties[$key]);
                    }
                }
            }
        }

        return [
            'x-subtypes' => $subtype_info,
            'type' => Doc\Schema::TYPE_OBJECT,
            'properties' => $shared_properties,
        ];
    }

    #[Route(path: '/Global', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search assets of all types',
        responses: [
            ['schema' => 'CommonAsset[]']
        ]
    )]
    public function searchAll(Request $request): Response
    {
        return Search::searchBySchema($this->getKnownSchema('CommonAsset'), $request->getParameters());
    }

    #[Route(path: '/{itemtype}', methods: ['GET'], requirements: [
        'itemtype' => [self::class, 'getAssetTypes']
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search assets of a specific type'
    )]
    public function search(Request $request): Response
    {
        $itemtype = $request->getAttribute('itemtype');
        return Search::searchBySchema($this->getKnownSchema($itemtype), $request->getParameters());
    }

    #[Route(path: '/{itemtype}/{id}', methods: ['GET'], requirements: [
        'itemtype' => [self::class, 'getAssetTypes'],
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get an asset of a specific type by ID',
    )]
    public function getItem(Request $request): Response
    {
        $itemtype = $request->getAttribute('itemtype');
        return Search::getOneBySchema($this->getKnownSchema($itemtype), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/{itemtype}', methods: ['POST'], requirements: [
        'itemtype' => [self::class, 'getAssetTypes'],
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create an asset of a specific type',
    )]
    public function createItem(Request $request): Response
    {
        $itemtype = $request->getAttribute('itemtype');
        return Search::createBySchema($this->getKnownSchema($itemtype), $request->getParameters() + ['itemtype' => $itemtype], [self::class, 'getItem']);
    }

    #[Route(path: '/{itemtype}/{id}', methods: ['PATCH'], requirements: [
        'itemtype' => [self::class, 'getAssetTypes'],
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update an asset of a specific type by ID',
    )]
    public function updateItem(Request $request): Response
    {
        $itemtype = $request->getAttribute('itemtype');
        return Search::updateBySchema($this->getKnownSchema($itemtype), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/{itemtype}/{id}', methods: ['DELETE'], requirements: [
        'itemtype' => [self::class, 'getAssetTypes'],
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete an asset of a specific type by ID',
    )]
    public function deleteItem(Request $request): Response
    {
        $itemtype = $request->getAttribute('itemtype');
        return Search::deleteBySchema($this->getKnownSchema($itemtype), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Cartridge', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search cartridges models'
    )]
    public function searchCartridgeItems(Request $request): Response
    {
        return Search::searchBySchema($this->getKnownSchema('CartridgeItem'), $request->getParameters());
    }

    #[Route(path: '/Cartridge/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a cartridge model by ID',
    )]
    public function getCartridgeItem(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('CartridgeItem'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Cartridge', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a cartridge model',
    )]
    public function createCartridgeItems(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('CartridgeItem'), $request->getParameters(), [self::class, 'getCartridgeItem']);
    }

    #[Route(path: '/Cartridge/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a cartridge model by ID',
    )]
    public function updateCartridgeItems(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('CartridgeItem'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Cartridge/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a cartridge model by ID',
    )]
    public function deleteCartridgeItems(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('CartridgeItem'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Cartridge/{cartridgeitems_id}/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a cartridge by ID',
    )]
    public function getCartridge(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('Cartridge'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Cartridge/{cartridgeitems_id}', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a cartridge',
    )]
    public function createCartridges(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('Cartridge'), $request->getParameters(), [self::class, 'getCartridge']);
    }

    #[Route(path: '/Cartridge/{cartridgeitems_id}/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a cartridge by ID',
    )]
    public function updateCartridges(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('Cartridge'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Cartridge/{cartridgeitems_id}/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a cartridge by ID',
    )]
    public function deleteCartridges(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('Cartridge'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Consumable', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search consumables models'
    )]
    public function searchConsumableItems(Request $request): Response
    {
        return Search::searchBySchema($this->getKnownSchema('ConsumableItem'), $request->getParameters());
    }

    #[Route(path: '/Consumable/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a consumable model by ID',
    )]
    public function getConsumableItem(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('ConsumableItem'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Consumable', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a consumable model',
    )]
    public function createConsumableItems(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('ConsumableItem'), $request->getParameters(), [self::class, 'getConsumableItem']);
    }

    #[Route(path: '/Consumable/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a consumable model by ID',
    )]
    public function updateConsumableItems(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('ConsumableItem'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Consumable/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a consumable model by ID',
    )]
    public function deleteConsumableItems(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('ConsumableItem'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Consumable/{consumableitems_id}/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a consumable by ID',
    )]
    public function getConsumable(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('Consumable'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Consumable/{consumableitems_id}', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a consumable',
    )]
    public function createConsumables(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('Consumable'), $request->getParameters(), [self::class, 'getConsumable']);
    }

    #[Route(path: '/Consumable/{consumableitems_id}/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a consumable by ID',
    )]
    public function updateConsumable(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('Consumable'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Consumable/{consumableitems_id}/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a consumable by ID',
    )]
    public function deleteConsumable(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('Consumable'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Software', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search software'
    )]
    public function searchSoftware(Request $request): Response
    {
        return Search::searchBySchema($this->getKnownSchema('Software'), $request->getParameters());
    }

    #[Route(path: '/Software/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a software by ID',
    )]
    public function getSoftware(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('Software'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Software', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a software',
    )]
    public function createSoftware(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('Software'), $request->getParameters(), [self::class, 'getSoftware']);
    }

    #[Route(path: '/Software/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a software by ID',
    )]
    public function updateSoftware(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('Software'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Software/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a software by ID',
    )]
    public function deleteSoftware(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('Software'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Rack', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search racks'
    )]
    public function searchRack(Request $request): Response
    {
        return Search::searchBySchema($this->getKnownSchema('Rack'), $request->getParameters());
    }

    #[Route(path: '/Rack/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a rack by ID',
    )]
    public function getRack(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('Rack'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Rack', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a rack',
    )]
    public function createRack(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('Rack'), $request->getParameters(), [self::class, 'getRack']);
    }

    #[Route(path: '/Rack/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a rack by ID',
    )]
    public function updateRack(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('Rack'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Rack/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a rack by ID',
    )]
    public function deleteRack(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('Rack'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Enclosure', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search enclosure'
    )]
    public function searchEnclosure(Request $request): Response
    {
        return Search::searchBySchema($this->getKnownSchema('Enclosure'), $request->getParameters());
    }

    #[Route(path: '/Enclosure/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a enclosure by ID',
    )]
    public function getEnclosure(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('Enclosure'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Enclosure', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a enclosure',
    )]
    public function createEnclosure(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('Enclosure'), $request->getParameters(), [self::class, 'getEnclosure']);
    }

    #[Route(path: '/Enclosure/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a enclosure by ID',
    )]
    public function updateEnclosure(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('Enclosure'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Enclosure/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a enclosure by ID',
    )]
    public function deleteEnclosure(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('Enclosure'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/PDU', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search PDUs'
    )]
    public function searchPDU(Request $request): Response
    {
        return Search::searchBySchema($this->getKnownSchema('PDU'), $request->getParameters());
    }

    #[Route(path: '/PDU/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a PDU by ID',
    )]
    public function getPDU(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('PDU'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/PDU', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a PDU',
    )]
    public function createPDU(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('PDU'), $request->getParameters(), [self::class, 'getPDU']);
    }

    #[Route(path: '/PDU/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a PDU by ID',
    )]
    public function updatePDU(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('PDU'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/PDU/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a PDU by ID',
    )]
    public function deletePDU(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('PDU'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/PassiveDCEquipment', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search passive DC equipment'
    )]
    public function searchPassiveDCEquipment(Request $request): Response
    {
        return Search::searchBySchema($this->getKnownSchema('PassiveDCEquipment'), $request->getParameters());
    }

    #[Route(path: '/PassiveDCEquipment/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a passive DC equipment by ID',
    )]
    public function getPassiveDCEquipment(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('PassiveDCEquipment'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/PassiveDCEquipment', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a passive DC equipment',
    )]
    public function createPassiveDCEquipment(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('PassiveDCEquipment'), $request->getParameters(), [self::class, 'getPassiveDCEquipment']);
    }

    #[Route(path: '/PassiveDCEquipment/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a passive DC equipment by ID',
    )]
    public function updatePassiveDCEquipment(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('PassiveDCEquipment'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/PassiveDCEquipment/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a passive DC equipment by ID',
    )]
    public function deletePassiveDCEquipment(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('PassiveDCEquipment'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Cable', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search cables'
    )]
    public function searchCables(Request $request): Response
    {
        return Search::searchBySchema($this->getKnownSchema('Cable'), $request->getParameters());
    }

    #[Route(path: '/Cable/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a cable by ID',
    )]
    public function getCable(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('Cable'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Cable', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a cable',
    )]
    public function createCable(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('Cable'), $request->getParameters(), [self::class, 'getCable']);
    }

    #[Route(path: '/Cable/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a software by ID',
    )]
    public function updateCable(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('Cable'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Cable/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a cable by ID',
    )]
    public function deleteCable(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('Cable'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Socket', methods: ['GET'], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search sockets'
    )]
    public function searchSockets(Request $request): Response
    {
        return Search::searchBySchema($this->getKnownSchema('Socket'), $request->getParameters());
    }

    #[Route(path: '/Socket/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a socket by ID',
    )]
    public function getSocket(Request $request): Response
    {
        return Search::getOneBySchema($this->getKnownSchema('Socket'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Socket', methods: ['POST'], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a socket',
    )]
    public function createSocket(Request $request): Response
    {
        return Search::createBySchema($this->getKnownSchema('Socket'), $request->getParameters(), [self::class, 'getSocket']);
    }

    #[Route(path: '/Socket/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a socket by ID',
    )]
    public function updateSocket(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('Socket'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Socket/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a socket by ID',
    )]
    public function deleteSocket(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('Socket'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Software/{software_id}/Version', methods: ['GET'], requirements: [
        'software_id' => '\d+',
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'List or search software versions'
    )]
    public function searchSoftwareVersions(Request $request): Response
    {
        $filters = $request->hasParameter('filter') ? $request->getParameter('filter') : '';
        $filters .= ';software.id==' . $request->getAttribute('software_id');
        $request->setParameter('filter', $filters);
        return Search::searchBySchema($this->getKnownSchema('SoftwareVersion'), $request->getParameters());
    }

    #[Route(path: '/Software/{software_id}/Version/{id}', methods: ['GET'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'], middlewares: [ResultFormatterMiddleware::class])]
    #[Doc\Route(
        description: 'Get a software version by ID',
    )]
    public function getSoftwareVersion(Request $request): Response
    {
        $filters = $request->hasParameter('filter') ? $request->getParameter('filter') : '';
        $filters .= ';software.id==' . $request->getAttribute('software_id');
        $request->setParameter('filter', $filters);
        return Search::getOneBySchema($this->getKnownSchema('SoftwareVersion'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Software/{software_id}/Version', methods: ['POST'], requirements: [
        'software_id' => '\d+',
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Create a software version',
    )]
    public function createSoftwareVersion(Request $request): Response
    {
        $request->setParameter('software', $request->getAttribute('software_id'));
        return Search::createBySchema($this->getKnownSchema('SoftwareVersion'), $request->getParameters(), [
            self::class, 'getSoftwareVersion'
        ], [
            'mapped' => [
                'software_id' => $request->getAttribute('software_id')
            ]
        ]);
    }

    #[Route(path: '/Software/{software_id}/Version/{id}', methods: ['PATCH'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Update a software version by ID',
    )]
    public function updateSoftwareVersion(Request $request): Response
    {
        return Search::updateBySchema($this->getKnownSchema('SoftwareVersion'), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Software/{software_id}/Version/{id}', methods: ['DELETE'], requirements: [
        'id' => '\d+'
    ], tags: ['Assets'])]
    #[Doc\Route(
        description: 'Delete a software version by ID',
    )]
    public function deleteSoftwareVersion(Request $request): Response
    {
        return Search::deleteBySchema($this->getKnownSchema('SoftwareVersion'), $request->getAttributes(), $request->getParameters());
    }
}
