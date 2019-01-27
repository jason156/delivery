<?php

require_once 'Delivery.php';

class Delivery_Wind_Action extends Delivery_Action
{
    function process(Vtiger_Request $req)
    {
        $id = $req->get('id');
        $this->_emit($this->full($id));
        /*
        TODO submit to service
        parse reply
        warnings
        create delivery
         */
    }

    function full($id)
    {
        if (empty($id)) return false;
        $order    = $this->getOrderData($id);
        $delivery = $this->crm2svc($order);
        $service  = $this->request(
            'orders_add',
            [$id => $delivery]
        );

        if (!array_key_exists('data', $service) || empty($service['data'])) {
            return ['error' => 'Request err: ' . $service['status']];
        }

        $result = json_decode($service['data'], 1);
        $invalid = !is_array($result) ||
            !array_key_exists('result', $result) ||
            array_key_exists('error', $result);
        if ($invalid) {
            return ['error' => 'Service error. ' . json_encode($result)];
        }

        $wind = array_pop($result['info']);
        if ($result['result'] != 1) {
            return $wind;
        }

        $crmDelivery = $this->createDelivery([
            'trackid' => $wind['itlogist_order_id'],
            'service' => 'Povetru',
            'status'  => 'Создано',
            'matter'  => 'Букет',
            'dst'     => $order['dst']['addr'],
            'cost'    => $order['cost'],
            'orderid' => $id,
            'shopid'  => $order['shopid'],
        ]);

        return $crmDelivery;
    }

    function getStatus($track)
    {
        return $this->_cRequest(
            'orders_status',
            ['orders' => $track],
            [],
            'get'
        );
    }

    function request($op, $data)
    {
        
        return $this->_cRequest(
            $op,
            ['orders' => json_encode($data)],
            ['Content-Type: application/x-www-form-urlencoded']
        );
    }

    /**
     * @see Delivery_Action->getSettings
     */
    function getSettings()
    {
        $token = 'de67f2b0b3728d564cacd97ffdff8a61'; //test
        $token = '27f9f53cb38f8adc986b26d887d8cdc3';
        return [
            'url'   => "http://povetru.itlogist.ru/api/v1/{$token}/",
            'uid'   => 'fantasy',
            'pass'  => 'wdtnjdbr',
            'key'   => $token,
            'useMultipart' => true,
        ];
    }

    /**
     * Implementation of abstract method
     */
    function crm2svc($data)
    {
        $map = [
            'ordertype'         =>  2,
            'ordernumber'       =>  $data['order'],
            'date_from'         =>  $data['tgtDate'],
            'time1_from'        =>  $data['from']['t1'],
            'time2_from'        =>  $data['from']['t2'],
            'date_to'           =>  $data['tgtDate'],
            'time1_to'          =>  $data['dst']['t1'],
            'time2_to'          =>  $data['dst']['t2'],
            'weight'            =>  1,
            'value'             =>  1,
            'pieces'            =>  1,
            'comment'           =>  $data['subject'],
            'appraised_value'   =>  $data['cost'],

            'cityfrom'          =>  $this->cityCode($data['city']),
            'streetfrom'        =>  $data['from']['addr'],
            'clientnamefrom'    =>  $data['name'],
            'clientphonefrom'   =>  $data['from']['phone'],
            'buildingfrom'      =>  '-',

            'cityto'            =>  $this->cityCode($data['city']),
            'streetto'          =>  $data['dst']['addr'],
            'clientnameto'      =>  $data['dst']['person'],
            'clientphoneto'     =>  $data['dst']['phone'],
            'buildingto'        =>  '-',
            /*
            'roomfrom'          =>  $data[''],
            'clientcontactfrom' =>  $data[''],
            'roomto'            =>  $data[''],
            'clientcontactto'   =>  $data['dst'][''],
            */
        ];
        if (array_key_exists('take', $data['dst'])) {
            $map['COD_amount'] = $data['dst']['take'];
        }

        return $map;
    }

    /**
     * Service specific method to get city code
     *
     * @param string $city cyrillic name
     *
     * @return int service code
     */
    function cityCode($city)
    {
        $codes = [
            'Москва' => 77,
            'Санкт-Петербург' => 78,
        ];

        return in_array($city, array_keys($codes))
            ? $codes[$city]
            : 78;
    }

    function xrequest($op, $data)
    {
        $timeout = 10;
        $token = 'de67f2b0b3728d564cacd97ffdff8a61';
        $url = "http://povetru.itlogist.ru/api/v1/{$token}/orders_add/";
        $args = http_build_query(['orders' => json_encode($data)]);
        $ch = curl_init();

        if (DBG) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: '. strlen($args)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec($ch);
        $error  = curl_error($ch);

        curl_close($ch);

        return [
            'status' => $error,
            'data'   => $output
        ];
    }

    public function createDelivery($data)
    {
        $mod = Vtiger_Module_Model::getInstance('Delivery');

        return $mod->saveDeliveryInfo($data);
    }
}
