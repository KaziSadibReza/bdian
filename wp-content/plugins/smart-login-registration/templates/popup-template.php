<?php
/**
 * Smart Login and Registration - Popup Template
 * 
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<button class="<?php echo esc_attr($atts['button_class']); ?>" data-action="login">
    <?php echo esc_html($atts['button_text']); ?>
</button>

<!-- Smart Login/Registration Popup Modal -->
<div id="slr-login-popup-container" class="slr-popup-container" style="display: none;">
    <div class="slr-popup-overlay"></div>
    <div class="slr-popup-modal">
        <div class="slr-popup-inner">
            <span class="slr-popup-close">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" />
                    <path d="M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg>
            </span>
            <div class="slr-popup-content">
                <div class="slr-popup-tabs">
                    <ul class="slr-popup-tab-nav">
                        <li class="active"><a href="#slr-login-tab"
                                data-tab="login"><?php _e('Login', 'smart-login-registration'); ?></a></li>
                        <?php if ($atts['show_register'] === 'yes' && get_option('users_can_register')) : ?>
                        <li><a href="#slr-register-tab"
                                data-tab="register"><?php _e('Register', 'smart-login-registration'); ?></a></li>
                        <?php endif; ?>
                        <li style="display: none;"><a href="#slr-forgot-tab"
                                data-tab="forgot"><?php _e('Forgot Password', 'smart-login-registration'); ?></a></li>
                    </ul>
                </div>

                <div class="slr-popup-tab-content">
                    <!-- Login Form -->
                    <div id="slr-login-tab" class="slr-tab-pane active">
                        <div class="slr-popup-form-header">
                            <h3><?php _e('Welcome Back!', 'smart-login-registration'); ?></h3>
                            <p><?php _e('Please login to your account', 'smart-login-registration'); ?></p>
                        </div>
                        <form id="slr-popup-login-form" method="post">
                            <?php wp_nonce_field('slr_login_nonce', 'slr_login_nonce'); ?>
                            <input type="hidden" name="action" value="slr_login">

                            <div class="slr-form-group">
                                <input type="text" name="log"
                                    placeholder="<?php _e('Email, Phone or Username', 'smart-login-registration'); ?>"
                                    required>
                            </div>

                            <div class="slr-form-group">
                                <input type="password" name="pwd"
                                    placeholder="<?php _e('Password', 'smart-login-registration'); ?>" required>
                            </div>

                            <div class="slr-form-group slr-checkbox-group">
                                <label>
                                    <input type="checkbox" name="rememberme" value="forever">
                                    <?php _e('Remember Me', 'smart-login-registration'); ?>
                                </label>
                                <a href="#" class="slr-forgot-password" data-tab="forgot">
                                    <?php _e('Forgot Password?', 'smart-login-registration'); ?>
                                </a>
                            </div>

                            <div class="slr-form-group">
                                <button type="submit" class="slr-popup-btn slr-btn-primary">
                                    <?php _e('Login', 'smart-login-registration'); ?>
                                </button>
                            </div>

                            <div class="slr-form-group slr-login-divider">
                                <span><?php _e('OR', 'smart-login-registration'); ?></span>
                            </div>

                            <div class="slr-form-group">
                                <button type="button" class="slr-popup-btn slr-btn-secondary slr-otp-login-btn">
                                    <?php _e('Login with OTP', 'smart-login-registration'); ?>
                                </button>
                            </div>

                            <div class="slr-login-response"></div>
                        </form>
                    </div>

                    <!-- OTP Login Form -->
                    <div id="slr-otp-login-tab" class="slr-tab-pane">
                        <div class="slr-popup-form-header">
                            <h3><?php _e('Login with OTP', 'smart-login-registration'); ?></h3>
                            <p><?php _e('Enter your email to receive a one-time password', 'smart-login-registration'); ?>
                            </p>
                        </div>
                        <form id="slr-popup-otp-login-form" method="post">
                            <?php wp_nonce_field('slr_otp_nonce', 'slr_otp_nonce'); ?>
                            <input type="hidden" name="action" value="slr_otp_login">

                            <div class="slr-form-group">
                                <input type="email" name="email"
                                    placeholder="<?php _e('Email Address', 'smart-login-registration'); ?>" required>
                            </div>

                            <div class="slr-form-group">
                                <button type="submit" class="slr-popup-btn slr-btn-primary">
                                    <?php _e('Send OTP', 'smart-login-registration'); ?>
                                </button>
                            </div>

                            <div class="slr-form-group" style="text-align: center; margin-top: 20px;">
                                <a href="#" class="slr-back-to-login" data-tab="login">
                                    <?php _e('← Back to Login', 'smart-login-registration'); ?>
                                </a>
                            </div>

                            <div class="slr-otp-login-response"></div>
                        </form>
                    </div>

                    <!-- OTP Verification Form -->
                    <div id="slr-otp-verify-tab" class="slr-tab-pane">
                        <div class="slr-popup-form-header">
                            <h3><?php _e('Enter OTP Code', 'smart-login-registration'); ?></h3>
                            <p><?php _e('We\'ve sent a 4-digit code to your email', 'smart-login-registration'); ?></p>
                        </div>
                        <form id="slr-popup-otp-verify-form" method="post">
                            <?php wp_nonce_field('slr_otp_nonce', 'slr_otp_nonce'); ?>
                            <input type="hidden" name="action" value="slr_verify_otp">
                            <input type="hidden" name="email" value="">
                            <input type="hidden" name="otp_type" value="">

                            <div class="slr-form-group">
                                <div class="slr-otp-input-group">
                                    <input type="text" name="otp"
                                        placeholder="<?php _e('0000', 'smart-login-registration'); ?>" maxlength="4"
                                        pattern="[0-9]{4}" required autocomplete="off">
                                </div>
                            </div>

                            <div class="slr-form-group">
                                <button type="submit" class="slr-popup-btn slr-btn-primary">
                                    <?php _e('Verify OTP', 'smart-login-registration'); ?>
                                </button>
                            </div>

                            <div class="slr-form-group slr-otp-resend">
                                <p><?php _e('Didn\'t receive the code?', 'smart-login-registration'); ?></p>
                                <button type="button" class="slr-resend-otp-btn" disabled>
                                    <?php _e('Resend OTP (60s)', 'smart-login-registration'); ?>
                                </button>
                            </div>

                            <div class="slr-form-group" style="text-align: center; margin-top: 20px;">
                                <a href="#" class="slr-back-to-login" data-tab="login">
                                    <?php _e('← Back to Login', 'smart-login-registration'); ?>
                                </a>
                            </div>

                            <div class="slr-otp-verify-response"></div>
                        </form>
                    </div>

                    <!-- Registration Form -->
                    <?php if ($atts['show_register'] === 'yes' && get_option('users_can_register')) : ?>
                    <div id="slr-register-tab" class="slr-tab-pane">
                        <div class="slr-popup-form-header">
                            <h3><?php _e('Create Account', 'smart-login-registration'); ?></h3>
                            <p><?php _e('Join us and start your journey!', 'smart-login-registration'); ?></p>
                        </div>
                        <form id="slr-popup-register-form" method="post">
                            <?php wp_nonce_field('slr_register_nonce', 'slr_register_nonce'); ?>
                            <input type="hidden" name="action" value="slr_register">

                            <div class="slr-form-group">
                                <input type="text" name="first_name"
                                    placeholder="<?php _e('Full Name', 'smart-login-registration'); ?>" required>
                            </div>

                            <div class="slr-form-group">
                                <input type="email" name="email"
                                    placeholder="<?php _e('Email Address', 'smart-login-registration'); ?>" required>
                            </div>

                            <div class="slr-form-group">
                                <input type="tel" name="phone"
                                    placeholder="<?php _e('Phone Number', 'smart-login-registration'); ?>" required>
                            </div>

                            <div class="slr-form-group">
                                <input type="password" name="password"
                                    placeholder="<?php _e('Password', 'smart-login-registration'); ?>" required>
                            </div>

                            <div class="slr-form-group">
                                <button type="submit" class="slr-popup-btn slr-btn-primary">
                                    <?php _e('Send OTP & Register', 'smart-login-registration'); ?>
                                </button>
                            </div>

                            <div class="slr-register-response"></div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Forgot Password Form -->
                    <div id="slr-forgot-tab" class="slr-tab-pane">
                        <div class="slr-popup-form-header">
                            <h3><?php _e('Reset Password', 'smart-login-registration'); ?></h3>
                            <p><?php _e('Enter your email or phone number to receive an OTP', 'smart-login-registration'); ?>
                            </p>
                        </div>
                        <form id="slr-popup-forgot-form" method="post">
                            <?php wp_nonce_field('slr_otp_nonce', 'slr_otp_nonce'); ?>
                            <input type="hidden" name="action" value="slr_send_reset_otp">

                            <div class="slr-form-group">
                                <input type="text" name="user_login"
                                    placeholder="<?php _e('Email or Phone Number', 'smart-login-registration'); ?>"
                                    required>
                            </div>

                            <div class="slr-form-group">
                                <button type="submit" class="slr-popup-btn slr-btn-primary">
                                    <?php _e('Send Reset OTP', 'smart-login-registration'); ?>
                                </button>
                            </div>

                            <div class="slr-forgot-response"></div>

                            <div class="slr-form-group" style="text-align: center; margin-top: 20px;">
                                <a href="#" class="slr-back-to-login" data-tab="login">
                                    <?php _e('← Back to Login', 'smart-login-registration'); ?>
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Password Reset OTP Verification Form -->
                    <div id="slr-reset-otp-verify-tab" class="slr-tab-pane">
                        <div class="slr-popup-form-header">
                            <h3><?php _e('Verify Reset Code', 'smart-login-registration'); ?></h3>
                            <p><?php _e('Enter the 4-digit code sent to your email', 'smart-login-registration'); ?></p>
                        </div>
                        <form id="slr-popup-reset-otp-verify-form" method="post">
                            <?php wp_nonce_field('slr_otp_nonce', 'slr_otp_nonce'); ?>
                            <input type="hidden" name="action" value="slr_verify_reset_otp">
                            <input type="hidden" name="email" value="">
                            <input type="hidden" name="user_login" value="">

                            <div class="slr-form-group">
                                <div class="slr-otp-input-group">
                                    <input type="text" name="otp"
                                        placeholder="<?php _e('0000', 'smart-login-registration'); ?>" maxlength="4"
                                        pattern="[0-9]{4}" required autocomplete="off">
                                </div>
                            </div>

                            <div class="slr-form-group">
                                <button type="submit" class="slr-popup-btn slr-btn-primary">
                                    <?php _e('Verify Code', 'smart-login-registration'); ?>
                                </button>
                            </div>

                            <div class="slr-form-group slr-otp-resend">
                                <p><?php _e('Didn\'t receive the code?', 'smart-login-registration'); ?></p>
                                <button type="button" class="slr-resend-reset-otp-btn" disabled>
                                    <?php _e('Resend OTP (60s)', 'smart-login-registration'); ?>
                                </button>
                            </div>

                            <div class="slr-form-group" style="text-align: center; margin-top: 20px;">
                                <a href="#" class="slr-back-to-forgot" data-tab="forgot">
                                    <?php _e('← Back to Reset', 'smart-login-registration'); ?>
                                </a>
                            </div>

                            <div class="slr-reset-otp-verify-response"></div>
                        </form>
                    </div>

                    <!-- New Password Form -->
                    <div id="slr-new-password-tab" class="slr-tab-pane">
                        <div class="slr-popup-form-header">
                            <h3><?php _e('Set New Password', 'smart-login-registration'); ?></h3>
                            <p><?php _e('Enter your new password below', 'smart-login-registration'); ?></p>
                        </div>
                        <form id="slr-popup-new-password-form" method="post">
                            <?php wp_nonce_field('slr_otp_nonce', 'slr_otp_nonce'); ?>
                            <input type="hidden" name="action" value="slr_reset_password">
                            <input type="hidden" name="user_id" value="">
                            <input type="hidden" name="reset_token" value="">

                            <div class="slr-form-group">
                                <input type="password" name="new_password"
                                    placeholder="<?php _e('New Password', 'smart-login-registration'); ?>" 
                                    required minlength="6">
                            </div>

                            <div class="slr-form-group">
                                <input type="password" name="confirm_password"
                                    placeholder="<?php _e('Confirm New Password', 'smart-login-registration'); ?>" 
                                    required minlength="6">
                            </div>

                            <div class="slr-form-group">
                                <button type="submit" class="slr-popup-btn slr-btn-primary">
                                    <?php _e('Update Password', 'smart-login-registration'); ?>
                                </button>
                            </div>

                            <div class="slr-new-password-response"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>