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

abstract class CommonITILSatisfaction extends CommonDBTM
{
    public $dohistory         = true;
    public $history_blacklist = ['date_answered'];

    /**
     * Survey is done internally
     */
    public const TYPE_INTERNAL = 1;

    /**
     * Survey is done externally
     */
    public const TYPE_EXTERNAL = 2;

    abstract public static function getConfigSufix(): string;
    abstract public static function getSearchOptionIDOffset(): int;

    public static function getTypeName($nb = 0)
    {
        return __('Satisfaction');
    }

    public static function getIcon()
    {
        return 'ti ti-star';
    }

    /**
     * Get the itemtype this satisfaction is for
     * @return string
     */
    public static function getItemtype(): string
    {
        // Return itemtype extracted from current class name (Remove 'Satisfaction' suffix)
        return preg_replace('/Satisfaction$/', '', static::class);
    }

    /**
     * for use showFormHeader
     **/
    public static function getIndexName()
    {
        return static::getItemtype()::getForeignKeyField();
    }

    public function getLogTypeID()
    {
        /** @var CommonITILObject $itemtype */
        $itemtype = static::getItemtype();
        return [$itemtype, $this->fields[$itemtype::getForeignKeyField()]];
    }

    public static function canUpdate()
    {
        /** @var CommonITILObject $itemtype */
        $itemtype = static::getItemtype();
        return (Session::haveRight($itemtype::$rightname, READ));
    }

