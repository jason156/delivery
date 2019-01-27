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

/**
 * Specs:
 * SSL verify false
 * Token auth
 * POST request
 * hours are whole numbers
 * minprice is 100
 */
class Delivery_Peshkariki_Action extends Vtiger_BasicAjax_Action {

    //Peshkariki
    private $url   = 'https://api.peshkariki.ru/commonApi/';
    private $uid   = 'info@cvetovik.com';
    private $pass  = 'wdtnjdbr';
    private $key   = '70666705jQc9xrCccM';
    private $acc   = '5484550010521555';
    private $phone = '79219387585';
    private $svcs  = [23]; // array of services - 23 без договора
    const SVC      = 'Peshkariki';
    const DBG      = false;
    public $status = [
        'Поиск курьера',
        'Взят в работу',
        'Получен у отправителя',
        'Доставлен получателю',
        'Завершен',
        'Отменен',
        'Возврат'
    ];
    public $shopid;

    function __construct()
    {
        $this->exposeMethod('placeOrder');
        $this->exposeMethod('cancelOrder');
        $this->exposeMethod('calcOrder');
        $this->exposeMethod('detailOrder');
        $this->exposeMethod('getOrders');
        $this->exposeMethod('getServices');
    }

    public function process(Vtiger_Request $request)
    {
        $mode = $request->getMode();
        if(!empty($mode) && $this->isMethodExposed($mode)) {
            $this->invokeExposedMethod($mode, $request);
            return;
        }
    }

    public function getAuth()
    {
        $op = 'login';
        $creds = [
            'login'     => $this->uid,
            'password'  => $this->pass
        ];
        $req = [ 'request' => json_encode($creds) ];
        $httpClient = new Vtiger_Net_Client($this->url . $op);
        try {
            $answer = $httpClient->doPost($req);
            if ($httpClient->wasError()) return false;

            $auth = json_decode($answer, 1);
            if (json_last_error()) return false;
            //if ($auth['success'] != 'true' ) return false;
            $this->key = $auth['response']['token'];
            return true;
        } catch (Exception $e) {
            //Network unreachable
            return false;
        }

        return false;
    }

    public function cGetAuth()
    {
        $op = 'login';
        $creds = [
            'login'     => $this->uid,
            'password'  => $this->pass
        ];
        $args = [ 'request' => json_encode($creds) ];
        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $this->url . $op);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $answer = curl_exec($ch);

            curl_close ($ch);

