<?php

/**
 * Contact modules
 * @package modules
 * @subpackage contacts
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/contacts/hm-contacts.php';

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_autocomplete_contact extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('contact_value'));
        $results = array();
        if ($success) {
            $val = trim($form['contact_value']);
            $contacts = $this->get('contact_store');
            $contacts->sort('email_address');
            $results = array_slice($contacts->search(array(
                'display_name' => $val,
                'email_address' => $val
            )), 0, 10);
        }
        $this->out('contact_suggestions', $results);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_find_message_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = array();
        $existing = $this->get('contact_store');
        $addr_headers = array('to', 'cc', 'bcc', 'sender', 'reply-to', 'from');
        $headers = $this->get('msg_headers', array());
        $addresses = array();
        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $addr_headers, true)) {
                foreach (Hm_Address_Field::parse($value) as $vals) {
                    if (!$existing->search(array('email_address' => $vals['email']))) {
                        $addresses[] = $vals;
                    }
                }
            }
        }
        $this->out('contact_addresses', $addresses);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_send_to_contact extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('contact_id', $this->request->get)) {
            $contacts = $this->get('contact_store');
            $contact = $contacts->get($this->request->get['contact_id']);
            if ($contact) {
                $to = sprintf('%s <%s>', $contact->value('display_name'), $contact->value('email_address'));
                $this->out('compose_draft', array('draft_to' => $to, 'draft_subject' => '', 'draft_body' => ''));
            }
        }
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_load_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts = new Hm_Contact_Store();
        $page = 1;
        if (array_key_exists('contact_page', $this->request->get)) {
            $page = $this->request->get['contact_page'];
        }
        $this->out('contact_page', $page);
        $this->out('contact_store', $contacts, false);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_process_export_contacts extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('contact_source', $this->request->get)) {
            $source = $this->request->get['contact_source'];
            $contact_list = $this->user_config->get('contacts', array());
            if ($source != 'all') {
                $contact_list = array_filter($contact_list, function($v) { return $v['source'] == $this->request->get['contact_source']; });
            }

            Hm_Functions::header('Content-Type: text/csv');
            Hm_Functions::header('Content-Disposition: attachment; filename="'.$source.'_contacts.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, array('display_name', 'email_address', 'phone_number'));
            foreach ($contact_list as $contact) {
                fputcsv($output, array($contact['display_name'], $contact['email_address'], $contact['phone_number']));
            }
            fclose($output);
            exit;
        }
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_contacts"><a class="unread_link" href="?page=contacts">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-people-fill fs-5 me-2"></i>';
        }
        $res .= $this->trans('Contacts').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_content_start extends Hm_Output_Module {
    protected function output() {
        $contact_source_list = $this->get('contact_sources', array());
        $actions = '<div class="src_title">'.$this->trans('Export Contacts as CSV').'</div>';
        $actions .= '<div class="list_src"><a href="?page=export_contact&amp;contact_source=all">'.$this->trans('All Contacts').'</a></div>';
        foreach ($contact_source_list as $value) {
            $actions .= '<div class="list_src"><a href="?page=export_contact&amp;contact_source='.$this->html_safe($value).'">'.$this->html_safe($this->html_safe($value).' Contacts').'</a></div>';
        }

        return '<div class="contacts_content px-0"><div class="content_title px-3"">'.$this->trans('Contacts').'</div>'.
        '<div class="list_controls source_link"><a href="#" title="'.$this->trans('Export Contacts').'" class="refresh_list">'.
                '<img src="'.Hm_Image_Sources::$save.'" alt="" width="16" height="16" onclick="listControlsMenu()"/></a></div>
                <div class="list_actions">'.$actions.'</div>';
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_content_end extends Hm_Output_Module {
    protected function output() {
        return '</div>';
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_add_message_contacts extends Hm_Output_Module {
    protected function output() {
        $addresses = $this->get('contact_addresses');
        $headers = $this->get('msg_headers');
        $backends = $this->get('contact_edit', array());
        if (!empty($addresses) && count($backends) > 0) {
            $res = '<div class="add_contact_row position-absolute top-0 end-0 z-3 p-2 d-flex align-content-center gap-3"><a href="#" title="'.
            $this->html_safe('Add Contact').'" onclick="$(\'.add_contact_controls\').toggle(); return false;">'.
                '<i class="bi bi-person-fill-add fs-3" ></i></a><div class="add_contact_controls"><div class="row g-1 mt-1"><div class="col"><select id="add_contact" class="form-select form-select-sm">';
            foreach ($addresses as $vals) {
                $res .= '<option value="'.$this->html_safe($vals['name']).' '.$this->html_safe($vals['email']).
                    '">'.$this->html_safe($vals['name']).' &lt;'.$this->html_safe($vals['email']).'&gt;</option>';
            }
            $res .= '</select></div> <div class="col"><select id="contact_source" class="form-select form-select-sm">';
            foreach ($backends as $val) {
                $res .= '<option value="'.$this->html_safe($val).'">'.$this->html_safe($val).'</option>';
            }
            $res .= '</select></div> <div class="col"><input onclick="return add_contact_from_message_view()" class="add_contact_button w-100 btn btn-success btn-sm" '.
                'type="button" value="'.$this->trans('Add Contact').'"></div></div></div></div>';
            $headers = $headers.$res;
        }
        $this->out('msg_headers', $headers, false);
    }
}

/**
 * @subpackage contacts/handler
 */
