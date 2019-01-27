<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
require_once 'vtlib/Vtiger/Net/Client.php';

class Delivery_Dostavista_Action extends Vtiger_BasicAjax_Action {

    //Dostavista
    /*
    private $url = 'https://robotapitest.dostavista.ru/bapi/order';
    private $uid = 101510;
    private $key = '741282504BC5430235C99F7F893B62E5';
    */
    private $url   = 'https://dostavista.ru/bapi/';
    private $uid   = '232980';
    private $key   = '6451296E68AE22A8F7436B38D53D7F6D';
    //private $acc   = '5484550010521555';
    private $acc   = '4274278091433543';
    private $phone = '79219387585';
    const SVC      = 'Dostavista';
    const DBGSVC   = false;

    function __construct()
    {
        $this->exposeMethod('placeOrder');
        $this->exposeMethod('cancelOrder');
        $this->exposeMethod('calcOrder');
        $this->exposeMethod('getOrders');
    }

    public function process(Vtiger_Request $request)
    {
        $mode = $request->getMode();
        if(!empty($mode) && $this->isMethodExposed($mode)) {
            $this->invokeExposedMethod($mode, $request);

            return;
        }
    }

    public function requestDV($data, $op = 'order', $method = 'get')
    {
        $methodName = ($method == 'get')?'doGet':'doPost';

        $creds = [
            'client_id' => $this->uid,
            'token'     => $this->key
        ];
        $params = array_merge($creds, $data);

        $httpClient = new Vtiger_Net_Client($this->url . $op);
        //try No network?
        if (self::DBGSVC) w(var_export($params,1));
        $answer = $httpClient->{$methodName}($params);
        if (self::DBGSVC) w(var_export($answer,1));
        //if ($answer['result'] != 1) { return false; }
        //Zend_Json::decode(decode_html(trim($answer)));
        return trim($answer);
    }

    /**
    * Retrives single delivery record related to Order and emits its status
    * @param Vtiger_Request
    */
    public function getOrders(Vtiger_Request $request)
    {
        $db = vglobal('adb');
        $record = $request->get('record');

        $sql = "SELECT trackid FROM vtiger_delivery
                WHERE salesorderid = ?
                    AND status NOT REGEXP '(Отменен|Завершен)'
                ORDER BY trackid DESC LIMIT 1";
        $result = $db->pquery($sql, [$record]);

        $response = new Vtiger_Response();

        if ($db->num_rows($result) == 0) {
            $response->setResult('{"result": 0, "error_message":["Нет доставок для этого заказа"]}');
            $response->emit();
            return;
        }
        /*
        $tracks = [];
        while ($row = $db->fetch_array($result)) {
            $tracks[] = $row['trackid'];
        }
        // $tracks = [75734,75713];
        $answer = $this->requestDV([ 'order_id' => $tracks ]);
        */
        $trackid = $db->fetch_array($result)['trackid'];
        $answer = $this->requestDV([], 'order/' . $trackid);

        $response->setResult($answer);
        $response->emit();
       // updateDelivery($answer);
    }

