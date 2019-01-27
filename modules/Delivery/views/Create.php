<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

class Delivery_Create_View extends Vtiger_PopupAjax_View {

    function process (Vtiger_Request $request)
    {
        $mod = $request->getModule();
        $rec = $request->get('record');

        $deModule = Vtiger_Module_Model::getInstance($mod);
        $soRecord = Vtiger_Record_Model::getInstanceById($rec, 'SalesOrder');
        $fields = [];
        $data = [];

        $dateField      = 'cf_650';
        $intervalField  = 'cf_652';
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

        $oCity = $soRecord->get($toCity);
        $oAddr = $soRecord->get($toAddr);
        $validAddr = '';
        if ($oCity && (strpos($oAddr, $oCity) === 0)){
            $validAddr = $oAddr;
        } else {
            $validAddr = "{$oCity}, {$oAddr}";
        }
        $data = [
            'LBL_DST'    => $validAddr,
            'timestart'  => $toTimeStart,
            'timeend'    => $toTimeEnd,
            'person'     => $soRecord->get($toPerson),
            'phone'      => $soRecord->get($toPhone),
            'note'       => $soRecord->get($addrNote)
        ];

        if (in_array($soRecord->get('cf_648'), ['Курьеру', 'Наёмному курьеру'])) {
            $data['taking'] = $soRecord->get($toTaking);
        }

        $svcList = [
            ['label' => 'Dostavista', 'id' => 'dv'],
            ['label' => 'Пешкарики',  'id' => 'pk'],
            ['label' => 'Test / Bringo',     'id' => 'br'],
        ];

        $viewer = $this->getViewer($request);
        $viewer->assign('SVCLIST', $svcList);
        $viewer->assign('ORDER', $data);
        echo $viewer->view('Create.tpl', $mod, true);
    }

}
