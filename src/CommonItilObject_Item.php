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
 * CommonItilObject_Item Class
 *
 * Relation between CommonItilObject_Item and Items
 */
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryUnion;

abstract class CommonItilObject_Item extends CommonDBRelation
{
    public static function getIcon()
    {
        return 'ti ti-package';
    }

    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public function canCreateItem()
    {
        $obj = new static::$itemtype_1();

        if ($obj->canUpdateItem()) {
            return true;
        }

        return parent::canCreateItem();
    }

    public function post_addItem()
    {

        $obj = new static::$itemtype_1();
        $input  = [
            'id'            => $this->fields[static::$items_id_1],
            'date_mod'      => $_SESSION["glpi_currenttime"],
        ];

        if (!isset($this->input['_do_notif']) || $this->input['_do_notif']) {
            $input['_forcenotif'] = true;
        }
        if (isset($this->input['_disablenotif']) && $this->input['_disablenotif']) {
            $input['_disablenotif'] = true;
        }

        $obj->update($input);
        parent::post_addItem();
    }

    public function post_purgeItem()
    {

        $obj = new static::$itemtype_1();
        $input = [
            'id'            => $this->fields[static::$items_id_1],
            'date_mod'      => $_SESSION["glpi_currenttime"],
        ];

        if (!isset($this->input['_do_notif']) || $this->input['_do_notif']) {
            $input['_forcenotif'] = true;
        }
        $obj->update($input);

        parent::post_purgeItem();
    }


    public function prepareInputForAdd($input)
    {
        // Avoid duplicate entry
        if (
            countElementsInTable(
                $this->getTable(),
                [
                    static::$items_id_1 => $input[static::$items_id_1],
                    'itemtype'   => $input['itemtype'],
                    'items_id'   => $input['items_id']
                ]
            ) > 0
        ) {
            return false;
        }

        return parent::prepareInputForAdd($input);
    }

    /**
     * validate the object type is align with itemtype_1
     *
     * @param $obj  object holding the item
     *     *
     * @return bool
     **/
    public static function validateObjectType($obj)
    {
        if (get_class($obj) != static::$itemtype_1) {
            trigger_error(
                sprintf(__('object from %1$s instead of %2$s'), get_class($obj), static::$itemtype_1),
                E_USER_WARNING
            );
            return false;
        }
        return true;
    }

    /**
     * Print the HTML ajax associated item add
     *
     * @param CommonDBTM $obj  object holding the item
     * @param array $options   array of possible options:
     *    - id                  : ID of the object holding the items
     *    - _users_id_requester : ID of the requester user
     *    - items_id            : array of elements (itemtype => array(id1, id2, id3, ...))
     *
     * @return void
     */
    protected static function displayItemAddForm(CommonDBTM $obj, $options = [])
    {
        if (!static::validateObjectType($obj)) {
            return false;
        }

        $params = [
            'id'                  => (isset($obj->fields['id']) && $obj->fields['id'] != '') ? $obj->fields['id'] : 0,
            'entities_id'         => (isset($obj->fields['entities_id']) && is_numeric($obj->fields['entities_id']) ? $obj->fields['entities_id'] : Session::getActiveEntity()),
            '_users_id_requester' => 0,
            'items_id'            => [],
            'itemtype'            => '',
            '_canupdate'          => false
        ];

        foreach ($options as $key => $val) {
            if (!empty($val)) {
                $params[$key] = $val;
            }
        }

        if (!$obj->can($params['id'], READ)) {
            return false;
        }

        $canedit = ($obj->can($params['id'], UPDATE) && $params['_canupdate']);
        $usedcount = 0;
        // ITIL Object update case
        if ($params['id'] > 0) {
            // Get associated elements for obj
            $used = static::getUsedItems($params['id']);
            foreach ($used as $itemtype => $items) {
                foreach ($items as $items_id) {
                    if (
                        !isset($params['items_id'][$itemtype])
                        || !in_array($items_id, $params['items_id'][$itemtype])
                    ) {
                        $params['items_id'][$itemtype][] = $items_id;
                    }
                    ++$usedcount;
                }
            }
        }

        $rand  = mt_rand();
        $count = 0;
        $twig_params = [
            'rand'               => $rand,
            'item_class'         => static::class,
            'can_edit'           => $canedit,
            'my_items_dropdown'  => '',
            'all_items_dropdown' => '',
            'items_to_add'       => [],
            'params'             => $params,
            'opt'                => []
        ];

        $class_template = get_class($obj) . "Template";
        if (class_exists($class_template)) {
            $class_template_lower = '_' . strtolower($class_template);
            // Get ITIL object template
            $tt = new $class_template($class_template);
            if (isset($options[$class_template_lower])) {
                $tt  = $options[$class_template_lower];
                if (isset($tt->fields['id'])) {
                    $twig_params['opt']['templates_id'] = $tt->fields['id'];
                }
            } else if (isset($options['templates_id'])) {
                $tt->getFromDBWithData($options['templates_id']);
                if (isset($tt->fields['id'])) {
                    $twig_params['opt']['templates_id'] = $tt->fields['id'];
                }
            }
        }

        // Show associated item dropdowns
        if ($canedit) {
            $p = [
                'used'       => $params['items_id'],
                'rand'       => $rand,
                static::$items_id_1 => $params['id']
            ];
            // My items
            if ($params['_users_id_requester'] > 0) {
                ob_start();
                static::dropdownMyDevices($params['_users_id_requester'], $params['entities_id'], $params['itemtype'], 0, $p);
                $twig_params['my_items_dropdown'] = ob_get_clean();
            }
            // Global search
            ob_start();
            static::dropdownAllDevices("itemtype", $params['itemtype'], 0, 1, $params['_users_id_requester'], $params['entities_id'], $p);
            $twig_params['all_items_dropdown'] = ob_get_clean();
        }

        // Display list
        if (!empty($params['items_id'])) {
            // No delete if mandatory and only one item
            $delete = $obj->canAddItem(static::class);
            $cpt = 0;
            foreach ($params['items_id'] as $itemtype => $items) {
                $cpt += count($items);
            }

            if ($cpt == 1 && isset($tt->mandatory['items_id'])) {
                $delete = false;
            }
            foreach ($params['items_id'] as $itemtype => $items) {
                foreach ($items as $items_id) {
                    $count++;
                    $twig_params['items_to_add'][] = static::showItemToAdd(
                        $params['id'],
                        $itemtype,
                        $items_id,
                        [
                            'rand'      => $rand,
                            'delete'    => $delete,
                            'visible'   => ($count <= 5)
                        ]
                    );
                }
            }
        }
        $twig_params['count'] = $count;
        $twig_params['usedcount'] = $usedcount;

        foreach (['id', '_users_id_requester', 'items_id', 'itemtype', '_canupdate', 'entities_id'] as $key) {
            $twig_params['opt'][$key] = $params[$key];
        }

        TemplateRenderer::getInstance()->display('components/itilobject/add_items.html.twig', $twig_params);
    }