    public function placeOrder(Vtiger_Request $request)
    {
        $moduleName = $request->getModule();
        $recordId   = $request->get('record');
        $soModule = Vtiger_Module_Model::getInstance('SalesOrder');
        $soRecord = Vtiger_Record_Model::getInstanceById($recordId, $soModule);
        $shopid = $soRecord->get('shopid');

        $shop = $this->getShopData($shopid);

        $subject        = 'Цветы';
        //mappings
        $dateField      = 'cf_650';
        $intervalField  = 'cf_652';
        // $fromAddr       = 'cf_659';
        $fromAddr       = $shop['addr'];
        $fromPhone      = substr($shop['phone'], -10);
        $toCity         = 'ship_city';
        $toAddr         = 'ship_street';
        $toPhone        = 'cf_658';
        $toPerson       = 'cf_657';
        $toTaking       = 'cf_835'; //if (cf_648 == Курьеру)
        $addrNote       = 'cf_675';
        $personNote     = 'cf_674';

        //retrive
        $tgtDate = $soRecord->get($dateField);
        preg_match("/\W*([\d:]+)\W*([\d:]+)/", $soRecord->get($intervalField), $tgtTime);
        $toTimeStart    = "{$tgtDate} {$tgtTime[1]}:00";
        $toTimeEnd      = "{$tgtDate} {$tgtTime[2]}:00";

        $ft = 'H:i:00';
        $ff = 'Y-m-d H:i:00';
        $fromTimeStart  = date($ff, strtotime('-1 hours', strtotime($toTimeStart)));
        // 1 minute margin
        $fromTimeEnd    = date($ff, strtotime('-1 min',   strtotime($toTimeStart)));

        /*
        //too complex
        if (empty($shop['bizhours']) || $shop['bizhours'] == 'Круглосуточно') {
            //expecting 24 from target date.
            $fromTimeEnd      = '23:59:59';
        } else {
            preg_match("/\W*([\d:]+)\W*([\d:]+)/", $shop['bizhours'], $openTime);
            //TODO compare TimeStarts
            $fromTimeStart  = "{$openTime[1]}:00";
            $fromTimeEnd    = "{$openTime[2]}:00";
        }
        */
        $p0timestart    = $fromTimeStart;
        $p0timeend      = $fromTimeEnd;
        $p0sonumber     = $soRecord->get('salesorder_no');
        $p0addr         = $shop['addr']; //$soRecord->get($fromAddr) + fromCity;
        $p0phone        = $fromPhone;

        $oCity = $soRecord->get($toCity);
        $oAddr = $soRecord->get($toAddr);
        $validAddr = '';
        if ($oCity && (strpos($oAddr, $oCity) === 0)){
            $validAddr = $oAddr;
        } else {
            $validAddr = "{$oCity}, {$oAddr}";
        }
        $p1addr         = $validAddr;
        $p1timestart    = $toTimeStart;
        $p1timeend      = $toTimeEnd;
        $p1person       = $soRecord->get($toPerson);
        $p1phone        = substr($soRecord->get($toPhone),-10);
        $p1note         = $soRecord->get($addrNote);


        if (in_array($soRecord->get('cf_648'), ['Курьеру', 'Наёмному курьеру'])) {
            $p1taking = $soRecord->get($toTaking);
        }

        //collect
        /* //valid point
        'address'              => 'Москва, Проспект Мира, 10',
        'required_time_start'  => '2016-11-26 00:00:00', //must be in future
        'required_time'        => '2200-12-31 00:00:00',
        'phone'                => '0001112233'
        */
        $data = [
            'matter'    => $subject,
            'backpayment_method'  => 2,
            'backpayment_details' => $this->acc,
            'point'     => [
                [
                    'client_order_id'      => $p0sonumber,
                    'address'              => $p0addr,
                    'required_time_start'  => $p0timestart,
                    'required_time'        => $p0timeend,
                    'phone'                => $p0phone
                ],
                [
                    'address'              => $p1addr,
                    'required_time_start'  => $p1timestart,
                    'required_time'        => $p1timeend,
                    'contact_person'       => $p1person,
                    'phone'                => $p1phone
                ]
            ]
        ];

        if (isset($p1taking)) {
            $data['point'][1]['taking'] = $p1taking;
        }

        if (!empty($shop['note'])) {
            $data['point'][0]['note'] = $shop['note'];
        }
        //TODO: point 1 note?
        if (!empty($p1taking)) {
            $data['point'][1]['note'] = $p1note;
        }

        try {
            //submit
            $answer = $this->requestDV($data, 'order', 'post');

            //save
            $trackData = Zend_Json::decode(decode_html($answer));
            if ($trackData['result'] == 1) {

                $newData = Zend_Json::decode(decode_html($this->requestDV(
                    ['show-points' => 1],
                    'order/' . $trackData['order_id']
                )));
                $dst = $newData['order']['points'][1]['address'];

                if (empty($dst)){
                    $dst = $p1addr;
                }

                $thisModule = Vtiger_Module_Model::getInstance('Delivery');
                $saveData = [
                    'trackid' => $trackData['order_id'],
                    'service' => $this::SVC,
                    'status'  => 'Обработка', //0 - Обработка, 1 - Поиск курьера
                    'matter'  => $subject,
                    'dst'     => $dst,
                    'orderid' => $recordId,
                    'shopid'  => $shopid,
                    'cost'    => $trackData['payment']
                ];
                $thisModule->saveDeliveryInfo($saveData);
            }
        } catch (Exception $e) {
            //TODO something is wrong - network, weather, etc
            $answer = '{"result": 0, "error_message":[' . json_encode($e) . ']}';
        }

        $response = new Vtiger_Response();
        $response->setResult($answer);
        $response->emit();
    }

    /**
     * Cancel an order
     *@param Vtiger_Request request data
     */
    public function cancelOrder(Vtiger_Request $request)
    {
        $op = 'cancel-order';
        $record = $request->get('record');
        $db = PearDatabase::getInstance();

        $sql = "SELECT trackid FROM vtiger_delivery
                WHERE salesorderid = ?
                    AND status NOT REGEXP '(Отменен|Завершен)'
                ORDER BY trackid DESC LIMIT 1";
        $result = $db->pquery($sql, [$record]);

        if ($db->num_rows($result) == 0) {
            $response->setResult('{"result": 0, "error_message":["Нет доставок для этого заказа"]}');
            $response->emit();
            return;
        }

        $queryResult = $db->fetch_array($result);
        $track = $queryResult['trackid'];

        //optional key substatus_id
        $answer = $this->requestDV(['order_id' => $track], $op, 'post');

        $response = new Vtiger_Response();
        $response->setResult($answer);
        $response->emit();
    }

    /**
     * Calculate order cost
     *@param Vtiger_Request request data
     */
    public function calcOrder(Vtiger_Request $request)
    {
        $op = 'calculate';
        $data = $this->prepare($request);

        $answer = $this->requestDV($data, $op, 'post');

        $this->emit($answer);
    }