    /**
     * Is the current user have right to update the current satisfaction
     *
     * @return boolean
     **/
    public function canUpdateItem()
    {
        /** @var CommonITILObject $itemtype */
        $itemtype = static::getItemtype();
        $item = new $itemtype();
        if (!$item->getFromDB($this->fields[$itemtype::getForeignKeyField()])) {
            return false;
        }

        // you can't change if your answer > 12h
        if (
            !is_null($this->fields['date_answered'])
            && ((time() - strtotime($this->fields['date_answered'])) > (12 * HOUR_TIMESTAMP))
        ) {
            return false;
        }

        if (
            $item->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())
            || ($item->fields["users_id_recipient"] === Session::getLoginUserID() && Session::haveRight($itemtype::$rightname, $itemtype::SURVEY))
            || (isset($_SESSION["glpigroups"])
                && $item->haveAGroup(CommonITILActor::REQUESTER, $_SESSION["glpigroups"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * form for satisfaction
     *
     * @param CommonITILObject $item The item this satisfaction is for
     **/
    public function showSatisactionForm($item)
    {
        $item_id             = $item->fields['id'];
        $options             = [];
        $options['colspan']  = 1;

        // for external inquest => link
        if ((int) $this->fields["type"] === self::TYPE_EXTERNAL) {
            $url = Entity::generateLinkSatisfaction($item);
            echo "<div class='center spaced'>" .
                "<a href='$url'>" . __('External survey') . "</a><br>($url)</div>";
        } else { // for internal inquest => form
            $config_suffix = $item->getType() === 'Ticket' ? '' : ('_' . strtolower($item->getType()));

            $this->showFormHeader($options);

            // Set default satisfaction to 3 if not set
            if (is_null($this->fields["satisfaction"])) {
                $default_rate = Entity::getUsedConfig('inquest_config' . $config_suffix, $item->fields['entities_id'], 'inquest_default_rate' . $config_suffix);
                $this->fields["satisfaction"] = $default_rate;
            }
            echo "<tr class='tab_bg_2'>";
            echo "<td>" . sprintf(__('Satisfaction with the resolution of the %s'), strtolower($item::getTypeName(1))) . "</td>";
            echo "<td>";
            echo "<input type='hidden' name='{$item::getForeignKeyField()}' value='$item_id'>";

            echo "<select id='satisfaction_data' name='satisfaction'>";

            $max_rate = Entity::getUsedConfig('inquest_config' . $config_suffix, $item->fields['entities_id'], 'inquest_max_rate' . $config_suffix);
            for ($i = 0; $i <= $max_rate; $i++) {
                echo "<option value='$i' " . (($i == $this->fields["satisfaction"]) ? 'selected' : '') .
                    ">$i</option>";
            }
            echo "</select>";
            echo "<div class='rateit' id='stars'></div>";
            echo  "<script type='text/javascript'>";
            echo "$(function() {";
            echo "$('#stars').rateit({value: " . $this->fields["satisfaction"] . ",
                                   min : 0,
                                   max : " . $max_rate . ",
                                   step: 1,
                                   backingfld: '#satisfaction_data',
                                   ispreset: true,
                                   resetable: false});";
            echo "});</script>";

            echo "</td></tr>";

            echo "<tr class='tab_bg_2'>";
            echo "<td rowspan='1'>" . __('Comments') . "</td>";
            echo "<td rowspan='1' class='middle'>";
            echo "<textarea class='form-control' rows='7' name='comment'>" . $this->fields["comment"] . "</textarea>";
            echo "</td></tr>";

            if ($this->fields["date_answered"] > 0) {
                echo "<tr class='tab_bg_2'>";
                echo "<td>" . __('Response date to the satisfaction survey') . "</td><td>";
                echo Html::convDateTime($this->fields["date_answered"]) . "</td></tr>\n";
            }

            $options['candel'] = false;
            $this->showFormButtons($options);
        }
    }

    public function prepareInputForUpdate($input)
    {
        if (array_key_exists('satisfaction', $input) && $input['satisfaction'] >= 0) {
            $input["date_answered"] = $_SESSION["glpi_currenttime"];
        }

        if (array_key_exists('satisfaction', $input) || array_key_exists('comment', $input)) {
            $satisfaction = array_key_exists('satisfaction', $input) ? $input['satisfaction'] : $this->fields['satisfaction'];
            $comment      = array_key_exists('comment', $input) ? $input['comment'] : $this->fields['comment'];
            $itemtype     = $this->getItemtype();
            $entities_id  = $this->getItemEntity($itemtype, $this->fields[getForeignKeyFieldForItemType($this->getItemtype())]);

            $config_suffix = $itemtype === 'Ticket' ? '' : ('_' . strtolower($itemtype));
            $inquest_mandatory_comment = Entity::getUsedConfig('inquest_config' . $config_suffix, $entities_id, 'inquest_mandatory_comment' . $config_suffix);
            if ($inquest_mandatory_comment && ($satisfaction <= $inquest_mandatory_comment) && empty($comment)) {
                Session::addMessageAfterRedirect(
                    sprintf(__('Comment is required if score is less than or equal to %d'), $inquest_mandatory_comment),
                    false,
                    ERROR
                );
                return false;
            }
        }

        if (array_key_exists('satisfaction', $input) && $input['satisfaction'] >= 0) {
            $item = static::getItemtype();
            $item = new $item();
            $fkey = static::getIndexName();
            if ($item->getFromDB($input[$fkey] ?? $this->fields[$fkey])) {
                $max_rate = Entity::getUsedConfig(
                    'inquest_config',
                    $item->fields['entities_id'],
                    'inquest_max_rate' . static::getConfigSufix()
                );
                $input['satisfaction_scaled_to_5'] = $input['satisfaction'] / ($max_rate / 5);
            }
        }

        return $input;
    }

    public function post_addItem()
    {
        global $CFG_GLPI;

        if (!isset($this->input['_disablenotif']) && $CFG_GLPI["use_notifications"]) {
            /** @var CommonDBTM $itemtype */
            $itemtype = static::getItemtype();
            $item = new $itemtype();
            if ($item->getFromDB($this->fields[$itemtype::getForeignKeyField()])) {
                NotificationEvent::raiseEvent("satisfaction", $item, [], $this);
            }
        }
    }

    public function post_UpdateItem($history = 1)
    {
        global $CFG_GLPI;

        if (!isset($this->input['_disablenotif']) && $CFG_GLPI["use_notifications"]) {
            /** @var CommonDBTM $itemtype */
            $itemtype = static::getItemtype();
            $item = new $itemtype();
            if ($item->getFromDB($this->fields[$itemtype::getForeignKeyField()])) {
                NotificationEvent::raiseEvent("replysatisfaction", $item, [], $this);
            }
        }
    }

    /**
     * display satisfaction value
     *
     * @param int $value Between 0 and 10
     **/
    public static function displaySatisfaction($value, $entities_id)
    {
        if (is_null($value)) {
            return "";
        }

        $max_rate = Entity::getUsedConfig(
            'inquest_config',
            $entities_id,
            'inquest_max_rate' . static::getConfigSufix()
        );

        if ($value < 0) {
            $value = 0;
        }
        if ($value > $max_rate) {
            $value = $max_rate;
        }

        $rand = mt_rand();
        $out = "<div id='rateit_$rand' class='rateit'></div>";
        $out .= Html::scriptBlock("
            $(function () {
                $('#rateit_$rand').rateit({
                    max: $max_rate,
                    resetable: false,
                    value: $value,
                    readonly: true,
                });
            });
        ");

        return $out;
    }


    /**
     * Get name of inquest type
     *
     * @param int $value Survey type ID
     **/
    public static function getTypeInquestName($value)
    {

        switch ($value) {
            case self::TYPE_INTERNAL:
                return __('Internal survey');

            case self::TYPE_EXTERNAL:
                return __('External survey');

            default:
                // Get value if not defined
                return $value;
        }
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'type':
                return self::getTypeInquestName($values[$field]);
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
            case 'type':
                $options['value'] = $values[$field];
                $typeinquest = [
                    self::TYPE_INTERNAL => __('Internal survey'),
                    self::TYPE_EXTERNAL => __('External survey')
                ];
                return Dropdown::showFromArray($name, $typeinquest, $options);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public static function getFormURLWithID($id = 0, $full = true)
    {

        $satisfaction = new static();
        if (!$satisfaction->getFromDB($id)) {
            return '';
        }

        /** @var CommonDBTM $itemtype */
        $itemtype = static::getItemtype();
        return $itemtype::getFormURLWithID($satisfaction->fields[$itemtype::getForeignKeyField()]) . '&forcetab=' . $itemtype::getType() . '$3';
    }

    public static function rawSearchOptionsToAdd()
    {
        $base_id = static::getSearchOptionIDOffset();
        $table = static::getTable();

        $tab[] = [
            'id'                 => 'satisfaction',
            'name'               => __('Satisfaction survey')
        ];

        $tab[] = [
            'id'                 => 31 + $base_id,
            'table'              => $table,
            'field'              => 'type',
            'name'               => _n('Type', 'Types', 1),
            'massiveaction'      => false,
            'searchtype'         => ['equals', 'notequals'],
            'searchequalsonfield' => true,
            'joinparams'         => [
                'jointype'           => 'child'
            ],
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => 60 + $base_id,
            'table'              => $table,
            'field'              => 'date_begin',
            'name'               => __('Creation date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child'
            ]
        ];

        $tab[] = [
            'id'                 => 61 + $base_id,
            'table'              => $table,
            'field'              => 'date_answered',
            'name'               => __('Response date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child'
            ]
        ];

        $tab[] = [
            'id'                 => 62 + $base_id,
            'table'              => $table,
            'field'              => 'satisfaction',
            'name'               => __('Satisfaction'),
            'datatype'           => 'number',
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child'
            ],
            'additionalfields' => ['TABLE.entities_id'],
        ];

        $tab[] = [
            'id'                 => 63 + $base_id,
            'table'              => $table,
            'field'              => 'comment',
            'name'               => __('Comments'),
            'datatype'           => 'text',
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child'
            ]
        ];

        return $tab;
    }
}
