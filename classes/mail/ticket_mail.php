<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Ticket mail helper.
 *
 * @package   local_helpdesk
 * @copyright 2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\mail;

use local_helpdesk\model\category_users;
use local_helpdesk\model\response;
use local_helpdesk\model\ticket;

defined('MOODLE_INTERNAL') || die();

/**
 * Class ticket_mail
 *
 * @package local_helpdesk\mail
 */
class ticket_mail {
    /**
     * Sends notifications for a new ticket.
     *
     * @param ticket $ticket
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function send_ticket(ticket $ticket) {
        global $CFG;

        $a = (object)[
            "ticketid" => $ticket->get_idkey(),
            "subject" => $ticket->get_subject(),
            "category" => $ticket->get_category()->get_name(),
            "url" => "{$CFG->wwwroot}/local/helpdesk/ticket.php?id={$ticket->get_idkey()}",
            "message" => $ticket->get_description(),
        ];

        $categoryusers = category_users::get_all(null, ["categoryid" => $ticket->get_categoryid()]);
        $sendevents = new send_message(
            get_string("mailticket_subject", "local_helpdesk", $a),
            get_string("mailticket_create_message", "local_helpdesk", $a),
            "ticket_created"
        );
        $sendevents->send_mail($ticket, $categoryusers);

        $creator = [new category_users(["userid" => $ticket->get_userid()])];
        $sendevents = new send_message(
            get_string("mailticket_subject", "local_helpdesk", $a),
            get_string("mailticket_user_message", "local_helpdesk", $a),
            "ticket_updated"
        );
        $sendevents->send_mail($ticket, $creator);
    }

    /**
     * Sends notifications for a ticket response.
     *
     * @param ticket $ticket
     * @param response $response
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function send_response(ticket $ticket, response $response) {
        global $CFG;

        $a = (object)[
            "ticketid" => $ticket->get_idkey(),
            "subject" => $ticket->get_subject(),
            "category" => $ticket->get_category()->get_name(),
            "url" => "{$CFG->wwwroot}/local/helpdesk/ticket.php?id={$ticket->get_idkey()}",
            "message" => $response->get_message(),
        ];

        $categoryusers = category_users::get_all(null, ["categoryid" => $ticket->get_categoryid()]);
        $sendevents = new send_message(
            "Re: " . get_string("mailticket_subject", "local_helpdesk", $a),
            get_string("mailticket_update_message", "local_helpdesk", $a),
            "ticket_updated"
        );
        $sendevents->send_mail($ticket, $categoryusers);

        $creator = [new category_users(["userid" => $ticket->get_userid()])];
        $sendevents = new send_message(
            "Re: " . get_string("mailticket_subject", "local_helpdesk", $a),
            get_string("mailticket_user_message", "local_helpdesk", $a),
            "ticket_updated"
        );
        $sendevents->send_mail($ticket, $creator);
    }
}
