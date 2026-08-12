<?php

/**
 * Plugin Name: Image Alt Text Generator
 * Description: Generates context-aware alt text for images using Google Gemini vision models.
 * Version: 2.1.0
 * Author: Ryan Dungan
 */

if (!defined('ABSPATH')) exit;

// Initialize Auto-Updater if library is present
if (file_exists(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {
    require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
    $myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/YOUR_GITHUB_USERNAME/ai-alt-generator/', // Replace with your GitHub Repo URL
        __FILE__,
        'ai-alt-generator'
    );
    $myUpdateChecker->setBranch('main');
}

class Custom_AI_Alt_Text {

    private $option_key_api = 'ai_alt_gemini_api_key';
    private $option_key_context = 'ai_alt_site_context';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_generate_single_alt_text', [$this, 'handle_ajax_generate']);
    }

    public function add_admin_menu() {
        add_management_page(
            'AI Alt Text Generator',
            'AI Alt Text',
            'manage_options',
            'ai-alt-text',
            [$this, 'render_admin_page']
        );
    }

    public function register_settings() {
        register_setting('ai_alt_settings_group', $this->option_key_api, ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('ai_alt_settings_group', $this->option_key_context, ['sanitize_callback' => 'sanitize_textarea_field']);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle settings save
        if (isset($_POST['ai_alt_save_settings']) && check_admin_referer('ai_alt_save_action', 'ai_alt_save_nonce')) {
            update_option($this->option_key_api, sanitize_text_field($_POST['api_key']));
            update_option($this->option_key_context, sanitize_textarea_field($_POST['site_context']));
            echo '<div class="updated"><p>Settings saved securely.</p></div>';
        }

        $saved_api_key  = get_option($this->option_key_api, '');
        $saved_context  = get_option($this->option_key_context, get_bloginfo('name') . ' - ' . get_bloginfo('description'));
        $active_tab     = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'generator';

        $missing_images = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                ['key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS'],
                ['key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '='],
            ],
        ]);
?>
        <div class="wrap">
            <h1>AI Alt Text Suite</h1>

            <!-- Navigation Tabs -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=ai-alt-text&tab=generator" class="nav-tab <?php echo $active_tab === 'generator' ? 'nav-tab-active' : ''; ?>">Alt Text Generator</a>
                <a href="?page=ai-alt-text&tab=setup_guide" class="nav-tab <?php echo $active_tab === 'setup_guide' ? 'nav-tab-active' : ''; ?>">API Setup & Cost Guide</a>
            </h2>

            <div class="tab-content" style="margin-top: 20px;">
                <?php if ($active_tab === 'generator'): ?>

                    <!-- TAB 1: GENERATOR -->
                    <form method="post" action="" style="max-width: 650px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
                        <?php wp_nonce_field('ai_alt_save_action', 'ai_alt_save_nonce'); ?>
                        <h2>1. Plugin Configuration</h2>
                        <p>
                            <label for="api_key"><strong>Google Gemini API Key:</strong></label><br>
                            <input type="password" id="api_key" name="api_key" class="regular-text" value="<?php echo esc_attr($saved_api_key); ?>" placeholder="AIzaSy..." required />
                        </p>
                        <p>
                            <label for="site_context"><strong>Company / Brand Context:</strong></label><br>
                            <textarea id="site_context" name="site_context" rows="4" class="large-text"><?php echo esc_textarea($saved_context); ?></textarea>
                            <span class="description">Include company name and services so Gemini naturally incorporates your brand into image descriptions.</span>
                        </p>
                        <p>
                            <input type="submit" name="ai_alt_save_settings" class="button button-secondary" value="Save Settings">
                        </p>
                    </form>

                    <div style="max-width: 650px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                        <h2>2. Run Batch Generator</h2>
                        <p>Images missing alt text: <strong><?php echo count($missing_images); ?></strong></p>
                        <button type="button" id="start-gen-btn" class="button button-primary button-large" <?php echo empty($saved_api_key) ? 'disabled' : ''; ?>>
                            <?php echo empty($saved_api_key) ? 'Please Save API Key First' : 'Generate Missing Alt Text'; ?>
                        </button>

                        <div id="ai-progress-log" style="margin-top: 15px; background: #f6f7f7; padding: 15px; border: 1px solid #dcdcde; max-height: 300px; overflow-y: auto; font-family: monospace;">
                            <em>Status logs will appear here when you click generate...</em>
                        </div>
                    </div>

                <?php else: ?>

                    <!-- TAB 2: SETUP & COST GUIDE -->
                    <div style="max-width: 750px; background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 4px; line-height: 1.6;">
                        <h2>How to Obtain a Google Gemini API Key</h2>
                        <ol>
                            <li>Navigate to <a href="https://aistudio.google.com/" target="_blank">Google AI Studio (aistudio.google.com)</a> and sign in with your Google Account.</li>
                            <li>Click on <strong>"Get API Key"</strong> in the top sidebar.</li>
                            <li>Click <strong>"Create API key"</strong> (you can select an existing Google Cloud project or create a default one).</li>
                            <li>Copy your new key (it starts with <code>AIzaSy...</code>) and paste it into the <strong>Alt Text Generator</strong> tab in this plugin.</li>
                        </ol>

                        <hr style="margin: 25px 0; border: 0; border-top: 1px solid #eee;">

                        <h2>Understanding Costs & Usage Limits</h2>
                        <p>Google offers two tiers for the Gemini API:</p>

                        <table class="widefat fixed striped" style="margin-bottom: 20px;">
                            <thead>
                                <tr>
                                    <th>Tier</th>
                                    <th>Price Per Image</th>
                                    <th>Usage Quota</th>
                                    <th>Privacy Notice</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Free Tier</strong></td>
                                    <td><strong>$0.00 (Free)</strong></td>
                                    <td>Up to 1,500 requests / day</td>
                                    <td>Prompts may be reviewed by Google to improve models.</td>
                                </tr>
                                <tr>
                                    <td><strong>Pay-As-You-Go</strong></td>
                                    <td><strong>~$0.0001 per image</strong></td>
                                    <td>Higher rate limits</td>
                                    <td>Enterprise privacy (data is NOT used for training).</td>
                                </tr>
                            </tbody>
                        </table>

                        <blockquote style="background: #e7f5fe; border-left: 4px solid #00a0d2; margin: 0; padding: 12px 15px;">
                            <strong>Cost Calculation Example:</strong> Running 1,000 images on the paid Flash tier costs roughly <strong>$0.10 to $0.15 total</strong>. For almost all small-to-medium websites, processing alt text remains completely free or costs a fraction of a penny.
                        </blockquote>
                    </div>

                <?php endif; ?>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                const imageIds = <?php echo json_encode($missing_images); ?>;
                const ajaxNonce = "<?php echo wp_create_nonce('ai_alt_ajax_nonce'); ?>";
                const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

                $('#start-gen-btn').on('click', async function() {
                    if (imageIds.length === 0) {
                        alert('No images missing alt text!');
                        return;
                    }

                    $(this).prop('disabled', true).text('Processing Batch...');
                    const $log = $('#ai-progress-log');
                    $log.html('<p><strong>Starting batch process...</strong></p>');

                    for (let i = 0; i < imageIds.length; i++) {
                        const id = imageIds[i];
                        $log.append(`<p>Processing Image ID: ${id} (${i + 1}/${imageIds.length})...</p>`);

                        if (i > 0) {
                            await delay(2000);
                        }

                        try {
                            const response = await $.post(ajaxurl, {
                                action: 'generate_single_alt_text',
                                image_id: id,
                                _ajax_nonce: ajaxNonce
                            });

                            if (response.success) {
                                $log.append(`<p style="color: green;">✓ ID ${id}: "${response.data.alt_text}"</p>`);
                            } else {
                                $log.append(`<p style="color: red;">✗ ID ${id} Failed: ${response.data}</p>`);
                            }
                        } catch (err) {
                            $log.append(`<p style="color: red;">✗ Network error on ID ${id}. Skipping...</p>`);
                        }

                        $log.scrollTop($log[0].scrollHeight);
                    }
                    $(this).prop('disabled', false).text('Batch Complete!');
                });
            });
        </script>
