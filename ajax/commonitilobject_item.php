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

/**
 * @var CommonDBTM $obj
 * @var CommonItilObject_Item $item_obj
 */

use Glpi\Http\Response;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

if (!($obj instanceof CommonDBTM) || !($item_obj instanceof CommonItilObject_Item)) {
    Response::sendError(400, 'Bad request', Response::CONTENT_TYPE_TEXT_HTML);
}

switch ($_GET['action']) {
    case 'add':
        if (isset($_GET['my_items']) && !empty($_GET['my_items'])) {
            list($_GET['itemtype'], $_GET['items_id']) = explode('_', $_GET['my_items']);
        }
        if (isset($_GET['items_id']) && isset($_GET['itemtype']) && !empty($_GET['items_id'])) {
            $_GET['params']['items_id'][$_GET['itemtype']][$_GET['items_id']] = $_GET['items_id'];
        }
        $item_obj::itemAddForm($obj, $_GET['params'] ?? []);
        break;

    case 'delete':
        if (isset($_GET['items_id']) && isset($_GET['itemtype']) && !empty($_GET['items_id'])) {
            $deleted = true;
            if ($_GET['params']['id'] > 0) {
                $obj_fkey = $obj->getForeignKeyField();
                $deleted  = $item_obj::deleteByCriteria([
                    $obj_fkey  => $_GET['params']['id'],
                    'items_id' => $_GET['items_id'],
                    'itemtype' => $_GET['itemtype']
                ]);
            }
            if ($deleted) {
                unset($_GET['params']['items_id'][$_GET['itemtype']][array_search($_GET['items_id'], $_GET['params']['items_id'][$_GET['itemtype']])]);
            }
            $item_obj::itemAddForm($obj, $_GET['params'] ?? []);
        }

        break;
}
