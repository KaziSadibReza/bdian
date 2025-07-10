/**
 * Smart Login and Registration - Admin JavaScript
 *
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 */

jQuery(document).ready(function ($) {
  "use strict";

  // Initialize admin functionality
  var SLRAdmin = {
    init: function () {
      this.bindEvents();
    },

    bindEvents: function () {
      // Settings form validation
      $("#submit").on("click", this.validateSettings);

      // Copy shortcode to clipboard
      $(document).on("click", ".slr-admin-widget code", this.copyToClipboard);

      // Toggle debug mode warning
      $('input[name="slr_settings[enable_debug]"]').on(
        "change",
        this.toggleDebugWarning
      );
    },

    validateSettings: function (e) {
      var errors = [];

      // Validate OTP expiry
      var otpExpiry = $('input[name="slr_settings[otp_expiry]"]').val();
      if (otpExpiry < 1 || otpExpiry > 60) {
        errors.push("OTP expiry must be between 1 and 60 minutes.");
      }

      // Validate rate limit
      var rateLimit = $('input[name="slr_settings[rate_limit]"]').val();
      if (rateLimit < 1 || rateLimit > 20) {
        errors.push("Rate limit must be between 1 and 20 requests per hour.");
      }

      // Validate max attempts
      var maxAttempts = $('input[name="slr_settings[max_attempts]"]').val();
      if (maxAttempts < 3 || maxAttempts > 10) {
        errors.push("Max attempts must be between 3 and 10.");
      }

      if (errors.length > 0) {
        e.preventDefault();
        alert("Please fix the following errors:\n\n" + errors.join("\n"));
      }
    },

    copyToClipboard: function (e) {
      var text = $(this).text();

      // Create temporary textarea
      var $temp = $("<textarea>");
      $("body").append($temp);
      $temp.val(text).select();

      try {
        document.execCommand("copy");

        // Show success message
        $(this).after('<span class="copied-message">Copied!</span>');

        setTimeout(function () {
          $(".copied-message").remove();
        }, 2000);
      } catch (err) {
        console.error("Unable to copy text: ", err);
      }

      $temp.remove();
    },

    toggleDebugWarning: function () {
      var $checkbox = $(this);
      var $warning = $("#debug-warning");

      if ($checkbox.is(":checked")) {
        if ($warning.length === 0) {
          $checkbox
            .parent()
            .after(
              '<div id="debug-warning" style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404;">' +
                "<strong>Warning:</strong> Debug mode will log sensitive information. Only enable for troubleshooting and disable when done." +
                "</div>"
            );
        }
      } else {
        $warning.remove();
      }
    },
  };

  // Initialize admin
  SLRAdmin.init();

  // Add styles for copied message
  var styles = `
        .copied-message {
            margin-left: 10px;
            color: #28a745;
            font-size: 12px;
            font-weight: bold;
        }
        
        .slr-admin-widget code {
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .slr-admin-widget code:hover {
            background-color: #e9ecef;
        }
    `;

  $("<style>").text(styles).appendTo("head");
});
