<?php
/**
 * Testing {@link Product} stock, updating stock levels when products
 * added, removed and carts are deleted by scheduled tasks.
 * 
 * Summary of tests:
 * -----------------
 * add product to cart, stock is reduced in product without creating a new version of product
 * remove product from cart, stock is replenished for product
 * add product variation to cart, stock is reduced for product variation
 * remove product variation from cart, stock is replenished for product variation
 * stock level cannot be reduced < 0 for products and variations
 * stock level can be reduced to = 0 for products and variations
 * stock levels set at unlimited (-1) are unaffected by adding/removing to cart for Product and Variation
 * add to cart form disappears when 0 stock left for products & products with variations
 * variations out of stock are not available in add to cart form
 * scheduled task deletes order and associated objects, replenishes stock
 * 
 * TODO
 * ----
 * cannot add to cart when 0 stock - belt and braces, the add to cart form is hidden anyway
 * cannot checkout when 0 stock
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package shop
 * @subpackage tests
 * @version 1.0
 */
class StockLevelTest extends FunctionalTest {
  
	static $fixture_file = 'shop/tests/Shop.yml';
	static $disable_themes = true;
	static $use_draft_site = false;
	
  function setUp() {
		parent::setUp();

		//Check that payment module is installed
		$this->assertTrue(class_exists('Payment'), 'Payment module is installed.');
		
		//Need to publish a few pages because not using the draft site
		$checkoutPage = $this->objFromFixture('CheckoutPage', 'checkout');  
		$accountPage = $this->objFromFixture('AccountPage', 'account');
		$cartPage = $this->objFromFixture('CartPage', 'cart');
		
		$this->loginAs('admin');
	  $checkoutPage->doPublish();
	  $accountPage->doPublish();
	  $cartPage->doPublish();
	  $this->logOut();
	}
	
	/**
	 * Log current member out by clearing session
	 */
	function logOut() {
	  $this->session()->clear('loggedInAs');
	}
	
	/**
   * Helper to get data from a form.
   * 
   * @param String $formID
   * @return Array
   */
  function getFormData($formID) {
    $page = $this->mainSession->lastPage();
    $data = array();
    
    if ($page) {
  		$form = $page->getFormById($formID);
  		if (!$form) user_error("Function getFormData() failed to find the form {$formID}", E_USER_ERROR);
  
  	  foreach ($form->_widgets as $widget) {
  
  	    $fieldName = $widget->getName();
  	    $fieldValue = $widget->getValue();
  	    
  	    $data[$fieldName] = $fieldValue;
  	  }
    }
    else user_error("Function getFormData() called when there is no form loaded.  Visit the page with the form first", E_USER_ERROR);
    
    return $data;
  }
  
  /**
   * Check that stock level correct, considering an item is added to an order in the fixture.
   */
  function testStockLevels() {
    
    $productA = $this->objFromFixture('Product', 'productA');
    $this->assertEquals(4, $productA->StockLevel()->Level);
    
    $teeshirtExtraLargePurpleCotton = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
    $this->assertEquals('Enabled', $teeshirtExtraLargePurpleCotton->Status);
    $this->assertEquals(5, $teeshirtExtraLargePurpleCotton->StockLevel()->Level);
  }

	/**
	 * Add a product to the cart and reduce stock level of product without affecting versions of product
	 */
	function testAddProductToCartReduceStock() {

	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(4, $productA->StockLevel()->Level); //Stock starts one down because of orderOneItemOne
	  
	  $this->logInAs('admin');
	  $productA->doPublish();
	  $this->logOut();
	  
	  $versions = DB::query('SELECT * FROM "Product_versions" WHERE "RecordID" = ' . $productA->ID);
	  $versionsAfterPublished = array();
	  foreach ($versions as $versionRow) $versionsAfterPublished[] = $versionRow;
	  
	  $variations = $productA->Variations();
	  $this->assertEquals(false, $variations->exists());
	  
	  $this->get(Director::makeRelative($productA->Link())); 
	  $this->submitForm('AddToCartForm_AddToCartForm', null, array(
	    'Quantity' => 1
	  ));
	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(3, $productA->StockLevel()->Level);
	  
	  
	  $this->get(Director::makeRelative($productA->Link())); 
	  $this->submitForm('AddToCartForm_AddToCartForm', null, array(
	    'Quantity' => 2
	  ));
	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(1, $productA->StockLevel()->Level);
	  
	  //Make sure a new version of the product was NOT created 
	  $versions = DB::query('SELECT * FROM "Product_versions" WHERE "RecordID" = ' . $productA->ID);
	  $versionsAfterStockChanges = array();
	  foreach ($versions as $versionRow) $versionsAfterStockChanges[] = $versionRow;
	  
	  $this->assertTrue($versionsAfterPublished == $versionsAfterStockChanges);
	}
	
