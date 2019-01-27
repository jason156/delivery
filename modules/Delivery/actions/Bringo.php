<?php
/**
 * В качестве контейнера для авторизационного ключа используется cookie.
 * Для обмена данными используется json.
 * HTTP метод по умолчанию - POST.
 * Дата и время сериализуются/десериализуются в формате ISO 8601
 * Причины закрытия доставки могут меняться.
 * Нужно быть готовыми к тому, что в ответе на info || create в качестве причины закрытия придёт что-то, чего вы не ожидаете.
 *
 * @category Action
 * @package  Vtiger 
 * @author   Sergey Emelyanov <se@sergeyem.ru>
 */

require_once 'Delivery.php';

/**
 * Bringo delivery integration
 */
class Delivery_Bringo_Action extends Delivery_Action
{
    const SVC = 'Bringo';
    const URL = 'https://lugh-demo.bringo247.ru/api/';
    /*
    // production
    const LOGIN = '8F8944';
    const PASS = '4awb5j';
    */
    // test
    const LOGIN = 'apisamplecompany@bringo247.ru';
    const PASS = '123123';

    public $expired = false;

    function __construct()
    {
        $this->exposeMethod('calcOrder');
        $this->exposeMethod('placeOrder');
        $this->exposeMethod('cancelOrder');
        $this->exposeMethod('detailOrder');
        /*
        $this->exposeMethod('getOrders');
        $this->exposeMethod('getServices');
        */
    }

    /**
     * Main routine
     * must emit results
     *
     * @see Vtiger_BasicAjax_Action::process
     * @return json
     */
    public function process(Vtiger_Request $request)
    {
        $mode = $request->getMode();
        $decline = empty($mode) || !$this->isMethodExposed($mode);
        if ($decline) {
            $this->_emit('Set mode', 1);
            return;
        }

        $authData = $this->getToken();
        if (!$authData) {
            return $this->_emit([$authData, $this->msgs]);
        }

        $this->cookie = $authData;

        $this->_emit($this->invokeExposedMethod($mode, $request));
    }


    /**
     * Success data => '{result: 111}'
     *
     * @param Vtiger_Request
     *
     * @return mixed
     */
    public function calcOrder(Vtiger_Request $req)
    {
        $id = $req->get('record');
        $order    = $this->getOrderData($id);
        $delivery = $this->crm2svc($order);
        $service  = $this->request(
            'deliveries/price',
            $delivery
        );

        return $this->decode($service);
    }

    /**
     * deliveries/create
     *
     * @param Vtiger_Request
     *
     * @return mixed
     */
    public function placeOrder(Vtiger_Request $req)
    {
        $id = $req->get('record');
        $order    = $this->getOrderData($id);
        $delivery = $this->crm2svc($order);
        $service  = $this->request(
            'deliveries/create',
            $delivery
        );

        $valid = $service['code'] == 200 && array_key_exists('data', $service);
        if (!$valid) return [
            $service,
            $this->msgs,
        ];

        $newDelivery = json_decode($service['data'], 1)['result'];

        //save
        $thisModule = Vtiger_Module_Model::getInstance('Delivery');
        $saveData = [
            'trackid' => $newDelivery['id'],
            'service' => self::SVC,
            'status'  => $this->getStatus($newDelivery['oldState']),
            'matter'  => $newDelivery['name'],
            'dst'     => $newDelivery['deliverySegments'][0]['to']['address']['addressText'],
            //'dst'     => $pkDelivery['routes'][1]['address'],
            'orderid' => $id,
            'shopid'  => $order['shopid'],
            'cost'    => $newDelivery['price']
        ];
        $thisModule->saveDeliveryInfo($saveData);

        return $newDelivery;
    }

    /**
     * deliveries/info/{id}
     *
     * @param Vtiger_Request $req implementation
     *
     * @return mixed
     */
    public function detailOrder(Vtiger_Request $req)
    {
        $id = $req->get('record');
        $delivery = Delivery_Module_Model::byOrder($id);

        $op = 'deliveries/info/' . $delivery['trackid'];
        $service  = $this->_cRequest($op, '', [], 'get');

        return $this->decode($service);
    }

    /**
     * /api/deliveries/cancel/{id}
     */
    public function cancelOrder(Vtiger_Request $req)
    {
        $id = $req->get('record');
        $delivery = Delivery_Module_Model::byOrder($id);

        $op = 'deliveries/cancel/' . $delivery['trackid'];
        $service  = $this->_cRequest($op, '', [], 'get');

        return $this->decode($service);
    }