    /**
     * Retrive data for Dostavista
     *@param Vtiger_Request request data
     *@return array associative
     */
    public function prepare($request)
    {
        $moduleName = $request->getModule();
        $recordId   = $request->get('record');
        $soModule = Vtiger_Module_Model::getInstance('SalesOrder');
        $soRecord = Vtiger_Record_Model::getInstanceById($recordId, $soModule);
        $shopid = $soRecord->get('shopid');

        $shop = $this->getShopData($shopid);

        $subject        = 'Цветы';
        //mappings
        $dateField      = 'cf_650';
        $intervalField  = 'cf_652';
        // $fromAddr       = 'cf_659';
        $fromAddr       = $shop['addr'];
        $fromPhone      = substr($shop['phone'], -10);
        $toCity         = 'ship_city';
        $toAddr         = 'ship_street';
        $toPhone        = 'cf_658';
        $toPerson       = 'cf_657';
        $toTaking       = 'cf_835'; //if (cf_648 == Курьеру)
        $addrNote       = 'cf_675';
        $personNote     = 'cf_674';

        //retrive
        $tgtDate = $soRecord->get($dateField);
        preg_match("/\W*([\d:]+)\W*([\d:]+)/", $soRecord->get($intervalField), $tgtTime);
        $toTimeStart    = "{$tgtDate} {$tgtTime[1]}:00";
        $toTimeEnd      = "{$tgtDate} {$tgtTime[2]}:00";

        $ft = 'H:i:00';
        $ff = 'Y-m-d H:i:00';
        $fromTimeStart  = date($ff, strtotime('-1 hours', strtotime($toTimeStart)));
        // 1 minute margin
        $fromTimeEnd    = date($ff, strtotime('-1 min',   strtotime($toTimeStart)));

        $p0timestart    = $fromTimeStart;
        $p0timeend      = $fromTimeEnd;
        $p0addr         = $shop['addr']; //$soRecord->get($fromAddr) + fromCity;
        $p0phone        = $fromPhone;

        $p1addr         = $soRecord->get($toCity) . ', ' . $soRecord->get($toAddr);
        $p1timestart    = $toTimeStart;
        $p1timeend      = $toTimeEnd;
        $p1person       = $soRecord->get($toPerson);
        $p1phone        = substr($soRecord->get($toPhone),-10);
        $p1note         = $soRecord->get($addrNote);

        if (in_array($soRecord->get('cf_648'), ['Курьеру', 'Наёмному курьеру'])) {
            $p1taking = $soRecord->get($toTaking);
        }

        //pack everything
        $data = [
            'matter'    => $subject,
            'backpayment_method'  => 2,
            'backpayment_details' => $this->acc,
            'point'     => [
                [
                    'address'              => $p0addr,
                    'required_time_start'  => $p0timestart,
                    'required_time'        => $p0timeend,
                    'phone'                => $p0phone
                ],
                [
                    'address'              => $p1addr,
                    'required_time_start'  => $p1timestart,
                    'required_time'        => $p1timeend,
                    'contact_person'       => $p1person,
                    'phone'                => $p1phone
                ]
            ]
        ];

        if (isset($p1taking)) {
            $data['point'][1]['taking'] = $p1taking;
        }

        if (!empty($shop['note'])) {
            $data['point'][0]['note'] = $shop['note'];
        }
        //TODO: point 1 note?
        if (!empty($p1taking)) {
            $data['point'][1]['note'] = $p1note;
        }

        return $data;
    }

    /**
     * Get Shop Info by ID
     *@param int shopid
     *@return array associative
     */
    private function getShopData($shopid)
    {
        $shopsModule = Vtiger_Module_Model::getInstance('Shops');
        $recModel    = Vtiger_Record_Model::getInstanceById($shopid, $shopsModule);
        //$ext = $recModel->get('phone');
        //$note = (!empty($ext)) ? 'Добавочный ' . $ext . ', ': '';
        $dsc = $recModel->get('description');
        $note .= (!empty($dsc)) ? $dsc : '';
        $shopData = [
            'name'      => $recModel->get('shopname'),
            'city'      => $recModel->get('city'),
            'addr'      => $recModel->get('city') . ', '. $recModel->get('address'),
            'bizhours'  => $recModel->get('bizhours'),
            'phone'     => $this->phone,
            //'phone'     => $recModel->get('phone')
            'note'      => $note
        ];

        return $shopData;
    }

    private function emit($data)
    {
        $response = new Vtiger_Response();
        $response->setResult($data);
        $response->emit();
    }

    /**
    *   Update delivery record with new status and courier info
    */
    public function updateDelivery($response='')
    {
        if (empty($response)) return false;

        $orderData = Zend_Json::decode(decode_html($response))['order'];

        $reqKeys = ['name', 'phone'];

        if (array_key_exists('status', $orderData)){
            $track = $orderData['order_id'];
        }

        // $fieldValues = []
        if (array_key_exists('courier', $orderData)){
            $name = $orderData['courier']['name'];
            $phone = $orderData['courier']['phone'];
        }

        return;

        $db = PearDatabase::getInstance();
        $sql = 'UPDATE vtiger_delivery set ' . implode(',', $setData) . ' WHERE ' . $where;
        $result = $db->pquery($sql, []);
    }

    public function getErrors($svcReply)
    {
       return 'Ашыпка';
    }
}
