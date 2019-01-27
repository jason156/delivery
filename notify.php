<?php

/*
 * Dostavista Notifications handler
 * Updates Delivery status/courier and SalesOrder
 */

if ($_SERVER['REQUEST_METHOD'] != 'POST') exit('No direct access');

$phpuser = get_current_user(); //root
$ref = $_SERVER['REMOTE_ADDR'];

require_once 'config.inc.php';
require_once 'data/CRMEntity.php';
require_once 'include/database/PearDatabase.php';
require_once 'include/Webservices/Revise.php';
require_once 'include/Webservices/Retrieve.php';
require_once 'modules/Users/Users.php';
//ModTracker reqs
require_once 'include/utils/utils.php';
require_once 'includes/Loader.php';
require_once 'includes/runtime/BaseModel.php';
require_once 'includes/runtime/Globals.php';
require_once 'includes/runtime/LanguageHandler.php';

require_once 'modules/Delivery/Delivery.php';
//require_once 'modules/SalesOrder/SalesOrder.php';

const UID     = 1;

$msg = date('Ymd H:i:s / ') . "$ref / ";

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {

    $adb  = PearDatabase::getInstance();
    $user = new Users();
    $current_user = $user->retrieveCurrentUserInfoFromFile(UID);

    $msg .= $data['data']['order_id'];

    file_put_contents('dv.log', "{$msg}\n", FILE_APPEND);
    processData($data['data']);

    //file_put_contents('dv.log', var_export($data['data'],1) . "\n", FILE_APPEND);
    if (strpos($data['data']['status_name'], 'Завер') > -1){
        wsUpdateOrder($data['data']['order_id']);
    }

}


exit();

/*
update vtiger_delivery set status = 'Завершен' where status = 3;
0 — создан
1 — доступен
2 — активен
3 — завершен
10 — отменен
16 — отложен
*/

/**
* Dostavista Request Data
* @param <Array> POST json parsed
*/
function processData($data)
{
    $trackid = $data['order_id'];
    $dvid = searchDelivery($trackid);

    if ($dvid === false) return;

    $moduleName = 'Delivery';

    $focus = CRMEntity::getInstance($moduleName);
    $focus->id = $dvid;
    $focus->mode = 'edit';
    $focus->retrieve_entity_info($dvid,$moduleName);

    $focus->column_fields['status'] = $data['status_name'];

    if (count($data['courier'])>0) {
        $focus->column_fields['couriername'] = $data['courier']['name'];
        $focus->column_fields['courierphone'] = $data['courier']['phone'];
    }

    // TODO: tracking
    // $focus->saveentity($moduleName);
    $focus->save($moduleName);
}

function searchDelivery($tid)
{
    $sql = 'SELECT deliveryid FROM vtiger_delivery WHERE trackid = ? LIMIT 1';
    $db = PearDatabase::getInstance();
    $res = $db->pquery($sql, [$tid]);

    //TODO refactoring - return order and delivery
    return ($db->num_rows($res)>0) ? $db->fetch_array($res)['deliveryid'] : false;
}


//Manual update
function updateDelivery($dvid, $dvData)
{
    $set = [];
    foreach ($dvData as $key => $value) {
        if (!empty($value)){
            $set[] = "{$key} = '{$value}'";
        }
    }

    $sql = 'UPDATE vtiger_delivery SET '. implode(',',$set) .' WHERE deliveryid = ?';
    print $sql;

    return;
    $db = PearDatabase::getInstance();
    $res = $db->pquery($sql, $dvid);
}

function getOrderByTrack($tid)
{
    $sql = 'SELECT salesorderid FROM vtiger_delivery
        LEFT JOIN vtiger_crmentity vcd ON deliveryid = vcd.crmid
        LEFT JOIN vtiger_crmentity vcs ON salesorderid = vcs.crmid
        WHERE trackid = ?
            AND vcd.deleted = 0
            AND vcs.deleted = 0
        LIMIT 1';
    $db = PearDatabase::getInstance();
    $res = $db->pquery($sql, [$tid]);

    if ($db->num_rows($res) == 0){
        return false;
    }

    return $db->fetch_array($res)['salesorderid'];
}

/*
 * @param delivery id
*/
function updateOrder($tid)
{
    $soId = getOrderByTrack($tid);
    if (is_bool($soId)) return;

    $sql = "UPDATE vtiger_salesorder SET sostatus = 'Delivered'
        WHERE salesorderid = ?";
    $db = PearDatabase::getInstance();
    $res = $db->pquery($sql, [$soId]);
    file_put_contents('dv.log', "Updated: $soId\n", FILE_APPEND);

    return $res;
}

function wsUpdateOrder($tid)
{
    $soId = getOrderByTrack($tid);
    if (is_bool($soId)) return;

    global $current_user;
    $moduleName = 'SalesOrder';
    $wsSoId = vtws_getWebserviceEntityId('SalesOrder', $soId);
    $order = vtws_retrieve($wsSoId, $current_user);

    $data = [
        'id'        => $wsSoId,
        'sostatus'  => 'Delivered',
        'LineItems' => $order['LineItems']
    ];

    $result = vtws_revise($data, $current_user);
    file_put_contents('dv.log', "Updated: $wsSoId\n", FILE_APPEND);

    return $wsSoId;
}

function crmUpdateOrder($soId)
{
    $moduleName = 'SalesOrder';

    $focus = CRMEntity::getInstance($moduleName);
    $focus->id = $soId;
    $focus->mode = 'edit';
    $focus->retrieve_entity_info($soId,$moduleName);

    $focus->column_fields['status'] = 'Delivered';

    $focus->save($moduleName);
}
/*
    */
function trackDown($changes)
{
    $this->id = $adb->getUniqueId('vtiger_modtracker_basic');

    $adb->pquery(
        'INSERT INTO vtiger_modtracker_basic
            (id, crmid, module, whodid, changedon, status)
            VALUES (?,?,?,?,?,?)',
        [
            $this->id,
            $salesorderid,
            'SalesOrder',
            1,
            date('Ymd H:i:s'),
            $status
        ]
    );

    //for each
    $adb->pquery(
        'INSERT INTO vtiger_modtracker_detail
            (id,fieldname,prevalue,postvalue)
            VALUES (?,?,?,?)',
        [
            $this->id,
            'sostatus',
            $oldValue,
            'Доставлено'
        ]
    );
}

/*
//
*/
