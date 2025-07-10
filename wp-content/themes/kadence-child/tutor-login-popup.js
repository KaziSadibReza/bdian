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

      // OTP form submissions
      $(document).on(
        "submit",
        "#tutor-popup-otp-login-form",
        this.handleOtpLogin
      );
      $(document).on(
        "submit",
        "#tutor-popup-otp-verify-form",
        this.handleOtpVerify
      );

      // OTP login button
      $(document).on("click", ".tutor-otp-login-btn", this.showOtpLogin);

      // Resend OTP button
      $(document).on("click", ".tutor-resend-otp-btn", this.handleResendOtp);

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

            // Check if we need to verify OTP
            if (response.data.step === "verify") {
              // Switch to OTP verification tab
              TutorLoginPopup.showOtpVerification(
                response.data.email,
                response.data.otp_type
              );
            } else {
              // Reload page after success
              setTimeout(function () {
                window.location.reload();
              }, 1500);
            }
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

    showOtpLogin: function (e) {
      e.preventDefault();

      // Switch to OTP login tab
      $(".tutor-popup-tab-nav li").removeClass("active");
      $(".tutor-tab-pane").removeClass("active");
      $("#tutor-otp-login-tab").addClass("active");

      // Focus on email input
      setTimeout(function () {
        $("#tutor-otp-login-tab").find('input[name="email"]').focus();
      }, 100);
    },

    handleOtpLogin: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".tutor-otp-login-response");
      var email = $form.find('input[name="email"]').val();

      // Clear previous messages
      $response.hide().removeClass("success error");

      // Validate email
      if (!email || !TutorLoginPopup.isValidEmail(email)) {
        $response
          .addClass("error")
          .text("Please enter a valid email address.")
          .show();
        return;
      }

      // Show loading
      $submitBtn.prop("disabled", true).text("Sending OTP...");

      $.ajax({
        url: tutor_login_ajax.ajax_url,
        type: "POST",
        data: {
          action: "tutor_otp_login",
          email: email,
          tutor_otp_nonce: tutor_login_ajax.otp_nonce,
        },
        success: function (response) {
          $submitBtn.prop("disabled", false).text("Send OTP");

          if (response.success) {
            $response
              .removeClass("error")
              .addClass("success")
              .html(response.data.message)
              .show();

            // Switch to OTP verification tab
            TutorLoginPopup.showOtpVerification(
              response.data.email,
              response.data.otp_type
            );
          } else {
            $response
              .removeClass("success")
              .addClass("error")
              .html(response.data.message)
              .show();
          }
        },
        error: function () {
          $submitBtn.prop("disabled", false).text("Send OTP");
          $response
            .removeClass("success")
            .addClass("error")
            .html("An error occurred. Please try again.")
            .show();
        },
      });
    },

    showOtpVerification: function (email, otpType) {
      // Set hidden fields
      $("#tutor-popup-otp-verify-form input[name='email']").val(email);
      $("#tutor-popup-otp-verify-form input[name='otp_type']").val(otpType);

      // Update header text
      var headerText =
        otpType === "login"
          ? "Enter OTP Code to Login"
          : "Enter OTP Code to Complete Registration";
      $("#tutor-otp-verify-tab .tutor-popup-form-header h3").text(headerText);

      // Switch to OTP verification tab
      $(".tutor-popup-tab-nav li").removeClass("active");
      $(".tutor-tab-pane").removeClass("active");
      $("#tutor-otp-verify-tab").addClass("active");

      // Start countdown for resend button
      TutorLoginPopup.startResendCountdown();

      // Focus on OTP input
      setTimeout(function () {
        $("#tutor-otp-verify-tab").find('input[name="otp"]').focus();
      }, 100);
    },

    handleOtpVerify: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".tutor-otp-verify-response");
      var $otpInput = $form.find('input[name="otp"]');

      // Validate OTP format
      var otp = $otpInput.val();
      if (!/^\d{6}$/.test(otp)) {
        $response
          .removeClass("success")
          .addClass("error")
          .html("Please enter a valid 6-digit OTP.")
          .show();
        $otpInput.focus();
        return;
      }

      // Show loading
      $submitBtn.prop("disabled", true).text("Verifying...");
      $response.hide();

      $.ajax({
        url: tutor_login_ajax.ajax_url,
        type: "POST",
        data: $form.serialize(),
        success: function (response) {
          $submitBtn.prop("disabled", false).text("Verify OTP");

          if (response.success) {
            $response
              .removeClass("error")
              .addClass("success")
              .html(response.data.message)
              .show();

            // Close popup and reload page after successful verification
            setTimeout(function () {
              TutorLoginPopup.closePopup({ preventDefault: function () {} });
              location.reload();
            }, 1500);
          } else {
            $response
              .removeClass("success")
              .addClass("error")
              .html(response.data.message)
              .show();
            $otpInput.focus().select();
          }
        },
        error: function () {
          $submitBtn.prop("disabled", false).text("Verify OTP");
          $response
            .removeClass("success")
            .addClass("error")
            .html("An error occurred. Please try again.")
            .show();
        },
      });
    },

    handleResendOtp: function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $form = $("#tutor-popup-otp-verify-form");
      var email = $form.find('input[name="email"]').val();
      var otpType = $form.find('input[name="otp_type"]').val();

      if ($btn.prop("disabled")) {
        return;
      }

      $btn.prop("disabled", true).text("Resending...");

      $.ajax({
        url: tutor_login_ajax.ajax_url,
        type: "POST",
        data: {
          action: "tutor_resend_otp",
          email: email,
          otp_type: otpType,
          tutor_otp_nonce: tutor_login_ajax.otp_nonce,
        },
        success: function (response) {
          if (response.success) {
            $(".tutor-otp-verify-response")
              .removeClass("error")
              .addClass("success")
              .html(response.data.message)
              .show();

            // Start countdown again
            TutorLoginPopup.startResendCountdown();
          } else {
            $btn.prop("disabled", false).text("Resend OTP");
            $(".tutor-otp-verify-response")
              .removeClass("success")
              .addClass("error")
              .html(response.data.message)
              .show();
          }
        },
        error: function () {
          $btn.prop("disabled", false).text("Resend OTP");
          $(".tutor-otp-verify-response")
            .removeClass("success")
            .addClass("error")
            .html("An error occurred. Please try again.")
            .show();
        },
      });
    },

    startResendCountdown: function () {
      var $btn = $(".tutor-resend-otp-btn");
      var countdown = 60;

      $btn.prop("disabled", true);

      var interval = setInterval(function () {
        countdown--;
        $btn.text("Resend OTP (" + countdown + "s)");

        if (countdown <= 0) {
          clearInterval(interval);
          $btn.prop("disabled", false).text("Resend OTP");
        }
      }, 1000);
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

    // Email validation
    isValidEmail: function (email) {
      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
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

  // OTP Input Enhancement
  $(document).on("input", 'input[name="otp"]', function () {
    var $this = $(this);
    var value = $this.val();

    // Only allow digits
    value = value.replace(/[^0-9]/g, "");

    // Limit to 6 digits
    if (value.length > 6) {
      value = value.substring(0, 6);
    }

    $this.val(value);

    // Auto-submit when 6 digits are entered
    if (value.length === 6) {
      setTimeout(function () {
        $this.closest("form").submit();
      }, 500);
    }
  });

  // OTP Input Paste Handler
  $(document).on("paste", 'input[name="otp"]', function (e) {
    var $this = $(this);

    setTimeout(function () {
      var value = $this.val();
      // Only allow digits
      value = value.replace(/[^0-9]/g, "");
      // Limit to 6 digits
      if (value.length > 6) {
        value = value.substring(0, 6);
      }
      $this.val(value);

      // Auto-submit when 6 digits are pasted
      if (value.length === 6) {
        setTimeout(function () {
          $this.closest("form").submit();
        }, 500);
      }
    }, 10);
  });

  // Email validation enhancement
  $(document).on("input", 'input[name="email"]', function () {
    var $this = $(this);
    var email = $this.val();

    // Remove existing validation message
    $this.next(".email-validation").remove();
    $this.removeClass("invalid valid");

    if (email.length > 0) {
      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        $this.addClass("invalid");
        $this.after(
          '<div class="email-validation" style="color: #dc3545; font-size: 12px; margin-top: 5px;">Please enter a valid email address</div>'
        );
      } else {
        $this.addClass("valid");
        $this.after(
          '<div class="email-validation" style="color: #28a745; font-size: 12px; margin-top: 5px;">✓ Valid email address</div>'
        );
      }
    }
  });
});
