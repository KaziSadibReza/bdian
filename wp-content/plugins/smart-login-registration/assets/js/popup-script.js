/**
 * Smart Login and Registration JavaScript
 *
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 */

jQuery(document).ready(function ($) {
  "use strict";

  // Initialize popup functionality
  var SmartLoginPopup = {
    init: function () {
      this.bindEvents();
    },

    bindEvents: function () {
      // Open popup when login button is clicked (with mobile support)
      $(document).on("click touchend", ".slr-login-popup-btn", this.openPopup);

      // Close popup events (with mobile support)
      $(document).on("click touchend", ".slr-popup-close", this.closePopup);
      $(document).on("click touchend", ".slr-popup-overlay", this.closePopup);

      // Tab switching (with mobile support)
      $(document).on("click touchend", ".slr-popup-tab-nav a", this.switchTab);

      // Form submissions
      $(document).on("submit", "#slr-popup-login-form", this.handleLogin);
      $(document).on("submit", "#slr-popup-register-form", this.handleRegister);
      $(document).on(
        "submit",
        "#slr-popup-forgot-form",
        this.handleSendResetOtp
      );

      // New password reset form submissions
      $(document).on(
        "submit",
        "#slr-popup-reset-otp-verify-form",
        this.handleVerifyResetOtp
      );
      $(document).on(
        "submit",
        "#slr-popup-new-password-form",
        this.handleNewPassword
      );

      // OTP form submissions
      $(document).on(
        "submit",
        "#slr-popup-otp-login-form",
        this.handleOtpLogin
      );
      $(document).on(
        "submit",
        "#slr-popup-otp-verify-form",
        this.handleOtpVerify
      );

      // OTP login button
      $(document).on("click", ".slr-otp-login-btn", this.showOtpLogin);

      // Resend OTP buttons
      $(document).on("click", ".slr-resend-otp-btn", this.handleResendOtp);
      $(document).on(
        "click",
        ".slr-resend-reset-otp-btn",
        this.handleResendResetOtp
      );

      // Navigation links
      $(document).on("click", ".slr-forgot-password", this.switchTab);
      $(document).on("click", ".slr-back-to-login", this.switchTab);
      $(document).on("click", ".slr-back-to-forgot", this.switchTab);

      // ESC key to close popup
      $(document).on("keydown", this.handleEscKey);
    },

    openPopup: function (e) {
      e.preventDefault();
      e.stopPropagation();

      var popup = $("#slr-login-popup-container");

      // Prevent body scrolling on mobile
      $("body").addClass("slr-popup-open");

      popup.show().addClass("active");

      // Focus on first input (delayed for mobile)
      setTimeout(function () {
        var firstInput = popup.find('input[name="log"]');
        if (firstInput.length && !/Mobi|Android/i.test(navigator.userAgent)) {
          // Only auto-focus on non-mobile devices to prevent keyboard issues
          firstInput.focus();
        }
      }, 300);
    },

    closePopup: function (e) {
      e.preventDefault();
      e.stopPropagation();

      var popup = $("#slr-login-popup-container");
      popup.removeClass("active");

      // Re-enable body scrolling
      $("body").removeClass("slr-popup-open");

      setTimeout(function () {
        popup.hide();
      }, 300);

      // Clear forms
      SmartLoginPopup.clearForms();
    },

    handleEscKey: function (e) {
      if (e.keyCode === 27) {
        // ESC key
        SmartLoginPopup.closePopup(e);
      }
    },

    switchTab: function (e) {
      e.preventDefault();
      var $this = $(this);
      var tabId = $this.data("tab");

      // Update nav
      $(".slr-popup-tab-nav li").removeClass("active");
      $this.parent().addClass("active");

      // Update content
      $(".slr-tab-pane").removeClass("active");
      $("#slr-" + tabId + "-tab").addClass("active");

      // Focus on first input
      setTimeout(function () {
        $("#slr-" + tabId + "-tab")
          .find("input")
          .first()
          .focus();
      }, 100);
    },

    showOtpLogin: function (e) {
      e.preventDefault();

      // Update nav
      $(".slr-popup-tab-nav li").removeClass("active");

      // Update content
      $(".slr-tab-pane").removeClass("active");
      $("#slr-otp-login-tab").addClass("active");

      // Focus on email input
      setTimeout(function () {
        $("#slr-otp-login-tab").find('input[name="email"]').focus();
      }, 100);
    },

    handleLogin: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".slr-login-response");

      // Clear previous messages
      $response.hide().removeClass("success error");

      // Add loading state
      $submitBtn.addClass("loading").prop("disabled", true);

      // Prepare data
      var formData = $form.serialize();

      // AJAX request
      $.ajax({
        url: slr_ajax.ajax_url,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success) {
            $response.addClass("success").text(response.data.message).show();

            // Reload page after success
            setTimeout(function () {
              window.location.href = "/dashboard/";
            }, 1500);
          } else {
            $response.addClass("error").text(response.data.message).show();
          }
        },
        error: function (xhr, status, error) {
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
      var $response = $form.find(".slr-register-response");

      // Clear previous messages
      $response.hide().removeClass("success error");

      // Validate phone number (required)
      var phone = $form.find('input[name="phone"]').val();
      if (!phone || phone.trim() === "") {
        $response.addClass("error").text("Phone number is required.").show();
        return;
      }
      if (!SmartLoginPopup.isValidPhone(phone)) {
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
        url: slr_ajax.ajax_url,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success) {
            $response.addClass("success").text(response.data.message).show();

            // Check if we need to verify OTP
            if (response.data.step === "verify") {
              // Switch to OTP verification tab
              SmartLoginPopup.showOtpVerification(
                response.data.email,
                response.data.otp_type
              );
            } else {
              // Reload page after success
              setTimeout(function () {
                window.location.href = "/dashboard/";
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

    handleSendResetOtp: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".slr-forgot-response");
      var userLogin = $form.find('input[name="user_login"]').val();

      // Clear previous messages
      $response.hide().removeClass("success error");

      // Validate input
      if (!userLogin.trim()) {
        $response
          .addClass("error")
          .text("Please enter your email or phone number.")
          .show();
        return;
      }

      // Add loading state
      $submitBtn.addClass("loading").prop("disabled", true);

      // AJAX request
      $.ajax({
        url: slr_ajax.ajax_url,
        type: "POST",
        data: $form.serialize(),
        success: function (response) {
          if (response.success) {
            $response.addClass("success").text(response.data.message).show();

            // Switch to OTP verification form
            setTimeout(function () {
              SmartLoginPopup.switchToResetOtpVerify(
                response.data.email,
                response.data.user_login
              );
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

    handleVerifyResetOtp: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".slr-reset-otp-verify-response");

      // Clear previous messages
      $response.hide().removeClass("success error");

      // Add loading state
      $submitBtn.addClass("loading").prop("disabled", true);

      // AJAX request
      $.ajax({
        url: slr_ajax.ajax_url,
        type: "POST",
        data: $form.serialize(),
        success: function (response) {
          if (response.success) {
            $response.addClass("success").text(response.data.message).show();

            // Switch to new password form
            setTimeout(function () {
              SmartLoginPopup.switchToNewPassword(
                response.data.user_id,
                response.data.reset_token
              );
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

    handleNewPassword: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".slr-new-password-response");
      var newPassword = $form.find('input[name="new_password"]').val();
      var confirmPassword = $form.find('input[name="confirm_password"]').val();

      // Clear previous messages
      $response.hide().removeClass("success error");

      // Validate passwords
      if (!newPassword || !confirmPassword) {
        $response.addClass("error").text("Please fill in all fields.").show();
        return;
      }

      if (newPassword !== confirmPassword) {
        $response.addClass("error").text("Passwords do not match.").show();
        return;
      }

      if (newPassword.length < 6) {
        $response
          .addClass("error")
          .text("Password must be at least 6 characters long.")
          .show();
        return;
      }

      // Add loading state
      $submitBtn.addClass("loading").prop("disabled", true);

      // AJAX request
      $.ajax({
        url: slr_ajax.ajax_url,
        type: "POST",
        data: $form.serialize(),
        success: function (response) {
          if (response.success) {
            $response.addClass("success").text(response.data.message).show();

            // Check if user is auto-logged in
            if (response.data.auto_logged_in) {
              // User is now logged in, close popup and reload page
              setTimeout(function () {
                // Close popup properly
                var popup = $("#slr-login-popup-container");
                popup.removeClass("active");
                $("body").removeClass("slr-popup-open");

                // Show a welcome message
                if (response.data.user_display_name) {
                  console.log(
                    "Password reset successful! Welcome back, " +
                      response.data.user_display_name +
                      "!"
                  );
                }

                // Force page reload - try multiple methods for compatibility
                try {
                  if (typeof window !== "undefined") {
                    window.location.href = window.location.href;
                  } else {
                    location.reload(true);
                  }
                } catch (e) {
                  // Fallback reload method
                  document.location.reload(true);
                }
              }, 1200);
            } else {
              // Switch back to login after success
              setTimeout(function () {
                SmartLoginPopup.switchTab({
                  preventDefault: function () {},
                  target: $('<a data-tab="login"></a>')[0],
                });
                // Clear all forms
                SmartLoginPopup.clearForms();
              }, 2000);
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

    handleResendResetOtp: function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $form = $btn.closest("form");
      var email = $form.find('input[name="email"]').val();
      var userLogin = $form.find('input[name="user_login"]').val();

      if (!email || !userLogin) {
        return;
      }

      $btn.prop("disabled", true);

      $.ajax({
        url: slr_ajax.ajax_url,
        type: "POST",
        data: {
          action: "slr_send_reset_otp",
          user_login: userLogin,
          slr_otp_nonce: slr_ajax.otp_nonce,
        },
        success: function (response) {
          if (response.success) {
            SmartLoginPopup.showMessage(
              ".slr-reset-otp-verify-response",
              "success",
              "New OTP sent successfully!"
            );
            SmartLoginPopup.startResendTimer($btn, 60);
          } else {
            SmartLoginPopup.showMessage(
              ".slr-reset-otp-verify-response",
              "error",
              response.data.message
            );
            $btn.prop("disabled", false);
          }
        },
        error: function () {
          SmartLoginPopup.showMessage(
            ".slr-reset-otp-verify-response",
            "error",
            "Failed to resend OTP. Please try again."
          );
          $btn.prop("disabled", false);
        },
      });
    },

    switchToResetOtpVerify: function (email, userLogin) {
      // Fill in the hidden fields
      $('#slr-reset-otp-verify-tab input[name="email"]').val(email);
      $('#slr-reset-otp-verify-tab input[name="user_login"]').val(userLogin);

      // Clear OTP input
      $('#slr-reset-otp-verify-tab input[name="otp"]').val("");

      // Switch to OTP verification tab
      SmartLoginPopup.switchToTab("reset-otp-verify");

      // Start resend timer
      SmartLoginPopup.startResendTimer($(".slr-resend-reset-otp-btn"), 60);
    },

    switchToNewPassword: function (userId, resetToken) {
      // Fill in the hidden fields
      $('#slr-new-password-tab input[name="user_id"]').val(userId);
      $('#slr-new-password-tab input[name="reset_token"]').val(resetToken);

      // Clear password inputs
      $('#slr-new-password-tab input[name="new_password"]').val("");
      $('#slr-new-password-tab input[name="confirm_password"]').val("");

      // Switch to new password tab
      SmartLoginPopup.switchToTab("new-password");
    },

    switchToTab: function (tabName) {
      // Hide all tab panes
      $(".slr-tab-pane").removeClass("active");

      // Show the target tab
      $("#slr-" + tabName + "-tab").addClass("active");

      // Update navigation if needed
      $(".slr-popup-tab-nav a").removeClass("active");
      $('.slr-popup-tab-nav a[data-tab="' + tabName + '"]').addClass("active");

      // Clear response messages
      $(".slr-tab-pane .slr-response").hide().removeClass("success error");
    },

    handleOtpLogin: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".slr-otp-login-response");
      var email = $form.find('input[name="email"]').val();

      // Clear previous messages
      $response.hide().removeClass("success error");

      // Validate email
      if (!email || !SmartLoginPopup.isValidEmail(email)) {
        $response
          .addClass("error")
          .text("Please enter a valid email address.")
          .show();
        return;
      }

      // Show loading
      $submitBtn.prop("disabled", true).text("Sending OTP...");

      $.ajax({
        url: slr_ajax.ajax_url,
        type: "POST",
        data: {
          action: "slr_otp_login",
          email: email,
          slr_otp_nonce: slr_ajax.otp_nonce,
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
            SmartLoginPopup.showOtpVerification(
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
      $("#slr-popup-otp-verify-form input[name='email']").val(email);
      $("#slr-popup-otp-verify-form input[name='otp_type']").val(otpType);

      // Update header text
      var headerText =
        otpType === "login"
          ? "Enter OTP Code to Login"
          : "Enter OTP Code to Complete Registration";
      $("#slr-otp-verify-tab .slr-popup-form-header h3").text(headerText);

      // Switch to OTP verification tab
      $(".slr-popup-tab-nav li").removeClass("active");
      $(".slr-tab-pane").removeClass("active");
      $("#slr-otp-verify-tab").addClass("active");

      // Start countdown for resend button
      SmartLoginPopup.startResendCountdown();

      // Focus on OTP input
      setTimeout(function () {
        $("#slr-otp-verify-tab").find('input[name="otp"]').focus();
      }, 100);
    },

    handleOtpVerify: function (e) {
      e.preventDefault();
      var $form = $(this);
      var $submitBtn = $form.find('button[type="submit"]');
      var $response = $form.find(".slr-otp-verify-response");
      var $otpInput = $form.find('input[name="otp"]');

      // Validate OTP format
      var otp = $otpInput.val();
      if (!/^\d{4}$/.test(otp)) {
        $response
          .removeClass("success")
          .addClass("error")
          .html("Please enter a valid 4-digit OTP.")
          .show();
        $otpInput.focus();
        return;
      }

      // Show loading
      $submitBtn.prop("disabled", true).text("Verifying...");
      $response.hide();

      $.ajax({
        url: slr_ajax.ajax_url,
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

            // Close popup and redirect to dashboard after successful verification
            setTimeout(function () {
              // Close popup properly
              var popup = $("#slr-login-popup-container");
              popup.removeClass("active");
              $("body").removeClass("slr-popup-open");

              // Redirect to dashboard
              window.location.href = "/dashboard/";
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
      var $form = $("#slr-popup-otp-verify-form");
      var email = $form.find('input[name="email"]').val();
      var otpType = $form.find('input[name="otp_type"]').val();

      if ($btn.prop("disabled")) {
        return;
      }

      $btn.prop("disabled", true).text("Resending...");

      $.ajax({
        url: slr_ajax.ajax_url,
        type: "POST",
        data: {
          action: "slr_resend_otp",
          email: email,
          otp_type: otpType,
          slr_otp_nonce: slr_ajax.otp_nonce,
        },
        success: function (response) {
          if (response.success) {
            $(".slr-otp-verify-response")
              .removeClass("error")
              .addClass("success")
              .html(response.data.message)
              .show();

            // Start countdown again
            SmartLoginPopup.startResendCountdown();
          } else {
            $btn.prop("disabled", false).text("Resend OTP");
            $(".slr-otp-verify-response")
              .removeClass("success")
              .addClass("error")
              .html(response.data.message)
              .show();
          }
        },
        error: function () {
          $btn.prop("disabled", false).text("Resend OTP");
          $(".slr-otp-verify-response")
            .removeClass("success")
            .addClass("error")
            .html("An error occurred. Please try again.")
            .show();
        },
      });
    },

    startResendCountdown: function () {
      var $btn = $(".slr-resend-otp-btn");
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
        "#slr-popup-login-form, #slr-popup-register-form, #slr-popup-forgot-form, #slr-popup-reset-otp-verify-form, #slr-popup-new-password-form"
      ).each(function () {
        this.reset();
        $(this)
          .find(
            ".slr-login-response, .slr-register-response, .slr-forgot-response, .slr-reset-otp-verify-response, .slr-new-password-response"
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

    // Show message utility
    showMessage: function (selector, type, message) {
      var $element = $(selector);
      $element.removeClass("success error").addClass(type).text(message).show();
    },

    // Start resend timer utility
    startResendTimer: function ($btn, seconds) {
      var countdown = seconds;
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
  };

  // Initialize when DOM is ready
  SmartLoginPopup.init();

  // Real-time phone validation
  $(document).on("input", 'input[name="phone"]', function () {
    var $this = $(this);
    var phone = $this.val();

    // Remove existing validation message
    $this.next(".phone-validation").remove();
    $this.removeClass("invalid valid");

    if (phone.length === 0) {
      // Phone is required, show error for empty field
      $this.addClass("invalid");
      $this.after(
        '<div class="phone-validation" style="color: #dc3545; font-size: 12px; margin-top: 5px;">Phone number is required</div>'
      );
    } else if (!SmartLoginPopup.isValidPhone(phone)) {
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

    // Limit to 4 digits
    if (value.length > 4) {
      value = value.substring(0, 4);
    }

    $this.val(value);

    // Auto-submit when 4 digits are entered
    if (value.length === 4) {
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
      // Limit to 4 digits
      if (value.length > 4) {
        value = value.substring(0, 4);
      }
      $this.val(value);

      // Auto-submit when 4 digits are pasted
      if (value.length === 4) {
        setTimeout(function () {
          $this.closest("form").submit();
        }, 500);
      }
    }, 10);
  });

  // Email validation enhancement
  $(document).on("blur", 'input[type="email"]', function () {
    var $this = $(this);
    var email = $this.val();

    $this.removeClass("invalid valid");

    if (email.length > 0) {
      if (!SmartLoginPopup.isValidEmail(email)) {
        $this.addClass("invalid");
      } else {
        $this.addClass("valid");
      }
    }
  });
});
