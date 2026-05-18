<?php

namespace AgoLab\Harden\Admin;

use AgoLab\Harden\Plugin;
use AgoLab\Harden\Score;

defined( 'ABSPATH' ) || exit;

class Page {

    public static function render(): void {
        $settings = Plugin::get_settings();
        $score    = Score::calculate( $settings );
        $color    = Score::color( $score );
        $label    = Score::label( $score );
        ?>
        <div class="wrap">
            <h1>
                <img src="<?php echo esc_url( AGO_HARDEN_URL . 'assets/img/agolab.webp' ); ?>" alt="aGo Lab" style="height:28px;width:auto;vertical-align:middle;margin-right:8px">
                <?php esc_html_e( 'aGo Harden', 'ago-harden' ); ?>
                <span style="font-size:12px;color:#999;margin-left:8px">v<?php echo esc_html( AGO_HARDEN_VERSION ); ?></span>
            </h1>

            <div class="ago-layout">
                <div class="ago-main">

                    <!-- Security Score -->
                    <div class="card ago-card ago-score-card">
                        <div class="ago-score-wrapper">
                            <div class="ago-score-gauge" id="ago-score-gauge">
                                <svg viewBox="0 0 120 120" width="120" height="120">
                                    <circle cx="60" cy="60" r="52" fill="none" stroke="#e0e0e0" stroke-width="8" />
                                    <circle cx="60" cy="60" r="52" fill="none" stroke="<?php echo esc_attr( $color ); ?>"
                                        stroke-width="8" stroke-linecap="round"
                                        stroke-dasharray="<?php echo esc_attr( 326.73 ); ?>"
                                        stroke-dashoffset="<?php echo esc_attr( 326.73 - ( 326.73 * $score / 100 ) ); ?>"
                                        transform="rotate(-90 60 60)"
                                        id="ago-score-circle" />
                                    <text x="60" y="55" text-anchor="middle" font-size="28" font-weight="700" fill="<?php echo esc_attr( $color ); ?>" id="ago-score-number"><?php echo esc_html( $score ); ?></text>
                                    <text x="60" y="72" text-anchor="middle" font-size="11" fill="#666">/100</text>
                                </svg>
                            </div>
                            <div class="ago-score-info">
                                <h2><?php esc_html_e( 'Security Score', 'ago-harden' ); ?></h2>
                                <p class="ago-score-label" id="ago-score-label" style="color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $label ); ?></p>
                                <p class="ago-score-hint"><?php esc_html_e( 'Enable more protections below to increase your score.', 'ago-harden' ); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Login & Authentication -->
                    <div class="card ago-card">
                        <h2><?php esc_html_e( 'Login & Authentication', 'ago-harden' ); ?></h2>

                        <div class="ago-toggle-section">
                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Custom Login URL', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-15">15 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( 'Change the login URL to a custom slug. Leave empty to disable.', 'ago-harden' ); ?></span>
                                <span class="ago-toggle-input">
                                    <code><?php echo esc_html( home_url( '/' ) ); ?></code>
                                    <input type="text" name="custom_login_url" value="<?php echo esc_attr( $settings['custom_login_url'] ); ?>" placeholder="my-secret-login" class="regular-text ago-text-input">
                                </span>
                            </label>

                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <input type="checkbox" name="limit_login_attempts" value="1" <?php checked( $settings['limit_login_attempts'] ); ?>>
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Limit Login Attempts', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-15">15 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( '3 failed attempts in 15 minutes triggers a 1-hour lockout per IP.', 'ago-harden' ); ?></span>
                            </label>

                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <input type="checkbox" name="hide_login_errors" value="1" <?php checked( $settings['hide_login_errors'] ); ?>>
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Hide Login Errors', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-5">5 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( 'Replace detailed login error messages with a generic one.', 'ago-harden' ); ?></span>
                            </label>

                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Force Logout After', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-5">5 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( 'Force session expiration after N hours. Set 0 to disable.', 'ago-harden' ); ?></span>
                                <span class="ago-toggle-input">
                                    <input type="number" name="force_logout_hours" value="<?php echo esc_attr( $settings['force_logout_hours'] ); ?>" min="0" max="720" step="1" class="small-text ago-number-input">
                                    <span><?php esc_html_e( 'hours', 'ago-harden' ); ?></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Information Exposure -->
                    <div class="card ago-card">
                        <h2><?php esc_html_e( 'Information Exposure', 'ago-harden' ); ?></h2>

                        <div class="ago-toggle-section">
                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <input type="checkbox" name="hide_wp_version" value="1" <?php checked( $settings['hide_wp_version'] ); ?>>
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Hide WordPress Version', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-5">5 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( 'Remove version meta tag and strip ?ver= from scripts and styles.', 'ago-harden' ); ?></span>
                            </label>

                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <input type="checkbox" name="block_author_enum" value="1" <?php checked( $settings['block_author_enum'] ); ?>>
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Block Author Enumeration', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-5">5 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( 'Block ?author=N enumeration and restrict REST API users endpoint.', 'ago-harden' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- HTTP & Transport -->
                    <div class="card ago-card">
                        <h2><?php esc_html_e( 'HTTP & Transport', 'ago-harden' ); ?></h2>

                        <div class="ago-toggle-section">
                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <input type="checkbox" name="security_headers" value="1" <?php checked( $settings['security_headers'] ); ?>>
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Security Headers', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-15">15 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( 'Add X-Frame-Options, X-Content-Type-Options, Referrer-Policy, X-XSS-Protection headers.', 'ago-harden' ); ?></span>
                            </label>

                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <input type="checkbox" name="disable_xmlrpc" value="1" <?php checked( $settings['disable_xmlrpc'] ); ?>>
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Disable XML-RPC', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-10">10 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( 'Disable XML-RPC endpoint. Skipped automatically if aGo Cleanup handles it.', 'ago-harden' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- File System -->
                    <div class="card ago-card">
                        <h2><?php esc_html_e( 'File System', 'ago-harden' ); ?></h2>

                        <div class="ago-toggle-section">
                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <input type="checkbox" name="disable_file_edit" value="1" <?php checked( $settings['disable_file_edit'] ); ?>>
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Disable File Editor', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-10">10 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( 'Disable the built-in theme and plugin editor in wp-admin.', 'ago-harden' ); ?></span>
                            </label>

                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <input type="checkbox" name="block_php_uploads" value="1" <?php checked( $settings['block_php_uploads'] ); ?>>
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Block PHP in Uploads', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-10">10 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( 'Block PHP execution in wp-content/uploads/ via .htaccess.', 'ago-harden' ); ?></span>
                            </label>

                            <label class="ago-toggle">
                                <span class="ago-toggle-header">
                                    <input type="checkbox" name="disable_directory_listing" value="1" <?php checked( $settings['disable_directory_listing'] ); ?>>
                                    <span class="ago-toggle-title"><?php esc_html_e( 'Disable Directory Listing', 'ago-harden' ); ?></span>
                                    <span class="ago-toggle-badge ago-badge-5">5 pts</span>
                                </span>
                                <span class="ago-toggle-desc"><?php esc_html_e( 'Add Options -Indexes to root .htaccess to prevent directory browsing.', 'ago-harden' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Save -->
                    <div class="ago-actions">
                        <button id="ago-save-btn" class="button button-primary button-hero">
                            <?php esc_html_e( 'Save Settings', 'ago-harden' ); ?>
                        </button>
                        <span id="ago-save-status" class="ago-save-status"></span>
                    </div>

                </div>

                <!-- SIDEBAR -->
                <div class="ago-sidebar">

                    <!-- About -->
                    <div class="card ago-card">
                        <h3><?php esc_html_e( 'About', 'ago-harden' ); ?></h3>
                        <p style="font-size:13px;color:#666">
                            <?php esc_html_e( 'Security hardening dashboard. Toggle protections on/off and monitor your security score in real-time.', 'ago-harden' ); ?>
                        </p>
                        <ul class="ago-features">
                            <li><?php esc_html_e( 'Custom login URL', 'ago-harden' ); ?></li>
                            <li><?php esc_html_e( 'Brute-force protection', 'ago-harden' ); ?></li>
                            <li><?php esc_html_e( 'Security headers', 'ago-harden' ); ?></li>
                            <li><?php esc_html_e( 'File editor lockdown', 'ago-harden' ); ?></li>
                            <li><?php esc_html_e( 'PHP upload blocking', 'ago-harden' ); ?></li>
                            <li><?php esc_html_e( 'Real-time security score', 'ago-harden' ); ?></li>
                        </ul>
                    </div>

                    <!-- Donation -->
                    <div class="card ago-card ago-donation">
                        <h3><?php esc_html_e( 'Support Open Source', 'ago-harden' ); ?></h3>
                        <p style="font-size:13px;color:#666">
                            <?php esc_html_e( 'If this plugin saves you time, consider supporting our open-source work.', 'ago-harden' ); ?>
                        </p>
                        <div class="ago-donation-amounts">
                            <a href="https://paypal.me/sixtovaldes/3" class="ago-amount" target="_blank" rel="noopener">$3</a>
                            <a href="https://paypal.me/sixtovaldes/5" class="ago-amount" target="_blank" rel="noopener">$5</a>
                            <a href="https://paypal.me/sixtovaldes/10" class="ago-amount" target="_blank" rel="noopener">$10</a>
                        </div>
                        <a href="https://paypal.me/sixtovaldes" class="ago-coffee-btn" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-coffee" style="margin-right:6px"></span>
                            <?php esc_html_e( 'Buy us a coffee', 'ago-harden' ); ?>
                        </a>
                        <p class="ago-donation-note">
                            <?php esc_html_e( 'Voluntary donation. Thank you!', 'ago-harden' ); ?>
                        </p>
                    </div>

                    <!-- Footer with logo -->
                    <div class="ago-footer">
                        <a href="https://ago.cl" target="_blank" rel="noopener" class="ago-footer-logo">
                            <img src="<?php echo esc_url( AGO_HARDEN_URL . 'assets/img/agolab.webp' ); ?>" alt="aGo Lab" style="height:40px;width:auto">
                        </a>
                        <p>
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: 1: heart icon HTML, 2: aGo Lab link HTML */
                                    __( 'Developed with %1$s by %2$s', 'ago-harden' ),
                                    '<span style="color:#e25555">&#10084;</span>',
                                    '<a href="https://ago.cl" target="_blank" rel="noopener"><strong>aGo Lab</strong></a>'
                                )
                            );
                            ?>
                        </p>
                        <p style="font-size:11px;color:#999">
                            <?php esc_html_e( 'Building tools for the web, one plugin at a time.', 'ago-harden' ); ?>
                        </p>
                    </div>

                </div>
            </div>

        </div>
        <?php
    }
}
