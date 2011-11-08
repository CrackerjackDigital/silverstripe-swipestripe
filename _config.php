<?php

//Decorators
Object::add_extension('Member', 'CustomerDecorator');
Object::add_extension('Payment', 'PaymentDecorator');
Object::add_extension('SiteConfig', 'ShopSettings');

//Extend page controller
Object::add_extension('Page_Controller', 'CartControllerExtension');

//Allow product images to be sorted
SortableDataObject::add_sortable_classes(array('ProductImage'));

//Redirect customers logging in to the account page
Security::set_default_login_dest('account');

//Rules for product links

Director::addRules(50, array( 
  //'product//$ID' => 'Product_Controller',
  'product//$ID/$Action' => 'Product_Controller'
));

//TODO is this necessary?
LeftAndMain::require_css('shop/css/ShopAdmin.css');

//For unit testing
/*
Object::add_extension('DummyProductPage', 'ProductDecorator');
Object::add_extension('DummyVirtualProductPage', 'ProductDecorator');
Object::add_extension('DummyVirtualProductPage', 'VirutalProductDecorator');
*/