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
use Calendar;
use CommonDBTM;
use Entity;
use Glpi\Api\HL\Doc as Doc;
use Glpi\Api\HL\Middleware\ResultFormatterMiddleware;
use Glpi\Api\HL\Route;
use Glpi\Api\HL\Search;
use Glpi\Http\JSONResponse;
use Glpi\Http\Request;
use Glpi\Http\Response;
use Group;
use Location;
use Manufacturer;
use Network;
use State;
use User;

#[Route(path: '/Dropdowns', priority: 1, tags: ['Dropdowns'])]
#[Doc\Route(
    parameters: [
        [
            'name' => 'itemtype',
            'description' => 'Dropdown type',
            'location' => Doc\Parameter::LOCATION_PATH,
            'schema' => ['type' => Doc\Schema::TYPE_STRING]
        ],
        [
            'name' => 'id',
            'description' => 'The ID of the dropdown item',
            'location' => Doc\Parameter::LOCATION_PATH,
            'schema' => ['type' => Doc\Schema::TYPE_INTEGER]
        ]
    ]
)]
final class DropdownController extends AbstractController
{
    use CRUDControllerTrait;

    protected static function getRawKnownSchemas(): array
    {
        $schemas = [];

        $schemas['Location'] = [
            'type' => Doc\Schema::TYPE_OBJECT,
            'x-itemtype' => Location::class,
            'description' => Location::getTypeName(1),
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'completename' => ['type' => Doc\Schema::TYPE_STRING],
                'code' => ['type' => Doc\Schema::TYPE_STRING],
                'aliases' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'parent' => self::getDropdownTypeSchema(class: Location::class, full_schema: 'Location'),
                'level' => ['type' => Doc\Schema::TYPE_INTEGER],
                'room' => ['type' => Doc\Schema::TYPE_STRING],
                'building' => ['type' => Doc\Schema::TYPE_STRING],
                'address' => ['type' => Doc\Schema::TYPE_STRING],
                'town' => ['type' => Doc\Schema::TYPE_STRING],
                'postcode' => ['type' => Doc\Schema::TYPE_STRING],
                'state' => ['type' => Doc\Schema::TYPE_STRING],
                'country' => ['type' => Doc\Schema::TYPE_STRING],
                'latitude' => ['type' => Doc\Schema::TYPE_STRING],
                'longitude' => ['type' => Doc\Schema::TYPE_STRING],
                'altitude' => ['type' => Doc\Schema::TYPE_STRING],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['State'] = [
            'type' => Doc\Schema::TYPE_OBJECT,
            'x-itemtype' => State::class,
            'description' => State::getTypeName(1),
            'properties' => [
                'id' => [
                    'type' => Doc\Schema::TYPE_INTEGER,
                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                    'x-readonly' => true,
                ],
                'name' => ['type' => Doc\Schema::TYPE_STRING],
                'completename' => ['type' => Doc\Schema::TYPE_STRING],
                'comment' => ['type' => Doc\Schema::TYPE_STRING],
                'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'parent' => self::getDropdownTypeSchema(class: State::class, full_schema: 'State'),
                'level' => ['type' => Doc\Schema::TYPE_INTEGER],
                'is_visible_computer' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_monitor' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_network_equipment' => ['x-field' => 'is_visible_networkequipment','type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_peripheral' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_phone' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_printer' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_software_version' => ['x-field' => 'is_visible_softwareversion','type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_software_license' => ['x-field' => 'is_visible_softwarelicense','type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_line' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_certificate' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_rack' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_passive_dcequipment' => ['x-field' => 'is_visible_passivedcequipment', 'type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_enclosure' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_pdu' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_cluster' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_contract' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_appliance' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_cable' => ['type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_database_instance' => ['x-field' => 'is_visible_databaseinstance', 'type' => Doc\Schema::TYPE_BOOLEAN],
                'is_visible_helpdesk' => ['x-field' => 'is_helpdesk_visible', 'type' => Doc\Schema::TYPE_BOOLEAN],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        $schemas['Manufacturer'] = [
            'type' => Doc\Schema::TYPE_OBJECT,
            'x-itemtype' => Manufacturer::class,
            'description' => Manufacturer::getTypeName(1),
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

        $schemas['Calendar'] = [
            'type' => Doc\Schema::TYPE_OBJECT,
            'x-itemtype' => Calendar::class,
            'description' => Calendar::getTypeName(1),
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
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
            ]
        ];

        return $schemas;
    }
}