<?php
    }

    public function handle_ajax_generate() {
        check_ajax_referer('ai_alt_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access.');
        }

        $image_id = intval($_POST['image_id']);
        $api_key  = get_option($this->option_key_api, '');
        $context  = get_option($this->option_key_context, '');

        if (empty($api_key)) {
            wp_send_json_error('API Key is missing.');
        }

        $file_path = get_attached_file($image_id);
        $mime_type = get_post_mime_type($image_id);

        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error('Image file not found on server.');
        }

        $image_bytes = file_get_contents($file_path);
        if (!$image_bytes) {
            wp_send_json_error('Failed to read image file.');
        }
        $base64_data = base64_encode($image_bytes);

        $system_instruction = "You are an expert accessibility copywriter. Write a concise, single COMPLETE sentence (under 120 characters) for image alt text. "
            . "CRITICAL INSTRUCTION: You MUST naturally incorporate the brand or company name provided in the site context into the alt text description where appropriate. "
            . "Do not use fluff phrases like 'Image showing' or 'Photo of'. Output ONLY the raw alt text string with no preamble.";

        $user_prompt = "Describe this image for website alt text. Company / Brand Context: '{$context}'";

        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=' . $api_key;

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $system_instruction]]
            ],
            'contents' => [
                [
                    'parts' => [
                        ['text' => $user_prompt],
                        ['inlineData' => ['mimeType' => $mime_type, 'data' => $base64_data]]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 1000,
                'temperature'     => 0.2
            ]
        ];

        $max_retries = 2;
        $response    = null;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $response = wp_remote_post($endpoint, [
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 40,
                'body'    => json_encode($payload)
            ]);

            if (!is_wp_error($response)) {
                break;
            }
            if ($attempt < $max_retries) {
                sleep(2);
            }
        }

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            wp_send_json_error($body['error']['message'] ?? 'Gemini API Error');
        }

        $alt_text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $alt_text = preg_replace('/^(\*\*|#+|Analyze the Image:?|Alt text:?)/i', '', trim($alt_text));
        $alt_text = trim($alt_text, ' "' . "\t\n\r");

        if (!empty($alt_text)) {
            update_post_meta($image_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
            wp_send_json_success(['alt_text' => $alt_text]);
        } else {
            wp_send_json_error('No alt text returned.');
        }
    }
}

new Custom_AI_Alt_Text();
