<?php
/**
 * 
 * Shipping abstract class
 * 
 * @author frankmullenger
 *
 */
class Shipping extends DataObject {
	
	public static $supported_methods = array(
		//'FlatFeeShipping',
		//'PerItemShipping'
	);
	
	/**
	 * Get al the fields from all the shipping modules that are enabled
	 * 
	 * @param Order $order
	 */
	static function combined_form_fields($order) {
	  $fields = new FieldSet();
	  
	  foreach (self::$supported_methods as $className) {
	    
	    $method = new $className();
	    $methodFields = $method->getFormFields($order);
	    
	    if ($methodFields && $methodFields->exists()) foreach ($methodFields as $field) {
	      $fields->push($field);
	    } 
	  }
	  return $fields;
	}
	
  function getFormFields($order) {
	  user_error("Please implement getFormFields() on $this->class", E_USER_ERROR);
	}
	
	function getFormRequirements() {
	  user_error("Please implement getFormRequirements() on $this->class", E_USER_ERROR);
	}
	
	function Amount($optionID, $order) {
	  return;
	}
	
	function Description($optionID) {
    return;
	}

}