	/**
	 * remove a product from the cart and replenish stock levels
	 */
	function testRemoveProductFromCartReplaceStock() {
	  
	  
	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(4, $productA->StockLevel()->Level); //Stock starts one down because of orderOneItemOne
	  
	  $this->logInAs('admin');
	  $productA->doPublish();
	  $this->logOut();
	  
	  $this->loginAs('buyer');
	  $buyer = $this->objFromFixture('Member', 'buyer');
    
	  $this->get(Director::makeRelative($productA->Link())); 
	  $this->submitForm('AddToCartForm_AddToCartForm', null, array(
	    'Quantity' => 3
	  ));
	  
	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(1, $productA->StockLevel()->Level);
	  
	  
	  //Remove the Item from the Order
	  $cartPage = $this->objFromFixture('CartPage', 'cart');
	  $this->get(Director::makeRelative($cartPage->Link()));

	  $order = CartControllerExtension::get_current_order();
	  $item = $order->Items()->First();
	  
	  $this->submitForm('CartForm_CartForm', null, array(
	    "Quantity[{$item->ID}]" => 0
	  ));
	  
	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(4, $productA->StockLevel()->Level);
	}

	/**
	 * Add a product variation to the cart, reduce stock level for variation without creating
	 * a new version for the variation
	 */
	function testAddProductVariationToCartReduceStock() {

	  $teeshirtA = $this->objFromFixture('Product', 'teeshirtA');

    $this->logInAs('admin');
	  $teeshirtA->doPublish();
	  $this->logOut();
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals('Enabled', $teeshirtAVariation->Status);
	  $this->assertEquals(5, $teeshirtAVariation->StockLevel()->Level);

	  $versions = DB::query('SELECT * FROM "Variation_versions" WHERE "RecordID" = ' . $teeshirtAVariation->ID);
	  $versionsAfterPublished = array();
	  foreach ($versions as $versionRow) $versionsAfterPublished[] = $versionRow;
	  
	  //Add variation to the cart
	  $this->get(Director::makeRelative($teeshirtA->Link())); 

	  $data = array('Quantity' => 1);
	  foreach ($teeshirtAVariation->Options() as $option) {
	    $data["Options[{$option->AttributeID}]"] = $option->ID;
	  }
	  $this->submitForm('AddToCartForm_AddToCartForm', null, $data);
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals(4, $teeshirtAVariation->StockLevel()->Level);
	  
	  //Make sure a new version of the product was NOT created 
	  $versions = DB::query('SELECT * FROM "Variation_versions" WHERE "RecordID" = ' . $teeshirtAVariation->ID);
	  $versionsAfterStockChanges = array();
	  foreach ($versions as $versionRow) $versionsAfterStockChanges[] = $versionRow;
	  
	  $this->assertTrue($versionsAfterPublished == $versionsAfterStockChanges);
	}
	
	/**
	 * Remove variation from the cart and replenish stock levels
	 */
	function testRemoveProductVariationFromCartReplaceStock() {

	  $teeshirtA = $this->objFromFixture('Product', 'teeshirtA');

    $this->logInAs('admin');
	  $teeshirtA->doPublish();
	  $this->logOut();
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals(5, $teeshirtAVariation->StockLevel()->Level);
	  
	  $this->loginAs('buyer');
	  $buyer = $this->objFromFixture('Member', 'buyer');
	  
	  //Add variation to the cart
	  $this->get(Director::makeRelative($teeshirtA->Link())); 
	  $data = array('Quantity' => 1);
	  foreach ($teeshirtAVariation->Options() as $option) {
	    $data["Options[{$option->AttributeID}]"] = $option->ID;
	  }
	  $this->submitForm('AddToCartForm_AddToCartForm', null, $data);
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals(4, $teeshirtAVariation->StockLevel()->Level);
	  
	  
	  //Remove the Item from the Order
	  $cartPage = $this->objFromFixture('CartPage', 'cart');
	  $this->get(Director::makeRelative($cartPage->Link()));

	  $order = CartControllerExtension::get_current_order();
	  $item = $order->Items()->First();
	  
	  $this->submitForm('CartForm_CartForm', null, array(
	    "Quantity[{$item->ID}]" => 0
	  ));

	  //Flush the cache
	  DataObject::flush_and_destroy_cache();
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals(5, $teeshirtAVariation->StockLevel()->Level);
	}
	
