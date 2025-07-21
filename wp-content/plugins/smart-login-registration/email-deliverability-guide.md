# Email Deliverability Configuration Guide

## Anti-Spam Improvements Applied

The OTP email system has been optimized to prevent emails from going to spam folders:

### 1. Email Content Optimization

- **Subject Line**: Removed brackets and excessive punctuation
- **HTML Template**: Simplified design, removed external fonts
- **Plain Text Version**: Added for better compatibility
- **Content**: Removed spam-triggering words and emojis

### 2. Email Headers Enhancement

- **Proper From Address**: Uses noreply@yourdomain.com format
- **Authentication Headers**: Added SPF/DKIM indicators
- **Message-ID**: Unique identifier for each email
- **List-Unsubscribe**: Required anti-spam header
- **Priority Headers**: Normal priority to avoid spam filters

### 3. Multipart Email Support

- **HTML + Plain Text**: Both versions included
- **Proper MIME Structure**: Correct multipart boundaries
- **Character Encoding**: UTF-8 with 7bit encoding

## SMTP Configuration (Recommended)

For best deliverability, configure SMTP by adding these constants to your `wp-config.php`:

```php
// SMTP Configuration for Better Email Deliverability
define('SLR_SMTP_HOST', 'smtp.gmail.com');          // Your SMTP server
define('SLR_SMTP_PORT', 587);                       // SMTP port (587 for TLS, 465 for SSL)
define('SLR_SMTP_SECURE', 'tls');                   // Security: 'tls' or 'ssl'
define('SLR_SMTP_AUTH', true);                      // Enable authentication
define('SLR_SMTP_USER', 'your-email@gmail.com');    // SMTP username
define('SLR_SMTP_PASS', 'your-app-password');       // SMTP password or app password
```

### Popular SMTP Providers

#### Gmail/Google Workspace

```php
define('SLR_SMTP_HOST', 'smtp.gmail.com');
define('SLR_SMTP_PORT', 587);
define('SLR_SMTP_SECURE', 'tls');
```

#### SendGrid

```php
define('SLR_SMTP_HOST', 'smtp.sendgrid.net');
define('SLR_SMTP_PORT', 587);
define('SLR_SMTP_SECURE', 'tls');
define('SLR_SMTP_USER', 'apikey');
define('SLR_SMTP_PASS', 'your-sendgrid-api-key');
```

#### Mailgun

```php
define('SLR_SMTP_HOST', 'smtp.mailgun.org');
define('SLR_SMTP_PORT', 587);
define('SLR_SMTP_SECURE', 'tls');
```

#### Amazon SES

```php
define('SLR_SMTP_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('SLR_SMTP_PORT', 587);
define('SLR_SMTP_SECURE', 'tls');
```

## DNS Configuration

### SPF Record

Add this TXT record to your domain's DNS:

```
v=spf1 include:_spf.google.com ~all
```

(Replace `_spf.google.com` with your SMTP provider's SPF record)

### DKIM Record

Configure DKIM through your email provider's dashboard and add the provided TXT record to your DNS.

### DMARC Record (Optional)

```
v=DMARC1; p=quarantine; rua=mailto:dmarc@yourdomain.com
```

## Testing Email Deliverability

### Manual Test

You can test email deliverability using the built-in test function:

```php
// In WordPress admin or plugin
$otp_handler = new SLR_OTP_Handler();
$otp_handler->configure_mail_settings();
$result = $otp_handler->test_email_deliverability('test@example.com');
var_dump($result);
```

### Online Tools

1. **Mail-tester.com** - Test spam score
2. **MXToolbox.com** - Check DNS records
3. **Google Postmaster Tools** - Monitor Gmail delivery
4. **Microsoft SNDS** - Monitor Outlook delivery

## Common Issues and Solutions

### Issue: Emails still going to spam

**Solutions:**

1. Configure SMTP with reputable provider
2. Set up SPF, DKIM, and DMARC records
3. Use dedicated IP if sending high volume
4. Warm up new sending domain gradually

### Issue: Gmail blocking emails

**Solutions:**

1. Use Gmail SMTP or Google Workspace
2. Enable 2-factor authentication and use app passwords
3. Add domain to Google Postmaster Tools

### Issue: Outlook/Hotmail blocking emails

**Solutions:**

1. Use Microsoft 365 SMTP
2. Register with Microsoft SNDS
3. Follow Microsoft's bulk sender guidelines

## Best Practices

1. **Consistent From Address**: Always use the same sender
2. **Clean HTML**: Avoid complex layouts and external resources
3. **Reasonable Frequency**: Don't send too many OTPs quickly
4. **Monitor Bounce Rates**: Keep bounce rate below 5%
5. **User Engagement**: High open rates improve sender reputation

## Troubleshooting

### Enable Debug Logging

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for email sending errors.

### Common Error Messages

**"Could not instantiate mail function"**

- Solution: Configure SMTP or check server mail() function

**"SMTP connect() failed"**

- Solution: Check SMTP credentials and server settings

**"Authentication failed"**

- Solution: Verify username/password or use app passwords

## Support

If emails continue going to spam after following this guide:

1. Check debug logs for errors
2. Test with mail-tester.com
3. Verify DNS records are properly configured
4. Consider switching to a dedicated email service provider

---

_This configuration has been tested and optimized for maximum deliverability across all major email providers._
