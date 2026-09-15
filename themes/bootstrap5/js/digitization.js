/*exported setUpDigitizationRequestForm */

/**
 * Set up the digitization request form by toggling the page number fields
 * based on the partial digitization checkbox.
 */
function setUpDigitizationRequestForm() {
  /**
   * Toggle the partial digitization fields based on the selected
   * digitization type radio button.
   */
  function togglePartialFields() {
    var $digitizationTypeRadios = document.querySelectorAll('input[name="gatheredDetails[digitizationType]"]');
    var $partialDigitizationContainer = document.querySelector('#partialFields');

    let checkedRadio = null;
    $digitizationTypeRadios.forEach(function findCheckedTypeRadio(radio) {
      if (radio.checked) {
        checkedRadio = radio;
      }
    });

    if (checkedRadio) {
      if (checkedRadio.value === 'partial') {
        $partialDigitizationContainer.removeAttribute('disabled');
      } else {
        $partialDigitizationContainer.setAttribute('disabled', 'disabled');
      }
    }
  }

  /**
   * Toggle the page range fields based on the selected partial
   * digitization type radio button.
   */
  function togglePageFields() {
    var $partialDigitizationTypeRadios = document.querySelectorAll('input[name="gatheredDetails[partialDigitizationType]"]');
    var $pageRangeContainer = document.querySelector('#pageRangeFields');

    let checkedRadio = null;
    $partialDigitizationTypeRadios.forEach(function findCheckedPageRadio(radio) {
      if (radio.checked) {
        checkedRadio = radio;
      }
    });

    if (checkedRadio) {
      if (checkedRadio.value === 'full') {
        $pageRangeContainer.setAttribute('disabled', 'disabled');
      } else {
        $pageRangeContainer.removeAttribute('disabled');
      }
    }
  }

  document.querySelectorAll('input[name="gatheredDetails[digitizationType]"]').forEach(
    function attachTypeChange(radio) {
      radio.addEventListener('change', togglePartialFields);
    }
  );
  document.querySelectorAll('input[name="gatheredDetails[partialDigitizationType]"]').forEach(
    function attachPageChange(radio) {
      radio.addEventListener('change', togglePageFields);
    }
  );

  togglePartialFields();
  togglePageFields();
}
