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

namespace tests\units\Glpi\Api\HL\Controller;

use Glpi\Http\Request;

class ComponentController extends \HLAPITestCase
{
    public function testIndex()
    {
        global $CFG_GLPI;
        $types = $CFG_GLPI['device_types'];

        $this->login();
        $this->api->call(new Request('GET', '/Components'), function ($call) use ($types) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) use ($types) {
                    $this->array($content)->size->isGreaterThanOrEqualTo(count($types));
                    foreach ($content as $component) {
                        $this->array($component)->hasKeys(['itemtype', 'name', 'href']);
                        $this->string($component['name'])->isNotEmpty();
                        $this->string($component['href'])->isEqualTo('/Components/' . $component['itemtype']);
                    }
                });
        });
    }

    protected function deviceTypeProvider()
    {
        $types = [
            'Battery', 'Camera', 'Case', 'Controller', 'Drive', 'Firmware', 'GenericDevice', 'GraphicCard',
            'HardDrive', 'Memory', 'NetworkCard', 'PCIDevice', 'PowerSupply', 'Processor', 'Sensor', 'SIMCard',
            'SoundCard', 'Systemboard'
        ];

        foreach ($types as $type) {
            yield [$type];
        }
    }

    /**
     * @dataProvider deviceTypeProvider
     */
    public function testCRUD(string $type)
    {
        $this->login('glpi', 'glpi');

        $func_name = __FUNCTION__;

        // Create component type
        $request = new Request('POST', '/Components/' . $type);
        $request->setParameter('designation', $type . $func_name);
        $new_item_location = null;
        $this->api->call($request, function ($call) use ($type, &$new_item_location) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->headers(function ($headers) use ($type, &$new_item_location) {
                    $this->array($headers)->hasKey('Location');
                    $this->string($headers['Location'])->startWith("/Components/" . $type);
                    $new_item_location = $headers['Location'];
                });
        });

        // Get and verify
        $this->api->call(new Request('GET', $new_item_location), function ($call) use ($func_name, $type) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) use ($func_name, $type) {
                    $this->array($content)->hasKeys(['id', 'designation']);
                    $this->integer($content['id'])->isGreaterThan(0);
                    $this->string($content['designation'])->isEqualTo($type . $func_name);
                });
        });

        // Update
        $request = new Request('PATCH', $new_item_location);
        $request->setParameter('designation', $type . $func_name . '2');
        $this->api->call($request, function ($call) use ($func_name, $type) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) use ($func_name, $type) {
                    $this->checkSimpleContentExpect($content, ['fields' => ['designation' => $type . $func_name . '2']]);
                });
        });

        // Delete
        $this->api->call(new Request('DELETE', $new_item_location), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK();
        });

        // Verify item does not exist anymore
        $this->api->call(new Request('GET', $new_item_location), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isNotFoundError();
        });
    }

    /**
     * @dataProvider deviceTypeProvider
     */
    public function testGetComponentsOfType(string $type)
    {
        global $DB;

        $this->login();

        $func_name = __FUNCTION__;

        //ignore SIMCard for now because sim cards not in a device are impossible to exist in this universe
        if ($type === 'SIMCard') {
            $this->boolean(true)->isTrue();
            return;
        }

        // Create the component type
        $request = new Request('POST', '/Components/' . $type);
        $request->setParameter('designation', $type . $func_name);
        $new_item_location = null;
        $this->api->call($request, function ($call) use ($type, &$new_item_location) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->headers(function ($headers) use ($type, &$new_item_location) {
                    $this->array($headers)->hasKey('Location');
                    $this->string($headers['Location'])->startWith("/Components/" . $type);
                    $new_item_location = $headers['Location'];
                });
        });

        $items_location = $new_item_location . '/Items';
        // By default, there is no component of this type. Response should be an empty array
        $this->api->call(new Request('GET', $items_location), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) {
                    $this->array($content)->isEmpty();
                });
        });

        // Find the itemtype of the component
        $schema = \Glpi\Api\HL\Controller\ComponentController::getKnownSchemas()[$type];
        $device_fk = $schema['x-itemtype']::getForeignKeyField();
        $itemtype = 'Item_' . $schema['x-itemtype'];
        $item = new $itemtype();

        $items_id = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $schema['x-itemtype']::getTable(),
            'WHERE'  => ['designation' => $type . $func_name],
        ])->current()['id'];
        $this->integer($items_id)->isGreaterThan(0);

        // Add a component of this type
        $this->integer($item->add([
            $device_fk => $items_id,
        ]))->isGreaterThan(0);

        // There should now be one component of this type
        $this->api->call(new Request('GET', $items_location), function ($call) use ($type) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) use ($type) {
                    $this->array($content)->size->isEqualTo(1);
                    $join_key = $type;
                    // If there is a lowercase letter followed by an uppercase letter, we need to add an underscore between them
                    // Ex: PowerSupply => Power_Supply
                    $join_key = preg_replace('/([a-z])([A-Z])/', '$1_$2', $join_key);
                    // If there are two or more uppercase letters in a row, we need an underscore before the last uppercase character in the sequence
                    // Ex: PCIDevice => PCI_Device
                    $join_key = preg_replace('/([A-Z]{2,})([A-Z])/', '$1_$2', $join_key);
                    // Make lowercase
                    $join_key = strtolower($join_key);
                    $this->array($content[0])->hasKeys(['id', $join_key]);
                });
        });
    }
}