class Hm_Handler_check_imported_contacts extends Hm_Handler_Module {
    public function process() {
        $contacts_imported = $this->session->get('contact_imported', array());
        $this->session->del('contact_imported');
        $this->out('contact_imported', $contacts_imported);
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_contacts_list extends Hm_Output_Module {
    protected function output() {
        // njo iyi monsieur
        $imported_contacts = $this->get('contact_imported', array());
        if (count($this->get('contact_sources', array())) == 0) {
            return '<div class="no_contact_sources">'.$this->trans('No contact backends are enabled!').
                '<br />'.$this->trans('At least one backend must be enabled in the config/app.php file to use contacts.').'</div>';
        }
        $per_page = 25;
        $current_page = $this->get('contact_page', 1);
        $res = '<div class="px-3 mt-3"><table class="contact_list table">';
        $res .=
        '<tr class="contact_import_detail"><td colspan="7"><a href="#" class="show_import_detail">'.$this->trans('Click here to see the detailed log of import operation').'</a></td></tr>';
        $res .= '<tr><td colspan="7" class="contact_list_title"><div class="server_title">'.$this->trans('Contacts').'</div></td></tr>';
        $contacts = $this->get('contact_store');
        $editable = $this->get('contact_edit', array());
        if ($contacts) {
            $total = count($contacts->dump());
            $contacts->sort('email_address');
            foreach ($contacts->page($current_page, $per_page) as $id => $contact) {
                $name = $contact->value('display_name');
                if (!trim($name)) {
                    $name = $contact->value('fn');
                }
                $res .= '<tr class="contact_row_'.$this->html_safe($id).'">';
                $res .= '<td><a data-id="contact_'.$this->html_safe($id).'_detail" '.
                    '" class="show_contact" title="'.$this->trans('Details').'">'.
                    '<i class="bi bi-person-fill"></i> '.
                    '</d><td>'.$this->html_safe($contact->value('type')).'<td><span class="contact_src">'.
                    ($contact->value('source') == 'local' ? '' : $this->html_safe($contact->value('source'))).'</span>'.
                    '</td><td>'.$this->html_safe($name).'</td>'.
                    '<td><div class="contact_fld">'.$this->html_safe($contact->value('email_address')).'</div></td>'.
                    '<td class="contact_fld"><a href="tel:'.$this->html_safe($contact->value('phone_number')).'">'.
                    $this->html_safe($contact->value('phone_number')).'</a></td>'.
                    '<td class="text-end" style="width : 100px">';
                if (in_array($contact->value('type').':'.$contact->value('source'), $editable, true)) {
                    $res .= '<a data-id="'.$this->html_safe($id).'" data-type="'.$this->html_safe($contact->value('type')).'" data-source="'.$this->html_safe($contact->value('source')).
                        '" class="delete_contact cursor-pointer" title="'.$this->trans('Delete').'"><i class="bi bi-trash3 text-danger ms-2"></i></a>'.
                        '<a href="?page=contacts&amp;contact_id='.$this->html_safe($id).'&amp;contact_source='.
                        $this->html_safe($contact->value('source')).'&amp;contact_type='.
                        $this->html_safe($contact->value('type')).'&amp;contact_page='.$current_page.
                        '" class="edit_contact cursor-pointer" title="'.$this->trans('Edit').'"><i class="bi bi-gear ms-2"></i></a>';
                }
                $res .= '<a href="?page=compose&amp;contact_id='.$this->html_safe($id).
                    '" class="send_to_contact cursor-pointer" title="'.$this->trans('Send To').'">'.
                    '<i class="bi bi-file-earmark-text ms-2"></i></a>';

                $res .= '</td></tr>';
                $res .= '<tr><td id="contact_'.$this->html_safe($id).'_detail" class="contact_detail_row" colspan="6">';
                $res .= build_contact_detail($this, $contact, $id).'</td>';
                $res .= '</td></tr>';
            }
            $res .= '<tr><td class="contact_pages" colspan="7">';
            if ($current_page > 1) {
                $res .= '<a href="?page=contacts&contact_page='.($current_page-1).'">Previous</a>';
            }
            if ($total > ($current_page * $per_page)) {
                $res .= ' <a href="?page=contacts&contact_page='.($current_page+1).'">Next</a>';
            }
            $res .= '</td></tr>';
        }
        $res .= '</table></div>';
        return $res;
    }
}

/**
 * @subpackage contacts/output
 */
class Hm_Output_filter_autocomplete_list extends Hm_Output_Module {
    protected function output() {
        $suggestions = array();
        foreach ($this->get('contact_suggestions', array()) as $item) {
            if(is_array($item)){
                $contact = $item[1];
                $contact_id = $item[0];

                if (trim($contact->value('display_name'))) {
                    $suggestions[] = $this->html_safe(sprintf(
                        '{"contact_id":%s, "contact": "%s %s", "type": "%s", "source": "%s"}', $contact_id, $contact->value('display_name'), $contact->value('email_address'), $contact->value('type'), $contact->value('source')
                    ));
                }
                else {
                    $suggestions[] = $this->html_safe(sprintf(
                        '%s', $contact->value('email_address')
                    ));
                }
            }
        }
        $this->out('contact_suggestions', $suggestions);
    }
}

/**
 * @subpackage contacts/functions
 */
if (!hm_exists('build_contact_detail')) {
function build_contact_detail($output_mod, $contact, $id) {
    $res = '<div class="contact_detail m-3" /><table class="w-auto"><thead></thead><tbody>';
    $all_fields = false;
    $contacts = $contact->export();
    ksort($contacts);
    foreach ($contacts as $name => $val) {
        if ($name == 'all_fields') {
            $all_fields = $val;
            continue;
        }
        if (substr($name, 0, 8) == 'carddav_') {
            continue;
        }
        if (!trim($val)) {
            continue;
        }
        $res .= '<tr><th>'.$output_mod->trans(name_map($name)).'</th>';
        $res .= '<td class="'.$output_mod->html_safe($name).'">'.$output_mod->html_safe($val).'</td></tr>';
    }
    if ($all_fields) {
        ksort($all_fields);
        foreach ($all_fields as $name => $val) {
            if (in_array($name, array(0, 'raw', 'objectclass', 'dn', 'ID', 'APP:EDITED', 'UPDATED'), true)) {
                continue;
            }
            $res .= '<tr><th>'.$output_mod->trans(name_map($name)).'</th>';
            $res .= '<td>'.$output_mod->html_safe($val).'</td></tr>';
        }
    }
    $res .= '</tbody></table></div>';
    return $res;
}}

/**
 * @subpackage contacts/functions
 */
if (!hm_exists('name_map')) {
function name_map($val) {
    $names = array(
        'display_name' => 'Display Name',
        'displayname' => 'Display Name',
        'givenname' => 'Given Name',
        'GD:GIVENNAME' => 'Given Name',
        'GD:FAMILYNAME' => 'Surname',
        'sn' => 'Surname',
        'mail' => 'E-mail Address',
        'source' => 'Source',
        'email_address' => 'E-mail Address',
        'l' => 'Locality',
        'st' => 'State',
        'street' => 'Street',
        'postalcode' => 'Postal Code',
        'title' => 'Title',
        'TITLE' => 'Title',
        'phone_number' => 'Telephone Number',
        'telephonenumber' => 'Telephone Number',
        'facsimiletelephonenumber' => 'Fax Number',
        'mobile' => 'Mobile Number',
        'roomnumber' => 'Room Number',
        'carlicense' => 'Vehicle License',
        'o' => 'Organization',
        'ou' => 'Organizational Unit',
        'departmentnumber' => 'Department Number',
        'employeenumber' => 'Employee Number',
        'employeetype' => 'Employee Type',
        'preferredlanguage' => 'Preferred Language',
        'labeleduri' => 'Homepage URL',
        'home_address' => 'Home Address',
        'work_address' => 'Work Address',
        'nickname' => 'Nickname',
        'pager' => 'Pager',
        'homephone' => 'Home Phone',
        'type' => 'Type',
        'url' => 'Website',
        'org' => 'Company',
        'fn' => 'Full Name',
        'uid' => 'Uid',
        'src_url' => 'URL',
        'adr' => 'Address'
    );
    if (array_key_exists($val, $names)) {
        return $names[$val];
    }
    return $val;
}}



class Hm_Output_import_modal extends Hm_Output_Module
{
    protected function output()
    {
        return get_import_detail_modal_content();
    }
}

if (!hm_exists('get_import_detail_modal_content')) {
    function get_import_detail_modal_content()
    {
        return '<div id="import_detail_modal" style="display: none;">
            <h1 class="import_detail_title"></h1>  
            <hr/>
            <div style="display: flex; height: 70px; margin-bottom: 10px;">
                <div style="width: 100%;">
                    <h3 style="margin-bottom: 2px;">General</h3>
                    <small>Input a name and order for your filter. In filters, the order of execution is important. You can define an order value (or priority value) for your filter. Filters will run from lowest to highest priority value.</small>
                </div>
            </div>
            <div style="margin-bottom: 10px; margin-top: 45px; display:flex; justify-content: end; align-items:stretch; flex-direction: column;">
                <b style="margin:5px 0px;">Filter Name:</b><input  style="margin:5px 0px; padding:5px;" class="modal_sieve_script_name" type="text" placeholder="Your filter name" /> 
                <b style="margin:5px 0px;">Priority:</b><input style="margin:5px 0px; padding:5px;" class="modal_sieve_script_priority" type="number" placeholder="0"  /> 
            </div>
            <div style="display: flex; height: 70px; margin-bottom: 10px;">
                <div style="width: 100%;">
                    <h3 style="margin-bottom: 2px;">Sieve Script</h3>
                    <small>Paste the Sieve script in the field below. Manually added scripts cannot be edited with the filters interface.</small>
                </div>
            </div>
            <div style="margin-bottom: 10px; margin-top:22px;">
                <textarea style="width: 100%;" rows="20" class="modal_sieve_script_textarea"></textarea>
            </div>
        </div>';
    }
}