	/**
	 * Stock levels cannot be reduced < 0, need to check bounds of stock level being set
	 * e.g: If stock level = 4 try adding 6 to a cart
	 */
	function testCheckBoundsWhenReducingProductStock() {
	  
	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(4, $productA->StockLevel()->Level); //Stock starts one down because of orderOneItemOne
	  
	  $this->logInAs('admin');
	  $productA->doPublish();
	  $this->logOut();
	  
	  $this->get(Director::makeRelative($productA->Link())); 
	  $this->submitForm('AddToCartForm_AddToCartForm', null, array(
	    'Quantity' => 6
	  ));
	  
	  //Flush the cache
	  DataObject::flush_and_destroy_cache();
	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(0, $productA->StockLevel()->Level);
	}
	
	/**
	 * Stock levels cannot be reduced < 0, need to check bounds of stock level being set
	 * e.g: If stock level = 5 try adding 6 to a cart
	 */
	function testCheckBoundsWhenReducingProductVariationStock() {
	  
	  $teeshirtA = $this->objFromFixture('Product', 'teeshirtA');

    $this->logInAs('admin');
	  $teeshirtA->doPublish();
	  $this->logOut();
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals(5, $teeshirtAVariation->StockLevel()->Level);
	  
	  //Add variation to the cart
	  $this->get(Director::makeRelative($teeshirtA->Link())); 
	  $data = array('Quantity' => 7);
	  foreach ($teeshirtAVariation->Options() as $option) {
	    $data["Options[{$option->AttributeID}]"] = $option->ID;
	  }
	  $this->submitForm('AddToCartForm_AddToCartForm', null, $data);
	  
	  DataObject::flush_and_destroy_cache();
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals(0, $teeshirtAVariation->StockLevel()->Level);
	}
	
	/**
	 * Stock levels can be reduced to exactly 0 for products
	 */
	function testProductStockLevelReducedToZero() {

	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(4, $productA->StockLevel()->Level); //Stock starts one down because of orderOneItemOne
	  
	  $this->logInAs('admin');
	  $productA->doPublish();
	  $this->logOut();
	  
	  $this->get(Director::makeRelative($productA->Link())); 
	  $this->submitForm('AddToCartForm_AddToCartForm', null, array(
	    'Quantity' => 4
	  ));
	  
	  //Flush the cache
	  DataObject::flush_and_destroy_cache();
	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(0, $productA->StockLevel()->Level);
	}
	
	/**
	 * Stock levels can be reduced to exactly 0 for product variations
	 */
	function testProductVariationStockLevelReducedToZero() {
	  
	  $teeshirtA = $this->objFromFixture('Product', 'teeshirtA');

    $this->logInAs('admin');
	  $teeshirtA->doPublish();
	  $this->logOut();
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals(5, $teeshirtAVariation->StockLevel()->Level);
	  
	  //Add variation to the cart
	  $this->get(Director::makeRelative($teeshirtA->Link())); 
	  $data = array('Quantity' => 5);
	  foreach ($teeshirtAVariation->Options() as $option) {
	    $data["Options[{$option->AttributeID}]"] = $option->ID;
	  }
	  $this->submitForm('AddToCartForm_AddToCartForm', null, $data);
	  
	  DataObject::flush_and_destroy_cache();
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals(0, $teeshirtAVariation->StockLevel()->Level);
	}
	
	/**
	 * Add and remove product to cart with unlimited stock (-1), stock level unaffected
	 */
	function testProductUnlimitedStockUnaffected() {

	  $productB = $this->objFromFixture('Product', 'productB');
	  $this->assertEquals(-1, $productB->StockLevel()->Level); //Stock starts one down because of orderOneItemOne
	  
	  $this->logInAs('admin');
	  $productB->doPublish();
	  $this->logOut();
	  
	  $variations = $productB->Variations();
	  $this->assertEquals(false, $variations->exists());
	  
	  $this->get(Director::makeRelative($productB->Link())); 
	  $this->submitForm('AddToCartForm_AddToCartForm', null, array(
	    'Quantity' => 3
	  ));
	  $productB = $this->objFromFixture('Product', 'productB');
	  $this->assertEquals(-1, $productB->StockLevel()->Level);
	  
	  
	  //Remove the Item from the Order
	  $cartPage = $this->objFromFixture('CartPage', 'cart');
	  $this->get(Director::makeRelative($cartPage->Link()));

	  $order = CartControllerExtension::get_current_order();
	  $item = $order->Items()->First();
	  
	  $this->submitForm('CartForm_CartForm', null, array(
	    "Quantity[{$item->ID}]" => 0
	  ));
	  
	  $order = CartControllerExtension::get_current_order();
	  $this->assertEquals(false, $order->Items()->exists());

	  $productB = $this->objFromFixture('Product', 'productB');
	  $this->assertEquals(-1, $productB->StockLevel()->Level);
	}

