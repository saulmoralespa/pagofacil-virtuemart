<?php
/**
 * @version $Id: getamazon.php 9292 2016-09-19 08:07:15Z Milbo $
 *
 * @author Valérie Isaksen
 * @package VirtueMart
 * @copyright Copyright (c) 2004 - 2012 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */
defined('JPATH_BASE') or die();


jimport('joomla.form.formfield');

class JFormFieldGetPagofacil extends JFormField {

    /**
     * Element name
     *
     * @access    protected
     * @var        string
     */
    var $type = 'getPagofacil';

    protected function getInput() {


        $notify = JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component';

        $url = 'https://pagofacil.net/';
        $logo = '<p><img src="'.JURI::root () .'media/images/stories/virtuemart/payment/pagofacil.png" /></p>';
        $html = '<p><a target="_blank" href="' . $url . '"  >' . $logo . '</a></p>';
        $html .= '<p>'.vmText::_('VMPAYMENT_PAGOFACIL_ADD_WEEHOOK').'</p>';
        $html .= '<span>'.$notify.'</span>';
        return $html;
    }

}