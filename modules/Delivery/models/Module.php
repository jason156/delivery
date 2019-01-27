<?php

class Delivery_Module_Model extends Vtiger_Module_Model {

    public function getSettingLinks()
    {
        $settingLinks = parent::getSettingLinks();
        $settingLinks[] = [
            'linktype'  => 'LISTVIEWSETTINGS',
            'linklabel' => 'LBL_DELIVERY_SETTINGS',
            'linkurl'   => 'index.php?parent=Settings&module=Delivery&view=Index'
        ];

        return $settingLinks;
    }

    public function isQuickCreateSupported()
    {
        return false;
    }

    public function isSummaryViewSupported()
    {
        return false;
    }

    public function isDeletable()
    {
        return false;
    }

    public function isPermitted($actionName) {
        if ($actionName === 'EditView') {
            return false;
        }
        return Users_Privileges_Model::isPermitted($this->getName(), $actionName);
    }

    /**
     * Save
     *
     * @param arr $data
     *
     * @return arr
     */
    public function saveDeliveryInfo($data)
    {
        $current_user = vglobal('current_user');
        if (isset($current_user) && !empty($current_user)) {
            $ownerid = $current_user->id;
        } else {
            $ownerid = 1;
        }

        $map = [
            'trackid' => 'trackid',
            'service' => 'service',
            'status'  => 'status',
            'matter'  => 'matter',
            'dst'     => 'dst',
            'cost'    => 'cost',
            'shopid'  => 'shopid',
            'salesorderid' => 'orderid',
        ];
        $moduleName = 'Delivery';
        // $moduleModel = Vtiger_Module_Model::getInstance($moduleName);
        $record = Vtiger_Record_Model::getCleanInstance($moduleName);
        $record->set('mode', '');
        foreach ($map as $crm => $svc) {
            $record->set($crm, $data[$svc]);
        }
        $record->set('assigned_user_id', $ownerid);
        $record->save();

        return $record->getData();
    }

    /**
     * Peshkariki specific
     *
     * @param int $id   crm delivery id
     * @param arr $data updates
     *   status
     *   dst?? routes
     *   delivery_price
     *   courier fio, phon
     *
     * @return arr
     */
    public function updateDelivery($id, $data)
    {
        $current_user = vglobal('current_user');
        if (isset($current_user) && !empty($current_user)) {
            $ownerid = $current_user->id;
        } else {
            $ownerid = 1;
        }

        $moduleName = 'Delivery';
        // $moduleModel = Vtiger_Module_Model::getInstance($moduleName);
        $record = Vtiger_Record_Model::getInstanceById($id, $moduleName);
        $record->set('mode', 'edit');
        $record->set('status', $data['status']);
        $record->set('dst',    $data['routes'][1]['address']);
        $record->set('cost',   $data['delivery_price']);
        if (!empty($data['courier'])) {
            $record->set('couriername', $data['courier']['fio']);
            $record->set('courierphone', $data['courier']['phone']);
        }
        $record->save();

        return $record->getData();
    }

    /**
     * Find unfinished, only delivery data
     *
     * @param int $soId salesorder crm id
     *
     * return bool | arr delivery data
     */
    public function getStatus($soId)
    {
        $db = PearDatabase::getInstance();
        $sql = 'SELECT * FROM vtiger_delivery
                WHERE salesorderid = ?
                    AND status NOT REGEXP \'(Отменен|Завершен)\'
                ORDER BY trackid DESC LIMIT 1';
        $result = $db->pquery($sql, [$soId]);

        return $db->num_rows($result)
            ? $db->fetchByAssoc($result)
            : false;
    }

    /**
     * Find unfinished
     * with crm data
     */
    public static function byOrder($soId)
    {
        $db = PearDatabase::getInstance();
        $sql = "SELECT * FROM vtiger_delivery vd
            LEFT JOIN vtiger_crmentity ON deliveryid = crmid
            WHERE salesorderid = ?
                AND vd.status NOT REGEXP '(Отменен|Завершен|Выполн)'
            ORDER BY trackid";
        $result = $db->pquery($sql, [$soId]);

        return $db->num_rows($result)
            ? $db->fetchByAssoc($result)
            : false;
    }

    /**
     * Find all pending deliveries for service
     *
     * @param str $svc       service label
     * @param arr $finStatus array of finished statuses to exclude
     *
     * @return arr of tracked ids
     */
    public function getPending($svc = false, $finStatus = [])
    {
        $args = [];
        $where = [
            'deleted = 0'
        ];

        if ($svc) {
            $where[] = 'service = ?';
            $args[] = $svc;
        }

        $where[] = 'vd.status NOT REGEXP ?';
        if (empty($finStatus)) {
            $args[] = '(Завершен)';
        } else {
            $args[] = '(' . implode('|', $finStatus) . ')';
        }

        $db = PearDatabase::getInstance();
        $db->database->SetFetchMode(2);
        $sql = "SELECT crmid, trackid, salesorderid, vd.status
            FROM vtiger_delivery vd
            LEFT JOIN vtiger_crmentity ON deliveryid = crmid
            WHERE " . implode(' AND ', $where)
            . " ORDER BY trackid";
        $result = $db->pquery($sql, $args);

        return $db->num_rows($result)
            ? $db->toArray($result)
            : false;
    }
}