            $auth = json_decode($answer, 1);
            if (json_last_error()) return false;
            //if ($auth['success'] != 'true' ) return false;
            $this->key = $auth['response']['token'];
            return true;
        } catch (Exception $e) {
            //Network unreachable
            return false;
        }

        return false;
    }

    /**
     * Updated request to handle ssl connections
     */
    public function requestP($data, $op = 'order', $method = 'post')
    {
        $newToken = $this->cGetAuth();
        $data['token'] = $this->key;

        $ch = curl_init();

        $args = ['request' => json_encode($data)];
        //curl_setopt($ch, CURLOPT_VERBOSE, true);
        //curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $this->url . $op);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($method == 'post'){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec($ch);

        curl_close ($ch);
        if ($this::DBG){
            w('Req: ' . var_export($data,1));
            w('Res: ' . var_export($output,1));
        }
        $pkData = json_decode($output,1);
        if (json_last_error()) return false;

        return ($pkData['success'])?$pkData['response']:$pkData;
    }

    /**
     * Deprecated. Vtiger net clients is outdated
     */
    public function request($data, $op = 'order', $method = 'post')
    {
        $methodName = ($method == 'post') ? 'doPost' : 'doGet';
        $newToken = $this->getAuth();
        $data['token'] = $this->key;

        $params = [ 'request' => json_encode($data) ];
        $httpClient = new Vtiger_Net_Client($this->url . $op);
        try {
            $answer = $httpClient->{$methodName}($params);
            if ($httpClient->wasError()) return false;

            $result = json_decode($answer, 1);
            if (json_last_error()) return false;

            if ($result['success']) return $result['response'];
            /*
            if (
                is_array($result)
                && array_key_exists('success', $result)
            ) {
                //w('response decoded '.var_export($result,1));
                //$result = Zend_Json::decode(decode_html($answer));
            } else {

            }
            */

            return $result;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Peshkariki specific: list of services
     */
    public function getServices(Vtiger_Request $request)
    {
        $op = 'getServicesList';

        $answer = $this->requestP([], $op);
        $html = '<table class="table"><tr>';
        $html .= join("</tr><tr>",array_map(function($x){
                return '<td>'
                    . join('</td><td>', [$x['id'], $x['price'] ,$x['name']])
                    . '</td>';
            },
            $answer
        ));
        $html .= '</tr></table>';
        echo $html;
        return;
    }

    /**
    * Retrives single delivery record related to Order and emits its status
    * @param Vtiger_Request
    */
    public function getOrders(Vtiger_Request $request)
    {
        $db = PearDatabase::getInstance();
        $record = $request->get('record');

        $sql = "SELECT deliveryid, trackid FROM vtiger_delivery
                WHERE salesorderid = ?
                    AND status NOT REGEXP '(Отменен|Завершен)'
                ORDER BY trackid DESC LIMIT 1";
        $result = $db->pquery($sql, [$record]);

        if ($db->num_rows($result) == 0) {
            $this->emit('{"result": 0, "error_message":["Нет доставок для этого заказа"]}');
            return;
        }
        $crmDeliveryData = $db->fetch_array($result);
        $answer = $this->requestP(
            ['order_id' => $crmDeliveryData['trackid']],
            'orderDetails'
            //'checkStatus'
        );

        if ($answer === false) return false;

        $trackData = reset($answer);
        if (!$trackData['success']) {
            $this->emit(false);
            return;
        }

        $trackData['status'] = $this->status[$trackData['status']];
        $this->emit($trackData);

        $thisModule = Vtiger_Module_Model::getInstance('Delivery');
        $thisModule->updateDelivery($crmDeliveryData['deliveryid'], $trackData);
    }

    public function placeOrder(Vtiger_Request $request)
    {
        $op = 'addOrder';
        $record = $request->get('record');
        $data = $this->prepare($request);
        //w(var_export($data),1);
        $answer = $this->requestP([ 'orders' => [ $data ] ], $op);

        if (empty($answer)) {
            $this->emit(false);
            return;
        }

        $newDelivery = reset($answer);

        if (!array_key_exists('id', $newDelivery)) {
            $this->emit($answer);
            return;
        }
        /*
        //24 Запросы слишком частые
        $details = $this->requestP(
            ['order_id' => $newOrder['id']],
            'orderDetails'
        );

        //w(var_export($details,1));
        if ($details === false) return false;

        $pkDelivery = reset($details);
        if (!$pkDelivery['success']) {
            $this->emit($pkDelivery);
            return;
        }
        */

        //save
        $thisModule = Vtiger_Module_Model::getInstance('Delivery');
        $saveData = [
            'trackid' => $newDelivery['id'],
            'service' => $this::SVC,
            'status'  => '',//$this->status[0],
            'matter'  => 'Цветы',
            'dst'     => '',
            //'dst'     => $pkDelivery['routes'][1]['address'],
            'orderid' => $record,
            'shopid'  => $this->shopid,
            'cost'    => $newDelivery['delivery_price']
        ];
        $thisModule->saveDeliveryInfo($saveData);

        $this->emit($newDelivery);
    }

    /**
     * Cancel an order
     *@param Vtiger_Request request data
     */
    public function cancelOrder(Vtiger_Request $request)
    {
        $op = 'cancelOrder';
        $record = $request->get('record');
        $db = PearDatabase::getInstance();

        $sql = "SELECT trackid FROM vtiger_delivery
                WHERE salesorderid = ?
                    AND status NOT REGEXP '(Отменен|Завершен)'
                ORDER BY trackid DESC LIMIT 1";
        $result = $db->pquery($sql, [$record]);

        if ($db->num_rows($result) == 0) {
            $this->emit(
                '{"result": 0, "error_message":["Нет доставок для этого заказа"]}'
            );
            return;
        }

        $track = $db->fetch_array($result)['trackid'];

        //optional key substatus_id
        $answer = $this->requestP(['order_id' => $track], $op);

        //TODO update db?
        $delModule = Vtiger_Module_Model::getInstance('Delivery');
        $delRecord = Vtiger_Record_Model::getInstanceById($record, $delModule);
        $delRecord->set('mode', 'edit');
        $delRecord->set('status', $this->status[5]);
        $delRecord->save();

        $this->emit($answer);
    }

    /**
     * Calculate order cost
     *@param Vtiger_Request request data
     */
    public function calcOrder(Vtiger_Request $request)
    {
        $op = 'addOrder';
        $data = $this->prepare($request);

        $data['calculate'] = 1;

        $newToken = $this->getAuth();

        $answer = $this->requestP([ 'orders' => [ $data ] ], $op);

        if (array_key_exists('code', $answer)) {
            $answer = $this->getErrors($answer['code']);
        }

        $this->emit($answer);
    }

    public function detailOrder(Vtiger_Request $request)
    {
        $op = 'orderDetails';
        $track = $request->get('track');
        $answer = $this->requestP([ 'order_id' => $track], $op);

        $this->emit($answer);
    }

    /**
     * Retrive data for Peshkariki
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
        $this->shopid = $shopid;
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
        $fromTimeStart  = date($ff, strtotime('-2 hours', strtotime($toTimeStart)));
        $fromTimeEnd    = $toTimeStart;

        $p0timestart    = $fromTimeStart;
        $p0timeend      = $fromTimeEnd;
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
            $p1taking = (int)$soRecord->get($toTaking);
        }
        $p0city = (!is_bool(strpos($shop['city'],'Москва')))?1:2; //1 MSK, 2 SPB
        //pack everything
        $data = [
            'inner_id'    => $recordId,
            'comment'     => $subject,
            'clearing'    => 0, //1 - from Peshkariki acc, 0 - req eWallet
            'ewalletType' => 0, //Sber, Ya, QIWI
            'ewallet'     => $this->acc . ' HOLDER NAME',
            'city_id'     => $p0city,
            'services'    => $this->svcs,
            'route'       => [
                [
                    //'subway_id'  => code,
                    'street'     => $p0addr,
                    'building'   => '',
                    'apartments' => '',
                    'time_from'  => $p0timestart,
                    'time_to'    => $p0timeend,
                    'name'       => 'Flowers',
                    'phone'      => $p0phone,
                    'return_dot' => 1
                ],
                [
                    //'subway_id'  => code,
                    'street'     => $p1addr,
                    'building'   => '',
                    'apartments' => '',
                    'time_from'  => $p1timestart,
                    'time_to'    => $p1timeend,
                    'name'       => $p1person,
                    'phone'      => $p1phone,
                    'items'      => [[
                        'name'   => $subject,
                        'price'  => 100,
                        'weight' => 300,
                        'quant'  => 1
                    ]]
                ]
            ]
        ];

        if (isset($p1taking)) {
            $data['cash'] = 1;
            //$data['ewalletType'] = 0; // Sber|Ya|Qiwi
            //$data['ewallet']     = $this->acc; // + Holder
            $data['route'][1]['delivery_price_to_return'] = $p1taking;
        }

        if (!empty($shop['note'])) {
            $data['route'][0]['target'] = $shop['note'];
        }

        if (!empty($p1note)) {
            $data['route'][1]['target'] = $p1note;
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
        $dsc = $recModel->get('description');
        $note .= (!empty($dsc)) ? $dsc : '';
        $shopData = [
            'name'      => $recModel->get('shopname'),
            'city'      => $recModel->get('city'),
            'addr'      => $recModel->get('address'),
            'bizhours'  => $recModel->get('bizhours'),
            'phone'     => $this->phone,
            'note'      => $note
        ];

        return $shopData;
    }

    public function emit($data)
    {
        $response = new Vtiger_Response();
        $response->setResult($data);
        $response->emit();
    }

    /**
     * Get Human readable Peshkariki error
     *@param int|string Peshkariki error code
     *@return string error text
     */
    public function getErrors($code)
    {
        $errList = [
            11  => 'В запросе нет обязательных параметров',
            12  => 'Истекло время жизни токена',
            13  => 'Пользователь не найден',
            14  => 'Пароль неверный',
            15  => 'Запрос(request) не передан',
            16  => 'Недостаточно прав (попытка работы с чужим заказом)',
            17  => 'Запрос (request) пуст',
            18  => 'Запрос (request) в неправильном формате',
            19  => 'В маршруте не хватает точек, либо информация неполная',
            20  => 'Отмена уже невозможна (курьер в пути к получателю)',
            21  => 'Заказ не существует',
            22  => 'Ошибка в товарах',
            23  => 'Ошибка в точке (ах) пути',
            24  => 'Запросы слишком частые',
            25  => 'Неверно указан способ оплаты товара',
            26  => 'Текущему пользователю недоступно создание заказов на выкуп',
            27  => 'Оплата наличными недоступна',
            28  => 'Превышено кол-во заказов для запрашивания информации',
            50  => 'Время начала забора слишком мало',
            51  => 'Время конца забора слишком большое',
            52  => 'Время начала доставки слишком мало',
            53  => 'Время начала доставки слишком большое',
            60  => 'Недостаточно денег на балансе',
            403 => 'API недоступно',
            404 => 'Метод не найден',
            500 => 'Неизвестная ошибка'
        ];

        return $errList[$code];
    }
}
