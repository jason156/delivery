<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Delivery_Choose_View extends Vtiger_PopupAjax_View {

    public function process(Vtiger_Request $request) {
        //$viewer = $this->getViewer($request);
        $moduleName = $request->getModule();
        $soId       = $request->get('record');
        $db = PearDatabase::getInstance();
        $sql = "SELECT trackid FROM vtiger_delivery vd
            LEFT JOIN vtiger_crmentity ON crmid = deliveryid
            WHERE salesorderid = ?
                AND deleted = 0
                AND vd.status NOT REGEXP '(Отменен|Завершен)'
            LIMIT 1";
        $result = $db->pquery($sql, [$soId]);

        $nr = $db->num_rows($result);

        if ($nr == 0){
            $deliveryView = new Delivery_Create_View();
        } else {
            $deliveryView = new Delivery_Status_View();
        }

        $deliveryView->process($request);
    }
}

