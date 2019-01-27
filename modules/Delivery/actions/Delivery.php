<?php

require_once 'modules/Delivery/models/Storage.php';

/**
 * Base class for Delivery actions
 */
abstract class Delivery_Action extends Vtiger_BasicAjax_Action
{
    const DBG = true;

    const SSLVERIFY = true;

    /**
     * Flag and container
     */
    public $cookie = false;

    /**
     * Container for service data
     */
    public $serviceCookie = false;

    /**
     * Tracing messages
     */
    public $msgs = [];

    protected $phone = '79876757777';
    /**
     * Method to return service configuration
     *
     * @return array
     */
    abstract public function getSettings();

    /**
     * Method to map crm data to service data
     *
     * @param array $data required order/shop values
     *
     * @return array
     */
    abstract public function crm2svc($data);

    /**
     * Specific request to a service
     *
     * @param string $operation type of action
     * @param array  $data      data to send
     *
     * @return array status and results
     */
    abstract public function request($operation, $data);

    /**
    * Retrives single delivery record related to Order and emits its status
    * 
    * @param int $orderId crm order id
    *
    * @return void
    */
    public function getOrders($orderId)
    {
        $crmDeliveryData = Delivery_Module_Model::byOrder($id);

        $answer = $this->_details($crmDeliveryData['trackid']);

        if ($answer === false) return false;

        $trackData = reset($answer);
        if (!$trackData['success']) {
            $this->emit(false);
            return;
        }

        $trackData['status'] = $this->status[$trackData['status']];
        $this->_emit($trackData);

        $thisModule = Vtiger_Module_Model::getInstance('Delivery');
        $thisModule->updateDelivery($crmDeliveryData['deliveryid'], $trackData);
    }

    /**
     * Retrive order data from crm
     *
     * @param int $id crm order id
     *
     * @return array associative
     */
    protected function getOrderData($id)
    {
        $soModule = Vtiger_Module_Model::getInstance('SalesOrder');
        $soRecord = Vtiger_Record_Model::getInstanceById($id, $soModule);
        $shopid   = $soRecord->get('shopid');
        $this->shopid = $shopid;
        $shop = $this->_getShopData($shopid);
        $goods = implode("\n", array_map(
            function ($x) {return "{$x['label']} - {$x['qty']}.";},
            $soRecord->getProductsList()
        ));
        //alias
        $brand          = 'cf_707';
        $dateField      = 'cf_650';
        $intervalField  = 'cf_652';
        // $fromAddr       = 'cf_659';
        $toCity         = 'ship_city';
        $toAddr         = 'ship_street';
        $toPhone        = 'cf_658';
        $toPerson       = 'cf_657';
        $payType        = 'cf_648';
        $toTaking       = 'cf_835'; //if (cf_648 == Курьеру)
        $addrNote       = 'cf_675';
        $personNote     = 'cf_674';

        //retrive
        preg_match(
            "/\W*([\d:]+)\W*([\d:]+)/",
            $soRecord->get($intervalField),
            $tgtTime
        );
        //TODO no matches
        $toTimeStart    = "{$tgtTime[1]}:00";
        $toTimeEnd      = "{$tgtTime[2]}:00";

        $ft = 'H:i:00';
        $ff = 'Y-m-d H:i:00';
        $fromTimeStart  = date($ft, strtotime('-1 hours', strtotime($toTimeStart)));
        // 1 minute margin
        $fromTimeEnd    = date($ft, strtotime('-1 min',   strtotime($toTimeStart)));

        $oCity = $soRecord->get($toCity);
        $oAddr = $soRecord->get($toAddr);
        $validAddr = $oAddr;

        $delivery = [
            'orderid'   => $id,
            'goods'     => $goods,
            'name'      => $soRecord->get($brand),
            'shopid'    => $shopid,
            'order'     => $soRecord->get('salesorder_no'),
            'cost'      => 0,
            'subject'   => 'Цветы',
            'city'      => $shop['city'],
            'tgtDate'   => $soRecord->get($dateField),
            'from' => [
                't1'    => $fromTimeStart,
                't2'    => $fromTimeEnd,
                'addr'  => $shop['addr'],
                'phone' => substr($shop['phone'], -10),
            ],
            'dst'  => [
                't1'     => $toTimeStart,
                't2'     => $toTimeEnd,
                'addr'   => $validAddr,
                'phone'  => substr($soRecord->get($toPhone), -10),
                'person' => $soRecord->get($toPerson),
                'note'   => $soRecord->get($addrNote),
            ],
        ];

        $gottaTake = in_array(
            $soRecord->get($payType),
            ['Курьеру', 'Наёмному курьеру']
        );
        if ($gottaTake) {
            $delivery['dst']['take'] = (int)$soRecord->get($toTaking);
        }


        return $delivery;
    }

