<?php

class Order extends DataObject {

	public static $db = array(
		'Status' => "Enum('Unpaid,Paid,Cart','Cart')",
	  'Total' => 'Money',
		'ReceiptSent' => 'Boolean'
	);
	
	public static $defaults = array(
	  'ReceiptSent' => false
	);

	public static $has_one = array(
		'Member' => 'Member'
	);

	public static $has_many = array(
	  'Items' => 'Item',
		'Payments' => 'Payment'
	);
	
	public static $table_overview_fields = array(
		'ID' => 'Order No',
		'Created' => 'Created',
		'Member.FirstName' => 'First Name',
		'Member.Surname' => 'Surname',
		'Total' => 'Total',
		'Status' => 'Status'
	);
	
	public static $summary_fields = array(
	  'ID' => 'Order No',
		'Created' => 'Created',
		'Member.Name' => 'Customer',
		'SummaryTotal' => 'Total',
		'Status' => 'Status'
	);
	
	public static $searchable_fields = array(
	  'ID' => array(
			'field' => 'TextField',
			'filter' => 'PartialMatchFilter',
			'title' => 'Order Number'
		),
		'Member.Surname' => array(
			'title' => 'Customer Surname',
			'filter' => 'PartialMatchFilter'
		),
		'Member.Email' => array(
			'title' => 'Customer Email',
			'filter' => 'PartialMatchFilter'
		)
	);
	
	/**
	 * Prevent orders from being created in the CMS
	 * 
	 * @see DataObject::canCreate()
	 */
  public function canCreate($member = null) {
    return false;
//		return Permission::check('ADMIN', 'any', $member);
	}
	
	/**
	 * Prevent orders from being deleted in the CMS
	 * @see DataObject::canDelete()
	 */
  public function canDelete($member = null) {
    return false;
//		return Permission::check('ADMIN', 'any', $member);
	}
	
	/**
	 * Set CMS fields for viewing this Order in the CMS
	 * Cannot change status of an order in the CMS
	 * 
	 * @see DataObject::getCMSFields()
	 */
	public function getCMSFields() {
	  $fields = parent::getCMSFields();
	  
	  $fields->insertBefore(new LiteralField('Title',"<h2>Order #$this->ID - ".$this->dbObject('Created')->Format('g:i a, j M y')." - ".$this->Member()->getName()."</h2>"),'Root');
	  
	  $toBeRemoved = array();
	  $toBeRemoved[] = 'MemberID';
	  $toBeRemoved[] = 'Total';
	  $toBeRemoved[] = 'Items';
	  foreach($toBeRemoved as $field) {
			$fields->removeByName($field);
		}
		
		$htmlSummary = $this->renderWith("Order");
		$fields->addFieldToTab('Root.Main', new LiteralField('MainDetails', $htmlSummary));
		
		$fields->makeFieldReadonly('Status');

	  return $fields;
	}
	
	/**
	 * Helper to get a nicely formatted total of the order
	 * 
	 * @return String
	 */
	function SummaryTotal() {
	  return $this->dbObject('Total')->Nice();
	}
	
	/**
	 * Generate the URL for viewing this order on the frontend
	 * 
	 * @return String URL for viewing this order
	 */
	function Link() {
	  //get the account page and go to it
	  $account = DataObject::get_one('AccountPage');
		return $account->Link()."order/$this->ID";
	}

	/**
	 * Helper to get payments made for this order
	 * 
	 * @return DataObjectSet Set of Payment objects
	 */
	function Payments() {
	  $payments = DataObject::get('Payment', "PaidForID = $this->ID AND PaidForClass = '$this->class'");
	  return $payments;
	}
	
	/**
	 * Calculate the total outstanding for this order that remains to be paid,
	 * all payments except 'Failure' payments are considered
	 * 
	 * @return Money With value and currency of total outstanding
	 */
	function TotalOutstanding() {
	  $total = $this->Total->getAmount();

	  foreach ($this->Payments() as $payment) {
	    if ($payment->Status != 'Failure') {
	      $total -= $payment->Amount->getAmount();
	    }
	  }
	  
	  $outstanding = new Money();
	  $outstanding->setAmount($total);
	  $outstanding->setCurrency($this->Total->getCurrency());
	  
	  return $outstanding;
	}
	
	/**
	 * Calculate the total paid for this order, only 'Success' payments
	 * are considered.
	 * 
	 * @return Money With value and currency of total paid
	 */
	function TotalPaid() {
	   $paid = 0;
	   
	  foreach ($this->Payments() as $payment) {
	    if ($payment->Status == 'Success') {
	      $paid += $payment->Amount->getAmount();
	    }
	  }
	  
	  $totalPaid = new Money();
	  $totalPaid->setAmount($paid);
	  $totalPaid->setCurrency($this->Total->getCurrency());
	  
	  return $totalPaid;
	}
	
	/**
	 * Processed if payment is successful
	 * 
	 * @see PaymentDecorator::onAfterWrite()
	 */
	function onAfterPayment() {
	  
	  $this->updateStatus();

		if(!$this->ReceiptSent){
			$this->sendReceipt();
		}
	}
	
	/**
	 * Update the order status after payment
	 * 
	 * @see Order::onAfterPayment()
	 */
	private function updateStatus() {

	  if ($this->getPaid()) {
	    $this->Status = 'Paid';
	    $this->write();
	    
	    //TODO: Notify the customer that their order is successful unless sending receipt at same time
	  }
	  elseif ($this->Status == 'Cart') {
	    $this->Status = 'Unpaid';
	    $this->write();
	  }
	}
	
