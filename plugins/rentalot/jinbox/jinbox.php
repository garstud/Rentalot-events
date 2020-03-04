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
	}
	//  			onRentalotSubmitEnquiry
	public function onEnquiryAfterSentMail ($context, $bookingModel, $emailInfoClient)
	{
        LARP_trace::trace("Enquiry jinbox ".$context);	
		if($context!="com_rentalot.enquiry") return;		
		
		// Send message
		$inboxTitle= "[".$bookingModel->_data->unit_name."] Contact resa from  ".$emailInfoClient['email_to'];
		$inboxMsg  = "Demande de reservation recu :<br />";
		$inboxMsg .= "- par : ".$bookingModel->_data->forenames." ".$bookingModel->_data->surname."<br/>";
		$inboxMsg .= "- pour le gite : <b>".$bookingModel->_data->unit_name."</b><br/>";
		$inboxMsg .= "- Du  ".$bookingModel->_data->date_from." au  ".$bookingModel->_data->date_to."<br/>";
		$inboxMsg .= "- Nb adultes : ".$bookingModel->_data->udf2."<br/>";
		$inboxMsg .= "- Nb enfants : ".$bookingModel->_data->udf3."<br/>";
		$inboxMsg .= "- Téléphone : ".$bookingModel->_data->udf4."<br/>";
		$inboxMsg .= "- Msg :<br />".$bookingModel->_data->comments;
				
		$uid = $this->params->get('jinbox_user');
    LARP_trace::trace("Enquiry jinbox uid ".$uid);	
		$this->_sendJinboxMessage($uid, $inboxTitle, $inboxMsg);
		
		return true;
	}	
	
	// Insert the object into the Joomla Messages table.
	private function _sendJinboxMessage($uid, $inboxTitle, $inboxMsg) {
		try {
			// save in JInBox
			$inbox = new stdClass();
			$inbox->user_id_from = $uid;
			$inbox->user_id_to = $uid;
			$inbox->date_time = Factory::getDate()->toSql();
			$inbox->state = 0; //=non-lu
			$inbox->priority = 0; //$inboxPrio; //FIXME 4 : inutilisé ???
			$inbox->subject = $inboxTitle;
			$inbox->message = $inboxMsg;			
			$result = $this->db->insertObject('#__messages', $inbox);
			$newMsgId = $this->db->insertid();
		}
		catch(Exception $e) {
			$this->app->enqueueMessage(get_class($this)." ".__FUNCTION__." on Jinbox insert : ".$e->getMessage(), 'warning'); // FIXME 2 : en Log d'alerte plutot !
		}
	}
}
