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
use Glpi\OAuth\Server;

class CoreController extends \HLAPITestCase
{
    protected function routeMatchProvider()
    {
        return [
            [new Request('GET', '/Session'), true],
            [new Request('PATCH', '/Session'), false],
            [new Request('PUT', '/Session'), false],
            [new Request('POST', '/Session'), true],
            [new Request('DELETE', '/Session'), true],
            [new Request('GET', '/A/B/C'), false],
        ];
    }

    /**
     * @dataProvider routeMatchProvider
     */
    public function testRouteMatches(Request $request, bool $expected)
    {
        $this->api->hasMatch($request)->isEqualTo($expected);
    }

    public function testOptionsRoute()
    {
        $this->login();
        $this->api->call(new Request('OPTIONS', '/Session'), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->headers(function ($headers) {
                    $this->array($headers['Allow'])->containsValues(['DELETE', 'GET', 'POST']);
                })
                ->status(fn ($status) => $this->integer($status)->isEqualTo(204));
        });
    }

    public function testHeadMethod()
    {
        $this->login();
        $this->api->call(new Request('HEAD', '/Session'), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->headers(function ($headers) {
                    $this->string($headers['Content-Type'])->isEqualTo('application/json');
                })
                ->content(fn ($content) => $this->string($content)->isEmpty());
        });
    }

    protected function responseContentSchemaProvider()
    {
        return [
            [new Request('GET', '/Session'), 'Session']
        ];
    }

    /**
     * @dataProvider responseContentSchemaProvider
     */
    public function testResponseContentSchema(Request $request, string $schema_name)
    {
        $this->login();
        $this->api->call($request, function ($call) use ($schema_name) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->matchesSchema($schema_name);
        });
    }

    public function testInitAndKillSession()
    {
        global $CFG_GLPI;

        $session_token = null;
        $request = new Request('POST', '/Session');
        $request = $request->withHeader('Authorization', 'Basic ' . base64_encode(TU_USER . ':' . TU_PASS));
        // Allow API login with basic auth
        $CFG_GLPI['enable_api_login_credentials'] = true;

        $this->api->call($request, function ($call) use (&$session_token) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) use (&$session_token) {
                    $this->string($content['session_token'])->isNotEmpty();
                    $session_token = $content['session_token'];
                });
        });

        // Try getting session information and verify the user_id is present and > 0
        $request = new Request('GET', '/Session');
        $request = $request->withHeader('Glpi-Session-Token', $session_token);
        $this->api->call($request, function ($call) use ($session_token) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) {
                    $this->integer($content['user_id'])->isGreaterThan(0);
                });
        });

        // Try killing the session
        $request = new Request('DELETE', '/Session');
        $request = $request->withHeader('Glpi-Session-Token', $session_token);
        $this->api->call($request, function ($call) use ($session_token) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->content(fn ($content) => $this->string($content)->isEmpty());
        }, false);

        // Tyy getting session information again, it should fail
        $request = new Request('GET', '/Session');
        $request = $request->withHeader('Glpi-Session-Token', $session_token);
        $this->api->call($request, function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isUnauthorizedError();
        }, false);
    }

    public function testTransferEntity()
    {
        $this->login();

        // Create 2 computers (not using API)
        $computer = new \Computer();
        $computers_id_1 = $computer->add([
            'name' => 'Computer 1',
            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
        ]);
        $this->integer($computers_id_1)->isGreaterThan(0);

        $computers_id_2 = $computer->add([
            'name' => 'Computer 2',
            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
        ]);
        $this->integer($computers_id_2)->isGreaterThan(0);

        // Create a monitor to test transfer options are passed correctly (keep_dc_monitor)
        $monitor = new \Monitor();
        $monitors_id = $monitor->add([
            'name' => 'Monitor 1',
            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
        ]);
        $this->integer($monitors_id)->isGreaterThan(0);

        // Connect the monitor to the computer
        $computer_item = new \Computer_Item();
        $computer_items_id = $computer_item->add([
            'computers_id' => $computers_id_1,
            'itemtype' => \Monitor::class,
            'items_id' => $monitors_id,
        ]);
        $this->integer($computer_items_id)->isGreaterThan(0);

        // Create 2 new entities (not using API)
        $entity = new \Entity();
        $entities_id_1 = $entity->add([
            'name' => 'Entity 1',
            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
        ]);
        $this->integer($entities_id_1)->isGreaterThan(0);

        $entities_id_2 = $entity->add([
            'name' => 'Entity 2',
            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
        ]);
        $this->integer($entities_id_2)->isGreaterThan(0);

        $transfer_records = [
            [
                'itemtype' => 'Computer',
                'items_id' => $computers_id_1,
                'entity' => $entities_id_1,
                'options' => [
                    'keep_dc_monitor' => 1,
                ]
            ],
            [
                'itemtype' => 'Computer',
                'items_id' => $computers_id_2,
                'entity' => $entities_id_2,
                'options' => [
                    'keep_dc_monitor' => 0,
                ]
            ],
        ];

        $request = new Request('POST', '/Transfer', ['Content-Type' => 'application/json'], json_encode($transfer_records));
        $this->api->call($request, function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->status(fn ($status) => $this->integer($status)->isEqualTo(200))
                ->content(fn ($content) => $this->string($content)->isEmpty());
        });

        // Check the computers have been transferred
        $this->boolean($computer->getFromDB($computers_id_1))->isTrue();
        $this->integer($computer->fields['entities_id'])->isEqualTo($entities_id_1);

        $this->boolean($computer->getFromDB($computers_id_2))->isTrue();
        $this->integer($computer->fields['entities_id'])->isEqualTo($entities_id_2);

        // Verify computer 1 has a monitor connection, and computer 2 doesn't
        $this->boolean($computer_item->getFromDBByCrit([
            'computers_id' => $computers_id_1,
            'itemtype' => \Monitor::class,
            'items_id' => $monitors_id,
        ]) === true)->isTrue();

        $this->boolean($computer_item->getFromDBByCrit([
            'computers_id' => $computers_id_2,
            'itemtype' => \Monitor::class,
            'items_id' => $monitors_id,
        ]) === true)->isFalse();
    }

    public function testOAuthPasswordGrant()
    {
        global $DB;

        // Create an OAuth client
        $client = new \OAuthClient();
        $client_id = $client->add([
            'name' => __FUNCTION__,
            'is_active' => 1,
            'is_confidential' => 1,
        ]);
        $this->integer($client_id)->isGreaterThan(0);

        // get client ID and secret
        $it = $DB->request([
            'SELECT' => ['identifier', 'secret'],
            'FROM' => \OAuthClient::getTable(),
            'WHERE' => ['id' => $client_id],
        ]);
        $this->integer($it->count())->isEqualTo(1);
        $client_data = $it->current();
        $auth_data = [
            'grant_type' => 'password',
            'client_id' => $client_data['identifier'],
            'client_secret' => (new \GLPIKey())->decrypt($client_data['secret']),
            'username' => TU_USER,
            'password' => TU_PASS,
            'scope' => ''
        ];

        // Expect 401 error if no grant is set
        $request = new Request('POST', '/Token', ['Content-Type' => 'application/json'], json_encode($auth_data));
        $this->api->call($request, function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->status(fn ($status) => $this->integer($status)->isEqualTo(401));
        });

        $client->update([
            'id' => $client_id,
            'grants' => ['password']
        ]);

        $request = new Request('POST', '/Token', ['Content-Type' => 'application/json'], json_encode($auth_data));
        $this->api->call($request, function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->status(fn ($status) => $this->integer($status)->isEqualTo(200))
                ->jsonContent(function ($content) {
                    $this->array($content)->hasKeys(['access_token', 'expires_in', 'token_type']);
                    $this->string($content['token_type'])->isEqualTo('Bearer');
                    $this->string($content['access_token'])->isNotEmpty();
                    $this->integer($content['expires_in'])->isGreaterThan(0);
                });
        });
    }

    public function testOAuthAuthCodeGrant()
    {
        // Not a complete end to end test. Not sure how that could be done. Should probably be using Cypress.
        global $DB;

        // Create an OAuth client
        $client = new \OAuthClient();
        $client_id = $client->add([
            'name' => __FUNCTION__,
            'is_active' => 1,
            'is_confidential' => 1,
        ]);
        $this->integer($client_id)->isGreaterThan(0);

        $client->update([
            'id' => $client_id,
            'grants' => ['authorization_code']
        ]);

        // get client ID and secret
        $it = $DB->request([
            'SELECT' => ['identifier', 'secret', 'redirect_uri'],
            'FROM' => \OAuthClient::getTable(),
            'WHERE' => ['id' => $client_id],
        ]);
        $this->integer($it->count())->isEqualTo(1);
        $client_data = $it->current();

        // Test authorize endpoint
        $request = new Request('GET', '/Authorize', [], null);
        $request = $request->withQueryParams([
            'response_type' => 'code',
            'client_id' => $client_data['identifier'],
            'scope' => '',
            'redirect_uri' => $client_data['redirect_uri'],
        ]);

        $this->api->call($request, function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->status(fn ($status) => $this->integer($status)->isEqualTo(302))
                ->headers(function ($headers) {
                    global $CFG_GLPI;
                    $this->string($headers['Location'])->matches('/^' . preg_quote($CFG_GLPI['url_base'], '/') . '\/\?redirect=/');
                });
        });
    }
}
