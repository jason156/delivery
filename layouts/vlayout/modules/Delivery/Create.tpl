{literal}
<style>
    div#globalmodal * {box-sizing: border-box}
    div#globalmodal {max-width: 600px;}
    table.notice {margin-bottom: 20px; min-width: 500px;}
    table.notice td:nth-of-type(2n-1){ font-weight:bold; text-align: right; padding-right: 10px}
    .notice td:nth(2) {vertical-align: top}
    .noticeLink {margin: 6px 8px;}
    .notice td {min-width: 210px; padding-bottom: 15px;}
    .frow,.buttons,.selector {display: flex; width: 100%;}
    .frow {justify-content: center;}
    .selector {flex-direction:column}
    .selector input {display:none}
    .selector label {display: block;padding: 15px 30px;
        margin:0;text-align:center;transition: all .3s ease;}
    .selector label:hover {background: #ced;}
    .selector input:checked + label {background: #bfd}
    .buttons {justify-content:space-around;max-width:60%;float:right;padding-top:20px;}
    .warn:after { font-family: FontAwesome; content: "\f00d"}
    .col-xs-6 {max-width: 50%; word-wrap: break-word;}
</style>
{/literal}
<h3>Доставка: Создание</h3><br/>
<table class="table notice">
{foreach from=$ORDER key=LABEL item=VALUE}
    <tr><td>{vtranslate($LABEL, 'Delivery')}</td><td>{$VALUE}</td></tr>
{/foreach}
</table>
<h5>Выберите службу:</h5>
<div class="frow">
<div class="selector col-xs-6">
{foreach from=$SVCLIST item=SVC}
    <input type=radio id="{$SVC['id']}" name="svc"/>
    <label id="lbl_{$SVC['id']}" for="{$SVC['id']}" class="opts">
        {$SVC['label']}: <span class="prc"></span>
    </label>
{/foreach}
</div>
<div id="serviceReply" class="col-xs-6">
</div>
</div>
<div class="buttons">
    <a class="cancelLink" type="reset" data-dismiss="modal">Отмена</a>
    <button id="createDelivery" class="btn disabled" data-state="off">Создать</button>
</div>
<script>
{literal}
(function(arg)
{
    var costs = [];
    var errSign = '<i class="fa fa-times-circle" style="color: #b94a48;"></i>';

    function wait4node(css, cb, time)
    {
        time = time || 300;
        var $node = $(css);
        if ($node.length == 1) {
            cb($node);
            return;
        } else {
            setTimeout(function() {
                wait4node(css, cb, time);
            }, time);
        }
    }

    function getEstimate(prov)
    {
        var prov = prov || 'Dostavista';
        var params = {
            module : 'Delivery',
            action : prov,
            mode   : 'calcOrder',
            record : app.getRecordId()
        }

        return AppConnector.request(params);
    }

    function flatternJSON(data)
    {
        return JSON.stringify(data);
    }

    function turnOn()
    {
        var fav = minCost();
        var $btn = $('#createDelivery');
        var providers = {
            'dv' : 'Dostavista',
            'pk' : 'Peshkariki'
        }

        if ('svc' in fav) {
            $('#'+fav.svc).prop('checked', true);
            $btn.data('provider', providers[fav.svc]);
        }
        if ($btn.data('state') == 'on') { return; }

        $btn.data('state','on');
        $btn.removeClass('disabled');
        $btn.addClass('btn-success');
    }

    function initCreate($node)
    {
        //TODO move to turnOn function
        $node.on('click',function(){
            var $btn = $(this);
            if ($btn.data('state') == 'off') { return; }

            $btn.data('state', 'off');
            $btn.text('Отправка...');

            var provider = $btn.data('provider');
            var params = {
                module : 'Delivery',
                action : provider,
                mode   : 'placeOrder',
                record : app.getRecordId()
            }

            var msg = '';
            var nType = 'info';
            var nDelay = 2000;
            //TODO verbose output
            AppConnector.request(params)
                .done(function(data){
                    msg = 'Без ошибок';
                    //#red-773
                    var res = data.result;
                    if ((typeof res == 'object')
                        && ('code' in res)
                        && ('additional' in res)
                    ){
                        var err = '';
                        err = res.additional.split("\n").reduce(function(a,x){
                            var val = x.replace(/[\(\)]/g,'');
                            return a + ((val.indexOf('Array')>-1)?'':val)
                            }, '');
                        msg = res.code + ': ' + err;
                        nType = 'error';
                        nDelay = 8000;
                    }
                })
                .fail(function(){ msg = 'Ошибка';})
                .always(function(){
                    app.hideModalWindow();
                    Vtiger_Helper_Js.showPnotify({
                        text: msg,
                        type: nType,
                        closer_hover: true,
                        delay: nDelay
                    });
                });
        });

        $('.frow input').prop('disabled', true);
        //Init switchers
        //TODO change to input based
        $('.opts').each(function(i,x){
            $(this).on('click',function(){
                //TODO use $node
                var $btn = $('#createDelivery');
                //TODO dynamic switch
                if (this.id == 'lbl_pk') {
                    $btn.data('provider','Peshkariki');
                }
                if (this.id == 'lbl_dv') {
                    $btn.data('provider','Dostavista');
                }
            })
        });
    }

    function minCost()
    {
        var fav;
        var minVal = costs.reduce(function(m, o){
            var k = Object.keys(o)[0];
            var v = 0;

            if (o[k] < m) {
                v = o[k];
                fav = k;
            } else {
                v = m;
            }

            return v;
            }, Infinity
        );

        return {'svc': fav, 'price': minVal}
    }

    function maxCost()
    {
        return costs.reduce(function(m, o){
            var k = Object.keys(o)[0];
            return o[k] > m ? o[k] : m;
            }, 0
        );
    }

    getEstimate('Dostavista').then(function(res){
        var msg = errSign;
        var err = [];

        if (!res.success) {
            err.push(res.error.message);
            $('#serviceReply').append('<b>Доставки</b>:<br/> ' + res.error.message + '<br/>');
            $('#lbl_dv .prc').html(msg);
            return;
        }

        if (res.result == ''){
            $('#lbl_dv .prc').html('Неизвестная ошибка');
            return false;
        }

        var data = JSON.parse(res.result);
        if ((typeof data == 'object')
            && ('payment' in data)
        ) {
            msg = data.payment + 'р.';
            costs.push({'dv': data.payment});
            $('#createDelivery').data('provider', 'Dostavista');
            turnOn();
            $('#dv').prop('disabled', false);
        } else {
            var reason = data.error_message.map(function(x){
                return x.replace(/\n+/g,"<br>");
            }).join('<br/>');
            $('#serviceReply').append('<b>Dostavista</b>:<br/> ' + reason + '<br/>');
        }
        $('#lbl_dv .prc').html(msg);
    });

    getEstimate('Peshkariki').then(function(res){
        var msg = errSign;
        var err = [];

        if (!res.success) {
            err.push(res.error.message);
            $('#serviceReply').append('<b>Доставки</b>:<br/> ' + res.error.message + '<br/>');
            $('#lbl_pk .prc').html(msg);
            return;
        }

        if (!res.result){
            $('#lbl_pk .prc').html('Неизвестная ошибка');
            return false;
        }

        var data = res.result;
        if ((typeof data == 'object')
            && ('delivery_price' in data)
        ) {
            msg = data.delivery_price + 'р.';
            costs.push({'pk': data.delivery_price});
            $('#createDelivery').data('provider', 'Peshkariki');
            turnOn();
            $('#pk').prop('disabled', false);
        } else {
            $('#serviceReply').append('<b>Peshkariki</b>:<br/> ' + data + '<br/>');
        }
        $('#lbl_pk .prc').html(msg);
    });

    wait4node('#createDelivery', initCreate);
})();
{/literal}
</script>
