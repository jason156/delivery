{literal}
<style>
    .selector {padding: 20px; box-sizing:border-box;}
    .deliveryLogo {
        background: url() no-repeat center 50%/contain;
        width:100%; height:40px; margin:20px 0;
    }
    .selector .buttons {display:flex;justify-content:space-around;}
    table.notice {margin-bottom: 20px; min-width: 500px;}
    table.notice td:nth-of-type(2n-1){font-weight:bold;text-align:right;padding-right:10px}
    .notice td:nth(2) {vertical-align: top}
    .noticeLink {margin: 6px 8px;}
    .notice td {min-width: 210px; padding-bottom: 15px;}
</style>
{/literal}
<div class="deliveryLogo" style="background-image:url({$LOGO})"></div>
{strip}
<div class="selector">
    <table class="table notice">
    {foreach from=$REQ key=LABEL item=VALUE}
        <tr><td>{vtranslate($LABEL, 'Delivery')}</td><td class="v_{$LABEL}">{$VALUE}</td></tr>
    {/foreach}
    </table>
    <div class="buttons">
        <a id="cancel" class="cancelLinkContainer cancelLink">Отменить Доставку</a>
        <button class="btn btn-success" data-dismiss="modal">Хорошо</button>
    </div>
</div>
{/strip}
{*
{assign var=valid value=['В работе','Отменен','Доставлен']}
{if in_array($REQ['status'], $valid)}
    hide
{/if}
*}
<script>
{literal}
(function(svc)
{
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

    /*
    * Peshkariki manual status refresh
    */
    function refreshStatus()
    {
		var skipToken = 'Достав';
        var param = {
            'module': 'Delivery',
            'action': svc,
            'mode'  : 'getOrders',
            'record': app.getRecordId()
            }
        //TODO update ui
		var delStatus = $('.v_status').text();
		if (delStatus.search(skipToken) > -1) return;

        AppConnector.request(param)
            .done(function(data){
                var res = data.result;
				//TODO validate and compare
                $('.v_status').text(res.status);
                $('.v_dst').text(res.routes[1].address);
                msg = 'Обновлено';
            })
            .fail(function(){ msg = 'Ошибка';})
            .always(function(){
                Vtiger_Helper_Js.showPnotify({
                    text: msg,
                    type: 'info'
                });
            })
    }

    /*
    * Dostavista retrive courier image
    */
    function retrivePhoto()
    {
        var param = {
            'module': 'Delivery',
            'action': 'Dostavista',
            'mode'  : 'getOrders',
            'record': app.getRecordId()
        }
        //TODO update ui
        AppConnector.request(param)
            .done(function(data){
                //TODO validate
                var res = JSON.parse(data.result).order;
                if (!('courier' in res)) return;
                $('.v_courierphone').parent()
                    .after('<tr><td>Курьер/Фото</td>'
                        + '<td><img style="max-width: 100px;" src="'
                        + res.courier.photo
                        + '"/></td></tr>');
            })
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
    * Discard delivery
    */
    function cancelOrder(service)
    {
        var param = {
            'module':'Delivery',
            'action': service,
            'mode'  : 'cancelOrder',
            'record': app.getRecordId()
            }

        return AppConnector.request(param)
            .done(function(){ msg = 'Выполнено';})
            .fail(function(){ msg = 'Ошибка';})
            .always(function(){
                Vtiger_Helper_Js.showPnotify({
                    text: msg,
                    type: 'info'
                });
            })
    }

    wait4node('a#cancel',function(node){
        node.on('click',function(e){
            cancelPrompt()
                .then(function(){
                    return cancelOrder(svc);
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
    });

    if (svc == 'Peshkariki'){
		wait4node('.v_status', refreshStatus);
    }

    if (svc == 'Dostavista'){
        retrivePhoto();
    }
}
{/literal}
)('{$SVC}');
</script>
