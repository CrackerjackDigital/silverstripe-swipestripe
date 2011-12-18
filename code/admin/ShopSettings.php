<?php
/**
 * Adding shop settings to the main {@link SiteConfig}. This will not work with subsites module due to
 * a problem with {@link ComplexTableField} which does not set the {@link SiteConfig} ID on records.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package shop
 * @subpackage admin
 * @version 1.0
 */
class ShopSettings extends DataObjectDecorator {
  
  /**
   * To hold the license key for the shop. Usually set in mysite/_config file.
   * 
   * @see ShopSettings::set_license_key()
   * @var String License key 
   */
  private static $license_key;
  
  /**
   * Set the license key, usually called in mysite/_config.
   * 
   * @param String $key License key
   */
  public static function set_license_key($key) {
    self::$license_key = $key;
  }

  /**
   * Add database fields for shop settings like emails etc.
   * 
   * @see DataObjectDecorator::extraStatics()
   */
	function extraStatics() {

		return array(
			'db' => array(
		    'EmailSignature' => 'HTMLText',
				'ReceiptSubject' => 'Varchar',
		    'ReceiptBody' => 'HTMLText',
		    'ReceiptFrom' => 'Varchar',
				'NotificationSubject' => 'Varchar',
		    'NotificationBody' => 'HTMLText',
		    'NotificationTo' => 'Varchar'
			)
		);
	}

	/**
	 * Adding fields for shop settings such as email, license key.
	 * 
	 * @see DataObjectDecorator::updateCMSFields()
	 */
  function updateCMSFields(FieldSet &$fields) {

    $fields->addFieldToTab("Root", new TabSet('Shop')); 
    
    //License key
    $fields->addFieldToTab("Root.Shop", 
      new Tab('LicenseKey')
    );
    $licenseKeyField = new TextField('LicenseKey', 'License Key', self::$license_key);
    $fields->addFieldToTab('Root.Shop.LicenseKey', $licenseKeyField->performReadonlyTransformation());
    
    //TODO include the license here in a text area field and some info about setting the license key perhaps
    
    //Shop emails
    $fields->addFieldToTab("Root.Shop", 
      new TabSet('Emails')
    );
    $fields->addFieldToTab("Root.Shop.Emails", 
      new Tab('Receipt'),
      new Tab('Notification'),
      new Tab('Signature')
    );

    $fields->addFieldToTab('Root.Shop.Emails.Receipt', new TextField('ReceiptFrom', 'Sender'));
    $fields->addFieldToTab('Root.Shop.Emails.Receipt', new TextField('ReceiptSubject', 'Subject line'));
    $fields->addFieldToTab('Root.Shop.Emails.Receipt', new HtmlEditorField('ReceiptBody', 'Message (order details are included in the email)', 15));

    $fields->addFieldToTab('Root.Shop.Emails.Notification', new TextField('NotificationTo', 'Recipient'));
    $fields->addFieldToTab('Root.Shop.Emails.Notification', new TextField('NotificationSubject', 'Subject line'));
    $fields->addFieldToTab('Root.Shop.Emails.Notification', new HtmlEditorField('NotificationBody', 'Message (order details are included in the email)', 15));
    
    $fields->addFieldToTab('Root.Shop.Emails.Signature', new HtmlEditorField('EmailSignature', 'Signature for all emails', 15));
	}

}