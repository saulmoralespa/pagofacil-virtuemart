<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 12/07/18
 * Time: 02:26 PM
 */

defined('_JEXEC') or die('Restricted access');
if (!class_exists('vmPSPlugin')) {
    require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

class plgVmpaymentPagofacil extends vmPSPlugin
{
    function __construct (&$subject, $config) {

        //if (self::$_this)
        //   return self::$_this;
        parent::__construct ($subject, $config);
        $this->_loggable = TRUE;
        $this->_debug = TRUE;
        $this->tableFields = array_keys ($this->getTableSQLFields ());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = array('id_sucursal'  	=> array('', 'char'),
            'id_usuario'      	=> array('', 'char'),
            'id_sucursal_test'  	=> array('', 'char'),
            'id_usuario_test'      	=> array('', 'char'),
            'enviroment'  				=> array(0, 'int'),
            'min_amount'                => array('', 'int'),
            'max_amount'             	=> array('', 'int'),
            'payment_logos'          	=> array('', 'char'),
            'tax_id'					=> array(0, 'int'));

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

        //self::$_this = $this;
    }

    public function getVmPluginCreateTableSQL () {

        return $this->createTableSQL ('Payment pago facil');
    }

    function getTableSQLFields() {

        $SQLfields = array(
            'id'                                     => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'                    => 'int(1) UNSIGNED',
            'order_number'                           => 'char(64)',
            'virtuemart_paymentmethod_id'            => 'mediumint(1) UNSIGNED',
            'payment_name'                           => 'varchar(5000)',
            'payment_order_total'                    => 'decimal(15,5) NOT NULL',
            'payment_currency'                       => 'smallint(1)',
            'email_currency'                         => 'smallint(1)',
            'cost_per_transaction'                   => 'decimal(10,2)',
            'cost_percent_total'                     => 'decimal(10,2)',
            'tax_id'                                 => 'smallint(1)');
        return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order){

        if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement ($method->payment_element)) {
            return FALSE;
        }

        $session = JFactory::getSession ();

        if (!class_exists ('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        if (!class_exists ('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        }

        $address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

        if (!class_exists ('TableVendors')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
        }
        $vendorModel = VmModel::getModel ('Vendor');
        $vendorModel->setId (1);
        $vendor = $vendorModel->getVendor ();
        $vendorModel->addImages ($vendor, 1);
        $this->getPaymentCurrency($method);
        $email_currency = $this->getEmailCurrency($this->_currentMethod);
        $currency_code_3 = shopFunctions::getCurrencyByID ($method->payment_currency, 'currency_code_3');

        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$method->payment_currency);
        if ($totalInPaymentCurrency['value'] <= 0) {
            vmInfo (JText::_ ('VMPAYMENT_PAGOFACIL_PAYMENT_AMOUNT_INCORRECT'));
            return FALSE;
        }
        $merchant_email = $order['details']['BT']->email;
        if (empty($merchant_email)) {
            vmInfo (JText::_ ('VMPAYMENT_PAGOFACIL_MERCHANT_EMAIL_NOT_SET'));
            return FALSE;
        }
        $quantity = 0;
        foreach ($cart->products as $key => $product) {
            $quantity = $quantity + $product->quantity;
            $nameproduct = $product->product_name;
        }
        if (count($cart->products) > 1) {
            $nameproduct .= ', etc';
        }


        $iva = floatval($order['details']['BT']->order_billTaxAmount);
        $baseDevolucionIva = $order['details']['BT']->order_subtotal;
        if($iva == 0)
        {
            $baseDevolucionIva = 0;
        }

        $id_sucursal = $method->id_sucursal;
        $id_usuario  = $method->id_usuario;
        if ($method->enviroment){
            $id_sucursal = $method->id_sucursal_test;
            $id_usuario  = $method->id_usuario_test;
        }


        $orderstatusurl = JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . vRequest::getInt('Itemid');

        $order_number = $order['details']['BT']->order_number;
        $price_total = $order['details']['BT']->order_total;
        $url_post_pay_card = $this->getUrlCard($method);
        $ip_connect = $this->getIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $email_customer = $order['details']['BT']->email;


        // Prepare data that should be stored in the database
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->_renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $method->payment_currency;
        $dbValues['email_currency'] = $email_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($dbValues);

        $html = "<form id='pagofacil' action='".$url_post_pay_card."' method='post'>
<input type='hidden' name='email' value='".$email_customer."'>
<input type='hidden' name='nombre' value='".$address->first_name."' >
<input type='hidden' name='apellidos' value='".$address->last_name."' >
<input type='hidden' name='telefono' value='".$address->phone_1."' >
<input type='hidden' name='calleyNumero' value='".$address->address_1."'>
<input type='hidden' name='municipio' value='".$address->city."'>
<input type='hidden' name='cp' value='".$address->zip."'>
<input type='hidden' value='".trim($id_sucursal)."' name='idSucursal'>
<input type='hidden' value='".trim($id_usuario)."' name='idUsuario'>
<input type='hidden' value='1' name='idServicio'>
<input type='hidden' value='".$order_number."' name='idPedido'>
<input type='hidden' value='".$price_total."' name='monto'>
<input type='hidden' name='redireccion' value='1'>
<input type='hidden' name='urlResponse' value='".$orderstatusurl."'>
<input type='hidden' name='ip' value='".$ip_connect."'>
<input type='hidden' name='httpUserAgent' value='".$user_agent."'>
        </form>";
        $js = "<script>
(function() {
    document.getElementById('pagofacil').submit();
})();
</script>";

        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        JRequest::setVar ('html', $html.$js);
    }

    /**
     * @param $html
     * @return bool|null|string
     */
    function plgVmOnPaymentResponseReceived(&$html) {


        $virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
        $order_number = vRequest::getVar('on', 0);  //?

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        if (!class_exists('VirtueMartCart'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        if (!class_exists('shopFunctionsF'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        // setup response html
        vmLanguage::loadJLang('com_virtuemart');
        $modelOrder = VmModel::getModel('orders');
        $payment_name = $this->_renderPluginName($method);


        $res = vRequest::getRequest();


        if (!isset($res['data']['idPedido'])) {
            return;
        }

        $item = $res['Itemid'];

        $aut = explode('?', $item);

        parse_str($aut[1], $aut);

        $aut = $aut['autorizado'];

        $order_number = $res['data']['idPedido'];
        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);


        if (!$virtuemart_order_id) {
            return;
        }

        $orderModel = VmModel::getModel('orders');
        $order = $modelOrder->getOrder($virtuemart_order_id);
        $order_history = $this->changeStatusPayment($res['data']['idPedido'], $aut);

        $orderModel->updateStatusForOneOrder($virtuemart_order_id, $order_history, TRUE);

        $html = $this->_getPaymentResponseHtml($res, $payment_name);
        $link=	JRoute::_("index.php?option=com_virtuemart&view=orders&layout=details&order_number=".$order['details']['BT']->order_number."&order_pass=".$order['details']['BT']->order_pass, false) ;
        $html .='<br />
        		<a class="vm-button-correct" href="'.$link.'">'.vmText::_('VIRTUEMART_PAGOFACIL_ORDER_VIEW_ORDER').'</a>';

        //We delete the old stuff
        // get the correct cart / session
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        return true;
    }

    function plgVmOnPaymentNotification() {
        if (!class_exists ('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }


        $mb_data = vRequest::getRequest();

        //acces get data

        $res = urldecode($mb_data);

        if (!isset($res['data']['idPedido'])) {
            return;
        }

        $order_number = $res['data']['idPedido'];

        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);

        if (!$virtuemart_order_id) {
            return;
        }

        $orderModel = VmModel::getModel('orders');

        $order_history = $this->changeStatusPayment($res['data']['idPedido'], $res['autorizado']);

        $orderModel->updateStatusForOneOrder($virtuemart_order_id, $order_history, TRUE);

    }


    function changeStatusPayment($order_number, $codeStatus){


        $order_history = array();
        $order_history['customer_notified'] = 1;

        if (!($payments = $this->getDatasByOrderNumber($order_number))) {
            return FALSE;
        }

        $method = $this->getVmPluginMethod($payments[0]->virtuemart_paymentmethod_id);

        switch ((int)$codeStatus) {
            case 1:
                $order_history['order_status'] = 'C';
                $order_history['comments'] = vmText::sprintf('VMPAYMENT_PAGOFACIL_PAYMENT_STATUS_CONFIRMED', $order_number);
                break;
            case 0:
                $order_history['order_status'] = 'R';
                $order_history['comments'] = vmText::sprintf('VMPAYMENT_PAGOFACIL_PAYMENT_STATUS_DENIED', $order_number);
        }

        return $order_history;
    }

    function getUrlCard($method){

        if ($method->enviroment){
            return 'https://sandbox.pagofacil.tech/Payform';
        }else{
            return 'https://api.pagofacil.tech/Payform';
        }
    }


    function getUrlCash($method){

        $enviroment = (bool)$method->enviroment;
        if ($enviroment)
            return 'https://sandbox.pagofacil.tech/cash/charge';
        return 'https://api.pagofacil.tech/cash/charge';
    }


    function getIP(){
        return ($_SERVER['REMOTE_ADDR'] == '::1' || $_SERVER['REMOTE_ADDR'] == '::' ||
            !preg_match('/^((?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9]).){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])$/m',
                $_SERVER['REMOTE_ADDR'])) ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];
    }




    /**
     * @param   int $virtuemart_order_id
     * @param string $order_number
     * @return mixed|string
     */
    private function _getPaypalInternalData($virtuemart_order_id, $order_number = '') {
        if (empty($order_number)) {
            $orderModel = VmModel::getModel('orders');
            $order_number = $orderModel->getOrderNumber($virtuemart_order_id);
        }
        $db = JFactory::getDBO();
        $q = 'SELECT * FROM `' . $this->_tablename . '` WHERE ';
        $q .= " `order_number` = '" . $order_number . "'";

        $db->setQuery($q);
        if (!($payments = $db->loadObjectList())) {
            // JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }
        return $payments;
    }

    protected function _getPaymentResponseHtml($tcoTable, $payment_name){
        $html = '<table>' . "\n";
        $html .= $this->getHtmlRow('VMPAYMENT_PAGOFACIL_PAYMENT_NAME', $payment_name);
        if (!empty($tcoTable)) {
            $html .= $this->getHtmlRow('VMPAYMENT_PAGOFACIL_ORDER_NUMBER', $tcoTable['data']['idPedido']);
            if(isset($tcoTable['idTransaccion'])){
                $html .= $this->getHtmlRow('VMPAYMENT_PAGOFACIL_ORDER_ID_TRANSACCION', $tcoTable['idTransaccion']);
            }
        }
        $html .= '</table>' . "\n";

        return $html;
    }

    protected function _renderPluginName($activeMethod) {
        $return = '';
        $plugin_name = $this->_psType . '_name';
        $plugin_desc = $this->_psType . '_desc';
        $description = '';
        // 		$params = new JParameter($plugin->$plugin_params);
        // 		$logo = $params->get($this->_psType . '_logos');
        $logosFieldName = $this->_psType . '_logos';
        $logos = $activeMethod->$logosFieldName;
        if (!empty($logos)) {
            $return = $this->displayLogos($logos) . ' ';
        }
        $pluginName = $return . '<span class="' . $this->_type . '_name">' . $activeMethod->$plugin_name . '</span>';
        if ($activeMethod->sandbox) {
            $pluginName .= ' <span style="color:red;font-weight:bold">Sandbox (' . $activeMethod->virtuemart_paymentmethod_id . ')</span>';
        }
        if (!empty($activeMethod->$plugin_desc)) {
            $pluginName .= '<span class="' . $this->_type . '_description">' . $activeMethod->$plugin_desc . '</span>';
        }
        return $pluginName;
    }

    function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

        if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
            return NULL;
        }
        if (!$this->selectedThisElement ($method->payment_element)) {
            return FALSE;
        }
        $this->getPaymentCurrency ($method);
        $paymentCurrencyId = $method->payment_currency;
    }


    function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        /*if (!($payments = $this->_getPaypalInternalData($virtuemart_order_id))) {
            // JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }*/
        $emailCurrencyId = $this -> getEmailCurrency($method);
        return $emailCurrencyId;

    }

    function getEmailCurrency (&$method) {

        if(empty($method->email_currency) or $method->email_currency == 'vendor'){
            $vendor_model = VmModel::getModel('vendor');
            $vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
            $emailCurrencyId = $vendor->vendor_currency;
        } else if($method->email_currency == 'payment'){
            $emailCurrencyId = $this->getPaymentCurrency($method);
        }
        else if($method->email_currency == 'user'){

        }
        return $emailCurrencyId;
    }




    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) {

        if (!$this->selectedThisByMethodId ($payment_method_id)) {
            return NULL; // Another method was selected, do nothing
        }

        if (!($payments = $this->_getPaypalInternalData($virtuemart_order_id))) {
            // JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }

        $html = '<table class="adminlist">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $first = TRUE;
        foreach ($payments as $payment) {
            $html .= '<tr class="row1"><td>' . JText::_ ('VMPAYMENT_PAGOFACIL_DATE') . '</td><td align="left">' . $payment->created_on . '</td></tr>';
            // Now only the first entry has this data when creating the order
            if ($first) {
                $html .= $this->getHtmlRowBE ('COM_VIRTUEMART_PAYMENT_NAME', $payment->payment_name);
                // keep that test to have it backwards compatible. Old version was deleting that column  when receiving an IPN notification
                if ($payment->payment_order_total and  $payment->payment_order_total != 0.00) {
                    $html .= $this->getHtmlRowBE ('COM_VIRTUEMART_TOTAL', $payment->payment_order_total . " " . shopFunctions::getCurrencyByID ($payment->payment_currency, 'currency_code_3'));
                }
                if ($payment->email_currency and  $payment->email_currency != 0) {
                    $html .= $this->getHtmlRowBE ('VMPAYMENT_PAGOFACIL_EMAIL_CURRENCY', shopFunctions::getCurrencyByID ($payment->email_currency, 'currency_code_3'));
                }
                $first = FALSE;
            }
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    protected function checkConditions ($cart, $method, $cart_prices) {

        $this->convert ($method);

        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
            OR
            ($method->min_amount <= $amount AND ($method->max_amount == 0)));

        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array ($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }
        // probably did not gave his BT:ST address
        if (!is_array ($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (in_array ($address['virtuemart_country_id'], $countries) || count ($countries) == 0) {
            if ($amount_cond) {
                return TRUE;
            }
        }

        return FALSE;
    }




    function convert($method) {

        $method->min_amount = (float)$method->min_amount;
        $method->max_amount = (float)$method->max_amount;
    }

    function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

        return $this->onStoreInstallPluginTable ($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {

        return $this->OnSelectCheck ($cart);
    }

    public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

        return $this->displayListFE ($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

        return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

        return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
    }

    public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

        $this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

        return $this->onShowOrderPrint ($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPaymentVM3( &$data) {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

        return $this->setOnTablePluginParams ($name, $id, $table);
    }

}