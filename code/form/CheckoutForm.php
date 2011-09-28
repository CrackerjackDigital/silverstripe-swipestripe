<?php
class CheckoutForm extends Form {
  
  public $currentOrder;
  
  function __construct($controller, $name, FieldSet $fields, FieldSet $actions, $validator = null, Order $currentOrder = null) {
    
		parent::__construct($controller, $name, $fields, $actions, $validator);
		$this->setTemplate('CheckoutForm');
		$this->currentOrder = $currentOrder;
  }
  
  function Cart() {
    return $this->currentOrder;
  }

}