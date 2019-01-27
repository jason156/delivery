<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

class Delivery_Status_View extends Vtiger_PopupAjax_View
{

    function process (Vtiger_Request $request)
    {
        $mod  = $request->getModule();
        $soId = $request->get('record');

        $deModule = Vtiger_Module_Model::getInstance($mod);
        $data = $deModule->getStatus($soId);
        if (is_bool($data)) {
            echo 'no Dels Found';
            return false;
        }
        $logo = [
            'Dostavista' => 'https://dostavista.ru/img/logo-ru.svg',
            'Peshkariki' => 'http://peshkariki.ru/images/peshkariki.png',
            'Povetru'    => 'http://x.itlogist.ru/imgs/lg.png',
            'Bringo'     => 'https://www.bringo.ro/landing/onboarding/3/img/logo.png',
        ];
        $service = $data['service'];

        $hide = [
            'deliveryid',
            'service',
            'salesorderid',
            'shopid'
        ];
        if (empty($data['couriername'])) {
            $hide[] = 'couriername';
        }
        if (empty($data['courierphone'])) {
            $hide[] = 'courierphone';
        }
        $display = $data;
        foreach ($hide as $field) {
            unset($display[$field]);
        };
        $display['cost'] = sprintf("%d р.", $data['cost']);
        $viewer = $this->getViewer($request);
        $viewer->assign('LOGO', $logo[$service]);
        $viewer->assign('SVC', $service);
        $viewer->assign('REQ', $display);
        $popup = $viewer->view('Status.tpl', $mod, true);
        echo $popup;
    }
}