    /**
     * Get Shop Info by ID
     *
     * @param int $shopid crm shop id
     *
     * @return array 
     */
    private function _getShopData($shopid)
    {
        $shopsModule = Vtiger_Module_Model::getInstance('Shops');
        try {
            $recModel = Vtiger_Record_Model::getInstanceById($shopid, $shopsModule);
        } catch (Exception $e) {
            return [];
        }
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

    /*
    // shortcuts
    function getRequest
    function postRequest
    */

    /**
     * Inner method, common curl request
     *
     * @param string $op      operation
     * @param array  $data    to send
     * @param array  $headers any specific
     * @param string $method  request type
     * @param int    $timeout connection timeout
     *
     * @return array status and response data
     */
    public function _cRequest(
        $op,
        $data,
        $headers = [],
        $method = 'post',
        $timeout = 15
    ) {
        $isLogin = ($op == 'login');
        $hasData = !empty($data);
        $cfg = $this->getSettings();
        // TODO replace with authtype token | cookie
        $needCookies = array_key_exists('useCookies', $cfg);
        $isMultipart = array_key_exists('useMultipart', $cfg);
        $head = [];

        $args = '';

        $ch = curl_init();
        if (self::DBG) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }

        $appendCookie = !$isLogin && $needCookies && $this->cookie;

        if ($appendCookie) {
            $head[] = 'Cookie: ' . $this->cookie;
        }

        if ($hasData) {
            // Encode Data
            if ($isMultipart) {
                $args = http_build_query($data);
            } else {
                $args = 1//$isLogin
                    ? json_encode($data)
                    : http_build_query($data);
            }

            // Define data format
            if ($method == 'post') {
                $head[] = 'Content-Length: '. strlen($args);
                // POST request. data goes as option
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
            } else {
                // GET request. data goes as URI
                $op .= '?' . $args;
            }
        }

        if (!empty($headers)) {
            $head = array_merge($head, $headers);
        }

        if (!empty($head)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $head);
        }

        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $cfg['url'] . $op);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, self::SSLVERIFY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, self::SSLVERIFY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($isLogin && $needCookies) {
            // Get response cookie
            $parser = function ($ch, $headerLine) {
                $clean = trim($headerLine);
                $lookup = preg_match('/^Set-Cookie:\s*([^;]*)/mi', $clean, $cookie);
                if ($lookup) {
                    $this->serviceCookie = array_pop($cookie);
                }
                return strlen($headerLine); // Needed by curl
            };

            //http_parse_headers
            //CURLOPT_COOKIELIST
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, $parser);
        }

        $output = curl_exec($ch);
        $error  = curl_error($ch);
        $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            'code'   => $http,
            'status' => $error,
            'data'   => $output,
            'head'   => $head,
        ];
    }

    /**
     * Process curl results
     *
     * @param arr $result curl response data
     *
     * @return bool | array json decoded response content
     */
    public function decode($result)
    {
        if ($result['code'] != 200) {
            $this->msgs[] = 'Invalid response: ' . $result['code'];
            return false;
        }

        $data = json_decode($result['data'], 1);
        if (empty($data)) {
            $this->msgs[] = 'Invalid data';
            return false;
        }

        if (array_key_exists('error', $data)) {
            $this->msgs[] = $data['error'];
            return false;
        }

        return $data;
    }
}
