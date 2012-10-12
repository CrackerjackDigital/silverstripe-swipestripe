<?php
/**
 * Order, created as soon as a user adds a {@link Product} to their cart, the cart is 
 * actually an Order with status of 'Cart'. Has many {@link Item}s and can have {@link Modification}s
 * which might represent a {@link Modifier} like shipping, tax, coupon codes.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage order
 */
class Order extends DataObject implements PermissionProvider {
  
  /**
   * Order status once Order has been made, waiting for payment to clear/be approved
   * 
   * @var String
   */
  const STATUS_PENDING = 'Pending';
  
  /**
   * Order status once payment approved, order being processed before being dispatched
   * 
   * @var String
   */
  const STATUS_PROCESSING = 'Processing';
  
  /**
   * Order status once Order has been sent
   * 
   * @var String
   */
  const STATUS_DISPATCHED = 'Dispatched';

  /**
   * DB fields for Order, such as Stauts, Payment Status etc.
   * 
   * @var Array
   */
	public static $db = array(
		'Status' => "Enum('Pending,Processing,Dispatched,Cancelled,Cart','Cart')",
	  'PaymentStatus' => "Enum('Unpaid,Paid','Unpaid')",
	  'TotalPrice' => 'Decimal(19,4)',
    'TotalCurrency' => 'Varchar(3)',
    'SubTotalPrice' => 'Decimal(19,4)',
    'SubTotalCurrency' => 'Varchar(3)',
	  'OrderedOn' => 'SS_Datetime',
	  'LastActive' => 'SS_Datetime',
	  'Notes' => 'Text'
	);

	public function Total() {

		// TODO: Multi currency

    $amount = new Price();
		$amount->setCurrency($this->TotalCurrency);
    $amount->setAmount($this->TotalPrice);
    $amount->setSymbol(ShopConfig::current_shop_config()->BaseCurrencySymbol);
    return $amount;
  }

  public function SubTotal() {

  	// TODO: Multi currency

    $amount = new Price();
		$amount->setCurrency($this->SubTotalCurrency);
    $amount->setAmount($this->SubTotalPrice);
    $amount->setSymbol(ShopConfig::current_shop_config()->BaseCurrencySymbol);
    return $amount;
  }
	
	/**
	 * Default values for Order
	 * 
	 * @var Array
	 */
	public static $defaults = array(
	  'ReceiptSent' => false,
	  'NotificationSent' => false
	);

	/**
	 * Relations for this Order
	 * 
	 * @var Array
	 */
	public static $has_one = array(
	  'Member' => 'Customer'
	);

	/*
	 * Relations for this Order
	 * 
	 * @var Array
	 */
	public static $has_many = array(
	  'Items' => 'Item',
		'Payments' => 'Payment',
	  'Modifications' => 'Modification',
	  'Addresses' => 'Address'
	);
	
	/**
	 * Summary fields for displaying Orders in the admin area
	 * 
	 * @var Array
	 */
	public static $summary_fields = array(
	  'ID' => 'Order No',
		'OrderedOn' => 'Ordered On',
		'Member.Name' => 'Customer',
		'SummaryOfTotal' => 'Total',
		'Status' => 'Status'
	);
	
