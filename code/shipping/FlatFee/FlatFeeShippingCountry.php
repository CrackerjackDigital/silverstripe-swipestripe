<?php
/**
 * 
 * Flat fee shipping countries
 * 
 * @author frankmullenger
 *
 */
class FlatFeeShippingCountry extends DataObject {
  
  public static $db = array(
    'CountryCode' => 'Varchar(2)', //Two letter country codes for ISO 3166-1 alpha-2
    'Amount' => 'Money'
	);
	
	static $has_one = array (
    'SiteConfig' => 'SiteConfig'
  );
	
  public function getCMSFields_forPopup() {

    $fields = new FieldSet();
    
    $amountField = new MoneyField('Amount');
		$amountField->setAllowedCurrencies(Product::$allowed_currency);
    $fields->push($amountField);
    
    $countryField = new DropdownField('CountryCode', 'Country', Shipping::supported_countries());
    $fields->push($countryField);

    return $fields;
  }
  
  public function AmountSummary() {
    return $this->Amount->Nice();
  }
	
}