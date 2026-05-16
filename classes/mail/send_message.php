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
 * Ticket message sender.
 *
 * @package   local_helpdesk
 * @copyright 2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\mail;

use local_helpdesk\model\category_users;
use local_helpdesk\model\ticket;

/**
 * Class send_message
 *
 * @package local_helpdesk\mail
 */
class send_message {
    /** @var string */
    protected $subject;

    /** @var string */
    protected $htmlmessage;

    /** @var string */
    protected $providername;

    /**
     * Constructor.
     *
     * @param string $subject
     * @param string $htmlmessage
     * @param string $providername
     */
    public function __construct($subject, $htmlmessage, $providername) {
        $this->subject = $subject;
        $this->htmlmessage = $htmlmessage;
        $this->providername = $providername;
    }

    /**
     * Sends a Moodle message to each category user.
     *
     * @param ticket $ticket
     * @param category_users[] $categoryusers
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function send_mail(ticket $ticket, array $categoryusers) {
        global $CFG;

        $userfrom = \core_user::get_noreply_user();
        $sendedusers = [];

        foreach ($categoryusers as $categoryuser) {
            if (isset($sendedusers[$categoryuser->get_userid()])) {
                continue;
            }
            $sendedusers[$categoryuser->get_userid()] = true;

            $userto = $categoryuser->get_user();
            if (!$userto) {
                continue;
            }

            $eventdata = new \core\message\message();
            $eventdata->component = "local_helpdesk";
            $eventdata->name = $this->providername;
            $eventdata->userfrom = $userfrom;
            $eventdata->userto = $userto;
            $eventdata->subject = $this->subject;
            $eventdata->fullmessage = html_to_text($this->htmlmessage);
            $eventdata->fullmessageformat = FORMAT_HTML;
            $eventdata->fullmessagehtml = $this->htmlmessage;
            $eventdata->smallmessage = "";
            $eventdata->notification = 1;
            $eventdata->modulename = "moodle";
            $eventdata->courseid = $ticket->get_courseid() ?: SITEID;
            $eventdata->contexturl = "{$CFG->wwwroot}/local/helpdesk/ticket.php?id={$ticket->get_idkey()}";
            $eventdata->contexturlname = $ticket->get_subject();

            message_send($eventdata);
        }
    }
}