	/**
	 * Add and remove product variation to cart with unlimited stock (-1), stock level unaffected
	 */
	function testProductVariationUnlimitedStockUnaffected() {
	  
	  $teeshirtA = $this->objFromFixture('Product', 'teeshirtA');

    $this->logInAs('admin');
	  $teeshirtA->doPublish();
	  $this->logOut();
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtSmallPurpleCotton');
	  $this->assertEquals('Enabled', $teeshirtAVariation->Status);
	  $this->assertEquals(-1, $teeshirtAVariation->StockLevel()->Level);

	  //Add variation to the cart
	  $this->get(Director::makeRelative($teeshirtA->Link())); 
	  $data = array('Quantity' => 1);
	  foreach ($teeshirtAVariation->Options() as $option) {
	    $data["Options[{$option->AttributeID}]"] = $option->ID;
	  }
	  $this->submitForm('AddToCartForm_AddToCartForm', null, $data);
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtSmallPurpleCotton');
	  $this->assertEquals(-1, $teeshirtAVariation->StockLevel()->Level);
	  
	  
	  //Remove the Item from the Order
	  $cartPage = $this->objFromFixture('CartPage', 'cart');
	  $this->get(Director::makeRelative($cartPage->Link()));

	  $order = CartControllerExtension::get_current_order();
	  $item = $order->Items()->First();
	  
	  $this->submitForm('CartForm_CartForm', null, array(
	    "Quantity[{$item->ID}]" => 0
	  ));

	  //Flush the cache
	  DataObject::flush_and_destroy_cache();
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtSmallPurpleCotton');
	  $this->assertEquals(-1, $teeshirtAVariation->StockLevel()->Level);
	}
	
	/**
	 * Check that out of stock products do not display add to cart form
	 */
	function testProductOutOfStockNoAddForm() {
	  
	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(4, $productA->StockLevel()->Level); //Stock starts one down because of orderOneItemOne
	  
	  $this->logInAs('admin');
	  $productA->doPublish();
	  $this->logOut();
	  
	  $this->get(Director::makeRelative($productA->Link())); 
	  $this->submitForm('AddToCartForm_AddToCartForm', null, array(
	    'Quantity' => 4
	  ));
	  
	  //Flush the cache
	  DataObject::flush_and_destroy_cache();
	  $productA = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(0, $productA->StockLevel()->Level);
	  
	  $this->get(Director::makeRelative($productA->Link())); 

	  $page = $this->mainSession->lastPage();

	  $form = $page->getFormById('AddToCartForm_AddToCartForm');
	  $this->assertEquals(false, $form);
	}
	
	/**
	 * Check that products with all out of stock variations do not have add to cart forms
	 */
	function testProductWithVariationsOutOfStockNoAddForm() {

	  $product = $this->objFromFixture('Product', 'jeans');
	  $this->assertEquals(-1, $product->StockLevel()->Level);
	  
	  $variation = $this->objFromFixture('Variation', 'jeansSmall');
	  $this->assertEquals(0, $variation->StockLevel()->Level);
	  
	  $variation = $this->objFromFixture('Variation', 'jeansMedium');
	  $this->assertEquals(1, $variation->StockLevel()->Level);
	  
	  $stockLevel = $this->objFromFixture('StockLevel', 'levelJeansMedium');
	  
	  $this->logInAs('admin');
	  $stockLevel->Level = 0;
	  $stockLevel->write();
	  $product->doPublish();
	  $this->logOut();
	  
	  $variation = $this->objFromFixture('Variation', 'jeansMedium');
	  $this->assertEquals(0, $variation->StockLevel()->Level);

	  $this->get(Director::makeRelative($product->Link())); 
	  
	  $page = $this->mainSession->lastPage();
	  $form = $page->getFormById('AddToCartForm_AddToCartForm');
	  $this->assertEquals(false, $form);
	}

