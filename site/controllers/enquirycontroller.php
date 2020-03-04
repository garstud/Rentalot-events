<?php
/********************************************************************
Product		: Rentalot Plus
Date		: 21 June 2019
Copyright	: Les Arbres Design 2009-2019
Contact		: http://www.lesarbresdesign.info
Licence		: GNU General Public License
*********************************************************************/
defined('_JEXEC') or die('Restricted Access');
require_once JPATH_ADMINISTRATOR.'/components/com_rentalotplus/helpers/email_helper.php';

//GW : adding classes to be used !
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;

class RentalotplusControllerEnquiry extends JControllerLegacy
{
function __construct()
	{
	parent::__construct();
	$this->jinput = JFactory::getApplication()->input;
	}

// -------------------------------------------------------------------------------
// Show the Enquiry View
//
function display($cachable = false, $urlparams = false)
{
	$this->addModelPath(JPATH_ADMINISTRATOR.'/components/com_rentalotplus/models');
	$attribute_model = $this->getModel('attribute');
	$enq_model = $this->getModel('enquiry');
	$currency_model = $this->getModel('currency');
	$leadsource_model = $this->getModel('leadsource');
	$config_model = $this->getModel('config');
	$unit_model = $this->getModel('unit');
	$extra_model = $this->getModel('extra');
	$config_data = $config_model->getData(CONFIG_CATEGORY_GENERAL.','.CONFIG_CATEGORY_ENQUIRY.','.CONFIG_CATEGORY_CLASSIC.','.CONFIG_CATEGORY_SELFBOOK);		
	$leadsource_list = $leadsource_model->getLeadsourceArray('', JText::_('COM_RENTALOTPLUS_SELECT'));

	if (empty($config_data->email_to))
		{
		echo '<div class="rp_page">'.JText::_('COM_RENTALOTPLUS_WARNING_ADMIN_EMAIL').'</div>';
		return;
		}

// Get the context and the currency information

	$context = LARP_Util::get_context();
	$base_currency  = $currency_model->getBaseCurrencyCode();
	$currency_list = $currency_model->getCurrencyArray(1);
	$current_currency_row = $currency_model->getCurrencyByCode($context['currency']);

// Initialise stuff

	$errorMessages = array();
	$post_data = $enq_model->getPostData($config_data);

// Initialise the name and email with the logged in User

	$user_info = JFactory::getUser();
	if (!$user_info->guest)
		{
		$post_data->name = $user_info->name;
		$post_data->email = $user_info->email;
		}
	
// Show the enquiry view

	$view = $this->getView('enquiry', 'html');
	$view->setModel($unit_model, true);
	$view->setModel($extra_model);
	$view->setModel($config_model);
	$view->setModel($attribute_model);
	$view->config_data = $config_data;
	$view->errormessages = $errorMessages;
	$view->post_data = $post_data;
	$view->leadsource_list = $leadsource_list;
	$view->unit_list = $unit_model->getUnitArray('', 1); // published units only
	$view->base_currency = $base_currency;
	$view->currency_list = $currency_list;
	$view->current_currency_row = $current_currency_row;
	$view->context = $context;
	$view->display();
}

// -------------------------------------------------------------------------------
// Receive input from the enquiry screen
// Validate the input and either send the email or re-display the enquiry screen
//
function ajax_submit()
{
	$task = $this->jinput->get('task', '', 'STRING');	// 'form_submit' or 'ajax_submit'
	$this->addModelPath(JPATH_ADMINISTRATOR.'/components/com_rentalotplus/models');
	$attribute_model = $this->getModel('attribute');
	$enq_model = $this->getModel('enquiry');
	$currency_model = $this->getModel('currency');
	$unit_model = $this->getModel('unit');
	$leadsource_model = $this->getModel('leadsource');
	$config_model = $this->getmodel('config');
	$extra_model = $this->getModel('extra');
	$log_model = $this->getModel('log');
	$config_data = $config_model->getData(CONFIG_CATEGORY_GENERAL.','.CONFIG_CATEGORY_ENQUIRY.','.CONFIG_CATEGORY_CLASSIC.','.CONFIG_CATEGORY_SELFBOOK);		
	$leadsource_list = $leadsource_model->getLeadsourceArray('', JText::_('COM_RENTALOTPLUS_SELECT'));
	$num_leadsource = count($leadsource_list);

// Get the context and the currency information

	$context = LARP_Util::get_context();
	$base_currency  = $currency_model->getBaseCurrencyCode();
	$currency_list = $currency_model->getCurrencyArray(1);
	$current_currency_row = $currency_model->getCurrencyByCode($context['currency']);

// Validate the data
// If not valid, re-display with error messages

	$errorMessages = array();
	$post_data = $enq_model->getPostData($config_data);
	$unit_data = $unit_model->getOne($context['unit_id']);
	$check = $enq_model->check_data($errorMessages, $leadsource_list, $config_data, $unit_data);

	if (!$check)
		{
		LARP_trace::trace("Enquiry check failed ".print_r($errorMessages,true));	
		$view = $this->getView('enquiry','html');	
    	$view->setModel($attribute_model);
		$view->setModel($unit_model, true);
		$view->setModel($extra_model);
		$view->setModel($config_model);
		$view->errormessages = $errorMessages;
		$view->leadsource_list = $leadsource_list;
		$view->config_data = $config_data;
		$view->post_data = $post_data;
		$view->unit_list = $unit_model->getUnitArray('', 1); // published units only
		$view->base_currency = $base_currency;
		$view->currency_list = $currency_list;
		$view->current_currency_row = $current_currency_row;
		$view->context = $context;
		echo $view->draw_form();			// only generate the form, not the whole page
		return;	
		}

// on multi-language sites, save the user's language in the enquiry record

    $languages = LARP_Util::get_site_languages();
    $num_languages = count($languages);
    if ($num_languages > 1)
		{
		$lang_obj = JFactory::getLanguage();
		$language_code = $lang_obj->get('tag');
		$enq_model->_data->language = $language_code;	// save the user's language
		}

// store the enquiry in the enquiry table

	$enq_model->_data->id = 0;                  // create a new record
	$enq_model->store();
	
// build a dummy booking record for the email merge
// We need the unit name

	$booking_model = $this->getModel('booking');	// use the backend booking model
	$booking_model->initData();
	$booking_model->_data->date_from = $context['datefrom_yyyy_mm_dd'];
	$booking_model->_data->date_to = $context['dateto_yyyy_mm_dd'];
	$booking_model->_data->salutation = $post_data->salutation;
	$booking_model->_data->forenames = $post_data->forenames;
	$booking_model->_data->surname = $post_data->surname;
	$booking_model->_data->email = $post_data->email;
	$booking_model->_data->unit_name = $unit_data->unit_name;
	$booking_model->_data->leadsource_name = $leadsource_list[$post_data->leadsource_id];
	$booking_model->_data->comments = $post_data->comments;
    $booking_model->_data->udf1 = $post_data->udf1;
    $booking_model->_data->udf2 = $post_data->udf2;
    $booking_model->_data->udf3 = $post_data->udf3;
    $booking_model->_data->udf4 = $post_data->udf4;
    $booking_model->_data->udf5 = $post_data->udf5;
    $booking_model->_data->udl1 = $post_data->udl1;
    $booking_model->_data->udl1_value = $post_data->udl1_value;
    $booking_model->_data->_combined_comments = self::make_combined_comments($post_data, $config_data);
	
// merge the admin enquiry email

	$template = $config_model->getOne('TEMPLATE_ENQUIRY_ADMIN', 'name');		// need the subject and the text
	$date_format = $config_model->getData('date_format_email');
	$email_subject = LARP_email::email_merge($booking_model->_data, $template->subject, $date_format);
	$email_body = LARP_email::email_merge($booking_model->_data, $template->value, $date_format);

// setup the email to the admin user

	$email_info = array();
	$email_info['email_to'] = $config_model->getData('email_to');	// admin to
	$email_info['reply_to'] = $post_data->email;					// client
	$email_info['email_cc'] = $config_model->getData('email_cc');	// admin cc
	$email_info['subject'] = $email_subject;
	$email_info['body'] = $email_body;
    $log_title = $email_info['email_to'].' - '.$email_info['subject'];
	
// Send the email to the admin

	$message = '';
	if (LARP_email::sendEmail($email_info, $message))
        {
		$log_detail = $email_body.'<br />['.JText::_('COM_RENTALOTPLUS_EMAIL_ACCEPTED').']';
        $log_model->create_new(LARP_LOG_ADMIN_EMAIL_OK, $log_title, $log_detail);
        }
    else
		{
        LARP_trace::trace("Email sending failed: ".$message);
		$log_detail = '<h4 class="lad_error_msg">'.$message.'</h4>'.$email_body;
		$log_model->create_new(LARP_LOG_EMAIL_FAIL, $log_title, $log_detail);
		}
		
// Send the email to the client, if required

    	//GW : moving info client outside the IF block to pass it to the plugin event
    	$email_info_client = array();
	$email_info_client['email_to'] = $post_data->email;			// Client
	$email_info_client['reply_to'] = $config_model->getData('email_to');	// Admin
	$email_info_client['subject'] = $email_subject;
	$email_info_client['body'] = $email_body;
	
	if ( ( ($config_data->enq_show_copy == LARP_COPYME_CHOOSE) && ($post_data->_copyMe == 1) )
	|| ($config_data->enq_show_copy == LARP_COPYME_ALWAYS) )
		{
		$template = $config_model->getOne('TEMPLATE_ENQUIRY_USER', 'name');		// need the subject and the text
		$email_subject = LARP_email::email_merge($booking_model->_data, $template->subject, $date_format);
		$email_body = LARP_email::email_merge($booking_model->_data, $template->value, $date_format);
	        $log_title = $email_info_client['email_to'].' - '.$email_info_client['subject'];
        
		$message = '';

	//GW : replace ... if (LARP_email::sendEmail($email_info_client, $message))
        //GW : PR to get back the state of the email sending, and send it to the trigger event below
        $isSentOk = LARP_email::sendEmail($email_info_client, $message);
        if($isSentOk)         
            {
		$log_detail = $email_body.'<br />['.JText::_('COM_RENTALOTPLUS_EMAIL_ACCEPTED').']';
            	$log_model->create_new(LARP_LOG_CLIENT_EMAIL_OK, $log_title, $log_detail);
            }
        else
	    {
	        LARP_trace::trace("Email sending failed: ".$message);
		$log_detail = '<h4 class="lad_error_msg">'.$message.'</h4>'.$email_body;
            	$log_model->create_new(LARP_LOG_EMAIL_FAIL, $log_title, $log_detail);
	    }		
	}

    //GW : PR to replace the emails sending by plugins events to personnalize the form save behavior
    PluginHelper::importPlugin('rentalot');
    // permits to do something after sending the enquiry mail to visitor
    $app = Factory::getApplication();
    $result = (array) $app->triggerEvent('onEnquiryAfterSentMail', 
            array('com_rentalot.enquiry', $booking_model, $email_info_client)
    );    
	
// Show the confirmation view
	
	$template = $config_model->getData('TEMPLATE_ENQ_ACKNOWLEDGE_TEXT');
	$message = LARP_email::email_merge($booking_model->_data, $template, $date_format);
	echo '<div class="rp_page">'.$message.'</div>';
}

//-------------------------------------------------------------------------------
// Make the combined comments for the %T_B_DETAILS% variable,
// which for historical reasons is the original optional enquiry fields and the comments field
// 
static function make_combined_comments($data, $config_data)
{
	$text = '';
	if (!empty($data->udl1))
		$text .= '<p>'.$config_data->udf_list_prompt.": ".$data->udl1_value."</p>";
	if ($data->udf1 !='')
		$text .= '<p>'.$config_data->udf_prompt1.": ".$data->udf1."</p>";
	if ($data->udf2 !='')
		$text .= '<p>'.$config_data->udf_prompt2.": ".$data->udf2."</p>";
	if ($data->udf3 != '')
		$text .= '<p>'.$config_data->udf_prompt3.": ".$data->udf3."</p>";
	if ($data->udf4 !='')
		$text .= '<p>'.$config_data->udf_prompt4.": ".$data->udf4."</p>";
	if ($data->udf5 !='')
		$text .= '<p>'.$config_data->udf_prompt5.": ".$data->udf5."</p>";
	if ($data->comments != '')
		$text .= '<p>'.$config_data->enq_area_prompt.": ".$data->comments."</p>";
	return $text;
}

//-------------------------------------------------------------------------------
// Serve a captcha image
// The helper will retrieve the details from the session
//
function captcha_image()
{
	$this->addModelPath(JPATH_ADMINISTRATOR.'/components/com_rentalotplus/models');
	$config_model = $this->getModel('config');
	$config_data = $config_model->getData(CONFIG_CATEGORY_ENQUIRY);		
	require_once(JPATH_ADMINISTRATOR.'/components/com_rentalotplus/helpers/secure_captcha.php');
	Secure_captcha::show_image($config_data);
}

}
