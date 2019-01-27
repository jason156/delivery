<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Settings_Delivery_Index_View extends Settings_Vtiger_Index_View {

	public function process(Vtiger_Request $request) {
		$qualifiedModuleName = $request->getModule(false);
        $sysinf = [];

        $mem = file('/proc/meminfo')[0];
        $sysinf = [
            'directory'  => __DIR__,
            'phpUser'    => get_current_user(),
            'phpVersion' => phpversion(),
            'memorySys'  => str_replace('MemTotal: ', '', $mem),
            'memoryLmt'  => ini_get('memory_limit'),
            'memoryUsg'  => sprintf('%.2fkb', memory_get_usage()/1024.0),
            'memoryPeak' => sprintf('%.2fkb', memory_get_peak_usage(true)/1024.0),
            'maxVars'    => ini_get('max_input_vars'),
            'execTime'   => ini_get('max_execution_time'),
            'inputTime'  => ini_get('max_input_time'),
            'maxPOST'    => ini_get('post_max_size'),
            'upload'     => ini_get('upload_max_filesize'),
            'display_errors'  => ini_get('display_errors')
        ];
		$viewer = $this->getViewer($request);
        $viewer->assign('STATS', $sysinf);
		$viewer->assign('QUALIFIED_MODULE_NAME', $qualifiedModuleName);

		$viewer->view('Index.tpl', $qualifiedModuleName);
	}
}
