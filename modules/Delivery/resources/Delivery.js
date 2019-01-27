
if ((app.getModuleName() == 'SalesOrder')
    && (app.getViewName() == 'Detail')
) {

var PIN = {
    mod : {
        css  : '<style>'
            + '.pinModal{z-index: 10000;top:0;left:0;width:100%;height:100%;'
            + 'background-color:hsla(0, 0%, 100%, 0.95);cursor:pointer;position: fixed;overflow: hidden;'
            + 'display: flex;justify-content: center;align-items: center;}'
            + '#modalContent{max-width: 600px; max-height: 80vh; margin-top: -5vh;overflow-y: auto; box-shadow: 0px 1px 10px; background:rgba(255,255,255,.8); padding: 30px}'
            + '</style>',
        html : '<div class="blockOverlay pinModal"><div id="modalContent"></div></div>',
        render : function (content){
            var _ = this;
            $('body').append(_.css + _.html);
            $('#modalContent').html(content);

            $('.pinModal').on('*', function(e){
                e.stopImmidiatePropogation();
            }).click(function(){
                $(this).remove();
            });
        }
    },
    wait4node : function(css, cb, time)
    {
        var _ = this;
        var time = time || 300;
        var $node = $(css);
        if ($node.length == 1) {
            cb($node);
            return;
        } else {
            setTimeout(function() {
                _.wait4node(css, cb, time);
            }, time);
        }
    },
    trans : function (t) {
        var labels = {
            'matter'                : 'Что доставляем',
            'p1address'             : 'Куда',
            'p1required_time_start' : 'Доставка с',
            'p1required_time'       : 'До',
            'p1contact_person'      : 'Контактное лицо',
            'p1phone'               : 'Телефон',
            'p1taking'              : 'Курьеру',
            'p1note'                : 'Заметки'
        }

        return (t in Object.keys(labels))?labels[t]:t;
    }
};

var modDelivery = (function (Delivery) {
    'use strict';

    //TODO: jquery on document ready?
    PIN.wait4node('a[href=deliveryLink]', initDelivery, 100);

    var DelExport = {
        estimateDV : function(){return getEstimate('Dostavista');},
        estimatePK : function(){return getEstimate('Peshkariki');},
        estimateBR : function(){return getEstimate('Bringo');},
        svcList    : getSvcList,
        select     : selectProvider,
        render     : renderInfo,
        processAddr: processAddr
    }

    return DelExport;

    /**
    * Init UI
    */
    function initDelivery(deliveryLink)
    {
        $(Delivery.controls).appendTo(deliveryLink);
        $('.dropdown-menu').css('min-width', '190px');
        var deliveryIcon = '<i id="deliveryStatus" class="fa fa-th-list pull-right"'
            + ' style="padding:7px 8px;font-size:14px;box-sizing:border-box;margin-top:-5px;"'
            + '></i>';
        /*
        $(deliveryIcon).appendTo('#SalesOrder_detailView_moreAction_Доставки a');

        $('#deliveryStatus').on('click',function(e){
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            var param = {
                module : 'Delivery',
                view   : 'Status',
                record : Delivery.record
            };

            return AppConnector.request(param).done(function(res){
                if (res != 'false') {
                    app.showModalWindow(res);
                } else {
                    Vtiger_Helper_Js.showPnotify({
                        text: 'Нет связанных доставок',
                        type: 'info'
                    });
                }
            });
        });
        */

        deliveryLink.on('click',function(e){
            e.preventDefault();
        });

        $('#placeOrder').on('click',function(e){
            e.preventDefault();
            showData();
        });

        $('#listOrders').on('click',function(e){
            e.preventDefault();
            getOrderList();
        });

        $('#estimateDV').on('click',function(e){
            e.preventDefault();
            getEstimate()
                .done(function(data){
                    showEstimate(data);
                })
                .fail(function(){
                    Vtiger_Helper_Js.showPnotify({text: 'Ошибка: подсчет недоступен'});
                });
        });
    }

    function selectProvider()
    {
        var pampam = {
            module : 'Delivery',
            view   : 'Select'
        }
        app.showModalWindow({
            url : 'index.php?' + $.param(pampam)
        });
    }

    function showEstimate(data)
    {
        //try
        var obj = JSON.parse(data.result);
        var msg = '';
        if (obj.result === 0){
            var err = ('error_message' in obj)?obj.error_message[0] : obj.error_code;
            msg += '<h2>Dostavista: Ошибка</h2><pre>' + err + '</pre>';
        } else {
            msg = '<h2>Запрос стоимости</h2><br/>'
                + 'Приблизительная стоимость : <b>'
                + obj.payment + ' р</b>';
        }
        msg += '<br><a href="#" data-dismiss="modal" class="pull-right">Понятно</a>';
        app.showModalWindow(
            msg,
            function(){},
            {padding: '20px'}
        );
    }

    function placeOrder()
    {
        // validate and send
        var params = {
            'module': 'Delivery',
            'action': 'Dostavista',
            'mode'  : 'placeOrder',
            'record': Delivery.record
        };

        AppConnector.request(params)
            .done(function(data){
                var obj = JSON.parse(data.result);
                var msg = '';
                if (obj.result === 0){
                    var err = ('error_message' in obj)?obj.error_message[0] : obj.error_code;
                    msg += '<h2>Dostavista: Ошибка</h2><pre>' + err + '</pre>';
                } else {
                    msg = '<h2>Удачно</h2>'
                        + '<br>Заказ: '    + obj.order_id
                        //+ '<br>Куда: '     + obj.dst
                        + '<br>На сумму: ' + obj.payment;
                }
                msg += '<br><a href="#" data-dismiss="modal" class="pull-right">Понятно</a>';
                app.showModalWindow(
                    msg,
                    function(){},
                    {padding: '20px'}
                );
            })
            .fail(function(){
                Vtiger_Helper_Js.showPnotify({text: 'Ошибка: Заказ не размещен'});
            });
    }


    function getOrderList(svc)
    {
        svc = svc || 'Dostavista';
        var params = {
            'module': 'Delivery',
            'action': svc,
            'mode'  : 'getOrders',
            'record': Delivery.record
        };
        var orderStatus = {
            '0'  : 'Создан',
            '1'  : 'Доступен',
            '2'  : 'Активен',
            '3'  : 'Завершен',
            '10' : 'Отменен',
            '16' : 'Отложен'
        };

        AppConnector.request(params)
            .done(function(data){
                var obj = JSON.parse(data.result);
                var msg = Delivery.css;
                var deliveryStatus = false;
                if (obj.result === 0){
                    var err = ('error_message' in obj)?obj.error_message[0] : obj.error_code;
                    msg += '<h2>Dostavista: Ошибка</h2><pre>' + err + '</pre>';
                } else {
                    msg += '<h2>Dostavista: Статус</h2>';
                    msg += '<table class="notice table table-bordered">';
                    var x = obj.order;
                    deliveryStatus = x['status'];
                    msg += '<tr><td>id</td><td>'     + x['order_id'] + '</td></tr>'
                         + '<tr><td>Статус</td><td>' + orderStatus[x['status']] + '</td></tr>'
                         + '<tr><td>Сумма</td><td>'  + x['cost'] + '</td></tr>';
                    if ('courier' in x) {
                        msg += '<tr><td>Курьер/Имя</td><td>' + x['courier'].name + '</td></tr>';
                        msg += '<tr><td>Курьер/Тел.</td><td>+7' + x['courier'].phone + '</td></tr>';
                        msg += '<tr><td>Курьер/Фото</td><td><img style="max-width: 100px;" src="' + x['courier'].photo + '"/></td></tr>';
                    }
                    msg += '</table>';
                }
                msg += '<br>';
                if (deliveryStatus && deliveryStatus < 3) {
                    msg += '<a href="#" id="discardSvc" class="cancelLink pull-left">Отменить Заказ</a>';
                }
                msg += '<button class="btn btn-success pull-right" data-dismiss="modal">Ok</button>';

                app.showModalWindow(
                    msg,
                    function(container){
                        if (obj.result === 0) return;

                        if (deliveryStatus && deliveryStatus > 3) return;

                        var sostatus   = $('[name=sostatus]').val();
                        var discardBtn = $('#discardSvc',container);
                        //discardBtn.css('color','#999');
                        discardBtn.on('click',function(){
                            cancelPrompt()
                                .then(function(){
                                    return cancelOrder();
                                })
                                .done(function(data){
                                    console.log('Cancelled',data)
                                })
                                .fail(function(){
                                    console.log('Another time')
                                })
                                .always(function(){
                                    app.hideModalWindow();
                                })
                        });
                        return;
                    },
                    {padding: '20px'}
                );

            })
            .fail(function(){
                Vtiger_Helper_Js.showPnotify({text: 'Ошибка: Список заказов недоступен'});
            });
    }

    function showData()
    {
        var DostaVistaResult = getDostaData();
        if (DostaVistaResult === false) return;

        deliveryConfirm(
            { title: 'Доставка в Dostavista', msg  : DostaVistaResult },
            'Отправить', 'Отмена'
        ).done(function(){
            var record = app.getRecordId();
            placeOrder(record);
        })
        .fail(function(){
            Vtiger_Helper_Js.showPnotify({text: 'Заказ не размещен', type: 'info'});
        })
    }

    /**
    * Delivery confirmation dialogue
    */
    function deliveryConfirm(data, lblYes, lblNo)
    {
        var a = $.Deferred();
        bootbox.confirm(
            "<h3>" + data['title'] + "</h3><hr/>"
            + data['msg'],
            lblNo,
            lblYes,
            function(result) {
                if(result){
                    a.resolve('Confirm');
                } else {
                    a.reject('Decline');
                }
            });

        getEstimate().then(function(data){
            if (data) {
                var obj = JSON.parse(data.result)
                $('#estim').html(obj.payment);
            }
        });

        $('#estim').on('click',function (e){
            e.preventDefault();
            this.innerHTML = (Math.random() * 100).toFixed(2);
        });

        return a.promise();
    }

    /**
    * Retrive Estimate cost
    */
    function getEstimate(prov)
    {
        var prov = prov || 'Dostavista';
        var params = {
            module : 'Delivery',
            action : prov,
            mode   : 'calcOrder',
            record : Delivery.record
        }

        return AppConnector.request(params);
    }

    /**
    * Discard delivery Prompt
    */
    function cancelPrompt()
    {
        var a = $.Deferred();
        bootbox.confirm(
            '<h3>Доставка: Отмена</h3><hr/>'
            + 'Подтвердите действие',
            'Нет',
            'Отменить доставку',
            function(result) {
                if(result){
                    a.resolve('Confirm');
                } else {
                    a.reject('Decline');
                }
            });

        return a.promise();
    }

    /**
    * Discard a delivery
    */
    function cancelOrder(svc)
    {
        svc = svc || 'Dostavista';
        var params = {
            module : 'Delivery',
            action : svc,
            mode   : 'cancelOrder',
            record : Delivery.record
        }

        return AppConnector.request(params);
    }

    /**
     * Displays validationEngine Error popup
     * @param {string} key field name
     * @param {string} error message
     */
    function invalidPopup(selector, msg)
    {
        var popupTarget = '';
        var viewName = app.getViewName();
        if (viewName == 'Detail') {
            popupTarget = '#SalesOrder_detailView_fieldValue_' + selector + ' span.value';
        } else {
            popupTarget = '[name = ' + selector + ']';
        }

        var invalidField = $(popupTarget);

        invalidField.validationEngine('showPrompt', msg); //not working , 'red', {promptPosition: 'topRight'}, true
        if (!$.validationEngine.defaults.autoHidePrompt){
            var autoHideDelay = 4000;
            setTimeout(function(){
                $('.formErrorContent').parent()
                    .fadeOut(1000, function(){$(this).remove();});
                }, autoHideDelay);
        }
    }

    /**
     * Validate and display data for Dostavista
     * @return {false} or {string}
     */
    function getDostaData()
    {
        var needDelivery = $("[name=cf_645]").is(':checked');

        if (!needDelivery) {
            invalidPopup(
                'cf_645',
                'Для работы этого пункта требуется <b>Да</b>'
            );
            return false;
        }

        var couriers = $('select[name=cf_656]');
        var triggerValue = 'Курьер DostaVista';

        if (couriers.val() != triggerValue) {
            invalidPopup(
                'cf_656',
                'Для работы этого пункта выберите значение <b>'
                 + triggerValue + '</b>'
            );
            return false;
        }

        var city = $('[name = ship_city]').val();
        var addr = $('[name = ship_street]').val();
        if (addr === ''){
            invalidPopup(
                'ship_street',
                'Адрес в формате “улица, дом”.<br/>'
                    + 'Пример: Солянка, 13к1, стр.6'
                );

            return false;
        }

        //"31-10-2016" - DatePicker format
        var delDate = $('[name = cf_650]').val();
        var validDate = delDate;
        var isDateValid = delDate.match(/\d{4}-\d{2}-\d{2}/);

        if (!isDateValid) {
            var validDateArray = delDate.match(/(\d{2}).*(\d{2}).*(\d{4})/);
            //altering datePicker format
            validDate = validDateArray[3] + '-' + validDateArray[2] + '-' + validDateArray[1];
            if (validDate.length != 10) {
                invalidPopup(
                    'cf_650',
                    'Дата доставки должна быть в формате <b>ГГГГ-ММ-ДД</b>'
                );

                return false;
            }
        }

        var delTime = $('[name = cf_652]').val();
        //"С 19:00 до 20:00"
        var inter = delTime.match(/\W*(\d{1,2}:\d{2})\W*(\d{1,2}:\d{2})/);
        //TODO check lower>upper
        if (inter === null || inter.length != 3) {
            invalidPopup(
                'cf_652',
                'Интервал должен содержать пару значений вида <b>Час:Минуты</b>'
            );

            return false;
        }
        //["С 19:00 до 20:00", "19:00", "20:00"]

        var startTime = validDate + ' ' + inter[1] + ':00';
        var endTime   = validDate + ' ' + inter[2] + ':00';

        var person = $('[name = cf_657]').val();
        if (person === '') {
            invalidPopup(
                'cf_657',
                'Нужно указать Получателя'
            );

            return false;
        }

        var phone  = $('[name = cf_658]').val();
        if (phone === '') {
            invalidPopup(
                'cf_658',
                'Телефон получателя не должен быть пустым'
            );

            return false;
        }

        var displayAddr = (addr.indexOf(city) == 0)? addr : (city + ',' + addr);
        var dostaQuery = {
            matter                 : 'Букет цветов',
            p1address              : displayAddr,
            p1required_time_start  : startTime,
            p1required_time        : endTime,
            p1contact_person       : person,
            p1phone                : phone
        };

        var payment = $('[name = cf_648]').val();
        var taking  = '';
        if (payment === 'Курьеру') {
            dostaQuery['p1taking'] = $('[name = cf_835]').val();
            taking = "<tr><td>Курьеру</td><td>" + dostaQuery['p1taking'] + "</td></tr>";
        }

        var addrNote = $('[name = cf_675]').val();
        var notice  = '';
        if (addrNote != '') {
            dostaQuery['p1note'] = addrNote;
            notice = "<tr><td>Заметки</td><td>" + dostaQuery['p1note'] + "</td></tr>";
        }

        var html  = '<table class="notice">'
            + '<tr><td>url</td><td>' + Delivery.url + '</td></tr>'
            + '<tr><td>Что доставляем</td><td>' + dostaQuery['matter'] + '</td></tr>'
            + '<tr><td>Куда</td><td>' + dostaQuery['p1address'] + '</td></tr>'
            + '<tr><td>Доставка с</td><td>' + dostaQuery['p1required_time_start'] + '</td></tr>'
            + '<tr><td>До</td><td>' + dostaQuery['p1required_time'] + '</td></tr>'
            + '<tr><td>Контактное лицо</td><td>' + dostaQuery['p1contact_person'] + '</td></tr>'
            + '<tr><td>Телефон</td><td>' + dostaQuery['p1phone'] + '</td></tr>'
            + taking
            + notice
            + '<tr><td>Стоимость</td><td><span id="estim">...</span></td></tr>'
            + '</table>';

        return Delivery.css + html;
    }

    /*
     *   return object for delivery
     */
    function collectData()
    {
        var needDelivery = $("[name=cf_645]").is(':checked');

        if (!needDelivery) {
            invalidPopup(
                'cf_645',
                'Для работы этого пункта требуется <b>Да</b>'
            );
            return false;
        }

        var city = $('[name = ship_city]').val();
        var addr = $('[name = ship_street]').val();
        if (addr === ''){
            invalidPopup(
                'ship_street',
                'Адрес в формате “улица, дом”.<br/>'
                    + 'Пример: Солянка, 13к1, стр.6'
                );

            return false;
        }

        //"31-10-2016" - DatePicker format
        var delDate = $('[name = cf_650]').val();
        var validDate = delDate;
        var isDateValid = delDate.match(/\d{4}-\d{2}-\d{2}/);

        if (!isDateValid) {
            var validDateArray = delDate.match(/(\d{2}).*(\d{2}).*(\d{4})/);
            //altering datePicker format
            validDate = validDateArray[3] + '-' + validDateArray[2] + '-' + validDateArray[1];
            if (validDate.length != 10) {
                invalidPopup(
                    'cf_650',
                    'Дата доставки должна быть в формате <b>ГГГГ-ММ-ДД</b>'
                );

                return false;
            }
        }

        var delTime = $('[name = cf_652]').val();
        //"С 19:00 до 20:00"
        var inter = delTime.match(/\W*(\d{1,2}:\d{2})\W*(\d{1,2}:\d{2})/);
        //TODO check lower>upper
        if (inter === null || inter.length != 3) {
            invalidPopup(
                'cf_652',
                'Интервал должен содержать пару значений вида <b>Час:Минуты</b>'
            );

            return false;
        }
        //["С 19:00 до 20:00", "19:00", "20:00"]

        var startTime = validDate + ' ' + inter[1] + ':00';
        var endTime   = validDate + ' ' + inter[2] + ':00';

        var person = $('[name = cf_657]').val();
        if (person === '') {
            invalidPopup(
                'cf_657',
                'Нужно указать Получателя'
            );

            return false;
        }

        var phone  = $('[name = cf_658]').val();
        if (phone === '') {
            invalidPopup(
                'cf_658',
                'Телефон получателя не должен быть пустым'
            );

            return false;
        }
        var displayAddr = (addr.indexOf(city) == 0)? addr : (city + ',' + addr);
        var data = {
            matter                 : 'Букет цветов',
            p1address              : displayAddr,
            p1required_time_start  : startTime,
            p1required_time        : endTime,
            p1contact_person       : person,
            p1phone                : phone
        };

        var payment = $('[name = cf_648]').val();
        if (payment === 'Курьеру') {
            data['p1taking'] = $('[name = cf_835]').val();
        }

        var addrNote = $('[name = cf_675]').val();
        if (addrNote != '') {
            data['p1note'] = addrNote;
        }

        return data;
    }

    /**
    * Discard a delivery
    */
    function renderInfo()
    {
        var params = {
            module : 'Delivery',
            view   : 'Choose',
            record : Delivery.record
        }

        return AppConnector.request(params).done(function(res){
            app.showModalWindow({
                'data': res,
                'css' : {'padding': '20px'}
            });
        });
    }

    function renderInfo0(orderData)
    {
        orderData = collectData();

        if (!orderData) return;
        var labels = {
            'matter'                : 'Что доставляем',
            'p1address'             : 'Куда',
            'p1required_time_start' : 'Доставка с',
            'p1required_time'       : 'До',
            'p1contact_person'      : 'Контактное лицо',
            'p1phone'               : 'Телефон',
            'p1taking'              : 'Курьеру',
            'p1note'                : 'Заметки'
        }
        var html  = '<h3>Доставка</h3><hr/>'
            + '<table class="notice">';
            //+ '<tr><td>url</td><td>' + Delivery.url + '</td></tr>';
        for (var k in orderData) {
            var v  = orderData[k];
            if (k == 'p1address') {
                v = '<span id="p1addr">' + v + '</span>';
            }
            html += '<tr><td>' + labels[k] + '</td><td>' + v + '</td></tr>';
        }

        html += '</table>';

        var selector = `
        <style>
            .buttons,.selector {display: flex; width: 100%;}
            .selector input {display:none}
            .selector label {display: block;box-sizing:border-box;width:50%;
                padding: 15px 30px;margin:0;text-align:center;transition: all .3s ease;}
            .selector label:hover {background: #ced;}
            .selector input:checked + label {background: #bfd}
            .buttons {justify-content:space-around;max-width:60%;float:right;padding-top:20px;}
            .warn:after { font-family: FontAwesome; content: "\f00d"}
        </style>
        <h5>Выберите службу:</h5>
        <div class="selector">
            <input type=radio id="dv" name="svc"/>
            <label id="lbl_dv" for="dv" class="opts">
                Dostavista: <span class="prc"></span>
            </label>
            <input type=radio id="pk" name="svc"/>
            <label id="lbl_pk" for="pk" class="opts">
                Пешкарики: <span class="prc"></span>
            </label>
            <input type=radio id="br" name="svc"/>
            <label id="lbl_br" for="br" class="opts">
                Bringo: <span class="prc"></span>
            </label>
        </div>
        <div class="buttons">
            <a class="cancelLink" type="reset" data-dismiss="modal">Отмена</a>
            <button id="createDelivery" class="btn disabled" data-state="off">Создать</button>
        </div>
        `;

        app.showModalWindow(
            Delivery.css + html + selector,
            modalInit,
            {'padding': '20px'}
        );
        //return html + selector;
    }

    /*
     *   callback for show Delivery prompt
     */
    function modalInit(){
        /*
        $('.prc').each(function(i,x){
            //Init precalc
            x.innerText = '---р.'
        });
        */
        $('.selector input').each(function(i,x){
            //Init precalc
            $(x).on('change', function(){
                console.log(this.checked);
            })
        });
        var addrNode = $('#p1addr');
        var addr = addrNode.text();
        $('.opts').each(function(i,x){
            //Enable on select
            $(this).on('click',function(){
                //Process address
                var $btn = $('#createDelivery');
                if (this.id == 'lbl_pk') {
                    $btn.data('provider','Peshkariki');
                    //addrNode.html(modDelivery.processAddr(addr));
                }
                if (this.id == 'lbl_dv') {
                    $btn.data('provider','Dostavista');
                    //addrNode.text(addr);
                }
                //Enable button
                if ($btn.data('state') == 'on') { return; }

                $btn.data('state','on');
                $btn.removeClass('disabled');
                $btn.addClass('btn-success');
                //btn.attr('type', 'submit');
            })
        }),

        DelExport.estimateDV().then(function(data){
            if (data.result == ''){
                $('#lbl_dv .prc').html('Неизвестная ошибка');
                return false;
            }
            var obj = JSON.parse(data.result);
            var msg = '<i class="fa fa-times-circle" style="color: #b94a48;"></i>';
            if ('validation_errors' in obj) {
                //$('#dv').prop('disabled', true);
                //$serviceReply append reason
            } else {
                msg = obj.payment + 'р.';
                $('#dv').prop('checked', true);
                $('#createDelivery').data('provider', 'Dostavista');
                turnOn();
            }
            $('#lbl_dv .prc').html(msg);
        });
    
        DelExport.estimatePK().then(function(data){
            if (!data.result){
                $('#lbl_pk .prc').html('Неизвестная ошибка');
                return false;
            }
            var obj = data.result;
            var msg = '<i class="fa fa-times-circle" style="color: #b94a48;"></i>';
            if ('delivery_price' in obj) {
                msg = obj.delivery_price + 'р.';
            } else {
                $('#pk').prop('disabled', true);
            }
            $('#lbl_pk .prc').html(msg);
        });
        $('#createDelivery').on('click',function(){
            var $btn = $(this);
            if ($btn.data('state') == 'off') { return; }

            var provider = $btn.data('provider');
            var params = {
                module : 'Delivery',
                action : provider,
                mode   : 'placeOrder',
                record : Delivery.record
            }
            var msg = '';
            AppConnector.request(params)
                .done(function(){ msg = 'Без ошибок';})
                .fail(function(){ msg = 'Ошибка';})
                .always(function(){
                    app.hideModalWindow();
                    Vtiger_Helper_Js.showPnotify({text: msg, type: 'info'});
                });
        });
    }

    function turnOn()
    {
        var $btn = $('#createDelivery');
        $btn.data('state','on');
        $btn.removeClass('disabled');
        $btn.addClass('btn-success');
    }

    function getSvcList()
    {
        var pampam = {
            module : 'Delivery',
            action : 'Peshkariki',
            mode   : 'getServices'
        }
        app.showModalWindow({
            url : 'index.php?' + $.param(pampam)
        });
    }

    function processAddr(addr)
    {
        var chunks = addr.split(',');
        var html = '';
        var parts = ['city','street','house','apt'];
        var labels = {
            'city'   : 'Город',
            'street' : 'Улица',
            'house'  : 'Дом',
            'apt'    : 'Квартира'
        };
        html += parts.map(function(x,i){
            return (typeof chunks[i] != 'undefined')?
                '<input name="'+ x +'" placeholder="' + labels[x] + '" value="' + chunks[i] + '"/>'
                : ''
        }).join('<br/>');

        return html;
    }

})({
        url : 'https://dostavista.ru/bapi/order',
        css : '<style>'
            + 'table.notice {margin-bottom: 20px;min-width: 500px;max-width:600px}'
            + 'table.notice td:nth-of-type(2n-1){ font-weight:bold; text-align: right; padding-right: 10px}'
            + '.notice td:nth(2) {vertical-align: top}'
            + '.noticeLink {margin: 6px 8px;}'
            + '.notice td {min-width: 210px; padding-bottom: 15px;}'
            + '</style>',
        controls : '<div class="input-append pull-right" style="margin: -5px 0;">'
            +'<span class="add-on" id="placeOrder"><i class="icon-plus"></i></span>'
            +'<span class="add-on" id="listOrders"><i class="icon-th-list"></i></span>'
            +'<span class="add-on" id="estimateDV" style="font-family: Impact; color: #000">$</span>'
            +'</div>',
        prop: 0,
        record: app.getRecordId(),
    });
}

var fairwind = {
    show : function () {
        AppConnector.request({
            module: 'Delivery',
            action: 'Wind',
            id: app.getRecordId()
        }).then(function (res) {
            PIN.mod.render(
                '<h2>Доставка создана!</h2><hr/>'
                + fairwind.render(res.result)
                //+ JSON.stringify(res.result)
            );
            /*
             * TODO update order with new courier
             * reload page
             */
        });
    },
    render : function (data) {
        if (!data) return 'No info';
        var html  = '<table class="table table-condensed">';
        for (var k in data) {
            var v  = data[k];
            if (v instanceof Object) v = fairwind.render(v);
            html += '<tr><td>' + k + '</td><td>' + v + '</td></tr>';
        }
        html += '</table>';

        return html;
    }
}
