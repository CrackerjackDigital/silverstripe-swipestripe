<?php
/**
 * A receipt email that is sent to the customer after they have completed their {@link Order}.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package shop
 * @subpackage emails
 * @version 1.0
 */
class ReceiptEmail extends ProcessedEmail {

	/**
	 * Create the new receipt email.
	 * 
	 * @param Member $customer
	 * @param Order $order
	 * @param String $from
	 * @param String $to
	 * @param String $subject
	 * @param String $body
	 * @param String $bounceHandlerURL
	 * @param String $cc
	 * @param String $bcc
	 */
	public function __construct(Member $customer, Order $order, $from = null, $to = null, $subject = null, $body = null, $bounceHandlerURL = null, $cc = null, $bcc = null) {
	  
	  $siteConfig = SiteConfig::current_site_config();
	  if ($customer->Email) $this->to = $customer->Email; 
	  if ($siteConfig->ReceiptSubject) $this->subject = $siteConfig->ReceiptSubject . ' - Order #'.$order->ID;
	  if ($siteConfig->ReceiptBody) $this->body = $siteConfig->ReceiptBody;
	  
	  if ($siteConfig->ReceiptFrom) $this->from = $siteConfig->ReceiptFrom;
	  elseif (Email::getAdminEmail()) $this->from = Email::getAdminEmail();
	  
	  if ($siteConfig->EmailSignature) $this->signature = $siteConfig->EmailSignature;

	  //Get css for Email by reading css file and put css inline for emogrification
	  $this->setTemplate('Order_ReceiptEmail');
	  if (file_exists(Director::getAbsFile($this->ThemeDir().'/css/Shop.css'))) {
	    $css = file_get_contents(Director::getAbsFile($this->ThemeDir().'/css/Shop.css'));
	  }
	  else {
	    $css = file_get_contents(Director::getAbsFile('shop/css/Shop.css'));
	  }

    $this->populateTemplate(
    	array(
    		'Message' => $this->Body(),
    		'Order' => $order,
    	  'Customer' => $customer,
    	  'InlineCSS' => "<style>$css</style>",
    	  'Signature' => $this->signature
    	)
    );

		parent::__construct($from, null, $subject, $body, $bounceHandlerURL, $cc, $bcc);
	}

}
