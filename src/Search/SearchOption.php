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

namespace Glpi\Search;

/**
 * Object representing a search option.
 *
 * This is a wrapper class for the array format search option.
 *
 * @internal Not for use outside {@link Search} class and the "Glpi\Search" namespace.
 */
final class SearchOption implements \ArrayAccess
{
    /**
     * Internal search option array
     * @var array{id: int, name: string, field: string, table: string}
     */
    private array $search_opt_array;

    public function __construct(array $search_opt_array)
    {
        $this->search_opt_array = $search_opt_array;
    }

    /**
     * @param array $search_options
     * @return SearchOption[]
     */
    public static function getMultipleFromArray(array $search_options): array
    {
        $result = [];
        foreach ($search_options as $search_option) {
            if (is_a($search_option, self::class)) {
                // Already wrapped, nothing to do
                $result[] = $search_option;
            }
            $result[] = new self($search_option);
        }
        return $result;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->search_opt_array[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->search_opt_array[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->search_opt_array[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->search_opt_array[$offset]);
    }

    /**
     * Check if this search option represents a virtual field
     * @return bool
     */
    public function isVirtual(): bool
    {
        return strpos($this['field'], '_virtual') === 0;
    }

    public function isForceGroupBy(): bool
    {
        return $this['forcegroupby'] ?? false;
    }

    public function isComputationGroupBy(): bool
    {
        return $this['computationgroupby'] ?? false;
    }

    /**
     * Get the SEARCH_OPTION array
     *
     * @param class-string<\CommonDBTM>  $itemtype     Item type
     * @param boolean $withplugins  Get search options from plugins (true by default)
     *
     * @return array The reference to the array of search options for the given item type
     **/
    public static function getOptionsForItemtype($itemtype, $withplugins = true): array
    {
        global $CFG_GLPI;
        $item = null;

        static $search = [];

        if (!isset($search[$itemtype])) {
            // standard type first
            switch ($itemtype) {
                case 'Internet':
                    $search[$itemtype]['common']            = __('Characteristics');

                    $search[$itemtype][1]['table']          = 'networkport_types';
                    $search[$itemtype][1]['field']          = 'name';
                    $search[$itemtype][1]['name']           = __('Name');
                    $search[$itemtype][1]['datatype']       = 'itemlink';
                    $search[$itemtype][1]['searchtype']     = 'contains';

                    $search[$itemtype][2]['table']          = 'networkport_types';
                    $search[$itemtype][2]['field']          = 'id';
                    $search[$itemtype][2]['name']           = __('ID');
                    $search[$itemtype][2]['searchtype']     = 'contains';

                    $search[$itemtype][31]['table']         = 'glpi_states';
                    $search[$itemtype][31]['field']         = 'completename';
                    $search[$itemtype][31]['name']          = __('Status');

                    $search[$itemtype] += \NetworkPort::getSearchOptionsToAdd('networkport_types');
                    break;

                case \AllAssets::getType():
                    $search[$itemtype]['common']            = __('Characteristics');

                    $search[$itemtype][1]['table']          = 'asset_types';
                    $search[$itemtype][1]['field']          = 'name';
                    $search[$itemtype][1]['name']           = __('Name');
                    $search[$itemtype][1]['datatype']       = 'itemlink';
                    $search[$itemtype][1]['searchtype']     = 'contains';

                    $search[$itemtype][2]['table']          = 'asset_types';
                    $search[$itemtype][2]['field']          = 'id';
                    $search[$itemtype][2]['name']           = __('ID');
                    $search[$itemtype][2]['searchtype']     = 'contains';

                    $search[$itemtype][31]['table']         = 'glpi_states';
                    $search[$itemtype][31]['field']         = 'completename';
                    $search[$itemtype][31]['name']          = __('Status');

                    $search[$itemtype] += \Location::getSearchOptionsToAdd();

                    $search[$itemtype][5]['table']          = 'asset_types';
                    $search[$itemtype][5]['field']          = 'serial';
                    $search[$itemtype][5]['name']           = __('Serial number');

                    $search[$itemtype][6]['table']          = 'asset_types';
                    $search[$itemtype][6]['field']          = 'otherserial';
                    $search[$itemtype][6]['name']           = __('Inventory number');

                    $search[$itemtype][16]['table']         = 'asset_types';
                    $search[$itemtype][16]['field']         = 'comment';
                    $search[$itemtype][16]['name']          = __('Comments');
                    $search[$itemtype][16]['datatype']      = 'text';

                    $search[$itemtype][70]['table']         = 'glpi_users';
                    $search[$itemtype][70]['field']         = 'name';
                    $search[$itemtype][70]['name']          = \User::getTypeName(1);

                    $search[$itemtype][7]['table']          = 'asset_types';
                    $search[$itemtype][7]['field']          = 'contact';
                    $search[$itemtype][7]['name']           = __('Alternate username');
                    $search[$itemtype][7]['datatype']       = 'string';

                    $search[$itemtype][8]['table']          = 'asset_types';
                    $search[$itemtype][8]['field']          = 'contact_num';
                    $search[$itemtype][8]['name']           = __('Alternate username number');
                    $search[$itemtype][8]['datatype']       = 'string';

                    $search[$itemtype][71]['table']         = 'glpi_groups';
                    $search[$itemtype][71]['field']         = 'completename';
                    $search[$itemtype][71]['name']          = \Group::getTypeName(1);

                    $search[$itemtype][19]['table']         = 'asset_types';
                    $search[$itemtype][19]['field']         = 'date_mod';
                    $search[$itemtype][19]['name']          = __('Last update');
                    $search[$itemtype][19]['datatype']      = 'datetime';
                    $search[$itemtype][19]['massiveaction'] = false;

                    $search[$itemtype][23]['table']         = 'glpi_manufacturers';
                    $search[$itemtype][23]['field']         = 'name';
                    $search[$itemtype][23]['name']          = \Manufacturer::getTypeName(1);

                    $search[$itemtype][24]['table']         = 'glpi_users';
                    $search[$itemtype][24]['field']         = 'name';
                    $search[$itemtype][24]['linkfield']     = 'users_id_tech';
                    $search[$itemtype][24]['name']          = __('Technician in charge of the hardware');
                    $search[$itemtype][24]['condition']     = ['is_assign' => 1];

                    $search[$itemtype][49]['table']          = 'glpi_groups';
                    $search[$itemtype][49]['field']          = 'completename';
                    $search[$itemtype][49]['linkfield']      = 'groups_id_tech';
                    $search[$itemtype][49]['name']           = __('Group in charge of the hardware');
                    $search[$itemtype][49]['condition']      = ['is_assign' => 1];
                    $search[$itemtype][49]['datatype']       = 'dropdown';

                    $search[$itemtype][80]['table']         = 'glpi_entities';
                    $search[$itemtype][80]['field']         = 'completename';
                    $search[$itemtype][80]['name']          = \Entity::getTypeName(1);
                    break;

                default:
                    if ($item = getItemForItemtype($itemtype)) {
                        $search[$itemtype] = $item->searchOptions();
                    }
                    break;
            }

            if (
                \Session::getLoginUserID()
                && in_array($itemtype, $CFG_GLPI["ticket_types"])
            ) {
                $search[$itemtype]['tracking']          = __('Assistance');

                $search[$itemtype][60]['table']         = 'glpi_tickets';
                $search[$itemtype][60]['field']         = 'id';
                $search[$itemtype][60]['datatype']      = 'count';
                $search[$itemtype][60]['name']          = _x('quantity', 'Number of tickets');
                $search[$itemtype][60]['forcegroupby']  = true;
                $search[$itemtype][60]['usehaving']     = true;
                $search[$itemtype][60]['massiveaction'] = false;
                $search[$itemtype][60]['joinparams']    = [
                    'beforejoin' => [
                        'table' => 'glpi_items_tickets',
                        'joinparams' => [
                            'jointype' => 'itemtype_item'
                        ]
                    ],
                    'condition' => getEntitiesRestrictRequest('AND', 'NEWTABLE')
                ];

                $search[$itemtype][140]['table']         = 'glpi_problems';
                $search[$itemtype][140]['field']         = 'id';
                $search[$itemtype][140]['datatype']      = 'count';
                $search[$itemtype][140]['name']          = _x('quantity', 'Number of problems');
                $search[$itemtype][140]['forcegroupby']  = true;
                $search[$itemtype][140]['usehaving']     = true;
                $search[$itemtype][140]['massiveaction'] = false;
                $search[$itemtype][140]['joinparams']    = [
                    'beforejoin' => [
                        'table' => 'glpi_items_problems',
                        'joinparams' => [
                            'jointype' => 'itemtype_item'
                        ]
                    ],
                    'condition' => getEntitiesRestrictRequest('AND', 'NEWTABLE')
                ];
            }

            if (
                in_array($itemtype, $CFG_GLPI["networkport_types"])
                || ($itemtype == \AllAssets::getType())
            ) {
                $search[$itemtype] += \NetworkPort::getSearchOptionsToAdd($itemtype);
            }

            if (
                in_array($itemtype, $CFG_GLPI["contract_types"])
                || ($itemtype == \AllAssets::getType())
            ) {
                $search[$itemtype] += \Contract::getSearchOptionsToAdd();
            }

            if (
                \Document::canApplyOn($itemtype)
                || ($itemtype == \AllAssets::getType())
            ) {
                $search[$itemtype] += \Document::getSearchOptionsToAdd();
            }

            if (
                \Infocom::canApplyOn($itemtype)
                || ($itemtype == \AllAssets::getType())
            ) {
                $search[$itemtype] += \Infocom::getSearchOptionsToAdd($itemtype);
            }

            if (
                in_array($itemtype, $CFG_GLPI["domain_types"])
                || ($itemtype == \AllAssets::getType())
            ) {
                $search[$itemtype] += \Domain::getSearchOptionsToAdd($itemtype);
            }

            if (
                in_array($itemtype, $CFG_GLPI["appliance_types"])
                || ($itemtype == \AllAssets::getType())
            ) {
                $search[$itemtype] += \Appliance::getSearchOptionsToAdd($itemtype);
            }

            if (in_array($itemtype, $CFG_GLPI["link_types"])) {
                $search[$itemtype]['link'] = \Link::getTypeName(\Session::getPluralNumber());
                $search[$itemtype] += \Link::getSearchOptionsToAdd($itemtype);
                $search[$itemtype]['manuallink'] = \ManualLink::getTypeName(\Session::getPluralNumber());
                $search[$itemtype] += \ManualLink::getSearchOptionsToAdd($itemtype);
            }

            if ($withplugins) {
                // Search options added by plugins
                $plugsearch = \Plugin::getAddSearchOptions($itemtype);
                $plugsearch = $plugsearch + \Plugin::getAddSearchOptionsNew($itemtype);
                if (count($plugsearch)) {
                    $search[$itemtype] += ['plugins' => ['name' => _n('Plugin', 'Plugins', \Session::getPluralNumber())]];
                    $search[$itemtype] += $plugsearch;
                }
            }

            // Complete linkfield if not define
            if (is_null($item)) { // Special union type
                $itemtable = $CFG_GLPI['union_search_type'][$itemtype];
            } else {
                $itemtable = $item->getTable();
            }

            foreach ($search[$itemtype] as $key => $val) {
                if (!is_array($val) || count($val) == 1) {
                    // skip sub-menu
                    continue;
                }
                // Force massive action to false if linkfield is empty :
                if (isset($val['linkfield']) && empty($val['linkfield'])) {
                    $search[$itemtype][$key]['massiveaction'] = false;
                }

                // Set default linkfield
                if (!isset($val['linkfield']) || empty($val['linkfield'])) {
                    if (
                        (strcmp($itemtable, $val['table']) == 0)
                        && (!isset($val['joinparams']) || (count($val['joinparams']) == 0))
                    ) {
                        $search[$itemtype][$key]['linkfield'] = $val['field'];
                    } else {
                        $search[$itemtype][$key]['linkfield'] = getForeignKeyFieldForTable($val['table']);
                    }
                }
                // Add default joinparams
                if (!isset($val['joinparams'])) {
                    $search[$itemtype][$key]['joinparams'] = [];
                }
            }
        }

        return $search[$itemtype];
    }

    /**
     * Is the search item related to infocoms
     *
     * @param class-string<\CommonDBTM>  $itemtype  Item type
     * @param integer $searchID  ID of the element in $SEARCHOPTION
     *
     * @return boolean
     **/
    public static function isInfocomOption($itemtype, $searchID): bool
    {
        if (!\Infocom::canApplyOn($itemtype)) {
            return false;
        }

        $infocom_options = \Infocom::rawSearchOptionsToAdd($itemtype);
        $found_infocoms  = array_filter($infocom_options, static function ($option) use ($searchID) {
            return isset($option['id']) && $searchID == $option['id'];
        });

        return (count($found_infocoms) > 0);
    }

    /**
     * @param class-string<\CommonDBTM>  $itemtype
     * @param integer $field_num
     **/
    public static function getActionsFor($itemtype, $field_num)
    {

        $searchopt = self::getOptionsForItemtype($itemtype);
        $actions   = [
            'contains'    => __('contains'),
            'notcontains' => __('not contains'),
            'empty'       => __('is empty'),
            'searchopt'   => []
        ];

        if (isset($searchopt[$field_num]) && isset($searchopt[$field_num]['table'])) {
            $actions['searchopt'] = $searchopt[$field_num];

            // Force search type
            if (isset($actions['searchopt']['searchtype'])) {
                // Reset search option
                $actions = [
                    'searchopt'   => $searchopt[$field_num]
                ];
                if (!is_array($actions['searchopt']['searchtype'])) {
                    $actions['searchopt']['searchtype'] = [$actions['searchopt']['searchtype']];
                }
                foreach ($actions['searchopt']['searchtype'] as $searchtype) {
                    switch ($searchtype) {
                        case "equals":
                            $actions['equals'] = __('is');
                            break;

                        case "notequals":
                            $actions['notequals'] = __('is not');
                            break;

                        case "contains":
                            $actions['contains']    = __('contains');
                            $actions['notcontains'] = __('not contains');
                            break;

                        case "notcontains":
                            $actions['notcontains'] = __('not contains');
                            break;

                        case "under":
                            $actions['under'] = __('under');
                            break;

                        case "notunder":
                            $actions['notunder'] = __('not under');
                            break;

                        case "lessthan":
                            $actions['lessthan'] = __('before');
                            break;

                        case "morethan":
                            $actions['morethan'] = __('after');
                            break;
                    }
                }
                // Force is empty to be last
                $actions['empty'] = __('is empty');
                return $actions;
            }

            if (isset($searchopt[$field_num]['datatype'])) {
                switch ($searchopt[$field_num]['datatype']) {
                    case 'mio':
                    case 'count':
                    case 'number':
                    case "integer":
                        $opt = [
                            'contains'    => __('contains'),
                            'notcontains' => __('not contains'),
                            'equals'      => __('is'),
                            'notequals'   => __('is not'),
                            'empty'       => __('is empty'),
                            'searchopt'   => $searchopt[$field_num]
                        ];
                        // No is / isnot if no limits defined
                        if (
                            !isset($searchopt[$field_num]['min'])
                            && !isset($searchopt[$field_num]['max'])
                        ) {
                            unset($opt['equals']);
                            unset($opt['notequals']);

                            // https://github.com/glpi-project/glpi/issues/6917
                            // change filter wording for numeric values to be more
                            // obvious if the number dropdown will not be used
                            $opt['contains']    = __('is');
                            $opt['notcontains'] = __('is not');
                        }
                        return $opt;

                    case 'bool':
                        return [
                            'equals'      => __('is'),
                            'notequals'   => __('is not'),
                            'contains'    => __('contains'),
                            'notcontains' => __('not contains'),
                            'empty'       => __('is empty'),
                            'searchopt'   => $searchopt[$field_num]
                        ];

                    case 'right':
                        return ['equals'    => __('is'),
                            'notequals' => __('is not'),
                            'empty'     => __('is empty'),
                            'searchopt' => $searchopt[$field_num]
                        ];

                    case 'itemtypename':
                        return ['equals'    => __('is'),
                            'notequals' => __('is not'),
                            'empty'     => __('is empty'),
                            'searchopt' => $searchopt[$field_num]
                        ];

                    case 'date':
                    case 'datetime':
                    case 'date_delay':
                        return [
                            'equals'      => __('is'),
                            'notequals'   => __('is not'),
                            'lessthan'    => __('before'),
                            'morethan'    => __('after'),
                            'contains'    => __('contains'),
                            'notcontains' => __('not contains'),
                            'empty'       => __('is empty'),
                            'searchopt'   => $searchopt[$field_num]
                        ];
                }
            }

            // switch ($searchopt[$field_num]['table']) {
            //    case 'glpi_users_validation' :
            //       return array('equals'    => __('is'),
            //                    'notequals' => __('is not'),
            //                    'searchopt' => $searchopt[$field_num]);
            // }

            switch ($searchopt[$field_num]['field']) {
                case 'id':
                    return ['equals'    => __('is'),
                        'notequals' => __('is not'),
                        'empty'     => __('is empty'),
                        'searchopt' => $searchopt[$field_num]
                    ];

                case 'name':
                case 'completename':
                    $actions = [
                        'contains'    => __('contains'),
                        'notcontains' => __('not contains'),
                        'equals'      => __('is'),
                        'notequals'   => __('is not'),
                        'empty'       => __('is empty'),
                        'searchopt'   => $searchopt[$field_num]
                    ];

                    // Specific case of TreeDropdown : add under
                    $itemtype_linked = getItemTypeForTable($searchopt[$field_num]['table']);
                    if ($itemlinked = getItemForItemtype($itemtype_linked)) {
                        if ($itemlinked instanceof \CommonTreeDropdown) {
                            $actions['under']    = __('under');
                            $actions['notunder'] = __('not under');
                        }
                        return $actions;
                    }
            }
        }
        return $actions;
    }

    /**
     *
     * Get an option number in the SEARCH_OPTION array
     *
     * @param class-string<\CommonDBTM> $itemtype  Item type
     * @param string $field     Name
     *
     * @return integer
     **/
    public static function getOptionNumber($itemtype, $field): int
    {

        $table = $itemtype::getTable();
        $opts  = self::getOptionsForItemtype($itemtype);

        foreach ($opts as $num => $opt) {
            if (
                is_array($opt) && isset($opt['table'])
                && ($opt['table'] == $table)
                && ($opt['field'] == $field)
            ) {
                return $num;
            }
        }
        return 0;
    }

    /**
     * Clean search options depending on the user active profile
     *
     * @param class-string<\CommonDBTM>  $itemtype     Item type to manage
     * @param integer $action       Action which is used to manupulate searchoption
     *                               (default READ)
     * @param boolean $withplugins  Get plugins options (true by default)
     *
     * @return array Clean $SEARCH_OPTION array
     **/
    public static function getCleanedOptions($itemtype, $action = READ, $withplugins = true): array
    {
        global $CFG_GLPI;

        $options = self::getOptionsForItemtype($itemtype, $withplugins);
        $todel   = [];

        if (
            !\Session::haveRight('infocom', $action)
            && \Infocom::canApplyOn($itemtype)
        ) {
            $itemstodel = \Infocom::getSearchOptionsToAdd($itemtype);
            $todel      = array_merge($todel, array_keys($itemstodel));
        }

        if (
            !\Session::haveRight('contract', $action)
            && in_array($itemtype, $CFG_GLPI["contract_types"])
        ) {
            $itemstodel = \Contract::getSearchOptionsToAdd();
            $todel      = array_merge($todel, array_keys($itemstodel));
        }

        if (
            !\Session::haveRight('document', $action)
            && \Document::canApplyOn($itemtype)
        ) {
            $itemstodel = \Document::getSearchOptionsToAdd();
            $todel      = array_merge($todel, array_keys($itemstodel));
        }

        // do not show priority if you don't have right in profile
        if (
            ($itemtype == 'Ticket')
            && ($action == UPDATE)
            && !\Session::haveRight('ticket', \Ticket::CHANGEPRIORITY)
        ) {
            $todel[] = 3;
        }

        if ($itemtype == 'Computer') {
            if (!\Session::haveRight('networking', $action)) {
                $itemstodel = \NetworkPort::getSearchOptionsToAdd($itemtype);
                $todel      = array_merge($todel, array_keys($itemstodel));
            }
        }
        if (!\Session::haveRight(strtolower($itemtype), READNOTE)) {
            $todel[] = 90;
        }

        if (count($todel)) {
            foreach ($todel as $ID) {
                if (isset($options[$ID])) {
                    unset($options[$ID]);
                }
            }
        }

        return $options;
    }
}
