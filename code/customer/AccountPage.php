<?php
/**
 * Account page shows order history and a form to allow
 * the member to edit his/her details.
 */
class AccountPage extends Page {

	static $db = array(
	);

	/**
	 * Automatically create an AccountPage if one is not found
	 * on the site at the time the database is built (dev/build).
	 */
	function requireDefaultRecords() {
		parent::requireDefaultRecords();

		if(!DataObject::get_one('AccountPage')) {
			$page = new AccountPage();
			$page->Title = 'Account';
			$page->Content = '<p>This is the account page. It is used for shop users to login and change their member details if they have an account.</p>';
			$page->URLSegment = 'account';
			$page->ShowInMenus = 0;
			$page->writeToStage('Stage');
			$page->publish('Stage', 'Live');

			DB::alteration_message('Account page \'Account\' created', 'created');
		}
	}
}

class AccountPage_Controller extends Page_Controller {
  
  static $allowed_actions = array (
    'order',
    'orders',
  	'downloadProduct'
  );
  
  function index() {
    
    $memberID = Member::currentUserID();
    if (!$memberID) {
      return Security::permissionFailure($this, 'You must be logged in to view this page.');
    }

    return array( 
       'Content' => $this->Content, 
       'Form' => $this->Form 
    );
  }

	/**
	 * Return the {@link Order} details for the current
	 * Order ID that we're viewing (ID parameter in URL).
	 * 
	 * TODO not checking member IDs before getting order for testing, need to fix
	 * TODO pass errors back using session instead
	 *
	 * @return array of template variables
	 */
	function order($request) {

		$memberID = Member::currentUserID();
		
	  if (!$memberID) {
      return Security::permissionFailure($this, 'You must be logged in to view this page.');
    }

		if($orderID = $request->param('ID')) {
//			if($order = DataObject::get_one('Order', "\"Order\".\"ID\" = '$orderID' AND \"Order\".\"MemberID\" = '$memberID'")) {
		  if($order = DataObject::get_one('Order', "\"Order\".\"ID\" = '$orderID'")) {
				return array(
					'Order' => $order
				);
			}
			else {
				return array(
					'Order' => false,
					'Message' => 'You do not have any order corresponding to this ID.'
				);
			}
		}
		else {
			return array(
				'Order' => false,
				'Message' => 'There is no order by that ID.'
			);
		}
	}
	
	/**
	 * Retreive processed (non cart) orders this member has made
	 * 
	 * @return DataObjectSet 
	 */
	function orders() {
	  $memberID = Member::currentUserID();
	  return DataObject::get('Order', "`MemberID` = $memberID AND `Order`.`Status` != 'Cart'");
	}
	
	/**
	 * Redirect browser to the download location, increment number of times
	 * this item has been downloaded.
	 * 
	 * If the item has been downloaded too many times redirects back with 
	 * error message.
	 * 
	 * @param SS_HTTPRequest $request
	 */
	function downloadProduct(SS_HTTPRequest $request) {
	  
	  //TODO can only download product if order has been paid for

	  $item = DataObject::get_by_id('Item', $request->requestVar('ItemID'));
	  if ($item->exists()) {
	    
	    $virtualProduct = $item->Object();
	    
	    if (isset($virtualProduct->FileLocation) && $virtualProduct->FileLocation) {
  	    if ($downloadLocation = $virtualProduct->downloadLocation()) {
    	    $item->DownloadCount = $item->DownloadCount + 1;
    	    $item->write();

    	    Director::redirect($downloadLocation);
    	    return;
    	  }
	    }
	  }

	  //TODO set an error message
	  Director::redirectBack();
	}

}

