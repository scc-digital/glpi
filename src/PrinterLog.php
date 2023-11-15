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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Dashboard\Widget;

/**
 * Store printer metrics
 */
class PrinterLog extends CommonDBChild
{
    public static $itemtype        = 'Printer';
    public static $items_id        = 'printers_id';
    public $dohistory              = false;


    /**
     * Get name of this type by language of the user connected
     *
     * @param integer $nb number of elements
     *
     * @return string name of this type
     */
    public static function getTypeName($nb = 0)
    {
        return __('Page counters');
    }

    public static function getIcon()
    {
        return 'ti ti-chart-line';
    }

    /**
     * Get the tab name used for item
     *
     * @param object $item the item object
     * @param integer $withtemplate 1 if is a template form
     * @return string|array name of the tab
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        $array_ret = [];

        if ($item->getType() == 'Printer') {
            $cnt = countElementsInTable([static::getTable()], [static::$items_id => $item->getField('id')]);
            $array_ret[] = self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $cnt, $item::getType());
        }
        return $array_ret;
    }


    /**
     * Display the content of the tab
     *
     * @param object $item
     * @param integer $tabnum number of the tab to display
     * @param integer $withtemplate 1 if is a template form
     * @return boolean
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Printer::getType() && $item->getID() > 0) {
            $printerlog = new self();
            $printerlog->showMetrics($item);
            return true;
        }
        return false;
    }

    /**
     * Get metrics
     *
     * @param Printer       $printer      Printer instance
     * @param array         $user_filters User filters
     * @param string        $interval     Date interval string (e.g. 'P1Y' for 1 year)
     * @param DateTime|null $start_date   Start date for the metrics range
     * @param DateTime      $end_date     End date for the metrics range
     * @param string        $format       Format for the metrics data ('dynamic', 'daily', 'weekly', 'monthly', 'yearly')
     *
     * @return array An array of printer metrics data
     */
    final public static function getMetrics(
        Printer $printer,
        array $user_filters = [],
        string $interval = 'P1Y',
        ?DateTime $start_date = null,
        DateTime $end_date = new DateTime(),
        string $format = 'dynamic'
    ): array {
        global $DB;

        if (!$start_date) {
            $start_date = new DateTime();
            $start_date->sub(new DateInterval($interval));
        }

        $filters = [
            ['date' => ['>=', $start_date->format('Y-m-d')]],
            ['date' => ['<=', $end_date->format('Y-m-d')]]
        ];
        $filters = array_merge($filters, $user_filters);

        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'printers_id'  => $printer->fields['id']
            ] + $filters,
            'ORDER'  => 'date ASC',
        ]);

        $series = iterator_to_array($iterator, false);

        if ($format == 'dynamic') {
            // Reduce the data to 25 points
            $count = count($series);
            $max_size = 25;
            if ($count > $max_size) {
                // Keep one row every X entry using modulo
                $modulo = round($count / $max_size);
                $series = array_filter(
                    $series,
                    fn ($k) => (($count - ($k + 1)) % $modulo) == 0,
                    ARRAY_FILTER_USE_KEY
                );
            }
        } else {
            $formats = [
                'daily' => 'Ymd', // Reduce the data to one point per day max
                'weekly' => 'YoW', // Reduce the data to one point per week max
                'monthly' => 'Ym', // Reduce the data to one point per month max
                'yearly' => 'Y', // Reduce the data to one point per year max
            ];

            $series = array_filter(
                $series,
                function ($k) use ($series, $format, $formats) {
                    if (!isset($series[$k + 1])) {
                        return true;
                    }

                    $current_date = date($formats[$format], strtotime($series[$k]['date']));
                    $next_date = date($formats[$format], strtotime($series[$k + 1]['date']));
                    return $current_date !== $next_date;
                },
                ARRAY_FILTER_USE_KEY
            );
        }

        return $series;
    }

    /**
     * Display form for agent
     *
     * @param Printer $printer Printer instance
     */
    public function showMetrics(Printer $printer)
    {
        $format = $_GET['date_format'] ?? 'dynamic';

        if (isset($_GET['date_interval'])) {
            $raw_metrics = self::getMetrics(
                $printer,
                interval: $_GET['date_interval'],
                format: $format,
            );
        } elseif (isset($_GET['date_start']) && isset($_GET['date_end'])) {
            $raw_metrics = self::getMetrics(
                $printer,
                start_date: new DateTime($_GET['date_start']),
                end_date: new DateTime($_GET['date_end']),
                format: $format,
            );
        } else {
            $raw_metrics = self::getMetrics(
                $printer,
                format: $format,
            );
        }

        // build graph data
        $params = [
            'label'         => $this->getTypeName(),
            'icon'          => Printer::getIcon(),
            'apply_filters' => [],
        ];

        $series = [];
        $labels = [];

        // Formatter to display the date (months names) in the correct language
        // Dates will be displayed as "d MMM YYYY":
        // d = short day number (1, 12, ...)
        // MMM = short month name (jan, feb, ...)
        // YYYY = full year (2021, 2022, ...)
        // Note that PHP use ISO 8601 Date Output here which is different from
        // the "Constants for PHP Date Output" used in others functions
        // See https://framework.zend.com/manual/1.12/en/zend.date.constants.html#zend.date.constants.selfdefinedformats
        $fmt = new IntlDateFormatter(
            $_SESSION['glpilanguage'] ?? 'en_GB',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'd MMM YYYY'
        );

        foreach ($raw_metrics as $metrics) {
            $date = new DateTime($metrics['date']);
            $labels[] = $fmt->format($date);
            unset($metrics['id'], $metrics['date'], $metrics['printers_id']);

            foreach ($metrics as $key => $value) {
                $label = $this->getLabelFor($key);
                if ($label && $value > 0) {
                    $series[$key]['name'] = $label;
                    $series[$key]['data'][] = $value;
                }
            }
        }

        $bar_conf = [
            'data'  => [
                'labels' => $labels,
                'series' => array_values($series),
            ],
            'label' => $params['label'],
            'icon'  => $params['icon'],
            'color' => '#ffffff',
            'distributed' => false,
            'show_points' => true,
            'line_width'  => 2,
        ];

        // display the printer graph buttons component
        TemplateRenderer::getInstance()->display('components/printer_graph_buttons.html.twig', [
            'start_date' => $_GET['date_start'] ?? '',
            'end_date'   => $_GET['date_end'] ?? '',
            'interval'   => $_GET['date_interval'] ?? 'P1Y',
            'format'     => $format,
            'export_url' => '/front/printerlogcsv.php?' . Toolbox::append_params([
                'id' => $printer->getID(),
                'start' => $_GET['date_start'] ?? '',
                'end'   => $_GET['date_end'] ?? '',
                'interval'   => $_GET['date_interval'] ?? 'P1Y',
                'format'     => $format,
            ]),
        ]);

        // display graph
        echo "<div class='dashboard printer_barchart pt-2'>";
        echo Widget::multipleAreas($bar_conf);
        echo "</div>";
    }

    /**
     * Get the label for a given column of glpi_printerlogs.
     * To be used when displaying the printed pages graph.
     *
     * @param string $key
     *
     * @return null|string null if the key didn't match any valid field
     */
    private function getLabelFor($key): ?string
    {
        switch ($key) {
            case 'total_pages':
                return __('Total pages');
            case 'bw_pages':
                return __('Black & White pages');
            case 'color_pages':
                return __('Color pages');
            case 'scanned':
                return __('Scans');
            case 'rv_pages':
                return __('Recto/Verso pages');
            case 'prints':
                return __('Prints');
            case 'bw_prints':
                return __('Black & White prints');
            case 'color_prints':
                return __('Color prints');
            case 'copies':
                return __('Copies');
            case 'bw_copies':
                return __('Black & White copies');
            case 'color_copies':
                return __('Color copies');
            case 'faxed':
                return __('Fax');
        }

        return null;
    }
}
