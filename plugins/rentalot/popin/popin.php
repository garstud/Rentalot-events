<?php
/**
 * @version 0.1
 * @package Joomla.plugins
 * @subpackage Rentalot
 * @license GNU/GPL v2
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;

class PlgRentalotPopin extends CMSPlugin
{
	//protected $autoloadLanguage = true;
	protected $db;
	protected $app;

	public function __construct( &$subject , $config )
	{
		parent::__construct($subject, $config);
	}
  
	//  			onRentalotSubmitEnquiry
	public function onEnquiryAfterSentMail ($context, $bookingModel, $emailInfoClient)
	{
    // additionnal control on context
		if($context!="com_rentalot.enquiry") return;		
		
		// Display Popins
		$this->app->enqueueMessage("Hello World, Enquiry Rentalot Plugin event is alive!", "info");
		$this->app->enqueueMessage("Rentalot visitor info email : ".$emailInfoClient['email_to'], "info");
		$this->app->enqueueMessage("Rentalot form comment : ".$bookingModel->_data->comments, "info");
				
		return true;
	}	
}
