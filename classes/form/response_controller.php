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
 * file
 *
 * @package   local_helpdesk
 * @copyright 2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\form;

use html_writer;
use local_helpdesk\mail\ticket_mail;
use local_helpdesk\model\response;
use local_helpdesk\model\ticket;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/formslib.php");

/**
 * Class response_controller
 *
 * @package local_helpdesk\form
 */
class response_controller {
    /**
     * Function insert_response
     *
     * @param ticket $ticket
     * @param bool $hasticketmanage
     *
     * @return string
     * @throws \Exception
     */
    public function insert_response($ticket, $hasticketmanage) {
        global $PAGE;

        if (optional_param("confirmresponse", false, PARAM_BOOL) && confirm_sesskey()) {
            $data = (object)[
                "message" => [
                    "text" => optional_param("message_text", "", PARAM_RAW),
                    "format" => optional_param("message_format", FORMAT_HTML, PARAM_INT),
                ],
                "attachment" => optional_param("attachment", 0, PARAM_INT),
            ];
            $this->save_response($ticket, $data, optional_param("confirmaction", "reply", PARAM_ALPHA));
            redirect(new moodle_url("/local/helpdesk/ticket.php?id={$ticket->get_idkey()}"));
        }

        $form = new response_form(null, ["ticket" => $ticket, "hasticketmanage" => $hasticketmanage]);

        if ($form->is_cancelled()) {
            redirect(new moodle_url("/local/helpdesk/ticket.php?id={$ticket->get_idkey()}"));
        } else if ($data = $form->get_data()) {
            $action = $this->get_response_action($data);

            if (empty($data->confirmresponse) && $this->has_same_role_conflict($ticket, $data)) {
                $this->display_conflict_confirmation($ticket, $data, $action);
                return;
            }

            $this->save_response($ticket, $data, $action);
            redirect(new moodle_url("/local/helpdesk/ticket.php?id={$ticket->get_idkey()}"));
        } else {
            $latestresponse = response::get_latest_message($ticket->get_id());
            $latestresponseid = $latestresponse ? $latestresponse->get_id() : 0;
            $latestresponserole = "";
            if ($latestresponse) {
                $latestresponserole = response::get_sender_role($latestresponse->get_userid(), $PAGE->context, $ticket);
            }

            $form->set_data([
                "id" => $ticket->get_id(),
                "idkey" => $ticket->get_idkey(),
                "latestresponseid" => $latestresponseid,
                "latestresponserole" => $latestresponserole,
                "confirmresponse" => 0,
                "confirmaction" => "",
            ]);
        }
        $form->display();
    }

    /**
     * Saves a response and applies the selected follow-up action.
     *
     * @param ticket $ticket
     * @param \stdClass $data
     * @param string $action
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function save_response(ticket $ticket, $data, $action) {
        global $USER;

        if ($ticket->get_status() == ticket::STATUS_OPEN && $ticket->get_userid() != $USER->id) {
            $ticket->change_status(ticket::STATUS_PROGRESS);
        }

        $response = new response([
            "ticketid" => $ticket->get_id(),
            "message" => $data->message["text"],
            "type" => response::TYPE_MESSAGE,
            "userid" => $USER->id,
            "createdat" => time(),
        ]);
        $response->save($ticket);

        $context = \context_system::instance();
        if (!empty($data->attachment)) {
            $options = [
                "subdirs" => true,
                "embed" => true,
            ];
            file_save_draft_area_files($data->attachment, $context->id,
                "local_helpdesk", "response", $response->get_id(), $options);
        }

        $mail = new ticket_mail();
        $mail->send_response($ticket, $response);

        if ($action == "resolved") {
            $ticket->change_status(ticket::STATUS_RESOLVED);
        }
        if ($action == "close") {
            $ticket->change_status(ticket::STATUS_CLOSED);
        }
    }

    /**
     * Returns the action selected by the submit button.
     *
     * @param \stdClass $data
     * @return string
     */
    private function get_response_action($data) {
        if (!empty($data->confirmaction)) {
            return $data->confirmaction;
        }

        if (isset($data->buttonar["resolvedbutton"])) {
            return "resolved";
        }

        if (isset($data->buttonar["closebutton"])) {
            return "close";
        }

        return "reply";
    }

    /**
     * Checks whether another response from the same sender role was added after the form was opened.
     *
     * @param ticket $ticket
     * @param \stdClass $data
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function has_same_role_conflict(ticket $ticket, $data) {
        global $PAGE, $USER;

        $latestresponse = response::get_latest_message($ticket->get_id());
        if (!$latestresponse) {
            return false;
        }

        if ($latestresponse->get_id() <= (int)$data->latestresponseid) {
            return false;
        }

        $currentrole = response::get_sender_role($USER->id, $PAGE->context, $ticket);
        $latestrole = response::get_sender_role($latestresponse->get_userid(), $PAGE->context, $ticket);

        return $currentrole == $latestrole;
    }

    /**
     * Displays a confirmation form before saving a potentially duplicate same-role response.
     *
     * @param ticket $ticket
     * @param \stdClass $data
     * @param string $action
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function display_conflict_confirmation(ticket $ticket, $data, $action) {
        global $OUTPUT;

        $url = new moodle_url("/local/helpdesk/ticket.php", ["id" => $ticket->get_idkey()]);
        $message = get_string("replyconflictmessage", "local_helpdesk");
        $draftmessage = format_text($data->message["text"], $data->message["format"] ?? FORMAT_HTML);

        echo html_writer::tag("style", ".path-local-helpdesk .response-message{display:block;}");
        echo $OUTPUT->notification($message, "warning");
        echo html_writer::start_div("card mb-3");
        echo html_writer::tag("h5", get_string("replyconflictdraftmessage", "local_helpdesk"), [
            "class" => "card-header",
        ]);
        echo html_writer::div($draftmessage, "card-body");
        echo html_writer::end_div();
        echo html_writer::start_tag("form", [
            "method" => "post",
            "action" => $url,
            "enctype" => "multipart/form-data",
            "class" => "mb-3",
        ]);
        echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "sesskey", "value" => sesskey()]);
        echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "id", "value" => $ticket->get_id()]);
        echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "idkey", "value" => $ticket->get_idkey()]);
        echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "confirmresponse", "value" => 1]);
        echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "confirmaction", "value" => $action]);
        echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "latestresponseid", "value" => $data->latestresponseid]);
        echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "latestresponserole", "value" => $data->latestresponserole]);
        echo html_writer::empty_tag("input", [
            "type" => "hidden",
            "name" => "message_text",
            "value" => $data->message["text"],
        ]);
        echo html_writer::empty_tag("input", [
            "type" => "hidden",
            "name" => "message_format",
            "value" => $data->message["format"] ?? FORMAT_HTML,
        ]);
        echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "attachment", "value" => $data->attachment ?? 0]);
        echo html_writer::tag("button", get_string("replyconfirmsend", "local_helpdesk"), [
            "type" => "submit",
            "class" => "btn btn-warning mr-2",
        ]);
        echo html_writer::link($url, get_string("replyconfirmreview", "local_helpdesk"), [
            "class" => "btn btn-secondary",
        ]);
        echo html_writer::end_tag("form");
    }
}
