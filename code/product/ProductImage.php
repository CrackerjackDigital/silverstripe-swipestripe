<?php
/**
 * A image for {@link Product}s.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage product
 * @version 1.0
 */
class ProductImage extends DataObject {
  
  /**
   * DB fields for the ProductImage
   * 
   * @var Array
   */
  static $db = array (
    'Caption' => 'Text'
  );

  /**
   * Has one relations for a ProductImage
   * 
   * @var Array
   */
  static $has_one = array (
    'Image' => 'Image',
    'Product' => 'Product'
  );

  /**
   * Create fields for editing a ProductImage in the CMS.
   * 
   * @return FieldSet
   */
  public function getCMSFields_forPopup() {
    
    $fields = new FieldSet();
    $fields->push(new TextareaField('Caption'));

    $imageUploadField = (class_exists('ImageUploadField')) ? new ImageUploadField('Image') : new FileIFrameField('Image');
    $fields->push($imageUploadField);
    
    return $fields;
  }
  
  /**
   * Helper method to return a thumbnail image for displaying in CTF fields in CMS.
   * 
   * @return Image|String If no image can be found returns '(No Image)'
   */
  function SummaryOfImage() {
    if ($Image = $this->Image()) return $Image->CMSThumbnail();
    else return '(No Image)';
  }
  
  /**
   * Necessary for displaying product images in the CMS
   * 
   * @return Image|String If no image can be found returns '(No Image)'
   */
  function fortemplate() {
    $image = $this->Image();
    $thumb = ($image && $image->exists()) ? $image->CroppedImage(40,40) : null;
    return ($thumb && $thumb->exists()) ? $thumb->forTemplate() : '(No Image)';
  }
}