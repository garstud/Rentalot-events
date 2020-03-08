<?php
/********************************************************************
Product		: Rentalot Plus
Date		: 20 January 2020
Copyright	: Les Arbres Design 2009-2020
Contact		: http://www.lesarbresdesign.info
Licence		: GNU General Public License
*********************************************************************/
defined('_JEXEC') or die('Restricted Access');

//Hacking : adding classes to be used !
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;

class RentalotplusControllerBooking extends JControllerLegacy
{

function __construct()
{
	parent::__construct();
	$this->registerTask('pay_online','my_booking');
	$this->app = JFactory::getApplication();
	$this->jinput = JFactory::getApplication()->input;
}

// -------------------------------------------------------------------------------
// Show the first booking screen
// if the user's session times out we come back here with $session_error = true
//
function book1($session_error = false)
{
	LARP_trace::trace("Front booking, book1");
	$this->get_common_data();
	$errorMessages = array();
	if ($session_error)
		$errorMessages['top'] = JText::_('COM_RENTALOTPLUS_SESSION_EXPIRED');
	$pricing_message = '';
	$base_price = '';
	$price_rows = array();
	$detail_list = '';

	$post_data = $this->app->getUserState(LARP_COMPONENT."booking", null);  // if we have data in the session, use it
	if (!$post_data)
		$post_data = $this->booking_model->getPostData($this->config_data, $this->extras_list); // if not, initialise it
		
// Initialise email with the logged in User
// - we no longer initialise the name because Joomla only has one name field and we have three. It just didn't work.

	$user_info = JFactory::getUser();
	if (!$user_info->guest)
		$post_data->email = $user_info->email;

	if ($this->availability_code == GA_AVAILABLE)
		{
		$this->backend_booking_model->calculateBookingPrices($this->config_data, $this->unit_data, $this->base_currency, $pricing_message, $price_rows, $this->currency_precision);
		$this->availability_message = $pricing_message;
		$base_price = $this->backend_booking_model->_data->base_price;
		if (($this->availability_code == GA_AVAILABLE) && ($this->config_data->sb_show_price_detail))
			$detail_list = $this->backend_booking_model->base_price_detail($price_rows, $this->config_data->date_format, $this->currency_rate, $this->currency_symbol, $this->currency_format);
		}
	
	$view = $this->getView('book','html');	
	$view->unit_data = $this->unit_data;
	$view->errormessages = $errorMessages;
	$view->unit_list = $this->unit_list;
	$view->currency_list = $this->currency_list;
	$view->setModel($this->config_model);
	$view->config_data = $this->config_data;
	$view->context = $this->context;
	$view->post_data = $post_data;
	$view->leadsource_list = $this->leadsource_list;
	$view->extras_list = $this->extras_list;
	$view->base_price = $base_price;
	$view->currency_symbol = $this->currency_symbol;
	$view->currency_format = $this->currency_format;
	$view->detail_list = $detail_list;
	$view->voucher_count = $this->voucher_count;
	$view->country_codes = $this->country_codes;
	$view->availability_code = $this->availability_code;
	$view->availability_message = $this->availability_message;
	$view->display();
}

// -------------------------------------------------------------------------------
// Receive input from book1 and validate
// - if any errors, re-display book1
// - if all ok, display book2
//
function ajax_book1_submit()
{
	LARP_trace::trace("Front booking, ajax_book1_submit");
    $token = JSession::getFormToken();				    // don't use JSession::checkToken() because it just redirects to the home page
    if (!$this->jinput->get($token, '', 'string'))		// this way is more user friendly
		{
		LARP_trace::trace("Front booking, ajax_book1_submit, session expired");
		echo '...';					// tell the Javascript to submit the form the old-fashioned way
		return;
		}

	$this->get_common_data();		// this also checks availability

	if ($this->availability_code != GA_AVAILABLE)
		{
		LARP_trace::trace("Front booking, ajax_book1_submit, not available");
		echo '...';					// tell the Javascript to submit the form the old-fashioned way
		return;
		}

// Validate the data entered on book1
// If not valid, re-display with error messages

	$post_data = $this->booking_model->getPostData($this->config_data, $this->extras_list);
	$this->app->setUserState(LARP_COMPONENT."booking", $post_data);
	$errorMessages = array();
	$check = $this->booking_model->check_data($errorMessages, $this->leadsource_list, $this->extras_list, $this->config_data, $this->unit_data);
		
	if (!$check)
		{
		$view = $this->getView('book','html');	
		$view->unit_data = $this->unit_data;
		$view->errormessages = $errorMessages;
		$view->unit_list = $this->unit_list;
		$view->currency_list = $this->currency_list;
		$view->leadsource_list = $this->leadsource_list;
		$view->config_data = $this->config_data;
		$view->context = $this->context;
		$view->post_data = $post_data;
		$view->extras_list = $this->extras_list;
		$view->currency_symbol = $this->currency_symbol;
		$view->currency_format = $this->currency_format;
		$view->voucher_count = $this->voucher_count;
		$view->country_codes = $this->country_codes;
		$view->draw_main_form();			// just send back the main part of the form
		return;	
		}
		
// Validation ok so display book2, the confirmation page
// Add the quantities to the extras list then calculate the total extras prices

	foreach ($this->booking_model->_data->extra_qty_array as $id => $qty)
		$this->extras_list[$id]['equantity'] = $qty;

	$total_extras = $this->extra_model->priceExtras($this->extras_list, $this->context['datefrom_yyyy_mm_dd'], $this->context['dateto_yyyy_mm_dd'],
							$this->booking_model->_data->num_adults,  $this->booking_model->_data->num_children, $this->booking_model->_data->num_babies);
	$this->backend_booking_model->_data->extras_price = $total_extras;

// Calculate the payment schedule and the amount due now

	$this->backend_booking_model->calculatePaymentSchedule($this->config_data, $this->currency_rate);

	//Hacking : PR to replace the emails sending by plugins events to personnalize the form save behavior
	PluginHelper::importPlugin('rentalot');
	// permits to do something after sending the booking mail to visitor
	$app = Factory::getApplication();
	$result = (array) $app->triggerEvent('onRentalotSubmitBooking', 
		array('com_rentalot.booking', $post_data, $this->backend_booking_model, $check)
	); 	
	
// Display book2, the confirmation page

	$view = $this->getView('book','html');
	$view->unit_data = $this->unit_data;
	$view->config_data = $this->config_data;
	$view->context = $this->context;
	$view->post_data = $post_data;
	$view->errormessages = $errorMessages;
	$view->extras_list = $this->extras_list;
	$view->total_extras = $total_extras;
	$view->currency_symbol = $this->currency_symbol;
	$view->currency_rate = $this->currency_rate;
	$view->currency_format = $this->currency_format;
	$view->backend_booking_data = $this->backend_booking_model->_data;
	$view->book2();		// the Ajax will replace the entire page with the output from book2
}

//-------------------------------------------------------------------------------
// Receive input from book2
// - the user has confirmed he wants to go ahead
// - we need to store the booking in Payage and display the payment buttons
//
function book2_submit()
{
	LARP_trace::trace("Front booking, book2_submit");
    $token = JSession::getFormToken();				    // don't use JSession::checkToken() because it just redirects to the home page
    if (!$this->jinput->get($token, '', 'string'))		// this way is more user friendly
		{
		LARP_trace::trace("Front booking, book2_submit, session expired");
		$this->book1(true);				// session expired - go back to the beginning
		return;
		}

	$this->get_common_data();		// this also checks availability

	if ($this->availability_code != GA_AVAILABLE)
		{
		LARP_trace::trace("Front booking, book2_submit, not available");
		$this->book1();				// not available - go back to book1
		return;
		}

// Availability is still ok - if they hit the "Change Details" button, go back to book1

	$change_button = $this->jinput->get('change_button', '', 'STRING');
	if (!empty($change_button))
		{
		LARP_trace::trace("Front booking, book2_submit, change button");
		$this->book1();
		return;	
		}

// retrieve the booking data from the session - if it's not there the user has left it too long

	$front_booking_model = $this->getModel('front_booking');
	$front_booking_model->_data = $this->app->getUserState(LARP_COMPONENT."booking", null);
	if (empty($front_booking_model->_data))
		{
		LARP_trace::trace("Front booking, book2_submit, booking data not in session");
		$this->book1(true);				// session expired - go back to the beginning
		return;
		}	

// Calculate the total extras prices
// first add the quantities to the extras list

	foreach ($front_booking_model->_data->extra_qty_array as $id => $qty)
		$this->extras_list[$id]['equantity'] = $qty;

	$total_extras = $this->extra_model->priceExtras($this->extras_list, $this->context['datefrom_yyyy_mm_dd'], $this->context['dateto_yyyy_mm_dd'],
			$front_booking_model->_data->num_adults,  $front_booking_model->_data->num_children, $front_booking_model->_data->num_babies);
	$this->backend_booking_model->_data->extras_price = $total_extras;
	
// the unit_id, dates and currency_rate were populated into the back end booking model by $check_model->get_availability()	
// - we now populate the rest of the booking data
// - it will be stored temporarily in the Payage app_transaction_details
// - if payment completes successfully it will be written to the booking table

	$this->backend_booking_model->_data->currency = $this->context['currency'];
	$this->backend_booking_model->_data->state = $this->config_data->sb_new_type;
	$this->backend_booking_model->_data->source = LARP_BOOKING_SOURCE_ONLINE;
	$this->backend_booking_model->_data->salutation = $front_booking_model->_data->salutation;
	$this->backend_booking_model->_data->forenames = $front_booking_model->_data->forenames;
	$this->backend_booking_model->_data->surname = $front_booking_model->_data->surname;
	$this->backend_booking_model->_data->address1 = $front_booking_model->_data->address1;
	$this->backend_booking_model->_data->address2 = $front_booking_model->_data->address2;
	$this->backend_booking_model->_data->city = $front_booking_model->_data->city;
	$this->backend_booking_model->_data->county = $front_booking_model->_data->county;
	$this->backend_booking_model->_data->postcode = $front_booking_model->_data->postcode;
	$this->backend_booking_model->_data->country = $front_booking_model->_data->country;
	$this->backend_booking_model->_data->email = $front_booking_model->_data->email;
	$this->backend_booking_model->_data->phone_home = $front_booking_model->_data->phone_home;
	$this->backend_booking_model->_data->phone_mobile = $front_booking_model->_data->phone_mobile;
	$this->backend_booking_model->_data->date_booked = date('Y-m-d');
	$this->backend_booking_model->_data->leadsource_id = $front_booking_model->_data->leadsource_id;
	$this->backend_booking_model->_data->comments = $front_booking_model->_data->comments;
	$this->backend_booking_model->_data->num_adults = $front_booking_model->_data->num_adults;
	$this->backend_booking_model->_data->num_children = $front_booking_model->_data->num_children;
	$this->backend_booking_model->_data->num_babies = $front_booking_model->_data->num_babies;

// bookings store the tax rates in force at the time of their creation

	$this->backend_booking_model->_data->extras_tax_rate = $this->config_data->extras_tax_rate;
	$this->backend_booking_model->_data->accommodation_tax_rate = $this->config_data->accommodation_tax_rate;
    
// optional fields

	$this->backend_booking_model->_data->udf1 = $front_booking_model->_data->udf1;
	$this->backend_booking_model->_data->udf2 = $front_booking_model->_data->udf2;
	$this->backend_booking_model->_data->udf3 = $front_booking_model->_data->udf3;
	$this->backend_booking_model->_data->udf4 = $front_booking_model->_data->udf4;
	$this->backend_booking_model->_data->udf5 = $front_booking_model->_data->udf5;
	$this->backend_booking_model->_data->udl1 = $front_booking_model->_data->udl1;

// calculate the payment schedule

	$this->backend_booking_model->calculatePaymentSchedule($this->config_data, $this->currency_rate);

// set the price notes

	$this->backend_booking_model->base_price_detail($this->price_rows, $this->config_data->date_format, $this->currency_rate, $this->currency_symbol, $this->currency_format);

// get the payment buttons and store the booking and extras in Payage
	
	$expected_payment_amount = $this->backend_booking_model->_data->_initial_booking_total;
	
	$payment_buttons = self::getPaymentButtons($this->config_data, $this->backend_booking_model->_data, $this->extras_list, $this->unit_data, $expected_payment_amount, true);
	
// Display the payment screen

	$view = $this->getView('_payment','html');
	$view->setModel($this->unit_model, true);
	$view->setModel($this->config_model, false);
	$view->config_data = $this->config_data;
	$view->context = $this->context;
	$view->post_data = $front_booking_model->_data;
	$view->currency_symbol = $this->currency_symbol;
	$view->currency_format = $this->currency_format;
	$view->currency_rate = $this->currency_rate;
	$view->expected_payment_amount = $expected_payment_amount;
	$view->booking_id = $this->backend_booking_model->_data->id;
	$view->payment_buttons = $payment_buttons;
	$view->display();
}

// -------------------------------------------------------------------------------
// Get common data for the booking functions
//
function get_common_data()
{
	LARP_trace::trace("Front booking, get_common_data");
	$this->context = LARP_Util::get_context();
	$voucher_code = $this->jinput->get('voucher_code', '', 'STRING');
	$this->context['voucher_code'] = $voucher_code;				// check_model->get_availability() needs this

	$this->addModelPath(JPATH_ADMINISTRATOR.'/components/com_rentalotplus/models');
	$this->check_model = $this->getModel('check');
	$this->currency_model = $this->getModel('currency');
	$this->unit_model = $this->getModel('unit');
	$this->config_model = $this->getmodel('config');
	$this->extra_model = $this->getModel('extra');
	$this->attribute_model = $this->getModel('attribute');
	$this->voucher_model = $this->getModel('voucher');
	$this->config_data = $this->config_model->getData(CONFIG_CATEGORY_GENERAL.','.CONFIG_CATEGORY_SCHEDULE.','.CONFIG_CATEGORY_DISCOUNT.','.CONFIG_CATEGORY_SELFBOOK.','.CONFIG_CATEGORY_CLASSIC);
	$this->backend_booking_model = $this->getModel('booking');

	$this->base_currency  = $this->currency_model->getBaseCurrencyCode();
	$this->current_currency_row = $this->currency_model->getCurrencyByCode($this->context['currency']);
	$this->currency_list = $this->currency_model->getCurrencyArray(1);
	$this->currency_rate = $this->current_currency_row->customer_rate;
	$this->currency_symbol = $this->current_currency_row->symbol;
	$this->currency_format = $this->current_currency_row->currency_format;
	$this->currency_precision = $this->current_currency_row->precision;

	$this->extras_list = $this->extra_model->getOneBookingExtras(0, $this->currency_rate, 1, $this->context['unit_id']);
	$this->num_extras = count($this->extras_list);
	$this->leadsource_model = $this->getModel('leadsource');
	$this->leadsource_list = $this->leadsource_model->getLeadsourceArray('', JText::_('COM_RENTALOTPLUS_SELECT'));
	$this->num_leadsource = count($this->leadsource_list);
	$this->country_model = $this->getModel('country');
	$this->country_codes = $this->country_model->getCountryArray('1', JText::_('COM_RENTALOTPLUS_SELECT'));
	$this->voucher_count = $this->voucher_model->getActiveVoucherCount();
	$this->booking_model = $this->getModel('front_booking');
	$this->unit_data = $this->unit_model->getOne($this->context['unit_id']);
	$this->unit_list = $this->unit_model->getUnitArray('', 1);

// get the availability response
// If there is any problem, re-display the check view with error messages

	$this->availability_message = '';
	$this->price_rows = array();
	$this->availability_code = $this->check_model->get_availability($this->availability_message, $this->price_rows, $this->context, $this->current_currency_row, $this->base_currency, $this->config_data, $this->unit_data, $this->backend_booking_model);
	LARP_trace::trace("Front booking, get_common_data, availability_code = ".$this->availability_code);
}

// -------------------------------------------------------------------------------
// Return the extras table for the selected unit and currency
//
function ajax_get_extras()
{
	LARP_trace::trace("Front booking, ajax_get_extras");
	$this->context = LARP_Util::get_context();
	$this->addModelPath(JPATH_ADMINISTRATOR.'/components/com_rentalotplus/models');
	$this->currency_model = $this->getModel('currency');
	$this->config_model = $this->getmodel('config');
	$this->extra_model = $this->getModel('extra');
	$this->config_data = $this->config_model->getData(CONFIG_CATEGORY_GENERAL.','.CONFIG_CATEGORY_CLASSIC);
	$this->current_currency_row = $this->currency_model->getCurrencyByCode($this->context['currency']);
	$this->currency_rate = $this->current_currency_row->customer_rate;
	$this->currency_symbol = $this->current_currency_row->symbol;
	$this->currency_format = $this->current_currency_row->currency_format;
	$this->extras_list = $this->extra_model->getOneBookingExtras(0, $this->currency_rate, 1, $this->context['unit_id']);
	$this->num_extras = count($this->extras_list);
	
	$view = $this->getView('book','html');	
	$view->config_data = $this->config_data;
	$view->extras_list = $this->extras_list;
	$view->currency_symbol = $this->currency_symbol;
	$view->currency_format = $this->currency_format;
	$view->show_extras();
}

// -------------------------------------------------------------------------------
// Can get here with task "my_booking" or "pay_online"
// Show the subsequent payments screen
// The id is the Long ID (lid) of the booking record
//
function my_booking()
{
	LARP_trace::trace("Front booking, my_booking");
	$this->addModelPath(JPATH_ADMINISTRATOR.'/components/com_rentalotplus/models');
	$backend_booking_model = $this->getModel('booking');
	$config_model = $this->getmodel('config');
	$config_data = $config_model->getData(CONFIG_CATEGORY_GENERAL.','.CONFIG_CATEGORY_SELFBOOK);
	$unit_model = $this->getModel('unit');
	$currency_model = $this->getModel('currency');
	$lid = $this->jinput->get('id','','STRING');

// Verify the long booking ID

	if ($lid == '')			// blank lid?
		{
		LARP_trace::trace("Front booking, my_booking, no id specified");
		echo "\n".'<h2>'.JText::_('COM_RENTALOTPLUS_MY_BOOKING').'</h2>';
		echo '<div class="rp_page">'.JText::_('COM_RENTALOTPLUS_INVALID_TRANSACTION').'</div>';
		return;
		}

// Get the booking record

	$booking_data = $backend_booking_model->getOne($lid,'lid');	// get booking by Long ID	
	if ($booking_data === false)
		{
		LARP_trace::trace("Front booking, my_booking, $lid not found in booking table");
		echo "\n".'<h2>'.JText::_('COM_RENTALOTPLUS_MY_BOOKING').'</h2>';
		echo '<div class="rp_page">'.JText::_('COM_RENTALOTPLUS_INVALID_TRANSACTION').'</div>';
		return;
		}

// Stop if the booking is cancelled

	if ($booking_data->state == STATE_CANCELLED)
		{
		LARP_trace::trace("Front booking, my_booking, $lid is a cancelled booking");
		echo "\n".'<h2>'.JText::_('COM_RENTALOTPLUS_MY_BOOKING').'</h2>';
		echo '<div class="rp_page">'.JText::_('COM_RENTALOTPLUS_INVALID_TRANSACTION').'</div>';
		return;
		}

// get the list of documents for this booking

    require_once JPATH_ADMINISTRATOR.'/components/com_rentalotplus/helpers/document_helper.php';
    $doc_list = LARP_doc::get_documents($config_data->sb_document_dir, $booking_data->id, $booking_data->unit_id);

// if we have a document ID, it is being requested by the popup window - read and serve the document requested

	$doc_index = $this->jinput->get('doc','','STRING');
    if ($doc_index != '')
        {
        if (!isset($doc_list[$doc_index]))
            return;
        $file_path = $config_data->sb_document_dir.'/'.$doc_list[$doc_index]['filename'];
      	$file_ext = JFile::getExt($file_path);
    	$file_name = JFile::getName($file_path);
    	$mime_type = self::getMimeType($file_ext);
    	while (@ob_end_clean());			// clean all output buffers - on some servers it is very important to clean all of them!
        header('Cache-control: private');
        header('Content-Length: '.filesize($file_path));
    	header("Content-Type: ".$mime_type);
    	header('Content-Disposition: inline; filename="'.$file_name.'"');
        readfile($file_path);
        return;
        }

// Get the unit name

	$unit_data = $unit_model->getOne($booking_data->unit_id);
	if ($unit_data === false)
		{
		$message = JText::_('COM_RENTALOTPLUS_INVALID_TRANSACTION').' (unit not found)';	// should never happen
		echo '<div class="rp_page">'.$message.'</div>';
		return;
		}

// Get more data

	$payment_list = $backend_booking_model->getPayments();
	require_once JPATH_ADMINISTRATOR.'/components/com_rentalotplus/helpers/email_helper.php';
	$total_paid = LARP_email::getTotalPaid($booking_data);
	$currency_model->getCurrencyByCode($booking_data->currency);
	$currency_format = $currency_model->_data->currency_format;
	$currency_symbol = $currency_model->_data->symbol;
	$extra_model = $this->getModel('extra');
	$extras_list = $extra_model->getOneBookingExtras($booking_data->id, $booking_data->currency_rate); 
	$payment_due_info = $this->calculateAmountsDue($booking_data, $payment_list);
            
// initialise the view

	$view = $this->getView('mybooking', 'html');
    $view->payment_due_status = $payment_due_info['status'];
	$view->expected_payment_amount = $payment_due_info['expected_payment_amount'];
	$view->config_data = $config_data;
	$view->booking_data = $booking_data;
	$view->extras_list = $extras_list;
	$view->unit_name = $unit_data->unit_name;
	$view->currency_format = $currency_format;
	$view->currency_symbol = $currency_symbol;
	$view->total_paid = $total_paid;
	$view->my_booking_link = LARP_email::makeMyBookingLink($booking_data);
    $view->config_data = $config_data;
    $view->doc_list = $doc_list;

// if the booking is completed, show TEMPLATE_BOOKING_COMPLETE and stop

    if ($booking_data->state == STATE_COMPLETED)
		{
		$template = $config_model->getData('TEMPLATE_BOOKING_COMPLETE');
		$booking_data = $backend_booking_model->getOneEmail($booking_data->id);
		$view->message = LARP_email::email_merge($booking_data, $template, $config_data->date_format);
    	$view->display();
        return;
		}

// if no payments are due, show TEMPLATE_PAYMENT_COMPLETE

	if ($payment_due_info['status'] == 0)
		{
		$template = $config_model->getData('TEMPLATE_PAYMENT_COMPLETE');
		$booking_data = $backend_booking_model->getOneEmail($booking_data->id);
        $view->message = LARP_email::email_merge($booking_data, $template, $config_data->date_format);;
    	$view->display();
        return;
		}

// there are payments outstanding, get the payment buttons

	$expected_payment_amount = $payment_due_info['expected_payment_amount'];
    $payment_buttons = self::getPaymentButtons($config_data, $booking_data, array(), $unit_data, $expected_payment_amount, false);
    $view->payment_buttons = $payment_buttons;
    $view->expected_payment_amount = $expected_payment_amount;
    if ($payment_due_info['status'] == 2)
        $view->payment_due_date = $payment_due_info['next_due_date'];
    $view->display();
}

//-------------------------------------------------------------------------------
// Get an array of payment buttons from the Payage payment component
// $new_booking is true for an initial booking and false for a subsequent payment
//
static function getPaymentButtons($config_data, $booking_data, $extras_data, $unit_data, $expected_payment_amount, $new_booking)
{
	LARP_trace::trace("Front booking, getPaymentButtons");
	$lang_obj = JFactory::getLanguage();
	$language_code = $lang_obj->get('tag');

// make a smaller extra array to store in Payage
// - the booking only needs the quantity and total price

	$booked_extras = array();
	foreach ($extras_data as $extra_id => $extra_info)
		if ($extra_info['equantity'] > 0)
			{
			$extra = array();
			$extra['equantity'] = $extra_info['equantity'];
			$extra['total_price'] = $extra_info['total_price'];
			$booked_extras[$extra_id] = $extra;
			}

// if the unit has an account_group use that, otherwise use the global config

    if (empty($unit_data->account_group))
        $account_group = $config_data->account_group;
    else
        $account_group = $unit_data->account_group;

// if we have two account groups and this is a subsequent payment, use the second account group

    $account_groups = explode(',',$account_group);
    $account_group = $account_groups[0];
    if ( (!$new_booking) and (isset($account_groups[1])) )
        $account_group = $account_groups[1];        

	if (!file_exists(JPATH_ADMINISTRATOR.'/components/com_payage/api.php'))
		{
		$button_array = array();
		$button_array[0]['status'] = 1;
		$button_array[0]['error'] = JText::_('COM_RENTALOTPLUS_PAYAGE_NOT_INSTALLED');
		return $button_array;
		}
	require_once JPATH_ADMINISTRATOR.'/components/com_payage/api.php';

	$info_array = array();										// store some details in app_transaction_details for when payment completes
	$info_array['Unit_name'] = $unit_data->unit_name;			// these are just for visual reference in Payage
	$info_array['Name'] = LARP_Util::full_name($booking_data->salutation, $booking_data->forenames, $booking_data->surname);
	$info_array['Date_from'] = $booking_data->date_from;
	$info_array['Date_to'] = $booking_data->date_to;
	$info_array['Booking_data'] = $booking_data;				// the booking data and the extras are what we really need
	$info_array['Extra_data'] = $booked_extras;
	$info_array['language'] = $language_code;                	// 15.00
	if ($new_booking)
		$info_array['New_booking'] = 'Y';
	else
		$info_array['New_booking'] = 'N';

	$call_array = array();
	$call_array['currency'] = $booking_data->currency;
	$call_array['group'] = $account_group;
	$call_array['app_name'] = 'RentalotPlus';
	$call_array['item_name'] = $unit_data->unit_name.': '.$booking_data->surname;
	$call_array['firstname'] = $booking_data->forenames;
	$call_array['lastname'] = $booking_data->surname;
	$call_array['address1'] = $booking_data->address1;
	$call_array['address2'] = $booking_data->address2;
	$call_array['city'] = $booking_data->city;		
	$call_array['state'] = $booking_data->county;	
	$call_array['zip_code'] = $booking_data->postcode;
	$call_array['country'] = $booking_data->country;
	$call_array['email'] = $booking_data->email;		
	$call_array['app_transaction_details'] = $info_array;
	$call_array['gross_amount'] = $expected_payment_amount;
	$call_array['app_transaction_id'] = $booking_data->lid;
	$call_array['app_return_url'] = LARP_Util::get_view_url('postpay');
	$call_array['app_update_path'] = JPATH_ROOT.'/components/com_rentalotplus/payment_update.php';

	LARP_trace::trace("Calling PayageApi::Get_payment_buttons with call_array: ".print_r($call_array,true));
	$button_array = PayageApi::Get_payment_buttons($call_array);
	
	if ($button_array[0]['status'] != 0)
		LARP_trace::trace("PayageApi::Get_payment_buttons() returned ".$button_array[0]['error']);

	if ($button_array[0]['status'] == 1)
		{
        LARP_trace::trace("NO PAYMENT BUTTONS FOUND FOR ACCOUNT GROUP ".$config_data->account_group." AND CURRENCY ".$booking_data->currency);
		$button_array[0]['error'] = JText::_('COM_RENTALOTPLUS_ERROR_NO_ACCOUNTS');
		return $button_array;
		}

	return $button_array;
}

//-------------------------------------------------------------------------------
// Get the amount due for the subsequent payments view
// Returns an array of payment due information, including ['status'] as follows:
//         0 if no payments are due
//         1 if one or more payments are overdue
//         2 if a payment is due in the future
//         3 if there is a pending payment outstanding
//
function calculateAmountsDue($booking_data, $payment_list)
{
	$payment_due_info = array();
	$payment_due_info['status'] = 0;
	$payment_due_info['expected_payment_amount'] = 0;
	$payment_due_info['next_due_date'] = '';

// are there any pending payments?

	foreach ($payment_list as $payment)
		if ($payment->pg_status_code == 2)
			{
			$payment_due_info['expected_payment_amount'] = $payment->gross_amount;
			$payment_due_info['status'] = 3;
			return $payment_due_info;
			}

// are any payments overdue or due today?

	$total = 0;
	if ( ($booking_data->payment1_due <= date('Y-m-d')) and (!$booking_data->payment1_paid) )
		$total += $booking_data->payment1_amount;
	if ( ($booking_data->payment2_due <= date('Y-m-d')) and (!$booking_data->payment2_paid) )
		$total += $booking_data->payment2_amount;
	if ( ($booking_data->payment3_due <= date('Y-m-d')) and (!$booking_data->payment3_paid) )
		$total += $booking_data->payment3_amount;
	if ( ($booking_data->payment4_due <= date('Y-m-d')) and (!$booking_data->payment4_paid) )
		$total += $booking_data->payment4_amount;
		
// if we found any, that is the amount that can be paid online now

	if ($total != 0)
		{
		$payment_due_info['expected_payment_amount'] = $total;
		$payment_due_info['status'] = 1;			// it's overdue
		return $payment_due_info;
		}

// there are no overdue payments or payments due today
// are there any due in the future?

	$index = 0;
	if (($booking_data->payment4_amount > 0) and (!$booking_data->payment4_paid))
		$index = 4;
	if (($booking_data->payment3_amount > 0) and (!$booking_data->payment3_paid))
		$index = 3;
	if (($booking_data->payment2_amount > 0) and (!$booking_data->payment2_paid))
		$index = 2;
	if (($booking_data->payment1_amount > 0) and (!$booking_data->payment1_paid))
		$index = 1;
		
// if we found any, that is the amount that can be paid

	if ($index != 0)
		{
		$payment_amount_field = 'payment'.$index.'_amount';
		$payment_due_field = 'payment'.$index.'_due';
		$payment_due_info['expected_payment_amount'] = $booking_data->$payment_amount_field;
		$payment_due_info['next_due_date'] = $booking_data->$payment_due_field;
		$payment_due_info['status'] = 2;			// it's due in the future
		return $payment_due_info;
		}

// nothing is owed
		
	return $payment_due_info;		// status is zero
}

//-------------------------------------------------------------------------------
// generate a dynamic sync file
// the call is ...index.php?option=com_rentalotplus&syncid=$id
//
function sync_out()
{
	$id = $this->jinput->get('syncid','', 'INT');
	if (!is_numeric($id))
		return;
	$this->addModelPath(JPATH_ADMINISTRATOR.'/components/com_rentalotplus/models');
	$sync_model = $this->getModel('sync');
	$booking_model = $this->getModel('booking');
	$log_model = $this->getModel('log');
	$sync_model->sync_out($id, $booking_model, $log_model);
}

//-------------------------------------------------------------------------------
// get the mime type for an extension
//
static function getMimeType($ext)
{
	switch ($ext)
		{
		case 'avi':   return 'video/x-msvideo';
		case 'doc':   return 'application/msword';
		case 'gif':   return 'image/gif';
		case 'htm':   return 'text/html;charset=UTF-8';
		case 'html':  return 'text/html;charset=UTF-8';
		case 'jpeg':  return 'image/jpeg';
		case 'jpg':   return 'image/jpeg';
		case 'mov':   return 'video/quicktime';
		case 'mp2':   return 'audio/mpeg';
		case 'mp3':   return 'audio/mpeg';
		case 'mpeg':  return 'video/mpeg';
		case 'mpg':   return 'video/mpeg';
		case 'pdf':   return 'application/pdf';
		case 'png':   return 'image/png';
		case 'ppt':   return 'application/vnd.ms-powerpoint';
		case 'qt':    return 'video/quicktime';
		case 'zip':   return 'application/zip';
		case 'txt':   return 'text/plain';
		default:      return 'application/octet-stream';
		}
}

}
