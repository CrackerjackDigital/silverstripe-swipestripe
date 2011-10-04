<?php

class ModifierSetField extends OptionsetField {
	
	/**
	 * @var Array
	 */
	protected $disabledItems = array();
	
	/**
	 * Template for main rendering
	 *
	 * @var string
	 */
	protected $template = "ModifierSetField";
	
	protected $modifierType;
	
	/**
	 * Creates a new optionset field for order modifers with the naming convention
	 * Modifiers[ClassName] where ClassName is name of modifier class.
	 * 
	 * @param name The field name
	 * @param title The field title
	 * @param source An map of the dropdown items
	 * @param value The current value
	 * @param form The parent form
	 */
	function __construct($name, $title = "", $source = array(), $value = "", $form = null, $modifierType = null) {
	  
	  $this->modifierType = $modifierType;
	  $name = "Modifiers[$name]";
		parent::__construct($name, $title, $source, $value, $form);
	}
	
  function FieldHolder() {
		return $this->renderWith($this->template);
	}
	
	function ModifierType() {
	  return $this->modifierType;
	}
	
}