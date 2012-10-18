<?php
/**
 * An option for an {@link Item} in the {@link Order}. Items can have many ItemOptions.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage order
 */
class ItemOption extends DataObject {

  /**
   * DB fields for this class
   * 
   * @var Array
   */
	public static $db = array(
	  // 'ObjectID' => 'Int',
	  // 'ObjectClass' => 'Varchar',
	  // 'ObjectVersion' => 'Int',
	  'Description' => 'Varchar',
	  'Price' => 'Decimal(19,4)',
    'Currency' => 'Varchar(3)',
	);

	public function Amount() {

		// TODO: Multi currency

    $amount = new Price();
		$amount->setCurrency($this->Currency);
    $amount->setAmount($this->Price);
    $amount->setSymbol(ShopConfig::current_shop_config()->BaseCurrencySymbol);
    return $amount;
  }

	/**
	 * Relations for this class
	 * 
	 * @var Array
	 */
	public static $has_one = array(
	  'Item' => 'Item'
	);
	
	/**
	 * Retrieve the object this item represents, usually a {@link Variation}.
	 * Uses the object version so that the correct object details such as price are
	 * retrieved.
	 * 
	 * @return DataObject 
	 */
	// function Object() {
	// 	$objectClass = $this->ObjectClass;
	// 	return $objectClass::get("\"ID\" = " . $this->ObjectID);

	//   //return Versioned::get_version($this->ObjectClass, $this->ObjectID, $this->ObjectVersion);
	// }
	
	/**
	 * By default all ItemOptions are valid.
	 * 
	 * @see DataObject::validate()
	 */
	// function validate() {
	//   return parent::validate();
	// }

 //  public function onAfterWrite() {

 //    // //Update stock levels if a variation is being saved here
 //    // parent::onAfterWrite();

 //    // if (ShopConfig::current_shop_config()->StockManagement == 'strict') {
 //    // 	$item = $this->Item();
	//    //  $variation = $this->Object();
	// 	  // if ($variation && $variation->exists() && $variation instanceof Variation) {
	// 	  //   $item->updateStockLevels();
	// 	  // }
 //    // }
	// }
}
