<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once('modules/Vtiger/CRMEntity.php');

class Delivery extends Vtiger_CRMEntity {
    var $db, $log;
    var $modName = 'Delivery';
    //var $IsCustomModule = true;
    var $table_name = 'vtiger_delivery';
    var $table_index= 'deliveryid';

    var $tab_name = [
        'vtiger_crmentity',
        'vtiger_delivery'
    ];

    var $tab_name_index = [
        'vtiger_crmentity'  => 'crmid',
        'vtiger_delivery'   => 'deliveryid'
    ];

    var $list_fields = [
        'LBL_TRACKID'   => ['delivery', 'trackid'],
        'LBL_STATUS'    => ['delivery', 'status'],
        'LBL_MATTER'    => ['delivery', 'matter'],
        'LBL_OWNER'     => ['crmentity','smownerid']
    ];
    var $list_fields_name = [
        'LBL_TRACKID'   => 'trackid',
        'LBL_STATUS'    => 'status',
        'LBL_MATTER'    => 'matter',
        'LBL_OWNER'     => 'assigned_user_id'
    ];

    var $list_link_field = 'trackid';
    var $search_fields = [];
    var $search_fields_name = [];

    var $popup_fields = ['trackid', 'status'];

    var $def_basicsearch_col = 'trackid';

    var $def_detailview_recname = 'trackid';

    var $mandatory_fields = ['trackid', 'assigned_user_id'];

    var $default_order_by = 'trackid';
    var $default_sort_order='ASC';

    function Delivery() {
        $this->db  = PearDatabase::getInstance();
        $this->column_fields = getColumnFields($this->modName);
    }

    /**
     * Invoked when special actions are performed on the module.
     * @param String Module name
     * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
     */
    function vtlib_handler($modulename, $event_type) {
        if($event_type == 'module.postinstall') {
            $DeliveryMod = Vtiger_Module::getInstance($this->modName);
            $DeliveryMod->initWebservice();
            $orders = Vtiger_Module::getInstance('SalesOrder');
            $orders->setRelatedList(
                $DeliveryMod,
                $this->modName,
                ['SELECT'],
                'get_dependents_list'
            );

            $this->addDeliveryLinks();
            //TODO add update status links to Delivery Details

            $this->enableModTracker($this->modName);

            $this->checkCourier();

            $DeliveryMod->setDefaultSharing();
        } else if($event_type == 'module.disabled') {
            $this->delDeliveryLinks();

        } else if($event_type == 'module.enabled') {
            $this->addDeliveryLinks();
            
        } else if($event_type == 'module.postupdate') {
        }

    }

    /**
     * Enable ModTracker for the module
     */
    public function enableModTracker($moduleName)
    {
        include_once 'vtlib/Vtiger/Module.php';
        include_once 'modules/ModTracker/ModTracker.php';
            
        $moduleInstance = Vtiger_Module::getInstance($moduleName);
        ModTracker::enableTrackingForModule($moduleInstance->getId());
    }

    public function checkCourier($field='cf_656')
    {
        $targetValue = 'Курьер DostaVista';
        $sql = "SELECT * FROM vtiger_{$field} WHERE {$field}='{$targetValue}'";

        $result = $this->db->pquery($sql,[]);
        if ($this->db->num_rows($result) == 1) return;

        $newValue     = $targetValue;
        $pickListName = $field;
        $moduleName   = 'SalesOrder';

        /*
        include_once 'vtlib/Vtiger/Utils.php';
        
        if (!class_exists('Settings_Picklist_Module_Model', false)){
            $log->debug('picklist model not exist, including');
            include_once 'modules/Settings/Picklist/models/Module.php';
        }
        if (!class_exists('Settings_Picklist_Field_Model', false)){
            $log->debug('picklist field not exist, including');
            include_once 'modules/Settings/Picklist/models/Field.php';
        }
        */
        $moduleModel = Settings_Picklist_Module_Model::getInstance($moduleName);
        $fieldModel  = Settings_Picklist_Field_Model::getInstance($pickListName, $moduleModel);
        /*
        $roleRecordList = Settings_Roles_Record_Model::getAll();
                foreach($roleRecordList as $roleRecord) {
                    $rolesSelected[] = $roleRecord->getId();
                }
        */
        $rolesSelected = ['H1','H2','H3','H4','H5','H6','H7','H8','H9']; //'all'

        $id = $moduleModel->addPickListValues($fieldModel, $newValue, $rolesSelected);

        return $id['id'];
    }

    public function addDeliveryLinks(){
        Vtiger_Link::addLink(
            0, 
            'HEADERSCRIPT', 
            'DostaVista',
            'modules/Delivery/resources/Delivery.js'
        );

        $SalesOrderMod = Vtiger_Module::getInstance('SalesOrder');
        $SalesOrderMod->addLink(
            'DETAILVIEWBASIC',  //type
            'DostaVista', //label
            'deliveryLink' //url, lets use this as ID
        );
    }

    public function delDeliveryLinks(){
        Vtiger_Link::deleteLink(
            0,
            'HEADERSCRIPT',
            'DostaVista',
            'modules/Delivery/resources/Delivery.js'
        );

        $SalesOrderMod = Vtiger_Module::getInstance('SalesOrder');
        $SalesOrderMod->deleteLink('DETAILVIEWBASIC', $this->modName);

    }

    /**
     * Handle getting related list information.
     * NOTE: This function has been added to CRMEntity (base class).
     * You can override the behavior by re-defining it here.
     */
    //function get_related_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

    /**
     * Handle getting dependents list information.
     * NOTE: This function has been added to CRMEntity (base class).
     * You can override the behavior by re-defining it here.
     */
    //function get_dependents_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

}
