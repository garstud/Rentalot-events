<?php
/**
 * @version 0.1
 * @subpackage Rentalot
 * @license GNU/GPL v2
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;

class PlgRentalotJinbox extends CMSPlugin
{
	protected $autoloadLanguage = true;
	protected $db;
	protected $app;

	public function __construct( &$subject , $config )
	{
		parent::__construct($subject, $config);
	// define a Log to trace debug
        JLog::addLogger( 
            array('text_file' => 'com_rentalot.log.php'),
            JLog::ALL,
            array('jinbox')
        );
	}

    /**
     * Event after submiting a Enquiry request form
     *
     * @param   string   $context       define the context of the event execution.
     * @param   object   $bookingModel  object contains input form data.
     * @param   array    $emailInfoClient  info from the sent mail.
     * @param   boolean  $isMailSent    the mail has been sent (1) or not (0).
     */
    public function onRentalotSubmitEnquiry($context, $bookingModel, $emailInfoClient, $isMailSent)
	{
        if (JDEBUG) JLog::add("onRentalotSubmitEnquiry ".$context, JLog::DEBUG, 'jinbox');
		if($context!="com_rentalot.enquiry") return;		
		
		// Send message
		$inboxTitle= "[".$bookingModel->_data->unit_name."] Enquiry from  ".$emailInfoClient['email_to'];
		$inboxMsg  = "Demande de reservation recue : ".$context."<br />";
		$inboxMsg .= "- par : ".$bookingModel->_data->forenames." ".$bookingModel->_data->surname."<br/>";
		$inboxMsg .= "- pour le gite : <b>".$bookingModel->_data->unit_name."</b><br/>";
		$inboxMsg .= "- Du  ".$bookingModel->_data->date_from." au  ".$bookingModel->_data->date_to."<br/>";
		$inboxMsg .= "- udf2 : ".$bookingModel->_data->udf2."<br/>";
		$inboxMsg .= "- udf3 : ".$bookingModel->_data->udf3."<br/>";
		$inboxMsg .= "- udf4 : ".$bookingModel->_data->udf4."<br/>";
		$inboxMsg .= "- Msg :<br />".$bookingModel->_data->comments;
				
		$uid = $this->params->get('jinbox_user');
       		if (JDEBUG) JLog::add("send to uid ".$uid, JLog::DEBUG, 'jinbox');
		$this->_sendJinboxMessage($uid, $inboxTitle, $inboxMsg);
		
		return true;
	}	
	
    /**
     * Event after submiting a booking form
     *
     * @param   string   $context      define the context of the event execution.
     * @param   array    $postData     the content of the form.
     * @param   object   $bookingModel  object contains input form data.
     * @param   boolean  $check        the mail has been sent (1) or not (0).
     */
	public function onRentalotSubmitBooking($context, $postData, $bookingModel, $check)
	{
        if (JDEBUG) JLog::add("onRentalotSubmitBooking ".$context, JLog::DEBUG, 'jinbox');
		if($context!="com_rentalot.booking") return;		
		
		// Send message
		$inboxTitle= "[".$bookingModel->_data->unit_id."] Booking from  ".$postData['email'];
		$inboxMsg  = "Reservation ferme recue : ".$context."<br />";
		$inboxMsg .= "- par : ".$postData['forenames']." ".$postData['surname']."<br/>";
		$inboxMsg .= "- pour le gite : <b>".$bookingModel->_data->unit_id."</b><br/>";
		$inboxMsg .= "- Du  ".$bookingModel->_data->date_from." au  ".$bookingModel->_data->date_to."<br/>";
		$inboxMsg .= "- Msg :<br />".$postData['comments'];   
				
		$uid = $this->params->get('jinbox_user');
        	if (JDEBUG) JLog::add("send to uid ".$uid, JLog::DEBUG, 'jinbox');
		$this->_sendJinboxMessage($uid, $inboxTitle, $inboxMsg);
		
		return true;
	}	
	
	// Insert the object into the Joomla Messages table for the admin Uid.
	private function _sendJinboxMessage($uid, $inboxTitle, $inboxMsg) {
		try {
			// save in JInBox
			$inbox = new stdClass();
			$inbox->user_id_from = $uid;
			$inbox->user_id_to = $uid;
			$inbox->date_time = Factory::getDate()->toSql();
			$inbox->state = 0; //=non-lu
			$inbox->priority = 0; // inutilisé dasn Joomla !?
			$inbox->subject = $inboxTitle;
			$inbox->message = $inboxMsg;			
			$result = $this->db->insertObject('#__messages', $inbox);
			$newMsgId = $this->db->insertid();
		}
		catch(Exception $e) {
            JLog::add(get_class($this)." ".__FUNCTION__." Error on Jinbox insert : ".$e->getMessage(), JLog::ERROR, 'jinbox');
		}
	}
}
