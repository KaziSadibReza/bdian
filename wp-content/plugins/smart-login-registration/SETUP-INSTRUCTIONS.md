# Smart Login and Registration Plugin - Setup Instructions

## Quick Start Guide

### 1. Plugin Activation

The plugin is now ready to be activated. Follow these steps:

1. Go to your WordPress admin dashboard
2. Navigate to `Plugins > Installed Plugins`
3. Find "Smart Login and Registration" in the list
4. Click "Activate"

### 2. Theme Integration

Since we've disabled the theme-based popup, you need to add the plugin shortcode to display the login popup.

#### Option A: Add to Theme Template

Add this code to your theme's header, footer, or any template file where you want the login popup to appear:

```php
<?php echo do_shortcode('[smart_login_popup]'); ?>
```

#### Option B: Add via WordPress Admin

1. Go to `Appearance > Widgets` or `Appearance > Customize`
2. Add a "Custom HTML" widget
3. Insert the shortcode: `[smart_login_popup]`

#### Option C: Add to Posts/Pages

Simply add the shortcode `[smart_login_popup]` to any post or page content.

### 3. Configure Settings

1. Go to `Settings > Smart Login & Registration`
2. Configure your preferences:
   - OTP expiry time (default: 10 minutes)
   - Rate limiting settings
   - Email template options
   - Enable/disable different login methods

### 4. Testing

1. Visit your website's frontend
2. Look for the login popup trigger (button or link)
3. Test the following flows:
   - Login with email
   - Register new account
   - Forgot password
   - OTP verification

## Important Notes

### Database Migration

The plugin uses its own database table `wp_slr_otp_codes` instead of the theme's table. When you activate the plugin, it will:

- Create the new OTP table
- Set up proper cron jobs for cleanup
- Initialize default settings

### Email Configuration

Make sure your WordPress site can send emails:

- Test with a simple contact form
- Check your hosting provider's email settings
- Consider using an SMTP plugin if needed

### Backup Recommendation

Before activating the plugin, it's recommended to:

1. Backup your database
2. Backup your theme files
3. Test on a staging site first

## Troubleshooting

### If the popup doesn't appear:

1. Check that you've added the shortcode to a visible location
2. Ensure the plugin is activated
3. Check browser console for JavaScript errors
4. Verify jQuery is loaded

### If OTP emails aren't sending:

1. Check WordPress email functionality
2. Look in spam folder
3. Enable debug mode in plugin settings
4. Check server mail logs

### If you get database errors:

1. Deactivate and reactivate the plugin
2. Check database permissions
3. Verify table creation in phpMyAdmin

## Migration from Theme-Based System

The plugin automatically handles the migration from the theme-based system:

- Uses the same popup design and functionality
- Maintains all security features
- Preserves user experience
- Adds admin interface for better management

## Next Steps

1. **Activate the plugin** following the steps above
2. **Test all functionality** to ensure everything works correctly
3. **Configure settings** to match your requirements
4. **Add the shortcode** to display the popup
5. **Monitor and maintain** using the admin dashboard

## Support

If you encounter any issues:

1. Check the plugin's admin settings
2. Enable debug mode for detailed logging
3. Review the README.md file for technical details
4. Contact the plugin author for support

---

**Plugin Author**: Kazi Sadib Reza
**Version**: 1.0.0
**Last Updated**: January 2025