	/**
	 * If the order has been totally paid
	 * 
	 * @return Boolean
	 */
	public function getPaid() {
	  return ($this->TotalPaid()->getAmount() == $this->Total->getAmount());
	}
	
	/**
	 * Get sender for the receipt emails
	 * 
	 * @see OrderConfigDecorator::extraStatics()
	 * @return Mixed Email address or empty string
	 */
	function getReceiptFrom() {
	  $siteConfig = SiteConfig::current_site_config();
	  if (!$email = $siteConfig->ReceiptFrom) {
	    $email = Email::getAdminEmail();
	  }
	  return $email;
	}
	
	/**
	 * Get the subject for the receipt email
	 * 
	 * @see OrderConfigDecorator::extraStatics()
	 * @return Mixed String or false if no subject exists
	 */
	function getReceiptSubject() {
	  $siteConfig = SiteConfig::current_site_config();
	  if ($subject = $siteConfig->ReceiptFrom) {
	    return $subject;
	  }
	  return false;
	}
	
	/**
	 * Get the body message for the receipt email
	 * 
	 * @see OrderConfigDecorator::extraStatics()
	 * @return Mixed String or false if nobody exists
	 */
	function getReceiptBody() {
	  $siteConfig = SiteConfig::current_site_config();
	  if ($body = $siteConfig->ReceiptBody) {
	    return $body;
	  }
	  return false;
	}
	
	/**
	 * Sending a receipt to the new customer
	 * Using ProcessedEmail class in order to use Emogrifier
	 * 
	 * @return Boolean True if sending email worked
	 */
	function sendReceipt() {

	  $customer = $this->Member();

	  $receipt = new ProcessedEmail(
	    $from = $this->getReceiptFrom(),
	    $to = $customer->Email, 
	    $subject = $this->getReceiptSubject(), 
	    $body = $this->getReceiptBody()
	  );
	  
	  $receipt->setTemplate('Order_ReceiptEmail');

	  //Get css for Email by reading css file and put css inline for emogrification
	  if (file_exists(Director::getAbsFile($this->ThemeDir().'/css/OrderReport.css'))) {
	    $css = file_get_contents(Director::getAbsFile($this->ThemeDir().'/css/OrderReport.css'));
	  }
	  else {
	    $css = file_get_contents(Director::getAbsFile('simplecart/css/OrderReport.css'));
	  }

	  $receipt->populateTemplate(
			array(
				'Message' => $this->getReceiptBody(),
				'Order' => $this,
			  'Customer' => $this->Member(),
			  'InlineCSS' => "<style>$css</style>"
			)
		);

	  if ($receipt->send() && !$this->ReceiptSent) {
	    $this->ReceiptSent = true;
	    $this->write();
	    return true;
	  }
	  else {
	    return false;
	  }
	}
	
	/**
	 * Add an item to the order representing the product, 
	 * if an item for this product exists increase the quantity
	 * 
	 * @param DataObject $product The product to be represented by this order item
	 */
	function addItem(DataObject $product) {
	  
	  //
	  $this->Total->setAmount($this->Total->getAmount() + $product->Amount->getAmount()); 
    $this->Total->setCurrency($product->Amount->getCurrency()); 
    $this->write();
    
    //Incrememnt the quantity if this item exists already
    $item = $this->Items()->find('ObjectID', $product->ID);
    
    if ($item && $item->exists()) {
      $item->Quantity = $item->Quantity + 1;
      $item->write();
    }
    else {
      $item = new Item();
      $item->ObjectID = $product->ID;
      $item->ObjectClass = $product->class;
      $item->Amount->setAmount($product->Amount->getAmount());
      $item->Amount->setCurrency($product->Amount->getCurrency());
      $item->OrderID = $this->ID;
      $item->write();
    }
	}
	
	/**
	 * Decrease quantity of an item or remove it if quantity = 1
	 * 
	 * @param DataObject $product The product to remove
	 */
	function removeItem(DataObject $product) {

    $item = $this->Items()->find('ObjectID', $product->ID);
    
    $this->Total->setAmount($this->Total->getAmount() - $item->Amount->getAmount()); 
    $this->write();
    
    if ($item && $item->exists()) {
      if ($item->Quantity == 1) {
        $item->delete();
      }
      else {
        $item->Quantity = $item->Quantity - 1;
        $item->write();
      }
    }
	}
	
	/**
	 * Retrieving the downloadable virtual products for this order
	 * 
	 * @return DataObjectSet Items for this order that can be downloaded
	 */
	function Downloads() {
	  
	  $virtualItems = new DataObjectSet();
	  $items = $this->Items();
	  
	  foreach ($items as $item) {
	    
	    if (isset($item->Object()->FileLocation) && $item->Object()->FileLocation) {
	      $virtualItems->push($item);
	    }
	  }
	  return $virtualItems;
	}
	
	/**
	 * Retreive products for this order from the order items.
	 * 
	 * @return DataObjectSet Set of Products, likely to be children of Page class
	 */
	function Products() {
	  $items = $this->Items();
	  $products = new DataObjectSet();
	  foreach ($items as $item) {
	    $products->push($item->Object());
	  }
	  return $products;
	}
	
	/**
	 * Helper to summarize payment status for an order.
	 * 
	 * @return String List of payments and their status
	 */
	function PaymentStatus() {
	  $payments = $this->Payments();
	  $status = null;

	  if ($payments->Count() == 1) {
	    $status = 'Payment ' . $payments->First()->Status;
	  }
	  else {
	    $statii = array();
  	  foreach ($payments as $payment) {
  	    $statii[] = "Payment #$payment->ID $payment->Status";
  	  }
  	  $status = implode(', ', $statii);
	  }
	  return $status;
	}

}
