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

namespace tests\units;

use DbTestCase;

class CommonITILValidationCron extends DbTestCase
{
    public function testRun()
    {
        global $DB, $CFG_GLPI;

        $this->login();

        $CFG_GLPI['use_notifications']  = true;

        // update entity
        $entity = new \Entity();
        $this->boolean(
            $entity->update([
                'id' => 0,
                'approval_reminder_repeat_interval' => 1,
            ])
        )->isTrue();

        // create ticket
        $ticket = new \Ticket();
        $ticket_id = $ticket->add([
            'name' => 'Ticket',
            'content' => 'Ticket',
        ]);
        $this->integer($ticket_id)->isGreaterThan(0);

        // create ticket validation
        $ticket_validation = new \TicketValidation();
        $ticket_validation_id = $ticket_validation->add([
            'tickets_id'      => $ticket_id,
            'itemtype_target' => 'User',
            'items_id_target' => getItemByTypeName('User', TU_USER, true),
        ]);
        $this->integer($ticket_validation_id)->isGreaterThan(0);

        // backdate ticket validation
        $this->boolean(
            $DB->update(
                \TicketValidation::getTable(),
                [
                    'submission_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
                ],
                [
                    'id' => $ticket_validation_id,
                ]
            )
        )->isTrue();

        // create crontask
        $crontask = new \CronTask();
        $crontask_id = $crontask->add([
            'name'        => 'approvalreminder',
            'itemtype'    => 'CommonITILValidationCron',
            'frequency'   => '60',
            'state'        => \CronTask::STATE_RUNNING,
        ]);
        $this->integer($crontask_id)->isGreaterThan(0);

        // run cron
        $this->integer(\CommonITILValidationCron::cronApprovalReminder($crontask))->isEqualTo(1);

        // verify last reminder date is set
        $this->boolean($ticket_validation->getFromDB($ticket_validation_id))->isTrue();
        $this->string($ticket_validation->fields['last_reminder_date'])->isNotEmpty();

        // reset last reminder date
        $this->boolean(
            $DB->update(
                \TicketValidation::getTable(),
                [
                    'last_reminder_date' => null,
                ],
                [
                    'id' => $ticket_validation_id,
                ]
            )
        )->isTrue();

        // verify last reminder date is empty
        $this->boolean($ticket_validation->getFromDB($ticket_validation_id))->isTrue();
        $this->string((string)$ticket_validation->fields['last_reminder_date'])->isEmpty();

        // Solve ticket
        $this->boolean(
            $ticket->update([
                'id' => $ticket_id,
                'status' => \Ticket::SOLVED,
            ])
        )->isTrue();

        // run cron
        $this->integer(\CommonITILValidationCron::cronApprovalReminder($crontask))->isEqualTo(1);

        // verify last reminder date is empty
        $this->boolean($ticket_validation->getFromDB($ticket_validation_id))->isTrue();
        $this->string((string)$ticket_validation->fields['last_reminder_date'])->isEmpty();

        // Close ticket
        $this->boolean(
            $ticket->update([
                'id' => $ticket_id,
                'status' => \Ticket::CLOSED,
            ])
        )->isTrue();

        // run cron
        $this->integer(\CommonITILValidationCron::cronApprovalReminder($crontask))->isEqualTo(1);

        // verify last reminder date is empty
        $this->boolean($ticket_validation->getFromDB($ticket_validation_id))->isTrue();
        $this->string((string)$ticket_validation->fields['last_reminder_date'])->isEmpty();
    }
}