    /**
     * Decode result
     *
     * @param arr $service curl response
     *
     * @return str | array reason | results
     */
    public function decode($service)
    {
        $invalid = ($service['code'] != 200)
            || !array_key_exists('data', $service)
            || empty($service['data']);

        if ($invalid) {
            return $service['code'] . '/' . $service['status'];
        }

        $data = json_decode($service['data'], 1);
        if (array_key_exists('error', $data)) {
            return "{$data['error']['code']} / {$data['error']['message']}";
        }

        return $data['result'];
    }

    /**
     * Check storage for keys. if missing or expired - request service
     *
     * @param bool $force use online request
     *
     * @return mixed false if online failed, else - array
     */
    public function getToken($force = false)
    {
        $token = false;
        $db = PearDatabase::getInstance();
        $bringoStorage = StorageFactory::get($db->database, 'bringo');
        if (!$bringoStorage->auth || $force) {
            $this->msgs[] = 'Using online data';
            $serviceData = $this->getOnlineData();
            if ($serviceData) {
                $bringoStorage->set($this->serviceCookie);
                $token = $bringoStorage->auth;
            }
        } else {
            $this->msgs[] = 'Using storage. Key ts: ' . $bringoStorage->ts;
            $token = $bringoStorage->auth;
        }

        return $token;
    }

    /**
     * Service request with post procesing
     *
     * @return mixed bool | array online auth data
     */
    function getOnlineData()
    {
        $serviceResponse = $this->login();
    
        if (!$serviceResponse) {
            return false;
        }

        if (array_key_exists('code', $serviceResponse)) {
            $this->msgs[] = $serviceResponse['message'];
            return false;
        }

        return $serviceResponse;
    }

    /**
     * Request to get "Cookie data"
     *
     * @return mixed
     *   false on nerwork / request failures
     *   array [code, message] in case of error
     *   array [bringo data]
     */
    public function login()
    {
        $result = $this->cGetAuth();
        if (empty($result['data'])) {
            return false;
        }
        $token = json_decode($result['data'], 1);
        if (!array_key_exists('result', $token)) {
            return $token['error'];
        }

        return $token['result'];
    }

    /**
     * Wrapper to a lowlevel request. Override
     *
     * @return curl results
     */
    public function cGetAuth()
    {
        $op = 'login';
        $creds = [
            'login'     => self::LOGIN,
            'password'  => self::PASS
        ];

        return $this->request($op, $creds);
    }

    /**
     * Wrapper
     *
     * @param string $op   operation
     * @param array  $data request data
     *
     * @return curl result
     */
    function request($op, $data)
    {
        return $this->_cRequest(
            $op,
            $data,
            ['Content-Type: application/json']
            /*
            ['orders' => json_encode($data)],
            ['Content-Type: application/x-www-form-urlencoded']
            */
        );
    }

    /**
     * Implement abstract method
     *
     * @return service settings
     */
    public function getSettings()
    {
        return [
            'url' => self::URL,
            'useCookies' => true,
        ];
    }

    /**
     * Convert crm fields to service data
     *
     * @param array $data crm order data
     *
     * @return array service data
     */
    public function crm2svc($data)
    {
        return [
            "name" =>"Цветы " . $data['name'],
            "description" => $data['goods'],
            "externalId"  => $data['order'], //$data[orderid]
            "deliverySegments" =>[
                [
                    "from" => [
                        "address" => [
                            "addressText" => $data['from']['addr'],
                            "contact"     => $data['name'],
                            "phone"       => $data['from']['phone'],
                            "comment"     => $data['subject'],
                            "cityId"      => $this->cityCode($data['city'])
                        ],
                        "timeInterval" => [
                            "from" => $this->getISO($data['tgtDate'] . ' ' . $data['from']['t1']),
                            "to"   => $this->getISO($data['tgtDate'] . ' ' . $data['from']['t2'])
                        ]
                    ],
                    "to" => [
                        "address" => [
                            "addressText" => $data['dst']['addr'],
                            "contact"     => $data['dst']['person'],
                            "phone"       => $data['dst']['phone'],
                            "comment"     => "",
                            "cityId"      => $this->cityCode($data['city'])
                        ],
                        "timeInterval" => [
                            "from" => $this->getISO($data['tgtDate'] . ' ' . $data['dst']['t1']),
                            "to"   => $this->getISO($data['tgtDate'] . ' ' . $data['dst']['t2'])
                        ]
                    ],
                    "cargoCost" => $data['cost'], // $data['dst']['take']
                    "height" => 0.1,
                    "length" => 0.1,
                    "width"  => 0.1,
                    "weight" => 3.00,
                    "isBuyout" => array_key_exists('take', $data['dst']),
                ]
            ]
        ];
    }