    /**
     * Print the HTML ajax associated item add
     *
     * @param $object_id  object id from item_ticket but it seems to be useless UNUSED
     * @param $itemtype   type of the item t show
     * @param $items_id   item id
     * @param $options   array of possible options:
     *    - id                  : ID of the object holding the items
     *    - _users_id_requester : ID of the requester user
     *    - items_id            : array of elements (itemtype => array(id1, id2, id3, ...))
     *
     * @return string
     **/
    public static function showItemToAdd($object_id, $itemtype, $items_id, $options)
    {
        $params = [
            'rand'      => mt_rand(),
            'delete'    => true,
            'visible'   => true,
            'kblink'    => true
        ];

        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        $result = "";

        if ($item = getItemForItemtype($itemtype)) {
            if ($params['visible']) {
                $item->getFromDB($items_id);
                $result =  "<div id='{$itemtype}_$items_id'>";
                $result .= $item->getTypeName(1) . " : " . $item->getLink(['comments' => true]);
                $result .= Html::hidden("items_id[$itemtype][$items_id]", ['value' => $items_id]);
                if ($params['delete']) {
                    $result .= " <i class='fas fa-times-circle pointer' onclick=\"itemAction" . $params['rand'] . "('delete', '$itemtype', '$items_id');\"></i>";
                }
                if ($params['kblink']) {
                    $result .= ' ' . $item->getKBLinks();
                }
                $result .= "</div>";
            } else {
                $result .= Html::hidden("items_id[$itemtype][$items_id]", ['value' => $items_id]);
            }
        }

        return $result;
    }

