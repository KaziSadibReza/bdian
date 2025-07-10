/**
 * Tutor Login Popup JavaScript
 *
 * @package Kadence Child
 * @author Kazi Sadib Reza
 */

jQuery(document).ready(function ($) {
  "use strict";

  // Initialize popup functionality
  var TutorLoginPopup = {
    init: function () {
      this.bindEvents();
    },

    bindEvents: function () {
      // Open popup when login button is clicked
      $(document).on("click", ".tutor-login-popup-btn", this.openPopup);

      // Close popup events
      $(document).on("click", ".tutor-popup-close", this.closePopup);
      $(document).on("click", ".tutor-popup-overlay", this.closePopup);

      // Tab switching
      $(document).on("click", ".tutor-popup-tab-nav a", this.switchTab);

      // Form submissions
      $(document).on("submit", "#tutor-popup-login-form", this.handleLogin);
      $(document).on(
        "submit",
        "#tutor-popup-register-form",
        this.handleRegister
      );
      $(document).on(
        "submit",
        "#tutor-popup-forgot-form",
        this.handleForgotPassword
      );

      // Forgot password link in login form
      $(document).on("click", ".tutor-forgot-password", this.switchTab);
      $(document).on("click", ".tutor-back-to-login", this.switchTab);

      // ESC key to close popup
      $(document).on("keydown", this.handleEscKey);
    },

    openPopup: function (e) {
      e.preventDefault();
      var popup = $("#tutor-login-popup-container");
      popup.show().addClass("active");

      // Focus on first input
      setTimeout(function () {
        popup.find('input[name="log"]').focus();
      }, 300);
    },

    closePopup: function (e) {
      e.preventDefault();
      var popup = $("#tutor-login-popup-container");
      popup.removeClass("active");

      setTimeout(function () {
        popup.hide();
      }, 300);

      // Clear forms
      TutorLoginPopup.clearForms();
    },

    switchTab: function (e) {
      e.preventDefault();
      var $this = $(this);
      var tabId = $this.data("tab");

      // Update nav
      $(".tutor-popup-tab-nav li").removeClass("active");
      $this.parent().addClass("active");

      // Update content
      $(".tutor-tab-pane").removeClass("active");
      $("#tutor-" + tabId + "-tab").addClass("active");

      // Focus on first input
      setTimeout(function () {
        $("#tutor-" + tabId + "-tab")
          .find("input")
          .first()
          .focus();
      }, 100);
    },

    handleLogin: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".tutor-login-response");

      // Clear previous messages
      $response.hide().removeClass("success error");

      // Add loading state
      $submitBtn.addClass("loading").prop("disabled", true);

      // Prepare data
      var formData = $form.serialize();

      // AJAX request
      $.ajax({
        url: tutor_login_ajax.ajax_url,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success) {
            $response.addClass("success").text(response.data.message).show();

            // Reload page after success
            setTimeout(function () {
              window.location.reload();
            }, 1500);
          } else {
            $response.addClass("error").text(response.data.message).show();
          }
        },
        error: function () {
          $response
            .addClass("error")
            .text("An error occurred. Please try again.")
            .show();
        },
        complete: function () {
          $submitBtn.removeClass("loading").prop("disabled", false);
        },
      });
    },

    handleRegister: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".tutor-register-response");

      // Clear previous messages
      $response.hide().removeClass("success error");

      // Validate phone number
      var phone = $form.find('input[name="phone"]').val();
      if (!TutorLoginPopup.isValidPhone(phone)) {
        $response
          .addClass("error")
          .text("Please enter a valid phone number.")
          .show();
        return;
      }

      // Add loading state
      $submitBtn.addClass("loading").prop("disabled", true);

      // Prepare data
      var formData = $form.serialize();

      // AJAX request
      $.ajax({
        url: tutor_login_ajax.ajax_url,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success) {
            $response.addClass("success").text(response.data.message).show();

            // Reload page after success
            setTimeout(function () {
              window.location.reload();
            }, 1500);
          } else {
            $response.addClass("error").text(response.data.message).show();
          }
        },
        error: function () {
          $response
            .addClass("error")
            .text("An error occurred. Please try again.")
            .show();
        },
        complete: function () {
          $submitBtn.removeClass("loading").prop("disabled", false);
        },
      });
    },

    handleForgotPassword: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".tutor-forgot-response");

      // Clear previous messages
      $response.hide().removeClass("success error");

      // Add loading state
      $submitBtn.addClass("loading").prop("disabled", true);

      // Prepare data
      var formData = $form.serialize();

      // AJAX request
      $.ajax({
        url: tutor_login_ajax.ajax_url,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success) {
            $response.addClass("success").text(response.data.message).show();

            // Clear form after success
            $form[0].reset();
          } else {
            $response.addClass("error").text(response.data.message).show();
          }
        },
        error: function () {
          $response
            .addClass("error")
            .text("An error occurred. Please try again.")
            .show();
        },
        complete: function () {
          $submitBtn.removeClass("loading").prop("disabled", false);
        },
      });
    },

    handleEscKey: function (e) {
      if (e.keyCode === 27) {
        // ESC key
        var popup = $("#tutor-login-popup-container");
        if (popup.hasClass("active")) {
          TutorLoginPopup.closePopup(e);
        }
      }
    },

    clearForms: function () {
      $(
        "#tutor-popup-login-form, #tutor-popup-register-form, #tutor-popup-forgot-form"
      ).each(function () {
        this.reset();
        $(this)
          .find(
            ".tutor-login-response, .tutor-register-response, .tutor-forgot-response"
          )
          .hide();
      });
    },

    // Phone number validation
    isValidPhone: function (phone) {
      // Remove all non-digit characters
      var cleaned = phone.replace(/[^0-9]/g, "");

      // Check various phone formats
      return (
        /^[0-9]{10,15}$/.test(cleaned) ||
        /^\+[0-9]{10,15}$/.test(phone) ||
        /^01[0-9]{9}$/.test(cleaned)
      ); // Bangladesh format
    },
  };

  // Initialize when DOM is ready
  TutorLoginPopup.init();

  // Real-time phone validation
  $(document).on("input", 'input[name="phone"]', function () {
    var $this = $(this);
    var phone = $this.val();

    // Remove existing validation message
    $this.next(".phone-validation").remove();
    $this.removeClass("invalid valid");

    if (phone.length > 0) {
      if (!TutorLoginPopup.isValidPhone(phone)) {
        $this.addClass("invalid");
        $this.after(
          '<div class="phone-validation" style="color: #dc3545; font-size: 12px; margin-top: 5px;">Please enter a valid phone number (e.g., 01700000000)</div>'
        );
      } else {
        $this.addClass("valid");
        $this.after(
          '<div class="phone-validation" style="color: #28a745; font-size: 12px; margin-top: 5px;">✓ Valid phone number</div>'
        );
      }
    }
  });

  // Format phone number as user types
  $(document).on("input", 'input[name="phone"]', function () {
    var $this = $(this);
    var phone = $this.val();

    // Remove all non-digit characters except +
    var cleaned = phone.replace(/[^0-9+]/g, "");

    // Set the cleaned value back
    if (cleaned !== phone) {
      $this.val(cleaned);
    }
  });
});