    /**
     * Response error codes
     *
     * @param int $code digital
     *
     * @return string
     */
    public function errorCodes($code)
    {
        $codes = [
            1   => 'Ошибка оплаты доставки, не хватает средств на виртуальном счёте компании',
            47  => 'указанная доставка/группа доставок не найдена',
            62  => 'Некорректные данные',
            76  => 'CityId разный в адресах. Доставки из города в город в разработке',
            108 => 'Ошибка активации черновика.',
            117 => 'В настройках компании не зафиксирован факт заключения контракта',
            151 => 'Контракт запрещает доставки с выкупом',
        ];

        return array_key_exists($code, $codes)
            ? $codes[$code]
            : "Unknown error {$code}";
    }

    /**
     * Convert service code to text status
     *
     * @param int $code digital
     *
     * @return str
     */
    public function getStatus($code)
    {
        $map = [
            10 => 'Черновик',
            20 => 'Новая',
            30 => 'Взята',
            40 => 'Курьер у отправителя',
            50 => 'Курьер забрал доставку',
            60 => 'Курьер у получателя',
            70 => 'Передано получателю',
            80 => 'Закрыта',
        ]; 

        return array_key_exists($code, $map)
            ? $map[$code]
            : "Unknown status {$code}";
    }

    /**
     * Requests result oldCloseCode to Message
     *
     * @param str $code acronym
     *
     * @return str description
     */
    public function getReason($code)
    {
        $map = [
            'БФ'   => 'Без финансовых последствий для отправителя',
            'ОО'   => 'Отмена отправителем (груз не был передан курьеру)',
            'ОН'   => 'Отправитель недоступен',
            'НВОП' => 'Не выполнена, опоздание к отправителю (груз не был передан курьеру)',
            'ОКВ'  => 'Отказ курьера от выполнения доставки',
            'ООГ'  => 'Отказ отправителя груз у курьера, будет создана обратная доставка',
            'КГ'   => 'Кража груза',
            'УГ'   => 'Груз утрачен/испорчен курьером',
            'ОПГ'  => 'Отмена получателем, груз у курьера, будет создана обратная доставка',
            'ПН'   => 'Получатель недоступен (груз был передан курьеру), будет создана обратная доставка',
            'В'    => 'Успешное выполнение заказа',
            'И'    => 'Истекла',
            'ОКЛ'  => 'Отменена клиентом',
            'ОП'   => 'Отмена получателем, груз не у курьера',
            'ОМ'   => 'Ошибка менеджера',
            'ОС'   => 'Ошибка системы',
        ];

        return array_key_exists($code, $map)
            ? $map[$code]
            : ('Unknown ' . $code);
    }

    /**
     * Service specific method to get city code
     *
     * @param string $city cyrillic name
     *
     * @return int service code
     */
    public function cityCode($city)
    {
        $codes = [
            'Москва' => 1,
            'Санкт-Петербург' => 2,
        ];

        return in_array($city, array_keys($codes))
            ? $codes[$city]
            : 2;
    }

    /**
     * Service date format is ISO8601
     *
     * @param str $date any acceptable date
     *
     * @return string
     */
    public function getISO($date)
    {
        return date(DATE_ISO8601, strtotime($date));
    }

    /**
     * Override / implement?
     */
    public function getFinalStatus()
    {
        return [
            'Закрыта'
        ];
    }

    /**
     * Sample service response
     *
     * @return array
     */
    public function mockResponse()
    {
        return json_decode('{
          "id": 2502,
          "name": "Цветы Fantasy",
          "description": "Some things",
          "price": 601.8,
          "deliverySegments": [
            {
              "from": {
                "address": {
                  "geoPoint": {
                    "lat": 59.8337339,
                    "lng": 30.3531567
                  },
                  "addressText": "Звездная ул.,3 корп.1",
                  "contact": "Fantasy",
                  "phone": "9219387585",
                  "comment": "Цветы",
                  "cityId": 2
                },
                "timeInterval": {
                  "from": "2018-10-19T06:00:00+03:00",
                  "to": "2018-10-19T06:59:00+03:00"
                }
              },
              "to": {
                "address": {
                  "geoPoint": {
                    "lat": 59.930603,
                    "lng": 30.366409
                  },
                  "addressText": "Невский,128",
                  "contact": "Мария Сидорова",
                  "phone": "9991112233",
                  "comment": "",
                  "cityId": 2
                },
                "timeInterval": {
                  "from": "2018-10-19T07:00:00+03:00",
                  "to": "2018-10-19T08:00:00+03:00"
                }
              },
              "cargoCost": 0,
              "height": 0.1,
              "length": 0.1,
              "width": 0.1,
              "weight": 3,
              "isBuyout": false
            }
          ],
          "oldState": 20
        }', 1);
    }
}