	/**
	 * Searchable fields with search filters
	 * 
	 * @var Array
	 */
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
		),
		'HasPayment' => array(
			'filter' => 'PaymentSearchFilter',
		),
  	'Status' => array(
  	  'title' => 'Status',
  		'filter' => 'OptionSetSearchFilter',
  	)
	);

	/**
	 * Castings for the searchable fields
	 * 
	 * @var Array
	 */
	public static $casting = array(
		'HasPayment' => 'Varchar'
	);
	
	/**
	 * The default sort expression. This will be inserted in the ORDER BY
	 * clause of a SQL query if no other sort expression is provided.
	 * 
	 * @see ShopAdmin
	 * @var String
	 */
	public static $default_sort = 'ID DESC';

	function providePermissions() {
    return array(
      'VIEW_ORDER' => 'View orders'
    );
  }

  function canView($member = null) {

		if ($member == null && !$member = Member::currentUser()) return false;

    $administratorPerm = Permission::check('ADMIN', 'any', $member);
    $customerPerm = Permission::check('VIEW_ORDER', 'any', $member) && $member->ID == $this->MemberID;

    return $administratorPerm || $customerPerm;
	}
	
	/**
	 * Prevent orders from being created in the CMS
	 * 
	 * @see DataObject::canCreate()
	 * @return Boolean False always
	 */
  public function canCreate($member = null) {
    return false;
	}
	
	/**
	 * Prevent orders from being deleted in the CMS
	 * 
	 * @see DataObject::canDelete()
	 * @return Boolean False always
	 */
  public function canDelete($member = null) {
    return false;
	}

  /**
	 * Clean up Order Items (ItemOptions by extension), Addresses and Modifications.
	 * All wrapped in a transaction.
	 */
	public function delete() {

	  try {

	  	DB::getConn()->transactionStart();

	    $items = $this->Items();
	    if ($items && $items->exists()) foreach ($items as $item) {
        $item->delete();
        $item->destroy();
	    }
	    
	    $addresses = $this->Addresses();
	    if ($addresses && $addresses->exists()) foreach ($addresses as $address) {
	      $address->delete();
	      $address->destroy();
	    }
	    
	    $modifications = $this->Modifications();
	    if ($modifications && $modifications->exists()) foreach ($modifications as $modification) {
	      $modification->delete();
	      $modification->destroy();
	    }
	    
	    parent::delete();
	    DB::getConn()->transactionEnd();

	  }
	  catch (Exception $e) {
	    DB::getConn()->transactionRollback();
	    SS_Log::log(new Exception(print_r($e->getMessage(), true)), SS_Log::NOTICE);
	    //TODO: Show an error to the customer here?
	  }

	  if ($this->canDelete(Member::currentUser())) {
      parent::delete();
    }
	}

	/**
	 * Filters for order admin area search.
	 * 
	 * @see DataObject::scaffoldSearchFields()
	 * @return FieldSet
	 */
  public function scaffoldSearchFields(){

  	Requirements::customCSS('
			.west .optionset li {
				width: 100%;
			}
		');

		$fieldSet = parent::scaffoldSearchFields();

		$fieldSet->push(CheckboxSetField::create('HasPayment', 'Has Payment', array(
		  1 => 'Yes',
		  2 => 'No'
		)));

		$fieldSet->push(new CheckboxSetField('Status', 'Status', array(
		  'Pending' => 'Pending',
		  'Processing' => 'Processing',
		  'Dispatched' => 'Dispatched'
		)));
		return $fieldSet;
	}

	/**
	 * Set the LastActive time when {@link Order} first created.
	 * 
	 * (non-PHPdoc)
	 * @see DataObject::onBeforeWrite()
	 */
	public function onBeforeWrite() {
    parent::onBeforeWrite();
    if (!$this->ID) $this->LastActive = SS_Datetime::now()->getValue();
  }

  public function onAfterWrite() {
  	parent::onAfterWrite();

  	//If status has changed from Cart reduce the stock
  	//If status has changed to Cancelled increase the stock
  }

  /**
	 * Processed if payment is successfully written, send a receipt to the customer
	 * and notification to the admin
	 * 
	 * @see Payment_Extension::onAfterWrite()
	 */
	public function onAfterPayment() {

		$this->Status = ($this->getPaid()) ? self::STATUS_PROCESSING :  self::STATUS_PENDING;
	  $this->PaymentStatus = ($this->getPaid()) ? 'Paid' : 'Unpaid';
		$this->write();

		ReceiptEmail::create($this->Member(), $this)
			->send();
		NotificationEmail::create($this->Member(), $this)
			->send();

	  $this->extend('onAfterPayment');
	}
	
	/**
	 * Set CMS fields for viewing this Order in the CMS
	 * Cannot change status of an order in the CMS
	 * 
	 * @see DataObject::getCMSFields()
	 */
	public function getCMSFields() {

		$fields = new FieldList();

    $fields->push(new TabSet('Root', 
      Tab::create('Order'),
      Tab::create('Actions')
    ));

    $fields->addFieldToTab('Root.Order', new LiteralField(
    	'Title', 
    	"<h2>Order #$this->ID - ".$this->dbObject('Created')->Format('g:i a, j M y')." - ".$this->Member()->getName()."</h2>"
    ));

    $htmlSummary = $this->customise(array(
			'MemberEmail' => $this->Member()->Email
		))->renderWith("OrderAdmin");
		$fields->addFieldToTab('Root.Order', new LiteralField('MainDetails', $htmlSummary));

		//Action fields
		$fields->addFieldToTab('Root.Actions', new HeaderField('OrderStatus', 'Order Status', 3));
		$statuses = $this->dbObject('Status')->enumValues();
		//unset($statuses['Cart']);
		$fields->addFieldToTab('Root.Actions', new DropdownField('Status', 'Status', $statuses));
		
		$fields->addFieldToTab('Root.Actions', new HeaderField('PaymentStatus', 'Payments Status', 3));
		$fields->addFieldToTab('Root.Actions', new LiteralField('PaymentStatusP', "<p>Payment status of this order is currently <strong>$this->PaymentStatus</strong>.</p>"));
    //$fields->addFieldToTab('Root.Actions', new DropdownField('PaymentStatus', 'Payment Status', $this->dbObject('PaymentStatus')->enumValues()));
		
		if ($this->Payments()) foreach ($this->Payments() as $item) {
		  
		  $customerName = (DataObject::get_by_id('Member', $item->PaidByID)) ? DataObject::get_by_id('Member', $item->PaidByID)->getName() : '';
		  $value = $item->dbObject('Amount')->Nice();
		  $date = $item->dbObject('Created')->Format('j M y g:i a');
		  $paymentType = implode(' ', preg_split('/(?<=\\w)(?=[A-Z])/', get_class($item)));
		  
		  $paymentMessage = $item->Message;
		  $paymentMessage = '';

		  $fields->addFieldToTab('Root.Actions', new DropdownField(
		  	'Payments['.$item->ID.']', 
		  	"$paymentType by $customerName <br />$value <br />$date <br />$paymentMessage", 
		    singleton('Payment')->dbObject('Status')->enumValues(),
		    $item->Status
		  ));
		}
		
		//Ability to edit fields added to CMS here
		$this->extend('updateOrderCMSFields', $fields);

    return $fields;
	}
	
	/**
	 * Set custom CMS actions which call 
	 * OrderAdmin_RecordController actions of the same name
	 * 
	 * @see DataObject::getCMSActions()
	 * @return FieldList
	 */
	public function getCMSActions() {
	  $actions = parent::getCMSActions();
	  return $actions;
	}
	
	/**
	 * Helper to get a nicely formatted total of the order
	 * 
	 * @return String Order total formatted with Nice()
	 */
	public function SummaryOfTotal() {
	  return $this->Total()->Nice();
	}
	
	/**
	 * Generate the URL for viewing this order on the frontend
	 * 
	 * @see PaypalExpressCheckoutaPayment_Handler::doRedirect()
	 * @return String URL for viewing this order
	 */
	public function Link() {
	  //get the account page and go to it
	  $account = DataObject::get_one('AccountPage');
		return $account->Link()."order/$this->ID";
	}

	/**
	 * Helper to get {@link Payment}s that are made against this Order
	 * 
	 * @return ArrayList Set of Payment objects
	 */
	public function Payments() {
		return Payment::get()
			->where("\"OrderID\" = {$this->ID}");
	}
	
	/**
	 * Calculate the total outstanding for this order that remains to be paid,
	 * all payments except 'Failure' payments are considered
	 * 
	 * @return Money With value and currency of total outstanding
	 */
	public function TotalOutstanding() {
	  $total = $this->Total()->getAmount();

	  foreach ($this->Payments() as $payment) {
	    if ($payment->Status != 'Failure') {
	      $total -= $payment->Amount->getAmount();
	    }
	  }
	  
	  //Total outstanding cannot be negative 
	  if ($total < 0) $total = 0;

	  // TODO: Multi currency
	  
	  $outstanding = new Price();
	  $outstanding->setAmount($total);
	  $outstanding->setCurrency($this->Total()->getCurrency());
	  $outstanding->setSymbol(ShopConfig::current_shop_config()->BaseCurrencySymbol);
	  
	  return $outstanding;
	}
	
	/**
	 * Calculate the total paid for this order, only 'Success' payments
	 * are considered.
	 * 
	 * @return Price With value and currency of total paid
	 */
	public function TotalPaid() {
	   $paid = 0;
	   
	  if ($this->Payments()) foreach ($this->Payments() as $payment) {
	    if ($payment->Status == 'Success') {
	      $paid += $payment->Amount->getAmount();
	    }
	  }
	  
	  $totalPaid = new Price();
	  $totalPaid->setAmount($paid);
	  $totalPaid->setCurrency($this->Total()->getCurrency());
	  
	  return $totalPaid;
	}
	
	/**
	 * If the order has been totally paid.
	 * 
	 * @return Boolean
	 */
	public function getPaid() {
	  return $this->TotalPaid()->getAmount() == $this->Total()->getAmount();
	}
	
	/**
	 * Add an item to the order representing the product, 
	 * if an item for this product exists increase the quantity. Update the Order total afterward.
	 * 
	 * @param DataObject $product The product to be represented by this order item
	 * @param ArrayList $productOptions The product variations to be added, usually just one
	 */
	public function addItem(DataObject $product, $quantity = 1, ArrayList $productOptions = null) {

	  //Check that product options exist if product requires them
	  //TODO perform this validation in Item->validate(), cannot at this stage because Item is written before ItemOption, no transactions, chicken/egg problem
	  if ((!$productOptions || !$productOptions->exists()) && $product->requiresVariation()) {
	    user_error("Cannot add item to cart, product options are required.", E_USER_WARNING);
	    //Debug::friendlyError();
	    return;
	  }

    //Increment the quantity if this item exists already
    $item = $this->findIdenticalItem($product, $productOptions);
    
    if ($item && $item->exists()) {
      $item->Quantity = $item->Quantity + $quantity;
      $item->write();
    }
    else {

      //TODO this needs transactions for Item->validate() to check that ItemOptions exist for Item before it is written
      $item = new Item();
      $item->ObjectID = $product->ID;
      $item->ObjectClass = $product->class;
      $item->ObjectVersion = $product->Version;

      $item->Price = $product->Amount()->getAmount();
      $item->Currency = $product->Amount()->getCurrency();

      $item->Quantity = $quantity;
      $item->OrderID = $this->ID;
      $item->write();
      
      
      if ($productOptions && $productOptions->exists()) foreach ($productOptions as $productOption) {
        
        $itemOption = new ItemOption();
        $itemOption->ObjectID = $productOption->ID;
        $itemOption->ObjectClass = $productOption->class;
        $itemOption->ObjectVersion = $productOption->Version;

        $itemOption->Price = $productOption->Amount()->getAmount();
        $itemOption->Currency = $productOption->Amount()->getCurrency();

        $itemOption->ItemID = $item->ID;
        $itemOption->write();
      }
    }
    
    $this->updateTotal();
	}
	
	/**
	 * Find an identical item in the order/cart, item is identical if the 
	 * productID, version and the options for the item are the same. Used to increase 
	 * quantity of items that already exist in the cart/Order.
	 * 
	 * @see Order::addItem()
	 * @param DatObject $product
	 * @param ArrayList $productOptions
	 * @return DataObject
	 */
	public function findIdenticalItem($product, ArrayList $productOptions) {
	  
	  foreach ($this->Items() as $item) {

	    if ($item->ObjectID == $product->ID && $item->ObjectVersion == $product->Version) {
	      
  	    $productOptionsMap = array();
  	    $existingOptionsMap = array();
  	    
    	  if ($productOptions) {
    	    $productOptionsMap = $productOptions->map('ID', 'Version');
    	  }

    	  if ($item) foreach ($item->ItemOptions() as $itemOption) {
    	    $productOption = $itemOption->Object();
    	    $existingOptionsMap[$productOption->ID] = $productOption->Version;
    	  }
    	  
    	  if ($productOptionsMap == $existingOptionsMap) {
    	    return $item;
    	  }
	    }
	  }
	}
	
	/**
	 * Go through items and modifiers and update cart total
	 * 
	 * Had to use DataObject::get() to retrieve Items because
	 * $this->Items() was not returning any items after first call
	 * to $this->addItem().
	 */
	public function updateTotal() {
	  
	  $total = 0;
	  $subTotal = 0;
	  $items = DataObject::get('Item', 'OrderID = '.$this->ID);
	  $modifications = DataObject::get('Modification', 'OrderID = '.$this->ID);
	  $shopConfig = ShopConfig::current_shop_config();
	  
	  if ($items) foreach ($items as $item) {
	    $total += $item->Total()->Amount;
	    $subTotal += $item->Total()->Amount;
	  }

	  if ($modifications) foreach ($modifications as $modification) {
	    
	    if ($modification->SubTotalModifier) {
	      $total += $modification->Amount()->getAmount();
	      $subTotal += $modification->Amount()->getAmount();
	    }
	    else {
	      $total += $modification->Amount()->getAmount();
	    }
	  }

    $this->SubTotalPrice = $subTotal; 
    $this->SubTotalCurrency = $shopConfig->BaseCurrency;

	  $this->TotalPrice = $total; 
	  $this->SubTotalCurrency = $shopConfig->BaseCurrency;

    $this->write();
	}

	/**
	 * Retreive products for this order from the order {@link Item}s.
	 * 
	 * @return ArrayList Set of {@link Product}s
	 */
	public function Products() {
	  $items = $this->Items();
	  $products = new ArrayList();
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
	public function SummaryOfPaymentStatus() {
	  $payments = $this->Payments();
	  $status = null;

	  if ($payments instanceof DataList) {
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
	  }
	  return $status;
	}

	/**
	 * Save modifiers for this Order at the checkout process. 
	 * 
	 * @param Array $data
	 */
	public function addModifiersAtCheckout(Array $data) {

	  //Remove existing Modifications
    $existingModifications = $this->Modifications();
    foreach ($existingModifications as $modification) {
      $modification->delete();
    }

    //Save new Modifications
	  if (isset($data['Modifiers']) && is_array($data['Modifiers'])) foreach ($data['Modifiers'] as $modifierClass => $value) {
	    
	    if (class_exists($modifierClass)) {
	      $modifier = new $modifierClass();
	      $modifier->addToOrder($this, $value);
	    }
	  }
	  $this->updateTotal();
	}
	
	/**
	 * Add addresses to this Order at the checkout.
	 * 
	 * @param Array $data
	 */
	public function addAddressesAtCheckout(Array $data) {

	  $member = Customer::currentUser() ? Customer::currentUser() : singleton('Customer');
    $order = Cart::get_current_order();
    
    $billingCountries = Country::billing_countries();
    $shippingCountries = Country::shipping_countries();
    $shippingRegions = Region::shipping_regions();

    //If there is a current billing and shipping address, update them, otherwise create new ones
    $existingBillingAddress = $this->BillingAddress();
    $existingShippingAddress = $this->ShippingAddress();

    if ($existingBillingAddress && $existingBillingAddress->exists()) {
      $newData = array();
      if (isset($data['Billing']) && is_array($data['Billing'])) foreach ($data['Billing'] as $fieldName => $value) {
        $newData[$fieldName] = $value;
      }
      
      $newData['CountryID'] = $data['Billing']['Country'];
      $newData['CountryName'] = (in_array($newData['CountryID'], array_keys($billingCountries))) 
  	    ? $billingCountries[$newData['CountryID']] 
  	    : null;
  	    
      if ($member->ID) $newData['MemberID'] = $member->ID;
      $existingBillingAddress->update($newData);
      $existingBillingAddress->write();
    }
    else {
      $billingAddress = new Address();
  	  $billingAddress->OrderID = $order->ID;
  	  if ($member->ID) $billingAddress->MemberID = $member->ID;
  	  $billingAddress->FirstName = $data['Billing']['FirstName'];
  	  $billingAddress->Surname = $data['Billing']['Surname'];
  	  $billingAddress->Company = $data['Billing']['Company'];
  	  $billingAddress->Address = $data['Billing']['Address'];
  	  $billingAddress->AddressLine2 = $data['Billing']['AddressLine2'];
  	  $billingAddress->City = $data['Billing']['City'];
  	  $billingAddress->PostalCode = $data['Billing']['PostalCode'];
  	  $billingAddress->State = $data['Billing']['State'];
  	  $billingAddress->CountryID = $data['Billing']['Country'];

  	  $billingAddress->CountryName = (in_array($data['Billing']['Country'], array_keys($billingCountries))) 
  	    ? $billingCountries[$data['Billing']['Country']] 
  	    : null;
  	  
  	  $billingAddress->Type = 'Billing';
  	  $billingAddress->write();
    }

    if ($existingShippingAddress && $existingShippingAddress->exists()) {
      $newData = array();
      if (isset($data['Shipping']) && is_array($data['Shipping'])) foreach ($data['Shipping'] as $fieldName => $value) {
        $newData[$fieldName] = $value;
      }
      
      $newData['CountryID'] = $data['Shipping']['Country'];
      $newData['CountryName'] = (in_array($newData['CountryID'], array_keys($shippingCountries))) 
  	    ? $shippingCountries[$newData['CountryID']] 
  	    : null;
  	    
  	  
  	  if (isset($newData['Region']) && isset($shippingRegions[$newData['Country']])) {
  	    if (in_array($newData['Region'], array_keys($shippingRegions[$newData['Country']]))) {
  	      $newData['RegionName'] = $shippingRegions[$newData['Country']][$newData['Region']];
  	    }
  	  }
  	  else $newData['RegionName'] = null;
  	  
      
      if ($member->ID) $newData['MemberID'] = $member->ID;
      $existingShippingAddress->update($newData);
      $existingShippingAddress->write();
    }
    else {
  	  $shippingAddress = new Address();
  	  $shippingAddress->OrderID = $order->ID;
  	  if ($member->ID) $shippingAddress->MemberID = $member->ID;
  	  $shippingAddress->FirstName = $data['Shipping']['FirstName'];
  	  $shippingAddress->Surname = $data['Shipping']['Surname'];
  	  $shippingAddress->Company = $data['Shipping']['Company'];
  	  $shippingAddress->Address = $data['Shipping']['Address'];
  	  $shippingAddress->AddressLine2 = $data['Shipping']['AddressLine2'];
  	  $shippingAddress->City = $data['Shipping']['City'];
  	  $shippingAddress->PostalCode = $data['Shipping']['PostalCode'];
  	  $shippingAddress->State = $data['Shipping']['State'];
  	  $shippingAddress->CountryID = $data['Shipping']['Country'];
  	  $shippingAddress->Region = (isset($data['Shipping']['Region'])) ? $data['Shipping']['Region'] : null;
  	  
  	  $shippingAddress->CountryName = (in_array($data['Shipping']['Country'], array_keys($shippingCountries))) 
  	    ? $shippingCountries[$data['Shipping']['Country']] 
  	    : null;
  	    
  	  $shippingAddress->RegionName = (isset($data['Shipping']['Region']) && isset($shippingRegions[$data['Shipping']['Country']]) && in_array($data['Shipping']['Region'], array_keys($shippingRegions[$data['Shipping']['Country']]))) 
  	    ? $shippingRegions[$data['Shipping']['Country']][$data['Shipping']['Region']] 
  	    : null; 
  	  
  	  $shippingAddress->Type = 'Shipping';
  	  $shippingAddress->write();
    }
	}
	
	/**
	 * Retrieve the billing {@link Address} for this Order.
	 * 
	 * @return Address
	 */
	public function BillingAddress() {
	  $address = null;
	  
	  $addresses = $this->Addresses();
	  if ($addresses && $addresses->exists()) {
	    $address = $addresses->find('Type', 'Billing');
	  }
	  
	  return $address;
	}
	
	/**
	 * Retrieve the shipping {@link Address} for this Order.
	 * 
	 * @return Address
	 */
	public function ShippingAddress() {
	  $address = null;
	  
	  $addresses = $this->Addresses();
	  if ($addresses && $addresses->exists()) {
	    $address = $addresses->find('Type', 'Shipping');
	  }
	  
	  return $address;
	}
	
	/**
	 * Valdiate this Order for use in Validators at checkout. Makes sure
	 * Items exist and each Item is valid.
	 * 
	 * @return ValidationResult
	 */
	public function validateForCart() {
	  
	  $result = new ValidationResult(); 
	  $items = $this->Items();
	  
	  if (!$items || !$items->exists()) {
	    $result->error(
	      'There are no items in this order',
	      'ItemExistsError'
	    );
	  }
	  
	  if ($items) foreach ($items as $item) {
	    
	    $validation = $item->validateForCart();
	    if (!$validation->valid()) {

	      $result->error(
  	      'Some of the items in this order are no longer available, please go to the cart and remove them.',
  	      'ItemValidationError'
  	    );
	    }
	  }
	  
	  return $result;
	}
	
	/**
	 * By default Orders are always valid
	 * 
	 * @see DataObject::validate()
	 */
	public function validate() {
	  return parent::validate();
	}
	
	/**
	 * Delete abandoned carts according to the Order timeout. This will release the stock 
	 * in the carts back to the shop. Can be run from a cron job task, also run on Product, Cart and
	 * Checkout pages so that cron job is not necessary.
	 * 
	 * @return Void
	 */
	public static function delete_abandoned() {

		$shopConfig = ShopConfig::current_shop_config();

		$timeout = DateInterval::createFromDateString($shopConfig->CartTimeout . ' ' . $shopConfig->CartTimeoutUnit);
		$ago = new DateTime();
		$ago->sub($timeout);

	  //Get orders that were last active over x ago according to shop config cart lifetime
	  $orders = Order::get()
	  	->where("\"Order\".\"LastActive\" < '" . $ago->format('Y-m-d H:i:s') . "' AND \"Order\".\"Status\" = 'Cart' AND \"Payment\".\"ID\" IS NULL")
	  	->leftJoin('Payment', "\"Payment\".\"OrderID\" = \"Order\".\"ID\"");

	  if ($orders && $orders->exists()) foreach ($orders as $order) {
      $order->delete();
      $order->destroy();      
	  }
	}
	
	/**
	 * Get modifications that apply changes to the Order sub total.
	 * 
	 * @return DataList Set of Modification DataObjects
	 */
	public function SubTotalModifications() {
	  $orderID = $this->ID;
	  return DataObject::get('Modification', "\"OrderID\" = $orderID AND \"SubTotalModifier\" = 1");
	}
	
	/**
	 * Get modifications that apply changes to the Order total (not the order sub total).
	 * 
	 * @return DataList Set of Modification DataObjects
	 */
	public function TotalModifications() {
	  $orderID = $this->ID;
	  return DataObject::get('Modification', "\"OrderID\" = $orderID AND \"SubTotalModifier\" = 0");
	}

}