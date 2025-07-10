# Smart Login and Registration Plugin

## Overview

The **Smart Login and Registration** plugin provides a comprehensive, AJAX-powered login and registration popup system with OTP (One-Time Password) email verification. This plugin supports multiple login methods including email, phone, and username authentication with real-time validation.

## Features

### 🔐 Authentication Methods

- **Email Login**: Users can log in using their email address
- **Phone Login**: Support for phone number authentication
- **Username Login**: Traditional username-based login
- **OTP Verification**: Secure one-time password verification via email

### 🎨 User Interface

- **Modern Popup Design**: Clean, responsive popup interface
- **Real-time Validation**: Instant feedback on form inputs
- **Multiple Form States**: Login, Register, Forgot Password, OTP Verification
- **Mobile Responsive**: Works seamlessly on all devices

### ⚡ Technical Features

- **AJAX Powered**: No page reloads, smooth user experience
- **Rate Limiting**: Prevents spam and abuse
- **Secure OTP System**: Time-limited, secure verification codes
- **Database Management**: Automatic cleanup of expired OTPs
- **Admin Dashboard**: Comprehensive settings and statistics

## Installation

1. **Upload the Plugin**

   - Upload the `smart-login-registration` folder to `/wp-content/plugins/`
   - Or install directly through the WordPress admin dashboard

2. **Activate the Plugin**

   - Go to `Plugins > Installed Plugins`
   - Find "Smart Login and Registration" and click "Activate"

3. **Configure Settings**
   - Go to `Settings > Smart Login & Registration`
   - Configure your preferences and OTP settings

## Usage

### Shortcodes

Use these shortcodes to display the login popup:

```php
[smart_login_popup]
```

or

```php
[slr_login_popup]
```

### Programmatic Usage

You can also trigger the popup programmatically:

```javascript
// Show login popup
if (typeof showLoginPopup === "function") {
  showLoginPopup();
}

// Show registration popup
if (typeof showRegisterPopup === "function") {
  showRegisterPopup();
}
```

### Template Integration

Add the shortcode to your theme files:

```php
<?php echo do_shortcode('[smart_login_popup]'); ?>
```

## Configuration

### Admin Settings

Navigate to `Settings > Smart Login & Registration` to configure:

- **OTP Expiry Time**: How long OTP codes remain valid (default: 10 minutes)
- **Rate Limiting**: Number of OTP requests per hour per user
- **Max Attempts**: Maximum failed login attempts before lockout
- **Email Template**: Customize OTP email appearance
- **Phone Validation**: Enable/disable phone number validation
- **Debug Mode**: Enable detailed logging for troubleshooting

### Database Tables

The plugin creates the following table:

- `wp_slr_otp_codes`: Stores OTP verification codes

## Developer Information

### AJAX Endpoints

The plugin provides these AJAX endpoints:

- `slr_login` - Handle login requests
- `slr_register` - Handle registration requests
- `slr_forgot_password` - Handle forgot password requests
- `slr_send_otp` - Send OTP verification code
- `slr_verify_otp` - Verify OTP code
- `slr_resend_otp` - Resend OTP code
- `slr_otp_login` - Login with OTP verification

### Hooks and Filters

Available hooks for customization:

```php
// Filter OTP email content
add_filter('slr_otp_email_content', 'custom_otp_email_content', 10, 2);

// Filter OTP expiry time
add_filter('slr_otp_expiry_time', 'custom_otp_expiry_time');

// Action after successful login
add_action('slr_after_login', 'custom_after_login_action', 10, 2);
```

## File Structure

```
smart-login-registration/
├── smart-login-registration.php     # Main plugin file
├── includes/
│   ├── class-smart-login-registration.php  # Main plugin class
│   ├── class-slr-otp-handler.php          # OTP management
│   ├── class-slr-user-handler.php         # User operations
│   ├── class-slr-ajax-handlers.php        # AJAX endpoints
│   └── class-slr-admin.php               # Admin interface
├── assets/
│   ├── css/
│   │   ├── popup-style.css           # Frontend popup styles
│   │   └── admin-style.css           # Admin interface styles
│   └── js/
│       ├── popup-script.js           # Frontend popup functionality
│       └── admin-script.js           # Admin interface scripts
└── templates/
    └── popup-template.php            # Popup HTML template
```

## Security Features

- **Nonce Verification**: All AJAX requests are protected with WordPress nonces
- **Rate Limiting**: Prevents abuse of OTP sending functionality
- **Input Sanitization**: All user inputs are properly sanitized
- **SQL Injection Prevention**: Uses prepared statements for database queries
- **XSS Protection**: Output is properly escaped

## Troubleshooting

### Common Issues

1. **OTP Not Received**

   - Check spam folder
   - Verify email settings in WordPress
   - Enable debug mode in plugin settings

2. **AJAX Errors**

   - Ensure jQuery is loaded
   - Check browser console for JavaScript errors
   - Verify AJAX URL is correct

3. **Database Issues**
   - Deactivate and reactivate the plugin
   - Check database table creation

### Debug Mode

Enable debug mode in the plugin settings to get detailed logs of plugin operations.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- jQuery (included with WordPress)

## Support

For support, feature requests, or bug reports, please contact:

- **Author**: Kazi Sadib Reza
- **GitHub**: https://github.com/KaziSadibReza
- **Email**: [Your email address]

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0

- Initial release
- Basic login/registration functionality
- OTP verification system
- Admin settings panel
- AJAX-powered interface
- Security features and rate limiting