	/**
	 * Test that only in stock variations are available on the add to cart form.
	 */
	function testOutOfStockVariationsNotAvailable() {
	  
	  $product = $this->objFromFixture('Product', 'jeans');
	  $this->assertEquals(-1, $product->StockLevel()->Level);
	  
	  $variation = $this->objFromFixture('Variation', 'jeansSmall');
	  $this->assertEquals(0, $variation->StockLevel()->Level);
	  $this->assertEquals(false, $variation->InStock());
	  
	  $variation = $this->objFromFixture('Variation', 'jeansMedium');
	  $this->assertEquals(1, $variation->StockLevel()->Level);
	  
	  $this->logInAs('admin');
	  $product->doPublish();
	  $this->logOut();
	  
	  $this->get(Director::makeRelative($product->Link())); 

	  $firstAttributeID = array_shift(array_keys($product->Attributes()->map()));
	  $firstAttributeOptions = $product->getOptionsForAttribute($firstAttributeID)->map();

	  //Check that first option select has valid options in it
	  $productPage = new DOMDocument();
	  $productPage->loadHTML($this->mainSession->lastContent());
	  //echo $productPage->saveHTML();

	  //Find the options for the first attribute select
	  $selectFinder = new DomXPath($productPage);
	  $firstAttributeSelectID = 'AddToCartForm_AddToCartForm_Options-'.$firstAttributeID;
	  $firstSelect = $selectFinder->query("//select[@id='$firstAttributeSelectID']");
	  
	  foreach ($firstSelect as $node) {

	    $tmp_doc = new DOMDocument(); 
      $tmp_doc->appendChild($tmp_doc->importNode($node, true));        
      $innerHTML = $tmp_doc->saveHTML();

      $optionFinder = new DomXPath($tmp_doc);

  	  if ($firstAttributeOptions) foreach ($firstAttributeOptions as $optionID => $optionTitle) {
  	    $options = $optionFinder->query("//option[@value='$optionID']");
  	    $this->assertEquals(1, $options->length);
  	  }
	  }
	  
	}
	
	/**
	 * Clean up abandoned carts, restock products in the orders that are deleted
	 */
	function testRemoveAbandonedCartsWithProductsTask() {
	  //use Order delete_abandoned()
	  //create some orders in the fixture
	  //check the stock levels afterward

	  $product = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(4, $product->StockLevel()->Level); //Stock starts one down because of orderOneItemOne
	  
	  $buyer = $this->objFromFixture('Member', 'buyer');
	  $orders = $buyer->Orders();
	  $this->assertEquals(1, $orders->Count());
	  $order = $orders->First();

	  $this->logInAs('admin');
	  $order->LastActive = '2011-12-22 17:02:49';
	  $order->Status = 'Cart';
	  $order->write();
	  $this->logOut();

	  //ini_set('display_errors', 1);
	  //error_reporting(E_ALL);
	  //error_reporting(E_ERROR | E_PARSE);
	  //error_reporting(E_USER_ERROR);
	  //trigger_error("Cannot divide by infinity and beyond", E_ERROR);
	  
	  Order::delete_abandoned();
	  
	  DataObject::flush_and_destroy_cache();
	  $orders = $buyer->Orders();
	  $this->assertEquals(0, $orders->Count());

	  $product = $this->objFromFixture('Product', 'productA');
	  $this->assertEquals(5, $product->StockLevel()->Level); 
	}
	
	/**
	 * Add a product variation to the cart, change the cart so that it is out of date
	 * then delete it
	 */
	function testRemoveAbandonedCartsWithProductVariationsTask() {

	  $teeshirtA = $this->objFromFixture('Product', 'teeshirtA');

    $this->logInAs('admin');
	  $teeshirtA->doPublish();
	  $this->logOut();
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals('Enabled', $teeshirtAVariation->Status);
	  $this->assertEquals(5, $teeshirtAVariation->StockLevel()->Level);

	  //Add variation to the cart
	  $this->get(Director::makeRelative($teeshirtA->Link())); 

	  $data = array('Quantity' => 1);
	  foreach ($teeshirtAVariation->Options() as $option) {
	    $data["Options[{$option->AttributeID}]"] = $option->ID;
	  }
	  $this->submitForm('AddToCartForm_AddToCartForm', null, $data);
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals(4, $teeshirtAVariation->StockLevel()->Level);
	  
	  $order = CartControllerExtension::get_current_order();
	  
	  $this->logInAs('admin');
	  $order->LastActive = '2011-12-22 17:02:49';
	  $order->Status = 'Cart';
	  $order->write();
	  $this->logOut();

	  Order::delete_abandoned();
	  DataObject::flush_and_destroy_cache();
	  
	  $teeshirtAVariation = $this->objFromFixture('Variation', 'teeshirtExtraLargePurpleCotton');
	  $this->assertEquals(5, $teeshirtAVariation->StockLevel()->Level);
	}

}