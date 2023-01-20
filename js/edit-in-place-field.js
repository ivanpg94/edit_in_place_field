/**
 * @file
 * Provides functionality for edit in place.
 */
(function ($, Drupal) {

  'use strict';

  function bindInPlace() {

  }

  /**
   * Auto submit on country select.
   */
  Drupal.behaviors.EditInPlaceFieldReferenceForm = {
    attach: function (context) {
      $('.edit-in-place-clickable .edit-in-place-editable').once('editInPlaceEditBehavior').click(function(e){
        var $fieldset = $(this).parents('fieldset');
        $fieldset.removeClass('edit-in-place-clickable-init');
        if (jQuery().chosen !== undefined) {
          $fieldset.find("select.edit-in-place").chosen();
        }
      });

      $('.edit-in-place-cancel').once('editInPlaceCancelBehavior').click(function(e){
        e.stopPropagation();
        e.preventDefault();
        $(this).parents('.edit-in-place-clickable').addClass('edit-in-place-clickable-init');
        return false;
      });
    }
  };

  if (Drupal.AjaxCommands) {

    /**
     * Add new command to rebind JavaScript.
     */
    Drupal.AjaxCommands.prototype.rebindJS = function(ajax, response, status){
      $(response.containerJquerySelector).addClass('edit-in-place-clickable-init');
      $(response.containerJquerySelector+' .edit-in-place-editable').click(function(e){
        var $fieldset = $(this).parents('fieldset');
        $fieldset.removeClass('edit-in-place-clickable-init');
        if (jQuery().chosen !== undefined) {
          $fieldset.find("select.edit-in-place").chosen();
        }
      });

      $(response.containerJquerySelector+'.edit-in-place-cancel').click(function(e){
        e.stopPropagation();
        e.preventDefault();
        $(this).parents('.edit-in-place-clickable').addClass('edit-in-place-clickable-init');
        return false;
      });
    };

  }
})(jQuery, Drupal);