    /**
     * Print the HTML array for Items linked to a ITIL object
     *
     * @param $obj object holding the item object
     *
     * @return bool|void
     **/
    public static function showForObject($obj, $options = [])
    {
        if (!static::validateObjectType($obj)) {
            return false;
        }

        $instID = $obj->fields['id'];

        if (!$obj->can($instID, READ)) {
            return false;
        }
        //can Add Item takes type as param but there is none here
        $canedit = $obj->canAddItem('');
        $rand    = mt_rand();

        $types_iterator = static::getDistinctTypes($instID);
        $number = count($types_iterator);

        $can_add_item = $obj instanceof CommonITILRecurrent
            || (
                $obj instanceof CommonITILObject
                && !in_array(
                    $obj->fields['status'],
                    array_merge(
                        $obj->getClosedStatusArray(),
                        $obj->getSolvedStatusArray()
                    )
                )
            );

        if ($canedit && $can_add_item) {
            echo "<div class='firstbloc'>";
            echo "<form name='commonitilobject_item_form$rand' method='post'
                    action='" . Toolbox::getItemTypeFormURL(static::class) . "'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Add an item') . "</th></tr>";
            echo "<tr class='tab_bg_1'><td>";
            $devices_user_id = $obj instanceof CommonITILObject ? ($options['_users_id_requester'] ?? 0) : 0;
            if ($devices_user_id > 0) {
                static::dropdownMyDevices(
                    $devices_user_id,
                    $obj->fields["entities_id"],
                    null,
                    0,
                    [static::$items_id_1 => $instID]
                );
            }
            $used = static::getUsedItems($instID);
            static::dropdownAllDevices("itemtype", null, 0, 1, $devices_user_id, $obj->fields["entities_id"], [static::$items_id_1 => $instID, 'used' => $used, 'rand' => $rand]);
            echo "<span id='item_selection_information$rand'></span>";
            echo "</td><td class='center' width='30%'>";
            echo "<input type='submit' name='add' value=\"" . _sx('button', 'Add') . "\" class='btn btn-primary'>";
            echo "<input type='hidden' name=" . static::$items_id_1 . " value='$instID'>";
            echo "</td></tr>";
            echo "</table>";
            Html::closeForm();
            echo "</div>";
        }

        echo "<div class='spaced'>";
        if ($canedit && $number) {
            Html::openMassiveActionsForm('mass' . static::class . $rand);
            $massiveactionparams = ['container' => 'mass' . static::class . $rand];
            Html::showMassiveActions($massiveactionparams);
        }
        echo "<table class='tab_cadre_fixehov'>";
        $header_begin  = "<tr>";
        $header_top    = '';
        $header_bottom = '';
        $header_end    = '';
        if ($canedit && $number) {
            $header_top    .= "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . static::class . $rand);
            $header_top    .= "</th>";
            $header_bottom .= "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . static::class . $rand);
            $header_bottom .= "</th>";
        }
        $header_end .= "<th>" . _n('Type', 'Types', 1) . "</th>";
        $header_end .= "<th>" . Entity::getTypeName(1) . "</th>";
        $header_end .= "<th>" . __('Name') . "</th>";
        $header_end .= "<th>" . __('Serial number') . "</th>";
        $header_end .= "<th>" . __('Inventory number') . "</th>";
        $header_end .= "<th>" . __('Knowledge base entries') . "</th>";
        $header_end .= "<th>" . State::getTypeName(1) . "</th>";
        $header_end .= "<th>" . Location::getTypeName(1) . "</th>";
        echo "<tr>";
        echo $header_begin . $header_top . $header_end;

        $totalnb = 0;
        foreach ($types_iterator as $row) {
            $itemtype = $row['itemtype'];
            if (!($item = getItemForItemtype($itemtype))) {
                continue;
            }

            if (in_array($itemtype, $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
                $iterator = static::getTypeItems($instID, $itemtype);
                $nb = count($iterator);

                $prem = true;
                foreach ($iterator as $data) {
                    $name = $data["name"] ?? '';
                    if (
                        $_SESSION["glpiis_ids_visible"]
                        || empty($data["name"])
                    ) {
                        $name = sprintf(__('%1$s (%2$s)'), $name, $data["id"]);
                    }
                    if ((Session::getCurrentInterface() != 'helpdesk') && $item::canView()) {
                        $link     = $itemtype::getFormURLWithID($data['id']);
                        $namelink = "<a href=\"" . $link . "\">" . $name . "</a>";
                    } else {
                        $namelink = $name;
                    }

                    echo "<tr class='tab_bg_1'>";
                    if ($canedit) {
                        echo "<td width='10'>";
                        Html::showMassiveActionCheckBox(static::class, $data["linkid"]);
                        echo "</td>";
                    }
                    if ($prem) {
                        $typename = $item->getTypeName($nb);
                        echo "<td class='center top' rowspan='$nb'>" .
                            (($nb > 1) ? sprintf(__('%1$s: %2$s'), $typename, $nb) : $typename) . "</td>";
                        $prem = false;
                    }
                    echo "<td class='center'>";
                    echo Dropdown::getDropdownName("glpi_entities", $data['entity']) . "</td>";
                    echo "<td class='center" .
                            (isset($data['is_deleted']) && $data['is_deleted'] ? " tab_bg_2_2'" : "'");
                    echo ">" . $namelink . "</td>";
                    echo "<td class='center'>" . (isset($data["serial"]) ?  "" . $data["serial"] . "" : "-") .
                        "</td>";
                    echo "<td class='center'>" .
                        (isset($data["otherserial"]) ? "" . $data["otherserial"] . "" : "-") . "</td>";
                    $item->getFromDB($data["id"]);
                    echo "<td class='center'>" . $item->getKBLinks() . "</td>";
                    echo "<td class='center'>";
                    echo Dropdown::getDropdownName("glpi_states", $data['states_id']) . "</td>";
                    echo "<td class='center'>";
                    echo Dropdown::getDropdownName("glpi_locations", $data['locations_id']) . "</td>";
                    echo "</tr>";
                }
                $totalnb += $nb;
            }
        }

        if ($number) {
            echo $header_begin . $header_bottom . $header_end;
        }

        echo "</table>";
        if ($canedit && $number) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
        }
        echo "</div>";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate) {
            $nb = 0;
            switch ($item->getType()) {
                case static::$itemtype_1:
                    if (
                        ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] != 0)
                        && (count($_SESSION["glpiactiveprofile"]["helpdesk_item_type"]) > 0)
                    ) {
                        if ($_SESSION['glpishow_count_on_tabs']) {
                            $nb = countElementsInTable(
                                static::getTable(),
                                [
                                    static::$items_id_1 => $item->getID(),
                                    'itemtype' => $_SESSION["glpiactiveprofile"]["helpdesk_item_type"]
                                ]
                            );
                        }
                        return static::createTabEntry(_n('Item', 'Items', Session::getPluralNumber()), $nb, $item::getType());
                    }
            }
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch ($item->getType()) {
            case static::$itemtype_1:
                static::showForObject($item);
                break;
            default:
                static::showListForItem($item, $withtemplate);
                break;
        }
        return true;
    }

    /**
     * Display object for an item
     *
     * Will also display objects of linked items
     *
     * @param CommonDBTM $item         CommonDBTM object
     * @param integer    $withtemplate (default 0)
     *
     * @return bool|void (display a table)
     **/
    public static function showListForItem(CommonDBTM $item, $withtemplate = 0, $options = [])
    {
        global $DB;

        if (!static::$itemtype_1::canView()) {
            return false;
        }

        if ($item->isNewID($item->getID())) {
            return false;
        }

        $criteria = static::$itemtype_1::getCommonCriteria();
        $params  = [
            'criteria' => [],
            'reset'    => 'reset',
            'restrict' => [],
        ];
        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }
        $restrict = $params['restrict'];

        $restrict[static::getTable() . ".items_id"] = $item->getID();
        $restrict[static::getTable() . ".itemtype"] = $item->getType();
        $params['criteria'][0]['field']      = 12;
        $params['criteria'][0]['searchtype'] = 'equals';
        $params['criteria'][0]['value']      = 'all';
        $params['criteria'][0]['link']       = 'AND';

        $params['metacriteria'][0]['itemtype']   = $item->getType();
        $params['metacriteria'][0]['field']      = Search::getOptionNumber(
            $item->getType(),
            'id'
        );
        $params['metacriteria'][0]['searchtype'] = 'equals';
        $params['metacriteria'][0]['value']      = $item->getID();
        $params['metacriteria'][0]['link']       = 'AND';
        // overide value from options
        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        $criteria['WHERE'] = $restrict + getEntitiesRestrictCriteria(static::$itemtype_1::getTable());
        $criteria['WHERE'][static::$itemtype_1::getTable() . ".is_deleted"] = 0;
        $criteria['LIMIT'] = (int)$_SESSION['glpilist_limit'];
        $iterator = $DB->request($criteria);
        $number = count($iterator);

        $colspan = 11;
        if (count($_SESSION["glpiactiveentities"]) > 1) {
            $colspan++;
        }

        // Object for the item
        // Link to open a new ITIL object
        if (
            $item->getID()
            && !$item->isDeleted()
            && CommonITILObject::isPossibleToAssignType($item->getType())
            && static::canCreate()
            && !(!empty($withtemplate) && ($withtemplate == 2))
            && (!isset($item->fields['is_template']) || ($item->fields['is_template'] == 0))
        ) {
            echo "<div class='firstbloc'>";
            Html::showSimpleForm(
                static::$itemtype_1::getFormURL(),
                '_add_fromitem',
                sprintf(__("New %s for this item..."), static::$itemtype_1::getTypeName(0)),
                [
                    'itemtype' => $item->getType(),
                    'items_id' => $item->getID()
                ]
            );
            echo "</div>";
        }

        echo "<div>";

        if ($number > 0) {
            echo "<table class='tab_cadre_fixehov'>";
            if (Session::haveRight(static::$itemtype_1::$rightname, static::$itemtype_1::READALL)) {
                Session::initNavigateListItems(
                    static::$itemtype_1,
                    //TRANS : %1$s is the itemtype name, %2$s is the name of the item (used for headings of a list)
                    sprintf(__('%1$s = %2$s'), $item->getTypeName(1), $item->getName())
                );
                echo "<tr class='noHover'><th colspan='$colspan'>";
                $title = sprintf(__('%d linked %s'), $number, static::$itemtype_1::getTypeName($number));
                $link = "<a href='" . static::$itemtype_1::getSearchURL() . "?" .
                        Toolbox::append_params($params, '&amp;') . "'>" . __('Show all') . "</a>";
                $title = printf(__('%1$s (%2$s)'), $title, $link);
                echo "</th></tr>";
            } else {
                echo "<tr><th colspan='$colspan'>" . static::$itemtype_1::getTypeName(1) . ": " .
                    __("You don't have right to see all") . "</th></tr>";
            }
        } else {
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th>" . static::$itemtype_1::getTypeName(0) . ": " . __('None found.') . "</th></tr>";
        }

        // object list
        if ($number > 0) {
            static::$itemtype_1::commonListHeader(Search::HTML_OUTPUT);

            foreach ($iterator as $data) {
                Session::addToNavigateListItems(static::$itemtype_1, $data["id"]);
                static::$itemtype_1::showShort($data["id"]);
            }
            static::$itemtype_1::commonListHeader(Search::HTML_OUTPUT);
        }

        echo "</table></div>";

        // Object for linked items
        $linkeditems = $item->getLinkedItems();
        $restrict    = [];
        if (count($linkeditems)) {
            foreach ($linkeditems as $ltype => $tab) {
                foreach ($tab as $lID) {
                    $restrict[] = ['AND' => ['itemtype' => $ltype, 'items_id' => $lID]];
                }
            }
        }

        if (
            count($restrict)
            && Session::haveRight(static::$itemtype_1::$rightname, static::$itemtype_1::READALL)
        ) {
            $criteria = static::$itemtype_1::getCommonCriteria();
            $criteria['WHERE'] = ['OR' => $restrict]
                + getEntitiesRestrictCriteria(static::$itemtype_1::getTable());
            $iterator = $DB->request($criteria);
            $number = count($iterator);

            echo "<div class='spaced'><table class='tab_cadre_fixe'>";
            echo "<tr><th colspan='12'>";
            printf('%s on linked items', static::$itemtype_1::getTypeName($number));
            echo "</th></tr>";
            if ($number > 0) {
                static::$itemtype_1::commonListHeader(Search::HTML_OUTPUT);
                foreach ($iterator as $data) {
                    // Session::addToNavigateListItems(TRACKING_TYPE,$data["id"]);
                    static::$itemtype_1::showShort($data["id"]);
                }
                static::$itemtype_1::commonListHeader(Search::HTML_OUTPUT);
            } else {
                echo "<tr><th>" . static::$itemtype_1::getTypeName(0) . ": " . __('None found.') . "</th></tr>";
            }
            echo "</table></div>";
        } // Subquery for linked item
    }

    /**
     * Make a select box for Object my devices
     *
     * @param integer $userID           User ID for my device section (default 0)
     * @param integer $entity_restrict  restrict to a specific entity (default -1)
     * @param string  $itemtype         of selected item (default 0)
     * @param integer $items_id         of selected item (default 0) UNUSED
     * @param array   $options          array of possible options:
     *    - used     : ID of the requester user
     *    - multiple : allow multiple choice
     *
     * @return void
     **/
    public static function dropdownMyDevices($userID = 0, $entity_restrict = -1, $itemtype = 0, $items_id = 0, $options = [])
    {
        global $DB, $CFG_GLPI;

        $params = [
            static::$items_id_1 => 0,
            'used'       => [],
            'multiple'   => false,
            'rand'       => mt_rand()
        ];

        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        if ($userID == 0) {
            $userID = Session::getLoginUserID();
        }

        $rand        = $params['rand'];
        $already_add = $params['used'];

        if (
            $_SESSION["glpiactiveprofile"]["helpdesk_hardware"]
            & pow(2, Ticket::HELPDESK_MY_HARDWARE)
        ) {
            $my_devices = ['' => Dropdown::EMPTY_VALUE];
            $devices    = [];

            // My items
            foreach ($CFG_GLPI["linkuser_types"] as $itemtype) {
                if (
                    ($item = getItemForItemtype($itemtype))
                    && CommonITILObject::isPossibleToAssignType($itemtype)
                ) {
                    $itemtable = getTableForItemType($itemtype);

                    $criteria = [
                        'FROM'   => $itemtable,
                        'WHERE'  => [
                            'users_id' => $userID
                        ] + getEntitiesRestrictCriteria($itemtable, '', $entity_restrict, $item->maybeRecursive()),
                        'ORDER'  => $item->getNameField()
                    ];

                    if ($item->maybeDeleted()) {
                        $criteria['WHERE']['is_deleted'] = 0;
                    }
                    if ($item->maybeTemplate()) {
                        $criteria['WHERE']['is_template'] = 0;
                    }
                    if (in_array($itemtype, $CFG_GLPI["helpdesk_visible_types"])) {
                        $criteria['WHERE']['is_helpdesk_visible'] = 1;
                    }

                    $iterator = $DB->request($criteria);
                    $nb = count($iterator);
                    if ($nb > 0) {
                        $type_name = $item->getTypeName($nb);

                        foreach ($iterator as $data) {
                            if (!isset($already_add[$itemtype]) || !in_array($data["id"], $already_add[$itemtype])) {
                                $output = $data[$item->getNameField()];
                                if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                                    $output = sprintf(__('%1$s (%2$s)'), $output, $data['id']);
                                }
                                $output = sprintf(__('%1$s - %2$s'), $type_name, $output);
                                if ($itemtype != 'Software') {
                                    if (!empty($data['serial'])) {
                                        $output = sprintf(__('%1$s - %2$s'), $output, $data['serial']);
                                    }
                                    if (!empty($data['otherserial'])) {
                                        $output = sprintf(__('%1$s - %2$s'), $output, $data['otherserial']);
                                    }
                                }
                                $devices[$itemtype . "_" . $data["id"]] = $output;

                                $already_add[$itemtype][] = $data["id"];
                            }
                        }
                    }
                }
            }

            if (count($devices)) {
                $my_devices[__('My devices')] = $devices;
            }
            // My group items
            if (Session::haveRight("show_group_hardware", "1")) {
                $iterator = $DB->request([
                    'SELECT'    => [
                        'glpi_groups_users.groups_id',
                        'glpi_groups.name'
                    ],
                    'FROM'      => 'glpi_groups_users',
                    'LEFT JOIN' => [
                        'glpi_groups'  => [
                            'ON' => [
                                'glpi_groups_users'  => 'groups_id',
                                'glpi_groups'        => 'id'
                            ]
                        ]
                    ],
                    'WHERE'     => [
                        'glpi_groups_users.users_id'  => $userID
                    ] + getEntitiesRestrictCriteria('glpi_groups', '', $entity_restrict, true)
                ]);

                $devices = [];
                $groups  = [];
                if (count($iterator)) {
                    foreach ($iterator as $data) {
                        $a_groups                     = getAncestorsOf("glpi_groups", $data["groups_id"]);
                        $a_groups[$data["groups_id"]] = $data["groups_id"];
                        $groups = array_merge($groups, $a_groups);
                    }

                    foreach ($CFG_GLPI["linkgroup_types"] as $itemtype) {
                        if (
                            ($item = getItemForItemtype($itemtype))
                            && CommonITILObject::isPossibleToAssignType($itemtype)
                        ) {
                            $itemtable  = getTableForItemType($itemtype);
                            $criteria = [
                                'FROM'   => $itemtable,
                                'WHERE'  => [
                                    'groups_id' => $groups
                                ] + getEntitiesRestrictCriteria($itemtable, '', $entity_restrict, $item->maybeRecursive()),
                                'ORDER'  => $item->getNameField()
                            ];

                            if ($item->maybeDeleted()) {
                                $criteria['WHERE']['is_deleted'] = 0;
                            }
                            if ($item->maybeTemplate()) {
                                $criteria['WHERE']['is_template'] = 0;
                            }

                            $iterator = $DB->request($criteria);
                            if (count($iterator)) {
                                $type_name = $item->getTypeName();
                                if (!isset($already_add[$itemtype])) {
                                    $already_add[$itemtype] = [];
                                }
                                foreach ($iterator as $data) {
                                    if (!in_array($data["id"], $already_add[$itemtype])) {
                                        $output = '';
                                        if (isset($data["name"])) {
                                            $output = $data["name"];
                                        }
                                        if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                                            $output = sprintf(__('%1$s (%2$s)'), $output, $data['id']);
                                        }
                                        $output = sprintf(__('%1$s - %2$s'), $type_name, $output);
                                        if (isset($data['serial'])) {
                                            $output = sprintf(__('%1$s - %2$s'), $output, $data['serial']);
                                        }
                                        if (isset($data['otherserial'])) {
                                            $output = sprintf(__('%1$s - %2$s'), $output, $data['otherserial']);
                                        }
                                        $devices[$itemtype . "_" . $data["id"]] = $output;

                                        $already_add[$itemtype][] = $data["id"];
                                    }
                                }
                            }
                        }
                    }
                    if (count($devices)) {
                        $my_devices[__('Devices own by my groups')] = $devices;
                    }
                }
            }
            // Get software linked to all owned items
            if (in_array('Software', $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
                $software_helpdesk_types = array_intersect($CFG_GLPI['software_types'], $_SESSION["glpiactiveprofile"]["helpdesk_item_type"]);
                foreach ($software_helpdesk_types as $itemtype) {
                    if (isset($already_add[$itemtype]) && count($already_add[$itemtype])) {
                        $iterator = $DB->request([
                            'SELECT'          => [
                                'glpi_softwareversions.name AS version',
                                'glpi_softwares.name AS name',
                                'glpi_softwares.id'
                            ],
                            'DISTINCT'        => true,
                            'FROM'            => 'glpi_items_softwareversions',
                            'LEFT JOIN'       => [
                                'glpi_softwareversions'  => [
                                    'ON' => [
                                        'glpi_items_softwareversions' => 'softwareversions_id',
                                        'glpi_softwareversions'       => 'id'
                                    ]
                                ],
                                'glpi_softwares'        => [
                                    'ON' => [
                                        'glpi_softwareversions' => 'softwares_id',
                                        'glpi_softwares'        => 'id'
                                    ]
                                ]
                            ],
                            'WHERE'        => [
                                'glpi_items_softwareversions.items_id' => $already_add[$itemtype],
                                'glpi_items_softwareversions.itemtype' => $itemtype,
                                'glpi_softwares.is_helpdesk_visible'   => 1
                            ] + getEntitiesRestrictCriteria('glpi_softwares', '', $entity_restrict),
                            'ORDERBY'      => 'glpi_softwares.name'
                        ]);

                        $devices = [];
                        if (count($iterator)) {
                            $item       = new Software();
                            $type_name  = $item->getTypeName();
                            if (!isset($already_add['Software'])) {
                                $already_add['Software'] = [];
                            }
                            foreach ($iterator as $data) {
                                if (!in_array($data["id"], $already_add['Software'])) {
                                    $output = sprintf(__('%1$s - %2$s'), $type_name, $data["name"]);
                                    $output = sprintf(
                                        __('%1$s (%2$s)'),
                                        $output,
                                        sprintf(__('%1$s: %2$s'), __('version'), $data["version"])
                                    );
                                    if ($_SESSION["glpiis_ids_visible"]) {
                                        $output = sprintf(__('%1$s (%2$s)'), $output, $data["id"]);
                                    }
                                    $devices["Software_" . $data["id"]] = $output;

                                    $already_add['Software'][] = $data["id"];
                                }
                            }
                            if (count($devices)) {
                                $my_devices[__('Installed software')] = $devices;
                            }
                        }
                    }
                }
            }
            // Get linked items to computers
            if (isset($already_add['Computer']) && count($already_add['Computer'])) {
                $devices = [];

                // Direct Connection
                $types = ['Monitor', 'Peripheral', 'Phone', 'Printer'];
                foreach ($types as $itemtype) {
                    if (
                        in_array($itemtype, $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])
                        && ($item = getItemForItemtype($itemtype))
                    ) {
                        $itemtable = getTableForItemType($itemtype);
                        if (!isset($already_add[$itemtype])) {
                            $already_add[$itemtype] = [];
                        }
                        $criteria = [
                            'SELECT'          => "$itemtable.*",
                            'DISTINCT'        => true,
                            'FROM'            => 'glpi_computers_items',
                            'LEFT JOIN'       => [
                                $itemtable  => [
                                    'ON' => [
                                        'glpi_computers_items'  => 'items_id',
                                        $itemtable              => 'id'
                                    ]
                                ]
                            ],
                            'WHERE'           => [
                                'glpi_computers_items.itemtype'     => $itemtype,
                                'glpi_computers_items.computers_id' => $already_add['Computer']
                            ] + getEntitiesRestrictCriteria($itemtable, '', $entity_restrict),
                            'ORDERBY'         => "$itemtable.name"
                        ];

                        if ($item->maybeDeleted()) {
                            $criteria['WHERE']["$itemtable.is_deleted"] = 0;
                        }
                        if ($item->maybeTemplate()) {
                            $criteria['WHERE']["$itemtable.is_template"] = 0;
                        }

                        $iterator = $DB->request($criteria);
                        if (count($iterator)) {
                            $type_name = $item->getTypeName();
                            foreach ($iterator as $data) {
                                if (!in_array($data["id"], $already_add[$itemtype])) {
                                    $output = $data["name"];
                                    if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                                        $output = sprintf(__('%1$s (%2$s)'), $output, $data['id']);
                                    }
                                    $output = sprintf(__('%1$s - %2$s'), $type_name, $output);
                                    if ($itemtype != 'Software') {
                                        $output = sprintf(__('%1$s - %2$s'), $output, $data['otherserial']);
                                    }
                                    $devices[$itemtype . "_" . $data["id"]] = $output;

                                    $already_add[$itemtype][] = $data["id"];
                                }
                            }
                        }
                    }
                }
                if (count($devices)) {
                    $my_devices[__('Connected devices')] = $devices;
                }
            }
            echo "<div id='tracking_my_devices' class='input-group mb-1'>";
            echo "<span class='input-group-text'>" . __('My devices') . "</span>";
            Dropdown::showFromArray('my_items', $my_devices, ['rand' => $rand]);
            echo "<span id='item_selection_information$rand' class='ms-1'></span>";
            echo "</div>";

            // Auto update summary of active or just solved ITIL object
            $params = ['my_items' => '__VALUE__'];
            $class_l = strtolower(static::class);
            Ajax::updateItemOnSelectEvent(
                "dropdown_my_items$rand",
                "item_selection_information$rand",
                $CFG_GLPI["root_doc"] . "/ajax/{$class_l}iteminformation.php",
                $params
            );
        }
    }

    /**
     * Make a select box with all glpi items
     *
     * @param $options array of possible options:
     *    - name         : string / name of the select (default is users_id)
     *    - value
     *    - comments     : boolean / is the comments displayed near the dropdown (default true)
     *    - entity       : integer or array / restrict to a defined entity or array of entities
     *                      (default -1 : no restriction)
     *    - entity_sons  : boolean / if entity restrict specified auto select its sons
     *                      only available if entity is a single value not an array(default false)
     *    - rand         : integer / already computed rand value
     *    - toupdate     : array / Update a specific item on select change on dropdown
     *                      (need value_fieldname, to_update, url
     *                      (see Ajax::updateItemOnSelectEvent for information)
     *                      and may have moreparams)
     *    - used         : array / Already used items ID: not to display in dropdown (default empty)
     *    - on_change    : string / value to transmit to "onChange"
     *    - display      : boolean / display or get string (default true)
     *    - width        : specific width needed
     *    - hide_if_no_elements  : boolean / hide dropdown if there is no elements (default false)
     *
     **/
    public static function dropdown($options = [])
    {
        global $DB;

        // Default values
        $p['name']           = 'items';
        $p['value']          = '';
        $p['all']            = 0;
        $p['on_change']      = '';
        $p['comments']       = 1;
        $p['width']          = '';
        $p['entity']         = -1;
        $p['entity_sons']    = false;
        $p['used']           = [];
        $p['toupdate']       = '';
        $p['rand']           = mt_rand();
        $p['display']        = true;
        $p['hide_if_no_elements'] = false;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $itemtypes = ['Computer', 'Monitor', 'NetworkEquipment', 'Peripheral', 'Phone', 'Printer'];

        $union = new QueryUnion();
        foreach ($itemtypes as $type) {
            $table = getTableForItemType($type);
            $union->addQuery([
                'SELECT' => [
                    'id',
                    new QueryExpression("$type AS " . $DB->quoteName('itemtype')),
                    "name"
                ],
                'FROM'   => $table,
                'WHERE'  => [
                    'NOT'          => ['id' => null],
                    'is_deleted'   => 0,
                    'is_template'  => 0
                ]
            ]);
        }

        $iterator = $DB->request(['FROM' => $union]);

        if ($p['hide_if_no_elements'] && $iterator->count() === 0) {
            return;
        }

        $output = [];

        foreach ($iterator as $data) {
            $item = getItemForItemtype($data['itemtype']);
            $output[$data['itemtype'] . "_" . $data['id']] = $item->getTypeName() . " - " . $data['name'];
        }

        return Dropdown::showFromArray($p['name'], $output, $p);
    }

    /**
     * Return used items for a ITIL object
     *
     * @param integer type $obj_id ITIL object on which the used item are attached
     *
     * @return array
     */
    public static function getUsedItems($items_id)
    {

        $data = getAllDataFromTable(static::getTable(), [static::$items_id_1 => $items_id]);
        $used = [];
        if (!empty($data)) {
            foreach ($data as $val) {
                $used[$val['itemtype']][] = $val['items_id'];
            }
        }

        return $used;
    }

    /**
     * Form for Followup on Massive action
     **/
    public static function showFormMassiveAction($ma)
    {
        global $CFG_GLPI;

        switch ($ma->getAction()) {
            case 'add_item':
                Dropdown::showSelectItemFromItemtypes([
                    'items_id_name'   => 'items_id',
                    'itemtype_name'   => 'item_itemtype',
                    'itemtypes'       => $CFG_GLPI['ticket_types'],
                    'checkright'      => true,
                    'entity_restrict' => $_SESSION['glpiactive_entity']
                ]);
                echo "<br><input type='submit' name='add' value=\"" . _sx('button', 'Add') . "\" class='btn btn-primary'>";
                break;

            case 'delete_item':
                Dropdown::showSelectItemFromItemtypes([
                    'items_id_name'   => 'items_id',
                    'itemtype_name'   => 'item_itemtype',
                    'itemtypes'       => $CFG_GLPI['ticket_types'],
                    'checkright'      => true,
                    'entity_restrict' => $_SESSION['glpiactive_entity']
                ]);

                echo "<br><input type='submit' name='delete' value=\"" . __('Delete permanently') . "\" class='btn btn-primary'>";
                break;
        }
    }

    /**
     * @since 0.85
     *
     * @see CommonDBTM::showMassiveActionsSubForm()
     **/
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {

        switch ($ma->getAction()) {
            case 'add_item':
                static::showFormMassiveAction($ma);
                return true;

            case 'delete_item':
                static::showFormMassiveAction($ma);
                return true;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        switch ($ma->getAction()) {
            case 'add_item':
                $input = $ma->getInput();

                $item_obj = new static();
                foreach ($ids as $id) {
                    if ($item->getFromDB($id) && !empty($input['items_id'])) {
                        $input[static::$items_id_1] = $id;
                        $input['itemtype'] = $input['item_itemtype'];

                        if ($item_obj->can(-1, CREATE, $input)) {
                            $ok = true;
                            if (!$item_obj->add($input)) {
                                $ok = false;
                            }

                            if ($ok) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                    }
                }
                return;

            case 'delete_item':
                $input = $ma->getInput();
                $item_obj = new static();
                foreach ($ids as $id) {
                    if ($item->getFromDB($id) && !empty($input['items_id'])) {
                        $item_found = $item_obj->find([
                            static::$items_id_1   => $id,
                            'itemtype'     => $input['item_itemtype'],
                            'items_id'     => $input['items_id']
                        ]);
                        if (!empty($item_found)) {
                            $item_founds_id = array_keys($item_found);
                            $input['id'] = $item_founds_id[0];

                            if ($item_obj->can($input['id'], DELETE, $input)) {
                                $ok = true;
                                if (!$item_obj->delete($input)) {
                                    $ok = false;
                                }

                                if ($ok) {
                                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                                } else {
                                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                    $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                                }
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                                $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                            $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                    }
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => static::$items_id_1,
            'name'               => static::$itemtype_1::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => $this->getTable(),
            'field'              => 'items_id',
            'name'               => _n('Associated element', 'Associated elements', Session::getPluralNumber()),
            'datatype'           => 'specific',
            'comments'           => true,
            'nosort'             => true,
            'additionalfields'   => ['itemtype']
        ];

        $tab[] = [
            'id'                 => '131',
            'table'              => $this->getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Associated item type', 'Associated item types', Session::getPluralNumber()),
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'ticket_types',
            'nosort'             => true
        ];

        return $tab;
    }


    /**
     * Add a message on add action
     **/
    public function addMessageOnAddAction()
    {
        $addMessAfterRedirect = false;
        if (isset($this->input['_add'])) {
            $addMessAfterRedirect = true;
        }

        if (
            isset($this->input['_no_message'])
            || !$this->auto_message_on_action
        ) {
            $addMessAfterRedirect = false;
        }

        if ($addMessAfterRedirect) {
            $item = getItemForItemtype($this->fields['itemtype']);
            $item->getFromDB($this->fields['items_id']);

            if (($name = $item->getName()) == NOT_AVAILABLE) {
               //TRANS: %1$s is the itemtype, %2$d is the id of the item
                $item->fields['name'] = sprintf(
                    __('%1$s - ID %2$d'),
                    $item->getTypeName(1),
                    $item->fields['id']
                );
            }

            $display = (isset($this->input['_no_message_link']) ? $item->getNameID()
                                                            : $item->getLink());

           // Do not display quotes
           //TRANS : %s is the description of the added item
            Session::addMessageAfterRedirect(sprintf(
                __('%1$s: %2$s'),
                __('Item successfully added'),
                stripslashes($display)
            ));
        }
    }

    /**
     * Add a message on delete action
     **/
    public function addMessageOnPurgeAction()
    {

        if (!$this->maybeDeleted()) {
            return;
        }

        $addMessAfterRedirect = false;
        if (isset($this->input['_delete'])) {
            $addMessAfterRedirect = true;
        }

        if (
            isset($this->input['_no_message'])
            || !$this->auto_message_on_action
        ) {
            $addMessAfterRedirect = false;
        }

        if ($addMessAfterRedirect) {
            $item = getItemForItemtype($this->fields['itemtype']);
            $item->getFromDB($this->fields['items_id']);

            if (isset($this->input['_no_message_link'])) {
                $display = $item->getNameID();
            } else {
                $display = $item->getLink();
            }
            //TRANS : %s is the description of the updated item
            Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'), __('Item successfully deleted'), $display));
        }
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'items_id':
                if (strpos($values[$field], "_") !== false) {
                    $item_itemtype      = explode("_", $values[$field]);
                    $values['itemtype'] = $item_itemtype[0];
                    $values[$field]     = $item_itemtype[1];
                }

                if (isset($values['itemtype'])) {
                    if (isset($options['comments']) && $options['comments']) {
                        $tmp = Dropdown::getDropdownName(
                            getTableForItemType($values['itemtype']),
                            $values[$field],
                            1
                        );
                         return sprintf(
                             __('%1$s %2$s'),
                             $tmp['name'],
                             Html::showToolTip($tmp['comment'], ['display' => false])
                         );
                    }
                    return Dropdown::getDropdownName(
                        getTableForItemType($values['itemtype']),
                        $values[$field]
                    );
                }
                break;
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        switch ($field) {
            case 'items_id':
                if (isset($values['itemtype']) && !empty($values['itemtype'])) {
                    $options['name']  = $name;
                    $options['value'] = $values[$field];
                    return Dropdown::show($values['itemtype'], $options);
                } else {
                    static::dropdownAllDevices($name, 0, 0);
                    return ' ';
                }
                break;
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public static function dropdownAllDevices(
        $myname,
        $itemtype,
        $items_id = 0,
        $admin = 0,
        $users_id = 0,
        $entity_restrict = -1,
        $options = []
    ) {
        global $CFG_GLPI;

        $params = [static::$items_id_1 => 0,
            'used'       => [],
            'multiple'   => 0,
            'rand'       => mt_rand()
        ];

        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        $rand = $params['rand'];

        if ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] == 0) {
            echo "<input type='hidden' name='$myname' value=''>";
            echo "<input type='hidden' name='items_id' value='0'>";
        } else {
            echo "<div id='tracking_all_devices$rand' class='input-group mb-1'>";
            // KEEP Ticket::HELPDESK_ALL_HARDWARE because it is only define on ticket level
            if (
                $_SESSION["glpiactiveprofile"]["helpdesk_hardware"] & pow(
                    2,
                    Ticket::HELPDESK_ALL_HARDWARE
                )
            ) {
               // Display a message if view my hardware
                if (
                    $users_id
                    && ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] & pow(
                        2,
                        Ticket::HELPDESK_MY_HARDWARE
                    ))
                ) {
                    echo "<span class='input-group-text'>" . __('Or complete search') . "</span>";
                }

                $types = static::$itemtype_1::getAllTypesForHelpdesk();
                $emptylabel = __('General');
                if ($params[static::$items_id_1] > 0) {
                    $emptylabel = Dropdown::EMPTY_VALUE;
                }
                Dropdown::showItemTypes(
                    $myname,
                    array_keys($types),
                    ['emptylabel' => $emptylabel,
                        'value'      => $itemtype,
                        'rand'       => $rand,
                        'display_emptychoice' => true
                    ]
                );
                $found_type = isset($types[$itemtype]);

                $p = ['itemtype'        => '__VALUE__',
                    'entity_restrict' => $entity_restrict,
                    'admin'           => $admin,
                    'used'            => $params['used'],
                    'multiple'        => $params['multiple'],
                    'rand'            => $rand,
                    'myname'          => "add_items_id"
                ];

                Ajax::updateItemOnSelectEvent(
                    "dropdown_$myname$rand",
                    "results_$myname$rand",
                    $CFG_GLPI["root_doc"] .
                                             "/ajax/dropdownTrackingDeviceType.php",
                    $p
                );
                echo "<span id='results_$myname$rand'>\n";

               // Display default value if itemtype is displayed
                if (
                    $found_type
                    && $itemtype
                ) {
                    if (
                        ($item = getItemForItemtype($itemtype))
                        && $items_id
                    ) {
                        if ($item->getFromDB($items_id)) {
                            Dropdown::showFromArray(
                                'items_id',
                                [$items_id => $item->getName()],
                                ['value' => $items_id]
                            );
                        }
                    } else {
                        $p['itemtype'] = $itemtype;
                        echo "<script type='text/javascript' >\n";
                        echo "$(function() {";
                        Ajax::updateItemJsCode(
                            "results_$myname$rand",
                            $CFG_GLPI["root_doc"] .
                                      "/ajax/dropdownTrackingDeviceType.php",
                            $p
                        );
                        echo '});</script>';
                    }
                }
                echo "</span>\n";
            }
            echo "</div>";
        }
        return $rand;
    }
}
