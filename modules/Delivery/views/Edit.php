<?php

class Delivery_Edit_View extends Vtiger_Edit_View {

    public function checkPermission(Vtiger_Request $request) {
        throw new AppException(
            vtranslate('LBL_PERMISSION_DENIED', $request->getModule())
        );
    }
}
