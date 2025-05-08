<?php
/**
 * Plugin Name: Woocommerce Order Links
 * Description: Adds configurable links per product on the thank you page and confirmation email. Now with automatic redirect on thank you page.
 * Version: 1.3
 * Author: RSL Awards
 */

if (!defined('ABSPATH')) exit;

class Order_Links_On_Success {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        add_action('woocommerce_thankyou', [$this, 'output_order_links'], 20);
        add_action('woocommerce_email_after_order_table', [$this, 'email_order_links'], 20, 4);
    }

    public function add_settings_page() {
        add_menu_page(
            'Order Links',
            'Order Links',
            'manage_options',
            'order-links-settings',
            [$this, 'settings_page_html'],
            'dashicons-admin-links'
        );
    }

    public function register_settings() {
        register_setting('order_links_settings_group', 'order_links_product_configs');
    }

    public function settings_page_html() {
        $configs = get_option('order_links_product_configs', []);
        ?>
        <div class="wrap">
            <h1>Order Links Per Product</h1>
            <form method="post" action="options.php">
                <?php settings_fields('order_links_settings_group'); ?>
                <div id="order-links-repeater">
                    <?php if (!empty($configs)) : foreach ($configs as $index => $config) : ?>
                        <?php $this->render_repeater_row($index, $config); ?>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" class="button" id="add-order-link">Add New Link</button>
                <br><br>
                <?php submit_button(); ?>
            </form>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            let index = <?php echo count($configs); ?>;
            document.getElementById('add-order-link').addEventListener('click', function () {
                const container = document.createElement('div');
                container.innerHTML = `<?php
                    ob_start();
                    $this->render_repeater_row('__INDEX__', []);
                    echo addslashes(ob_get_clean());
                ?>`.replace(/__INDEX__/g, index);
                document.getElementById('order-links-repeater').appendChild(container);
                index++;
            });
        });
        // Remove row functionality
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-order-link')) {
                if (confirm('Are you sure you want to remove this link?')) {
                    e.target.closest('.order-link-row').remove();
                }
            }
        });
        </script>
        <style>
            .order-link-row {
                margin-bottom: 20px;
                padding: 15px;
                border: 1px solid #ddd;
                background: #f9f9f9;
            }
        </style>
        <?php
    }

    private function render_repeater_row($index, $config) {
        $product_id   = $config['product_id'] ?? '';
        $intro        = $config['intro'] ?? '';
        $button_text  = $config['button_text'] ?? '';
        $url          = $config['url'] ?? '';
        ?>
        <div class="order-link-row">
            <p>
                <label>Product:
                    <select name="order_links_product_configs[<?php echo $index; ?>][product_id]">
                        <option value="">-- Select Product --</option>
                        <?php
                        $products = get_posts([
                            'post_type'      => 'product',
                            'posts_per_page' => -1,
                            'orderby'        => 'title',
                            'order'          => 'ASC',
                        ]);
                        foreach ($products as $product) {
                            $selected = (int) $product_id === (int) $product->ID ? 'selected' : '';
                            echo '<option value="' . esc_attr($product->ID) . '" ' . $selected . '>' . esc_html($product->post_title) . '</option>';
                        }
                        ?>
                    </select>
                </label>
            </p>
            <p>
                <label>Intro Text:<br>
                    <textarea name="order_links_product_configs[<?php echo $index; ?>][intro]" rows="2" cols="60"><?php echo esc_textarea($intro); ?></textarea>
                </label>
            </p>
            <p>
                <label>Button Text:<br>
                    <input type="text" name="order_links_product_configs[<?php echo $index; ?>][button_text]" value="<?php echo esc_attr($button_text); ?>" />
                </label>
            </p>
            <p>
                <label>Button URL:<br>
                    <input type="text" name="order_links_product_configs[<?php echo $index; ?>][url]" value="<?php echo esc_attr($url); ?>" size="60" />
                </label>
            </p>
            <p>
                <button type="button" class="button remove-order-link" style="background-color: #dc3232; color: #fff;">Remove</button>
            </p>
        </div>
        <?php
    }

    private function get_order_links_for_products($product_ids, $order, $return_first_url_only = false) {
        $configs = get_option('order_links_product_configs', []);
        $output = '';
        $order_id = $order->get_id();

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = $item->get_product();

            foreach ($configs as $config) {
                if (!empty($config['product_id']) && (int)$config['product_id'] === (int)$product_id) {
                    $intro = $config['intro'] ?? '';
                    $btn = $config['button_text'] ?? '';
                    $base_url = $config['url'] ?? '';
                    if ($btn && $base_url) {
                        $product_name = $product ? $product->get_name() : '';

                        if ($item->get_variation_id()) {
                            $variation_data = wc_get_formatted_variation($item, true, false, true);
                            if (!empty($variation_data)) {
                                $product_name .= ' - ' . strip_tags($variation_data);
                            }
                        }

                        $query_args = [
                            'product_name'   => rawurlencode($product_name),
                            'order_id'       => $order_id,
                            'order_item_id'  => $item_id,
                        ];
                        $final_url = add_query_arg($query_args, $base_url);

                        if ($return_first_url_only) {
                            return $final_url;
                        }

                        $output .= '<div class="order-links-section" style="margin-top: 30px; margin-bottom: 30px;">';
                        if ($intro) {
                            $output .= '<p>' . esc_html($intro) . '</p>';
                        }
                        $output .= '<a href="' . esc_url($final_url) . '" class="button" style="background: #0071a1; color: #fff; padding: 10px 20px; text-decoration: none;">' . esc_html($btn) . '</a>';
                        $output .= '</div>';
                    }
                }
            }
        }

        return $return_first_url_only ? '' : $output;
    }

    public function output_order_links($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || !$order->has_status('processing')) return;

        $product_ids = [];
        foreach ($order->get_items() as $item) {
            $product_ids[] = $item->get_product_id();
        }

        // Output all matching links
        echo $this->get_order_links_for_products(array_unique($product_ids), $order);

        // Get first matching link for redirect
        $redirect_url = $this->get_order_links_for_products(array_unique($product_ids), $order, true);
        if ($redirect_url) {
            ?>
            <div id="order-link-redirect-msg" style="margin-top: 20px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0071a1;">
                You’ll be redirected in <span id="redirect-countdown">10</span> seconds…
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var seconds = 10;
                var countdownEl = document.getElementById('redirect-countdown');
                var countdown = setInterval(function () {
                    seconds--;
                    if (seconds <= 0) {
                        clearInterval(countdown);
                        window.location.href = <?php echo json_encode($redirect_url); ?>;
                    } else {
                        countdownEl.textContent = seconds;
                    }
                }, 1000);
            });
            </script>
            <?php
        }
    }

    public function email_order_links($order, $sent_to_admin, $plain_text, $email) {
        if (!$order || !$order->has_status('processing')) return;

        $product_ids = [];
        foreach ($order->get_items() as $item) {
            $product_ids[] = $item->get_product_id();
        }

        echo $this->get_order_links_for_products(array_unique($product_ids), $order);
    }

}

new Order_Links_On_Success();

// no edit fields with noedit class
function disable_noedit_fields() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        document.querySelectorAll('.wpforms-field.noedit input, .wpforms-field.noedit textarea, .wpforms-field.noedit select').forEach(function(field) {
            field.setAttribute('readonly', true);
            field.style.backgroundColor = '#f5f5f5'; 
        });

    });
    </script>
    <?php
}
add_action('wp_footer', 'disable_noedit_fields');

// Register custom page template from plugin
function order_links_register_page_template($templates) {
    $templates['order-links-check.php'] = 'Order Link Verification';
    return $templates;
}
add_filter('theme_page_templates', 'order_links_register_page_template');

// Include the template file
function order_links_load_template($template) {
    if (is_page()) {
        $page_template = get_page_template_slug();
        if ('order-links-check.php' === $page_template) {
            $template = plugin_dir_path(__FILE__) . 'order-links-check.php';
        }
    }
    return $template;
}
add_filter('template_include', 'order_links_load_template');