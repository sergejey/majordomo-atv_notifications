<?php
/**
 * ATV Notifications
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 21:12:43 [Dec 27, 2020])
 */
//
//
class atv_notifications extends module
{
    /**
     * atv_notifications
     *
     * Module class constructor
     *
     * @access private
     */
    function __construct()
    {
        $this->name = "atv_notifications";
        $this->title = "ATV Notifications";
        $this->module_category = "<#LANG_SECTION_DEVICES#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
    {
        $p = array();
        if (IsSet($this->id)) {
            $p["id"] = $this->id;
        }
        if (IsSet($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (IsSet($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['TAB'] = $this->tab;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'atv_devices' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_atv_devices') {
                $this->search_atv_devices($out);
            }
            if ($this->view_mode == 'edit_atv_devices') {
                $this->edit_atv_devices($out, $this->id);
            }
            if ($this->view_mode == 'delete_atv_devices') {
                $this->delete_atv_devices($this->id);
                $this->redirect("?");
            }
        }
    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        $this->admin($out);
    }

    /**
     * atv_devices search
     *
     * @access public
     */
    function search_atv_devices(&$out)
    {
        require(dirname(__FILE__) . '/atv_devices_search.inc.php');
    }

    /**
     * atv_devices edit/add
     *
     * @access public
     */
    function edit_atv_devices(&$out, $id)
    {
        require(dirname(__FILE__) . '/atv_devices_edit.inc.php');
    }

    function api_send_message($id, $message)
    {

        $rec=SQLSelectOne("SELECT * FROM atv_devices WHERE ID=".(int)$id);
        $ip = $rec['IP'];

        if (!$ip) return;

        $payload = array(
            "type" => "0",
            "title" => 'MajorDoMo',
            "msg" => $message,
            "duration" => "5",
            "fontsize" => "3",
            "position" => "0",
            "bkgcolor" => "#607d8b",
            "transparency" => "3",
            "offset" => "0",
            "app" => "MajorDoMo",
            "force" => "true",
            "interrupt" => "false"
        );

        if ($rec['MSG_DURATION']) $payload['duration']=(string)$rec['MSG_DURATION'];
        if ($rec['MSG_FONTSIZE']) $payload['fontsize']=(string)$rec['MSG_FONTSIZE'];
        if ($rec['MSG_BKGCOLOR']) $payload['bkgcolor']=(string)$rec['MSG_BKGCOLOR'];
        if ($rec['MSG_TRANSPARENCY']) $payload['transparency']=(string)$rec['MSG_TRANSPARENCY'];
        if ($rec['MSG_POSITION']) $payload['position']=(string)$rec['MSG_POSITION'];

        $url = 'http://' . $ip . ':7676/?';

        foreach($payload as $k=>$v) {
            $url.='&'.$k.'='.urlencode($v);
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
    }

    /**
     * atv_devices delete record
     *
     * @access public
     */
    function delete_atv_devices($id)
    {
        $rec = SQLSelectOne("SELECT * FROM atv_devices WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM atv_devices WHERE ID='" . $rec['ID'] . "'");
    }

    function processSubscription($event, $details = '')
    {
        if ($event == 'SAY') {
            $level = $details['level'];
            $message = $details['message'];

            $devices = SQLSelect("SELECT * FROM atv_devices");
            $total = count($devices);
            for($i=0;$i<$total;$i++) {
                $min_level = (int)processTitle($devices[$i]['MIN_MSG_LEVEL']);
                if ($level>=$min_level) {
                    $this->api_send_message($devices[$i]['ID'],$message);
                }
            }
        }
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        subscribeToEvent($this->name, 'SAY');
        parent::install();
    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS atv_devices');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
        /*
        atv_devices -
        */
        $data = <<<EOD
 atv_devices: ID int(10) unsigned NOT NULL auto_increment
 atv_devices: TITLE varchar(100) NOT NULL DEFAULT ''
 atv_devices: IP varchar(255) NOT NULL DEFAULT ''
 atv_devices: MIN_MSG_LEVEL varchar(255) NOT NULL DEFAULT ''
 atv_devices: MSG_DURATION int(3) NOT NULL DEFAULT '5'
 atv_devices: MSG_FONTSIZE int(3) NOT NULL DEFAULT '3'
 atv_devices: MSG_BKGCOLOR varchar(20) NOT NULL DEFAULT '#607d8b'
 atv_devices: MSG_TRANSPARENCY int(3) NOT NULL DEFAULT '3'
 atv_devices: MSG_POSITION int(3) NOT NULL DEFAULT '0'
 
EOD;
        parent::dbInstall($data);
    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgRGVjIDI3LCAyMDIwIHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
