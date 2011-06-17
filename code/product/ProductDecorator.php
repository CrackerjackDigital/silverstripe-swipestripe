<?php
/**
 * Mixin for other data objects that are to represent products.
 * 
 * @author frankmullenger
 */
class ProductDecorator extends DataObjectDecorator {
  
  /**
   * Add fields for products such as Amount
   * 
   * @see DataObjectDecorator::extraStatics()
   */
	function extraStatics() {
		return array(
			'db' => array(
				'Amount' => 'Money',
			)
		);
	}
	
	/**
	 * Update the CMS with form fields for extra db fields above
	 * 
	 * @see DataObjectDecorator::updateCMSFields()
	 */
	function updateCMSFields(&$fields) {

	  //TODO: get allowed currencies from Payment class like:
	  //$amountField->setAllowedCurrencies(DPSAdapter::$allowed_currencies);
	  
		$amountField = new MoneyField('Amount', 'Amount');
		$amountField->setAllowedCurrencies(array(
		  'USD'=>'United States Dollar',
  		'NZD'=>'New Zealand Dollar'
  	));
		
  	//TODO: Assuming that the dataobject being decorated is a Page not ideal?
		$fields->addFieldToTab('Root.Content.Main', $amountField, 'Content');
	}
	
	/**
	 * Generate the get params for cart links
	 * 
	 * @see ProductDecorator::AddToCartLink()
	 * @see ProductDecorator::RemoveFromCartLink()
	 * @param String $productClass Class name of product
	 * @param Int $productID ID of product
	 * @param Int $quantity Quantity of product
	 * @param String $redirectURL URL to redirect to 
	 * @return String Get params joined by &
	 */
	private function generateGetString($productClass, $productID, $quantity = 1, $redirectURL = null) {
	  
	  $string = "ProductClass=$productClass&ProductID=$productID";
	  if ($quantity && is_numeric($quantity) && $quantity > 0) $string .= "&Quantity=$quantity";
	  if ($redirectURL && Director::is_site_url($redirectURL)) $string .= "&Redirect=$redirectURL"; 
	  return $string;
	}
	
	/**
	 * Helper to get URL for adding a product to the cart
	 * 
	 * @return String URL to add product to the cart
	 */
  function AddToCartLink($quantity = null, $redirectURL = null) {

		$getParams = $this->generateGetString(
		  $this->owner->ClassName, 
		  $this->owner->ID,
		  $quantity,
		  $redirectURL
		);
		return Director::absoluteURL(Controller::curr()->Link()."add/?".$getParams);
	}
	
	/**
	 * Helper to get URL for removing a product from the cart
	 * 
	 * @return String URL to remove a product from the cart
	 */
  function RemoveFromCartLink($quantity = null, $redirectURL = null) {

		$getParams = $this->generateGetString(
		  $this->owner->ClassName, 
		  $this->owner->ID,
		  $quantity,
		  $redirectURL
		);
		return Director::absoluteURL(Controller::curr()->Link()."remove/?".$getParams);
	}
	
	/**
	 * Helper to get URL for clearing the cart
	 * 
	 * @return String URL to clear the cart
	 */
	function ClearCartLink() {
	  return Director::absoluteURL(Controller::curr()->Link()."clear/");
	}
	
	/**
	 * Helper to get URL for the checkout page
	 * TODO if checkout page does not exist throw error
	 * 
	 * @return String URL for the checkout page
	 */
	function GoToCheckoutLink() {
		return DataObject::get_one('CheckoutPage')->Link();
	}
}


