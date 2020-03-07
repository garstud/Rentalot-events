# Rentalot-events
Adding some events to trigger plugins in the Rentalot component

## Plugin Events

Add event **'onRentalotSubmitEnquiry'** in controller Enquiry at the end of the ajax_submit to add taks when a request is sent.

```/**
     * Event after submiting a Enquiry request form
     *
     * @param   string   $context       define the context of the event execution.
     * @param   object   $bookingModel  object contains input form data.
     * @param   array    $emailInfoClient  info from the sent mail.
     * @param   boolean  $isMailSent    the mail has been sent (1) or not (0).
     */
     public function onRentalotSubmitEnquiry($context, $bookingModel, $emailInfoClient, $isMailSent)
```


Add event **'onRentalotSubmitBooking'** in controller Bookink at the end of the ajax_submit to add taks when a booking is sent.

```/**
     * Event after submiting a booking form
     *
     * @param   string   $context      define the context of the event execution.
     * @param   array    $postData     the content of the form.
     * @param   object   $bookingModel  object contains input form data.
     * @param   boolean  $check        the mail has been sent (1) or not (0).
     */
     public function onRentalotSubmitBooking($context, $postData, $bookingModel, $check)
```

## Controllers updates
To make it possible to trigger these events, we need to connect Rentalot with these events.
the '$app->triggerEvent' is the solution in Joomla to request the associated plugins.
It contains 2 params :
1. the name of the events to call
1. an array containing params to send to the plugin : context, BookingModel ...

### Enquiry
the controllers/enquirycontroller.php has been modified with the following lines of code :

```
    use Joomla\CMS\Factory;
    use Joomla\CMS\Plugin\PluginHelper;
...
    $isSentOk = LARP_email::sendEmail($email_info_client, $message);
    if($isSentOk) ...
...
    PluginHelper::importPlugin('rentalot');
    // permits to do something after sending the enquiry mail to visitor
    $app = Factory::getApplication();
    $result = (array) $app->triggerEvent('onRentalotSubmitEnquiry', 
            array('com_rentalot.enquiry', $booking_model, $email_info_client, $isSentOk)
    ); 
```

See history updates on the PHP file to check codes updates.

### Booking
the controllers/bookngcontroller.php has been modified with the following lines of code :

```
    use Joomla\CMS\Factory;
    use Joomla\CMS\Plugin\PluginHelper;
...
    PluginHelper::importPlugin('rentalot');
    // permits to do something after sending the booking mail to visitor
    $app = Factory::getApplication();
    $result = (array) $app->triggerEvent('onRentalotSubmitBooking', 
            array('com_rentalot.booking', $post_data, $this->backend_booking_model, $check)
    );  
```

See history updates on the PHP file to check codes updates.


# Plugins example
The folder **'plugins'** contains plugins in the joomla group/type **'rentalot'** to explain how to developp them and to test them on Rentalot component Enquiries or Booking with the previous modification of the code in the controller !
