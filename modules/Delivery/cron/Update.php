<?php

chdir('../../../');
ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT); //

require_once 'config.inc.php';
require_once 'includes/main/WebUI.php';
require_once 'include/Webservices/Revise.php';
require_once 'include/Webservices/Retrieve.php';
require_once 'modules/Users/Users.php';
require_once 'modules/Delivery/actions/Delivery.php';

$current_user = Users::getActiveAdminUser();

global $log;

$module = Vtiger_Module_Model::getInstance('Delivery');
$bringo = new Delivery_Bringo_Action;

$pending = $module->getPending('Bringo', $bringo->getFinalStatus());

if (empty($pending)) exit('Nothing to update');

$auth = $bringo->getToken();
$bringo->cookie = $auth;

$processed = [];
foreach ($pending as $delivery) {
    $op = 'deliveries/info/' . $delivery['trackid'];
    $service = $bringo->_cRequest($op, '', [], 'get');
    $data = $bringo->decode($service);

    $info = array_shift($data['result']['deliveries']); 
    $orderno  = $info['externalId'];
    $status = $info['oldState'];
    $fullStatus = $bringo->getStatus($status) . ':'
        . $bringo->getReason($info['oldCloseCode']);
    if ($status == $delivery['status']) continue;
    $processed[] = updateDelivery(
        $delivery['crmid'],
        $fullStatus
    );
}


function updateDelivery($id, $status)
{
    $record = Vtiger_Record_Model::getInstanceById($id);
    $record->set('mode', 'edit');
    $record->set('status', $status);
    $record->save();

    return $record->getId();
}
